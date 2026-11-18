import { getDb } from "./db";
import { blogPosts, courses } from "../drizzle/schema";
import { eq, and, desc } from "drizzle-orm";
import fs from "fs";
import path from "path";

interface SeoMetaData {
  title: string;
  description: string;
  keywords: string;
  canonical: string;
  jsonLd: Record<string, any>[];
  bodyHtml: string;
  ogImage?: string;        // URL absoluta; padrão = og-image.png genérica
  ogType?: string;
}

const attr = (s: string) => String(s ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

/** Troca (ou injeta) <meta property|name="key" content="..."> — usado para Open Graph e Twitter Card. */
function setMeta(template: string, kind: "property" | "name", key: string, value: string): string {
  const keyRe = key.replace(/[.:]/g, (ch) => "\\" + ch);
  const re = new RegExp("<meta\\s+" + kind + '="' + keyRe + '"\\s+content="[^"]*"\\s*/?>', "i");
  const tag = `<meta ${kind}="${key}" content="${attr(value)}" />`;
  return re.test(template) ? template.replace(re, () => tag) : template.replace("</head>", () => `  ${tag}
</head>`);
}

export async function renderSeoPrerender(urlPath: string, template: string): Promise<string> {
  // Clean path (remove query params)
  const cleanPath = urlPath.split("?")[0].replace(/\/$/, "") || "/";

  let seoData: SeoMetaData = await getSeoMetadata(cleanPath);

  // 1. Replace Title
  const titleRegex = /<title>[^*]*?<\/title>/i;
  const newTitleTag = `<title>${attr(seoData.title)}</title>`;
  if (titleRegex.test(template)) {
    template = template.replace(titleRegex, () => newTitleTag);
  } else {
    template = template.replace("</head>", `  ${newTitleTag}\n</head>`);
  }

  // 2. Replace Description
  const descRegex = /<meta\s+name="description"\s+content="[^*]*?"\s*\/?>/i;
  const newDescTag = `<meta name="description" content="${attr(seoData.description)}" />`;
  if (descRegex.test(template)) {
    template = template.replace(descRegex, () => newDescTag);
  } else {
    template = template.replace("</head>", `  ${newDescTag}\n</head>`);
  }

  // 3. Replace Keywords
  const keyRegex = /<meta\s+name="keywords"\s+content="[^*]*?"\s*\/?>/i;
  const newKeyTag = `<meta name="keywords" content="${attr(seoData.keywords)}" />`;
  if (keyRegex.test(template)) {
    template = template.replace(keyRegex, () => newKeyTag);
  } else {
    template = template.replace("</head>", `  ${newKeyTag}\n</head>`);
  }

  // 3b. Open Graph / Twitter — é o que o WhatsApp, Instagram e redes usam na prévia do link.
  // Antes ficava sempre a prévia genérica da home, para qualquer página compartilhada.
  const shareUrl = `https://www.vetrank.com.br${seoData.canonical}`;
  const ogImage = seoData.ogImage ?? "https://www.vetrank.com.br/og-image.png";
  template = setMeta(template, "property", "og:title", seoData.title);
  template = setMeta(template, "property", "og:description", seoData.description);
  template = setMeta(template, "property", "og:url", shareUrl);
  template = setMeta(template, "property", "og:image", ogImage);
  template = setMeta(template, "property", "og:type", seoData.ogType ?? "website");
  template = setMeta(template, "name", "twitter:title", seoData.title);
  template = setMeta(template, "name", "twitter:description", seoData.description);
  template = setMeta(template, "name", "twitter:image", ogImage);

  // 4. Inject Canonical Link
  const canonicalUrl = `https://vetrank.com.br${seoData.canonical}`;
  const canonicalRegex = /<link\s+rel="canonical"\s+href="[^*]*?"\s*\/?>/i;
  const newCanonicalTag = `<link rel="canonical" href="${canonicalUrl}" />`;
  if (canonicalRegex.test(template)) {
    template = template.replace(canonicalRegex, newCanonicalTag);
  } else {
    template = template.replace("</head>", `  ${newCanonicalTag}\n</head>`);
  }

  // 5. Inject JSON-LD Schema Scripts in the <head>
  const jsonLdTags = seoData.jsonLd
    .map(
      (schema) =>
        `  <script type="application/ld+json">\n    ${JSON.stringify(schema, null, 2).replace(/\n/g, "\n    ")}\n  </script>`
    )
    .join("\n");
  template = template.replace("</head>", `${jsonLdTags}\n</head>`);

  // 6. Inject Semantic HTML inside <div id="root">
  const rootDivRegex = /<div\s+id="root">\s*<\/div>/i;
  const newRootDiv = `<div id="root">\n${seoData.bodyHtml}\n</div>`;
  if (rootDivRegex.test(template)) {
    template = template.replace(rootDivRegex, newRootDiv);
  } else {
    // fallback if formatting is slightly different
    template = template.replace(`<div id="root">`, `<div id="root">\n${seoData.bodyHtml}`);
  }

  return template;
}

async function getSeoMetadata(route: string): Promise<SeoMetaData> {
  const baseJsonLd = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "VetRank",
    "url": "https://vetrank.com.br",
    "logo": "https://vetrank.com.br/logo_vetrank.svg",
    "description": "Plataforma gamificada de estudos para estudantes e profissionais de Medicina Veterinária."
  };

  const webSiteJsonLd = {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "VetRank",
    "url": "https://vetrank.com.br",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://vetrank.com.br/bank?search={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  };

  const db = await getDb();

  // Blog Details Router: `/blog/:slug`
  if (route.startsWith("/blog/")) {
    const slug = route.substring(6); // get everything after `/blog/`
    if (slug && slug !== "blog") {
      let post: any = null;

      if (db) {
        try {
          const posts = await db
            .select()
            .from(blogPosts)
            .where(and(eq(blogPosts.slug, slug), eq(blogPosts.active, true)))
            .limit(1);

          if (posts.length > 0) {
            post = posts[0];
          }
        } catch (err) {
          console.error("Error in prerendering blog details DB:", err);
        }
      }

      // Local JSON fallback
      if (!post) {
        try {
          const seedFilePath = path.resolve(import.meta.dirname, "../data/blog_posts_seed.json");
          if (fs.existsSync(seedFilePath)) {
            const localPosts = JSON.parse(fs.readFileSync(seedFilePath, "utf8"));
            post = localPosts.find((p: any) => p.slug === slug && p.active);
          }
        } catch (localErr) {
          console.error("Error reading local blog seed fallback for prerender details:", localErr);
        }
      }

      if (post) {
        return {
          title: `${post.title} | Blog VetRank`,
          description: post.summary,
          keywords: "veterinária, artigos científicos veterinária, " + post.title.toLowerCase().replace(/[^a-z0-9\s]/g, "").split(" ").join(", "),
          canonical: `/blog/${post.slug}`,
          jsonLd: [
            baseJsonLd,
            {
              "@context": "https://schema.org",
              "@type": "BlogPosting",
              "headline": post.title,
              "description": post.summary,
              "author": {
                "@type": "Person",
                "name": post.author
              },
              "datePublished": post.createdAt,
              "dateModified": post.updatedAt,
              "mainEntityOfPage": `https://vetrank.com.br/blog/${post.slug}`
            },
            {
              "@context": "https://schema.org",
              "@type": "BreadcrumbList",
              "itemListElement": [
                {
                  "@type": "ListItem",
                  "position": 1,
                  "name": "Home",
                  "item": "https://vetrank.com.br"
                },
                {
                  "@type": "ListItem",
                  "position": 2,
                  "name": "Blog",
                  "item": "https://vetrank.com.br/blog"
                },
                {
                  "@type": "ListItem",
                  "position": 3,
                  "name": post.title,
                  "item": `https://vetrank.com.br/blog/${post.slug}`
                }
              ]
            }
          ],
          bodyHtml: `
            <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
              <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
                <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
                <div>
                  <a href="/blog" style="color: #10b981; margin-left: 20px; text-decoration: none;">Blog</a>
                  <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
                  <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
                </div>
              </nav>
            </header>

            <main style="max-width: 800px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
              <article>
                <span style="color: #10b981; font-size: 14px; font-weight: bold;">Artigo Científico</span>
                <h1 style="font-size: 36px; font-family: serif; color: white; margin-top: 10px; margin-bottom: 20px; line-height: 1.2;">
                  ${post.title}
                </h1>
                <div style="color: #9ca3af; font-size: 14px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #21262d;">
                  Por <strong>${post.author}</strong> • Publicado em ${new Date(post.createdAt).toLocaleDateString("pt-BR")}
                </div>
                <div style="background-color: #161b22; padding: 20px; border-radius: 12px; border: 1px solid #21262d; margin-bottom: 30px; font-style: italic;">
                  ${post.summary}
                </div>
                <div style="line-height: 1.8; font-size: 18px; color: #d1d5db;">
                  ${post.body}
                </div>
              </article>
            </main>
          `
        };
      }
    }
  }

  // Blog Listing Router: `/blog`
  if (route === "/blog") {
    let postList: any[] = [];

    if (db) {
      try {
        const posts = await db.execute(`
          SELECT slug, title, summary, author, createdAt
          FROM blog_posts
          WHERE active = 1
          ORDER BY createdAt DESC
          LIMIT 10
        `) as any;

        postList = posts[0] || [];
      } catch (err) {
        console.error("Error fetching blog list for prerender DB:", err);
      }
    }

    // Local JSON fallback
    if (postList.length === 0) {
      try {
        const seedFilePath = path.resolve(import.meta.dirname, "../data/blog_posts_seed.json");
        if (fs.existsSync(seedFilePath)) {
          const localPosts = JSON.parse(fs.readFileSync(seedFilePath, "utf8"));
          postList = localPosts.filter((p: any) => p.active).slice(0, 10);
        }
      } catch (localErr) {
        console.error("Error reading local blog seed fallback for prerender list:", localErr);
      }
    }

    const listHtml = postList
      .map(
        (p: any) => `
        <div style="background-color: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #21262d; margin-bottom: 20px;">
          <a href="/blog/${p.slug}" style="font-size: 22px; font-family: serif; color: #10b981; font-weight: bold; text-decoration: none; display: block; margin-bottom: 10px;">
            ${p.title}
          </a>
          <p style="color: #9ca3af; font-size: 15px; line-height: 1.6; margin-bottom: 15px;">
            ${p.summary}
          </p>
          <span style="color: #6b7280; font-size: 13px;">Por ${p.author} • ${new Date(p.createdAt).toLocaleDateString("pt-BR")}</span>
        </div>
      `
      )
      .join("");

    return {
      title: "Blog Científico e Guias de Estudo | VetRank",
      description: "Acesse artigos científicos, resumos de editais e dicas práticas elaboradas por especialistas para provas de Residência Veterinária, ENARE e ENADE.",
      keywords: "veterinária, blog veterinária, artigos veterinária, enare veterinária, enade veterinária, residência veterinária",
      canonical: "/blog",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "Blog",
          "name": "Blog Científico VetRank",
          "url": "https://vetrank.com.br/blog",
          "description": "Artigos de excelência e guias de estudos para Medicina Veterinária."
        },
        {
          "@context": "https://schema.org",
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://vetrank.com.br"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Blog",
              "item": "https://vetrank.com.br/blog"
            }
          ]
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/blog" style="color: #10b981; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1000px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
          <h1 style="font-size: 40px; font-family: serif; color: white; margin-bottom: 10px; text-align: center;">Blog Científico VetRank</h1>
          <p style="color: #9ca3af; font-size: 18px; text-align: center; max-width: 700px; margin: 0 auto 50px auto;">
            Guias de estudos, revisões de conteúdos e atualizações clínicas do cenário da Medicina Veterinária.
          </p>
          <div>
            ${listHtml || `<p style="text-align: center; color: #6b7280;">Preparando artigos científicos...</p>`}
          </div>
        </main>
      `
    };
  }

  // Course Details Router: `/cursos/:slug`
  if (route.startsWith("/cursos/")) {
    const slug = route.substring(8); // get everything after `/cursos/`
    if (slug && slug !== "cursos") {
      let course: any = null;

      if (db) {
        try {
          const fetchedCourses = await db
            .select()
            .from(courses)
            .where(and(eq(courses.slug, slug), eq(courses.active, true)))
            .limit(1);

          if (fetchedCourses.length > 0) {
            course = fetchedCourses[0];
          }
        } catch (err) {
          console.error("Error in prerendering course details DB:", err);
        }
      }

      if (course) {
        return {
          title: `${course.title} | Cursos VetRank`,
          description: course.description || `Curso completo de ${course.title} na plataforma VetRank.`,
          keywords: `curso veterinaria, ${course.title.toLowerCase()}, medicina veterinaria, ${course.category || ""}`,
          canonical: `/cursos/${course.slug}`,
          jsonLd: [
            baseJsonLd,
            {
              "@context": "https://schema.org",
              "@type": "Course",
              "name": course.title,
              "description": course.description,
              "provider": {
                "@type": "Organization",
                "name": "VetRank",
                "sameAs": "https://vetrank.com.br"
              }
            }
          ],
          bodyHtml: `
            <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
              <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
                <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
                <div>
                  <a href="/cursos" style="color: #10b981; margin-left: 20px; text-decoration: none;">Cursos</a>
                  <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
                  <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
                </div>
              </nav>
            </header>

            <main style="max-width: 800px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
              <article>
                <span style="color: #10b981; font-size: 14px; font-weight: bold;">Curso Online - ${course.category || "Medicina Veterinária"}</span>
                <h1 style="font-size: 36px; font-family: serif; color: white; margin-top: 10px; margin-bottom: 20px; line-height: 1.2;">
                  ${course.title}
                </h1>
                <div style="color: #9ca3af; font-size: 14px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #21262d;">
                  Instrutor: <strong>${course.instructorName || "Equipe VetRank"}</strong> • Duração: ${course.durationHours} horas • Nível: ${course.level}
                </div>
                <div style="line-height: 1.8; font-size: 18px; color: #d1d5db;">
                  <p>${course.description || ""}</p>
                  ${course.objectives ? `<h3 style="color: white; margin-top: 30px;">O que você vai aprender:</h3><p>${course.objectives}</p>` : ""}
                </div>
              </article>
            </main>
          `
        };
      }
    }
  }

  // Course Listing Router: `/cursos`
  // Radar de Concursos: `/concursos/:slug`
  if (route.startsWith("/concursos/")) {
    const slug = route.substring("/concursos/".length);
    try {
      const { buscarConcursoPorSlug, fmtData, fmtSalario } = await import("./concursos");
      const c = await buscarConcursoPorSlug(slug);
      if (c) {
        const cargos = ((c.cargos as any[]) ?? []).map(x => `${x.nome}${x.vagas ? ` (${x.vagas})` : ""}${x.salario ? ` — ${x.salario}` : ""}`);
        const prazo = c.inscricoesFim ? `Inscrições até ${fmtData(c.inscricoesFim)}` : "Confira o edital";
        const sal = c.salarioMax ? ` Salário até ${fmtSalario(c.salarioMax)}.` : "";
        const areaLabel = c.area === "zootecnia" ? "Zootecnista" : c.area === "ambas" ? "Veterinário e Zootecnista" : "Médico Veterinário";
        const title = `Concurso ${c.orgao}${c.uf ? " (" + c.uf + ")" : ""} — ${areaLabel} | VetRank`;
        const description = `${c.vagasVet ? c.vagasVet + " para " + areaLabel + ". " : ""}${prazo}.${sal}${c.banca ? " Banca: " + c.banca + "." : ""}`.slice(0, 300);
        const h = (x: string) => attr(x);
        return {
          title, description,
          keywords: `concurso veterinário, concurso zootecnista, concurso médico veterinário ${c.uf ?? ""}, ${c.orgao.toLowerCase()}`,
          canonical: `/concursos/${c.slug}`,
          ogImage: `https://www.vetrank.com.br/api/og/concurso/${c.slug}.png`,
          ogType: "article",
          jsonLd: [baseJsonLd, {
            "@context": "https://schema.org", "@type": "BreadcrumbList",
            "itemListElement": [
              { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://vetrank.com.br" },
              { "@type": "ListItem", "position": 2, "name": "Concursos", "item": "https://vetrank.com.br/concursos" },
              { "@type": "ListItem", "position": 3, "name": c.orgao, "item": `https://vetrank.com.br/concursos/${c.slug}` },
            ],
          }],
          bodyHtml: `<main style="max-width:860px;margin:40px auto;padding:0 20px;color:#f3f4f6;font-family:sans-serif">
            <p><a href="/concursos" style="color:#10b981">Radar de Concursos — Veterinária e Zootecnia</a></p>
            <h1 style="font-family:serif">${h(c.orgao)}${c.uf ? " — " + h(c.uf) : ""}</h1>
            <p>${h(prazo)}${c.banca ? " · Banca: " + h(c.banca) : ""}</p>
            ${c.resumo ? `<p>${h(c.resumo)}</p>` : ""}
            <ul>${cargos.map(x => `<li>${h(x)}</li>`).join("")}</ul>
            <p><a href="/api/concursos/${h(c.slug)}/guia.pdf" style="color:#10b981">Baixar Resumo do Edital + Guia de Estudos (PDF)</a></p>
            <p>Curadoria oficial Radar VetRank. Confira sempre o edital oficial da banca organizadora.</p>
          </main>`,
        };
      }
    } catch (err) {
      console.error("Error in prerendering concurso:", err);
    }
  }

  if (route === "/concursos") {
    let lista: any[] = [];
    let fmt: (d: any) => string | null = () => null;
    try {
      const { listarConcursos, fmtData } = await import("./concursos");
      lista = (await listarConcursos({ limite: 60 })).filter(c => c.status !== "encerrado");
      fmt = fmtData;
    } catch (err) { console.error("Error prerendering /concursos:", err); }
    const h = (x: string) => attr(x);
    return {
      title: "Concursos para Veterinário e Zootecnista Abertos — Radar VetRank",
      description: `${lista.length} concursos com vagas para Médico Veterinário e Zootecnista vigentes. Prazos, vagas, salários, editais verticalizados em PDF e simulados.`,
      keywords: "concurso veterinário, concurso zootecnista, concurso médico veterinário 2026, concursos abertos veterinária, edital veterinário",
      canonical: "/concursos",
      ogImage: `https://www.vetrank.com.br/api/og/concursos-semana.png?d=${new Date().toISOString().slice(0, 10)}`,
      jsonLd: [baseJsonLd, {
        "@context": "https://schema.org", "@type": "ItemList", "name": "Concursos para Veterinário e Zootecnista",
        "itemListElement": lista.map((c, i) => ({ "@type": "ListItem", "position": i + 1, "url": `https://vetrank.com.br/concursos/${c.slug}` })),
      }],
      bodyHtml: `<main style="max-width:960px;margin:40px auto;padding:0 20px;color:#f3f4f6;font-family:sans-serif">
        <h1 style="font-family:serif">Concursos para Veterinária e Zootecnia vigentes</h1>
        <ul>${lista.map(c => `<li><a href="/concursos/${h(c.slug)}" style="color:#10b981">${h(c.orgao)}${c.uf ? " (" + h(c.uf) + ")" : ""}</a>${c.inscricoesFim ? " — inscrições até " + h(fmt(c.inscricoesFim) ?? "") : ""}</li>`).join("")}</ul>
      </main>`,
    };
  }

  if (route === "/cursos") {
    let courseList: any[] = [];

    if (db) {
      try {
        courseList = await db
          .select({
            slug: courses.slug,
            title: courses.title,
            description: courses.description,
            category: courses.category,
            instructorName: courses.instructorName,
            durationHours: courses.durationHours,
          })
          .from(courses)
          .where(eq(courses.active, true))
          .orderBy(desc(courses.createdAt));
      } catch (err) {
        console.error("Error fetching course list for prerender DB:", err);
      }
    }

    const listHtml = courseList
      .map(
        (c: any) => `
        <div style="background-color: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #21262d; margin-bottom: 20px;">
          <a href="/cursos/${c.slug}" style="font-size: 22px; font-family: serif; color: #10b981; font-weight: bold; text-decoration: none; display: block; margin-bottom: 10px;">
            ${c.title}
          </a>
          <p style="color: #9ca3af; font-size: 15px; line-height: 1.6; margin-bottom: 15px;">
            ${c.description || ""}
          </p>
          <span style="color: #6b7280; font-size: 13px;">Instrutor: ${c.instructorName || "Equipe VetRank"} • ${c.durationHours} horas • Categoria: ${c.category || "Geral"}</span>
        </div>
      `
      )
      .join("");

    return {
      title: "Cursos Online de Medicina Veterinária | VetRank",
      description: "Aprimore seus conhecimentos e prepare-se para o mercado com nossos cursos online em Medicina Veterinária, ministrados por especialistas e com certificado.",
      keywords: "cursos veterinaria, cursos online veterinaria, anatomia veterinaria, clinica medica veterinaria, certificado veterinaria",
      canonical: "/cursos",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "ItemList",
          "name": "Cursos VetRank",
          "itemListElement": courseList.map((c, idx) => ({
            "@type": "ListItem",
            "position": idx + 1,
            "url": `https://vetrank.com.br/cursos/${c.slug}`
          }))
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/cursos" style="color: #10b981; margin-left: 20px; text-decoration: none;">Cursos</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1000px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
          <h1 style="font-size: 40px; font-family: serif; color: white; margin-bottom: 10px; text-align: center;">Cursos de Medicina Veterinária</h1>
          <p style="color: #9ca3af; font-size: 18px; text-align: center; max-width: 700px; margin: 0 auto 50px auto;">
            Cursos teóricos e práticos com certificado para capacitação acadêmica e profissional.
          </p>
          <div>
            ${listHtml || `<p style="text-align: center; color: #6b7280;">Nenhum curso publicado no momento.</p>`}
          </div>
        </main>
      `
    };
  }

  // Verify Certificate: `/verificar-certificado`
  if (route.startsWith("/verificar-certificado")) {
    return {
      title: "Verificação de Certificados | VetRank",
      description: "Valide a autenticidade de certificados emitidos pela plataforma VetRank para cursos de Medicina Veterinária.",
      keywords: "verificar certificado, validar certificado, certificado vetrank, autenticidade certificado",
      canonical: "/verificar-certificado",
      jsonLd: [baseJsonLd],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/cursos" style="color: white; margin-left: 20px; text-decoration: none;">Cursos</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>
        <main style="max-width: 800px; margin: 40px auto; padding: 0 20px; color: #f3f4f6; text-align: center;">
          <h1 style="font-size: 36px; font-family: serif; color: white; margin-bottom: 20px;">Verificação de Certificado</h1>
          <p style="color: #9ca3af; line-height: 1.6; font-size: 16px;">
            Insira o código de autenticação do certificado para validar sua emissão oficial pelo VetRank.
          </p>
        </main>
      `
    };
  }

  // Home Page: `/`
  if (route === "/" || route === "") {
    return {
      title: "VetRank — Prepare-se para Medicina Veterinária | Banco de Questões",
      description: "Prepare-se para ENADE, ENARE, Residência Veterinária e provas de graduação em Medicina Veterinária com banco de questões, simulados cronometrados e gamificação completa.",
      keywords: "veterinária, questões veterinária, simulado veterinária, residência veterinária, ENADE veterinária, ENARE, banco de questões veterinária, anatomia veterinária, farmacologia veterinária",
      canonical: "/",
      jsonLd: [
        baseJsonLd,
        webSiteJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "FAQPage",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "O que é o VetRank?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "O VetRank é uma plataforma de estudos inteligente e gamificada desenvolvida especificamente para estudantes de Medicina Veterinária e profissionais formados que desejam se preparar para o ENADE, ENARE, provas de Residência e concursos públicos."
              }
            },
            {
              "@type": "Question",
              "name": "Quais provas são contempladas no banco de questões?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Nosso banco de questões engloba provas anteriores e simulados inéditos para o ENADE de Medicina Veterinária, provas nacionais de Residência (como ENARE e editais de universidades federais) e concursos municipais/estaduais."
              }
            },
            {
              "@type": "Question",
              "name": "A plataforma possui um plano gratuito?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "Sim! Ao se cadastrar, você inicia automaticamente um período de teste gratuito (trial) de 7 dias com acesso total a todos os recursos. Após esse período, você pode continuar usando a plataforma no plano gratuito básico com limite diário de resoluções ou assinar um de nossos planos Premium."
              }
            }
          ]
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/bank" style="color: white; margin-left: 20px; text-decoration: none;">Questões</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/enade" style="color: white; margin-left: 20px; text-decoration: none;">ENADE</a>
              <a href="/enare" style="color: white; margin-left: 20px; text-decoration: none;">ENARE</a>
              <a href="/residencia" style="color: white; margin-left: 20px; text-decoration: none;">Residência</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #f3f4f6;">
          <section style="text-align: center; margin-bottom: 60px;">
            <h1 style="font-size: 48px; font-family: serif; color: white; margin-bottom: 20px;">
              Acelere sua aprovação em <span style="color: #10b981;">Medicina Veterinária</span>
            </h1>
            <p style="font-size: 20px; color: #9ca3af; max-width: 800px; margin: 0 auto 30px auto; line-height: 1.6;">
              Estude com o maior banco de questões comentadas, simulados cronometrados personalizados e trilhas de aprendizado gamificadas com ranking semanal.
            </p>
            <div style="margin-bottom: 20px;">
              <a href="/register" style="background-color: #10b981; color: #0d1117; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 8px; text-decoration: none; display: inline-block;">
                Começar Teste Grátis (7 Dias)
              </a>
              <a href="/pricing" style="border: 1px solid #374151; color: white; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 8px; text-decoration: none; display: inline-block; margin-left: 15px;">
                Ver Planos Premium
              </a>
            </div>
            <p style="color: #9ca3af; font-size: 14px;">✓ Sem cartão de crédito necessário no cadastro</p>
          </section>

          <section style="margin-bottom: 60px;">
            <h2 style="font-size: 32px; font-family: serif; color: white; text-align: center; margin-bottom: 40px;">
              Tudo o que você precisa para dominar as provas
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
              <div style="background-color: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #21262d;">
                <h3 style="color: #10b981; font-size: 20px; margin-bottom: 10px;">Banco de Questões</h3>
                <p style="color: #9ca3af; line-height: 1.5;">Milhares de questões comentadas e explicadas detalhadamente por especialistas em Medicina Veterinária, organizadas por grande área, disciplina e assunto.</p>
              </div>
              <div style="background-color: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #21262d;">
                <h3 style="color: #10b981; font-size: 20px; margin-bottom: 10px;">Simulados Customizados</h3>
                <p style="color: #9ca3af; line-height: 1.5;">Monte provas idênticas aos editais do ENADE, ENARE e principais Residências. Defina tempo máximo, disciplinas e acompanhe a evolução do seu desempenho.</p>
              </div>
              <div style="background-color: #161b22; padding: 30px; border-radius: 12px; border: 1px solid #21262d;">
                <h3 style="color: #10b981; font-size: 20px; margin-bottom: 10px;">Gamificação & Hábito</h3>
                <p style="color: #9ca3af; line-height: 1.5;">Ganhe experiência (XP), conquiste medalhas por conquistas de desempenho e acompanhe seu nome no ranking semanal nacional dos melhores estudantes.</p>
              </div>
            </div>
          </section>
        </main>
      `
    };
  }

  // Pricing SEO
  if (route === "/pricing") {
    return {
      title: "Planos e Assinaturas Premium | VetRank",
      description: "Tenha acesso ilimitado ao maior banco de questões comentadas, simulados cronometrados estilo ENADE/ENARE e ranking do VetRank. Comece seu teste de 7 dias grátis.",
      keywords: "vetrank premium, planos vetrank, assinatura vetrank, preço vetrank",
      canonical: "/pricing",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "Product",
          "name": "Assinatura VetRank Premium",
          "image": "https://vetrank.com.br/og-image.png",
          "description": "Plataforma de estudos inteligente para Medicina Veterinária. Acesso ilimitado a simulados e banco de questões resolvidas e comentadas.",
          "offers": {
            "@type": "AggregateOffer",
            "priceCurrency": "BRL",
            "lowPrice": "29.90",
            "highPrice": "299.00",
            "offerCount": "2"
          }
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/pricing" style="color: #10b981; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/bank" style="color: white; margin-left: 20px; text-decoration: none;">Questões</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #f3f4f6; text-align: center;">
          <h1 style="font-size: 40px; font-family: serif; color: white; margin-bottom: 15px;">Escolha o plano ideal para a sua aprovação</h1>
          <p style="font-size: 18px; color: #9ca3af; max-width: 700px; margin: 0 auto 50px auto; line-height: 1.5;">
            Inicie no plano Gratuito ou acerte seus estudos com a nossa assinatura Premium. Cancele quando quiser.
          </p>
        </main>
      `
    };
  }

  // ENADE Landing SEO
  if (route === "/enade") {
    return {
      title: "Simulados e Questões ENADE de Medicina Veterinária | VetRank",
      description: "Prepare-se para o ENADE de Medicina Veterinária. Resolva questões de exames anteriores comentadas, faça simulados cronometrados oficiais e analise suas falhas.",
      keywords: "enade veterinaria, simulado enade veterinaria, questoes enade medicina veterinaria, prova enade veterinaria, enade veterinaria gabarito",
      canonical: "/enade",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "FAQPage",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "Como se preparar para o ENADE de Veterinária?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "A melhor preparação para o ENADE de Medicina Veterinária é a resolução de provas anteriores. No VetRank, todas as questões do ENADE estão disponíveis, gabaritadas e comentadas por grandes especialistas."
              }
            }
          ]
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/enade" style="color: #a855f7; margin-left: 20px; text-decoration: none;">ENADE</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #f3f4f6; text-align: center;">
          <h1 style="font-size: 44px; font-family: serif; color: white; margin-top: 15px; margin-bottom: 20px;">
            Gabarite a prova do <span style="color: #a855f7;">ENADE Veterinária</span>
          </h1>
        </main>
      `
    };
  }

  // ENARE Landing SEO
  if (route === "/enare") {
    return {
      title: "Questões e Simulados ENARE Medicina Veterinária | VetRank",
      description: "Conquiste sua vaga na residência dos sonhos. Resolva questões comentadas do ENARE de Medicina Veterinária, monte simulados cronometrados e estude por assunto.",
      keywords: "enare veterinaria, questoes enare medicina veterinaria, simulado enare veterinaria, prova enare veterinaria, residencia enare veterinaria",
      canonical: "/enare",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "FAQPage",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "O que é a prova do ENARE de Medicina Veterinária?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "O ENARE (Exame Nacional de Residência) é a principal porta de entrada para residências veterinárias em dezenas de hospitais universitários do Brasil."
              }
            }
          ]
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/enare" style="color: #06b6d4; margin-left: 20px; text-decoration: none;">ENARE</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #f3f4f6; text-align: center;">
          <h1 style="font-size: 44px; font-family: serif; color: white; margin-top: 15px; margin-bottom: 20px;">
            Acelere sua aprovação no <span style="color: #06b6d4;">ENARE Veterinária</span>
          </h1>
        </main>
      `
    };
  }

  // General Residency Landing SEO
  if (route === "/residencia") {
    return {
      title: "Questões e Simulados de Residência Veterinária | VetRank",
      description: "Estude pelas principais provas de Residência de Medicina Veterinária do Brasil. Banco de questões resolvidas e comentadas, simulados focados e trilhas de estudo.",
      keywords: "residencia veterinaria, questoes residencia veterinaria, prova de residencia veterinaria, simulado residencia veterinaria, residencia veterinaria USP, residencia veterinaria UNESP",
      canonical: "/residencia",
      jsonLd: [
        baseJsonLd,
        {
          "@context": "https://schema.org",
          "@type": "FAQPage",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "Como funcionam as provas de residência em Medicina Veterinária?",
              "acceptedAnswer": {
                "@type": "Answer",
                "text": "As provas de residência veterinária consistem em questões teóricas avançadas e, muitas vezes, questões discursivas."
              }
            }
          ]
        }
      ],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/residencia" style="color: #ec4899; margin-left: 20px; text-decoration: none;">Residência</a>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>

        <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #f3f4f6; text-align: center;">
          <h1 style="font-size: 44px; font-family: serif; color: white; margin-top: 15px; margin-bottom: 20px;">
            Garanta sua Aprovação na <span style="color: #ec4899;">Residência Veterinária</span>
          </h1>
        </main>
      `
    };
  }

  // Terms of Use Landing SEO
  if (route === "/terms") {
    return {
      title: "Termos e Condições de Uso | VetRank",
      description: "Leia os Termos e Condições de Uso da plataforma VetRank para compreender suas regras de estudo, planos e responsabilidades de uso.",
      keywords: "termos de uso vetrank, condicoes de uso, contrato vetrank",
      canonical: "/terms",
      jsonLd: [baseJsonLd],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>
        <main style="max-width: 800px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
          <h1 style="font-size: 36px; font-family: serif; color: white; margin-bottom: 20px;">Termos e Condições de Uso</h1>
          <p style="color: #9ca3af; line-height: 1.6; font-size: 16px;">
            Estes termos regem o uso da plataforma VetRank. Ao se cadastrar, você concorda integralmente com as condições descritas.
          </p>
        </main>
      `
    };
  }

  // Privacy Policy Landing SEO
  if (route === "/privacy") {
    return {
      title: "Política de Privacidade e Proteção de Dados | VetRank",
      description: "Saiba como a plataforma VetRank coleta, armazena, protege e trata os seus dados cadastrais e de desempenho de forma segura em conformidade com a LGPD.",
      keywords: "politica de privacidade vetrank, privacidade, lgpd vetrank, dados vetrank",
      canonical: "/privacy",
      jsonLd: [baseJsonLd],
      bodyHtml: `
        <header style="padding: 20px; background-color: #0d1117; color: white; border-bottom: 1px solid #1f2937;">
          <nav style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
            <a href="/" style="font-size: 24px; font-weight: bold; color: #10b981; text-decoration: none;">VetRank</a>
            <div>
              <a href="/pricing" style="color: white; margin-left: 20px; text-decoration: none;">Planos</a>
              <a href="/blog" style="color: white; margin-left: 20px; text-decoration: none;">Blog</a>
              <a href="/" style="color: white; margin-left: 20px; text-decoration: none;">Home</a>
            </div>
          </nav>
        </header>
        <main style="max-width: 800px; margin: 40px auto; padding: 0 20px; color: #f3f4f6;">
          <h1 style="font-size: 36px; font-family: serif; color: white; margin-bottom: 20px;">Política de Privacidade</h1>
          <p style="color: #9ca3af; line-height: 1.6; font-size: 16px;">
            A sua privacidade é nossa prioridade máxima. Entenda como tratamos seus dados cadastrais em conformidade com a LGPD.
          </p>
        </main>
      `
    };
  }

  // Fallback default
  return {
    title: "VetRank — Plataforma de Estudos para Medicina Veterinária",
    description: "Banco de questões de múltipla escolha e simulados cronometrados para ENADE, Residência, ENARE e provas de graduação em Medicina Veterinária.",
    keywords: "veterinária, simulado veterinária, questões veterinária",
    canonical: route,
    jsonLd: [baseJsonLd],
    bodyHtml: `<div style="text-align: center; padding: 50px; color: #9ca3af;"><h1 style="color: white;">VetRank</h1><p>Carregando a maior plataforma de Medicina Veterinária do Brasil...</p></div>`
  };
}
