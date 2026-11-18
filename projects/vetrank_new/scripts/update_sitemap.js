import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SEED_FILE = path.join(__dirname, "../data/blog_posts_seed.json");
const SITEMAP_FILE = path.join(__dirname, "../client/public/sitemap.xml");

function run() {
  console.log("Generating sitemap.xml with 50+ blog posts...");

  const posts = JSON.parse(fs.readFileSync(SEED_FILE, "utf8"));
  const nowStr = new Date().toISOString().split("T")[0];
  
  let xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://vetrank.com.br/</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/bank</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/leaderboard</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/pricing</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/enade</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/enare</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/residencia</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/blog</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/cursos</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/verificar-certificado</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
`;

  // Dynamically append all 50 blog posts detail URLs
  posts.forEach(p => {
    if (p.active) {
      const date = p.updatedAt || p.createdAt || nowStr;
      const dateStr = new Date(date).toISOString().split("T")[0];
      xml += `  <url>\n    <loc>https://vetrank.com.br/blog/${p.slug}</loc>\n    <lastmod>${dateStr}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n  </url>\n`;
    }
  });

  xml += `  <url>
    <loc>https://vetrank.com.br/terms</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
  </url>
  <url>
    <loc>https://vetrank.com.br/privacy</loc>
    <lastmod>${nowStr}</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.3</priority>
  </url>
</urlset>
`;

  fs.writeFileSync(SITEMAP_FILE, xml, "utf8");
  console.log(`Successfully generated sitemap.xml at: ${SITEMAP_FILE}`);
}

run();
