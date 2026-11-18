#!/usr/bin/env node
/**
 * Post-build SEO (D-36): writes one HTML file per public route with its own <title>, description,
 * canonical URL, Open Graph/Twitter tags and JSON-LD, plus sitemap.xml.
 * Crawlers and link previews (WhatsApp, Telegram, Discord) read these without running JavaScript;
 * the SPA then renders as usual. Draft QuickHunts articles get noindex and stay out of the sitemap.
 * D-43: one share card per tool (frontend/public/og/*.png, scripts/og-images.mjs), og:locale, JSON-LD per page
 * and one pre-rendered page per hunting place (shared/hunting-places.json).
 */
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const DIST = path.join(ROOT, 'dist');
const SITE = 'https://bagparser.com';
const LANGS = ['en', 'pt-BR', 'es', 'pl'];
const OG_LOCALES = ['en_US', 'pt_BR', 'es_ES', 'pl_PL'];
/** D-84: bump when the cards change, so WhatsApp and the others fetch them again */
const OG_VERSION = 2;
const DEFAULT_IMAGE = { url: `${SITE}/og/home.png?v=${OG_VERSION}`, alt: 'BagParser — Tibia Hunt Analyser, loot router, hunting places and character tracker' };
const og = (name, alt) => ({ url: `${SITE}/og/${name}.png?v=${OG_VERSION}`, alt });
const today = new Date().toISOString().slice(0, 10);

const ROUTES = [
  {
    path: '/',
    title: 'BagParser — Tibia Hunt Analyser, loot router and hunting places',
    description:
      'Free Tibia tools: save and compare hunts (XP/h, profit, build, damage taken per creature, party payments), sell loot at the best NPC, hunting places with map, character tracker, daily Rashid, Yasir and boosted info, and a daily Tibia bulletin on Telegram and Discord.',
    priority: '1.0',
    changefreq: 'daily',
  },
  {
    path: '/parser',
    image: og('parser', 'Tibia loot parser: best NPC per item, city route and party split'),
    kind: 'app',
    title: 'Tibia Loot Parser — where to sell each item | BagParser',
    description:
      'Free Tibia loot parser: paste the Hunt or Party Hunt Analyser and get the best NPC and city for every item, market alerts, sell route, profit per hour and party loot split.',
    priority: '0.9',
    changefreq: 'weekly',
  },
  {
    path: '/hunt-analyser',
    image: og('hunt-analyser', 'Tibia Hunt Analyser: hunts, build, damage per creature and party payments'),
    kind: 'app',
    title: 'Tibia Hunt Analyser — hunts, build, charms and party payments | BagParser',
    description:
      'Save your Tibia hunts from the Hunt, Party, Input Analyser and Server Log: XP/h, profit after extra expenses, set, Wheel, charms and preys, damage taken per creature and element, creature analysis, party payments with transfer commands, reports and character performance.',
    priority: '0.9',
    changefreq: 'daily',
  },
  {
    path: '/drops',
    image: og('drops', 'Which Tibia creature drops an item most often'),
    kind: 'app',
    title: 'Where to get a Tibia item — best creature to drop it | BagParser',
    description:
      'Find which Tibia creature drops an item most often, how many kills it takes on average and where to hunt it. Built for weekly delivery tasks: add several items and see places that cover them all.',
    priority: '0.8',
    changefreq: 'weekly',
  },
  {
    path: '/hunt-analyser/public',
    kind: 'page',
    image: og('ranking', 'Public Tibia hunts and the best of the week'),
    title: 'Public Tibia hunts by vocation and level | BagParser',
    description: 'Hunt sessions shared by Tibia players, with the best of the week per vocation: XP/h, profit/h, place, level and vocation. Filter to find what works for your character.',
    priority: '0.7',
    changefreq: 'daily',
  },
  {
    path: '/hunt-analyser/ranking',
    kind: 'page',
    image: og('ranking', 'Tibia hunt ranking: best XP/h and profit of the week'),
    title: 'Tibia hunt ranking — best XP/h and profit of the week | BagParser',
    description: 'Weekly ranking of the best public Tibia hunts by XP/h, raw XP/h and profit/h, plus community statistics per hunting place.',
    priority: '0.7',
    changefreq: 'daily',
  },
  {
    path: '/pricing',
    image: og('pricing', 'BagParser Pro: Server Log analysis, automatic character tracking, alerts and no ads'),
    title: 'BagParser Pro — plans and support | BagParser',
    description:
      'BagParser Pro: full Server Log analysis saved with the hunt, up to 10 characters tracked automatically, instant level/death/goal alerts on Telegram and Discord, guild XP ranking and no ads. Card (Stripe) or Pix.',
    priority: '0.4',
    changefreq: 'monthly',
  },
  {
    path: '/characters',
    image: og('characters', 'Tibia character tracker with level goals and ETA'),
    kind: 'app',
    title: 'Tibia character tracker — level goals and XP per day | BagParser',
    description:
      'Track your Tibia character: level history, experience per day, deaths, and level goals with ETA plus weekly and monthly targets.',
    priority: '0.8',
    changefreq: 'weekly',
  },
  {
    path: '/spawnrank',
    image: og('spawnrank', 'SpawnRank: best hunting places for any Tibia creature'),
    kind: 'app',
    title: 'SpawnRank — best Tibia hunting places for a creature | BagParser',
    description: 'Find where to hunt a Tibia creature: hunting places ranked by spawn density, task synergy, profit and access.',
    priority: '0.7',
    changefreq: 'weekly',
  },
  {
    path: '/bossledger',
    image: og('bossledger', 'BossLedger: Tibia boss drop rates from the community'),
    kind: 'app',
    title: 'BossLedger — Tibia boss drop rates from the community | BagParser',
    description: 'Community-recorded Tibia boss loot: drop rates per item for each boss, from real server log lines.',
    priority: '0.7',
    changefreq: 'daily',
  },
  {
    path: '/quickhunts',
    image: og('quickhunts', 'QuickHunts: short Tibia hunting guides'),
    kind: 'page',
    title: 'QuickHunts — short Tibia hunting guides | BagParser',
    description: 'Short Tibia hunting guides: level, vocation, route and loot for popular hunting places.',
    priority: '0.6',
    changefreq: 'weekly',
  },
  {
    path: '/hunting',
    image: og('hunting', 'Tibia hunting places by level and vocation'),
    kind: 'app',
    title: 'Tibia hunting places by level and vocation | BagParser',
    description: 'Every Tibia hunting place with recommended level and skills for the 5 vocations, experience and loot rating, creatures, access quests and a fast map with stairs, ropes and NPC markers.',
    priority: '0.8',
    changefreq: 'weekly',
  },
  {
    path: '/creatures',
    image: og('creatures', 'Tibia creatures: weaknesses, damage and drops'),
    kind: 'app',
    title: 'Tibia creatures: weaknesses, damage and drops | BagParser',
    description: 'Every Tibia creature at a glance: which element to hit it with, what to protect from, drops by rarity, abilities and where to hunt it.',
    priority: '0.7',
    changefreq: 'weekly',
  },
  {
    path: '/soulcores',
    image: og('soulcores', 'Tibia soul cores tracker per character'),
    kind: 'app',
    title: 'Soul cores tracker — which ones your character still needs | BagParser',
    description: 'Track the Tibia soul cores your character already has for the Soulpit and see which are missing, by bestiary class and difficulty.',
    priority: '0.6',
    changefreq: 'weekly',
  },
  {
    path: '/yasir',
    image: og('yasir', 'Where Yasir is today, per Tibia world'),
    kind: 'page',
    title: 'Yasir today — where the travelling trader is, per world | BagParser',
    description: 'Where is Yasir today in Tibia? Pick your world and report him in one tap; cities confirmed by trusted players, plus patterns per world and weekday.',
    priority: '0.6',
    changefreq: 'daily',
  },
  {
    path: '/news',
    image: og('news', 'BagParser news: Telegram bot and daily Tibia bulletin'),
    kind: 'news',
    title: 'BagParser news — new Tibia tools and updates',
    description:
      'What is new on BagParser: the Telegram bot with a daily Tibia bulletin (boosted, Rashid, Yasir, rare bosses killed), instant character alerts, guild XP ranking, Hunt Analyser updates and more.',
    priority: '0.6',
    changefreq: 'weekly',
  },
  {
    path: '/support',
    title: 'Support and bug reports | BagParser',
    description: 'Report a problem, ask a question or send a suggestion to the BagParser team, on the site or through the Telegram bot @bagparser_bot.',
    priority: '0.3',
    changefreq: 'monthly',
  },
  { path: '/about', title: 'About BagParser — contact and how the site is funded', description: 'About BagParser, a Tibia fansite: contact, credits to CipSoft and TibiaWiki, and how the site is funded.', priority: '0.3', changefreq: 'monthly' },
  { path: '/privacy', title: 'Privacy Policy | BagParser', description: 'How BagParser handles personal data under the LGPD and GDPR.', priority: '0.2', changefreq: 'yearly' },
  { path: '/terms', title: 'Terms of Use | BagParser', description: 'Terms of use of the BagParser Tibia fansite.', priority: '0.2', changefreq: 'yearly' },
  // Not indexed: personal or transactional pages
  { path: '/login', title: 'Sign in | BagParser', description: 'Sign in to BagParser.', noindex: true },
  { path: '/register', title: 'Create account | BagParser', description: 'Create a free BagParser account.', noindex: true },
  { path: '/forgot', title: 'Forgot password | BagParser', description: 'Get a link to choose a new BagParser password.', noindex: true },
  { path: '/verify', title: 'Confirm your e-mail | BagParser', description: 'Confirm the e-mail of your BagParser account.', noindex: true },
  { path: '/account', title: 'My account | BagParser', description: 'Your BagParser account, plan and bulletins.', noindex: true },
  { path: '/retro', title: 'Your month in Tibia | BagParser', description: 'Your month in Tibia in numbers, with a card to share.', noindex: true },
];

/** D-84: what WhatsApp and the other apps show next to the card. The page <title> stays in English for search engines. */
const SHARE_TEXT = {
  '/': ['BagParser: cace melhor, venda melhor', 'Analise suas hunts, venda o loot no NPC certo e acompanhe seus personagens de Tibia. Grátis.'],
  '/parser': ['Venda cada item no lugar certo | BagParser', 'Cole o analisador de loot do Tibia e veja o melhor NPC, a rota de cidades e a divisão da party.'],
  '/hunt-analyser': ['Toda hunt salva e explicada | BagParser', 'XP/h, lucro, build, dano por criatura e divisão da party das suas hunts de Tibia.'],
  '/hunt-analyser/ranking': ['As melhores hunts da semana | BagParser', 'Top XP/h e lucro/h por vocação, com hunts reais da comunidade de Tibia.'],
  '/drops': ['Quem dropa esse item? | BagParser', 'A melhor criatura de Tibia para cada item e quantas kills você vai precisar.'],
  '/characters': ['Sua meta de level com data | BagParser', 'Histórico de level, XP por dia, mortes e previsão de meta dos seus personagens de Tibia.'],
  '/spawnrank': ['O melhor lugar para sua task | BagParser', 'Escolha a criatura de Tibia e veja os locais de caça em ordem.'],
  '/bossledger': ['Controle todos os seus bosses | BagParser', 'Cooldown por personagem e drops de bosses de Tibia registrados pela comunidade.'],
  '/quickhunts': ['Guias de hunt por vocação | BagParser', 'Do level 8 ao end-game: rota, loot e dicas para cada vocação de Tibia.'],
  '/hunting': ['Onde caçar no seu level | BagParser', 'Hunts de Tibia por vocação, com o melhor elemento para bater e para se proteger.'],
  '/creatures': ['Conheça cada criatura de Tibia | BagParser', 'Com o que bater, do que se proteger, drops e onde caçar cada criatura.'],
  '/soulcores': ['Quais soul cores faltam? | BagParser', 'Marque o que seu personagem de Tibia já tem e veja o que falta.'],
  '/yasir': ['Onde está o Yasir hoje? | BagParser', 'Escolha o mundo de Tibia e veja a cidade confirmada pelos jogadores.'],
  '/news': ['Novidades do BagParser', 'Ferramentas novas, boletim diário e avisos no Telegram para quem joga Tibia.'],
  '/pricing': ['Apoie o site e libere o Pro | BagParser', 'Server Log completo, 10 personagens, avisos na hora e sem anúncios.'],
};

const slugify = (name) =>
  name
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/'/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, ''); // same rule as backend/src/huntingPlaces.ts

/** One page per hunting place (D-43): title/description built from the TibiaWiki data */
async function huntingRoutes() {
  let places = [];
  try {
    places = JSON.parse(await readFile(path.resolve(ROOT, '../shared/hunting-places.json'), 'utf8')).places ?? [];
  } catch {
    return [];
  }
  const seen = new Set();
  const out = [];
  for (const p of places) {
    const slug = slugify(p.name);
    if (!slug || seen.has(slug)) continue;
    seen.add(slug);
    const levels = [p.level?.knights, p.level?.paladins, p.level?.mages].filter((v) => v != null);
    const min = levels.length ? Math.min(...levels) : null;
    const creatures = (p.creatures ?? []).slice(0, 5).join(', ');
    const quest = p.accessQuests?.length ? ` Access may need: ${p.accessQuests.slice(0, 2).join(', ')}.` : '';
    out.push({
      path: `/hunting/${slug}`,
      title: `${p.name}${p.city ? ` (${p.city})` : ''} — Tibia hunting place${min ? `, level ${min}+` : ''} | BagParser`,
      description: `${p.name}${p.city ? ` near ${p.city}` : ''}: ${min ? `recommended from level ${min}, ` : ''}${
        p.expStars ? `experience ${p.expStars}/5, ` : ''
      }${p.lootStars ? `loot ${p.lootStars}/5, ` : ''}creatures ${creatures || 'listed on the page'}.${quest} Map and levels per vocation.`.slice(0, 300),
      priority: '0.5',
      changefreq: 'monthly',
      image: og('hunting', `${p.name} — Tibia hunting place`),
      share: [
        `${p.name}${min ? `: level ${min}+` : ''} | BagParser`,
        `Local de caça de Tibia${p.city ? ` perto de ${p.city}` : ''}. Criaturas: ${creatures || 'veja na página'}. Mapa e level por vocação.`.slice(0, 200),
      ],
      kind: 'place',
      breadcrumb: [
        ['Hunting places', '/hunting'],
        [p.name, `/hunting/${slug}`],
      ],
    });
  }
  return out;
}

/** D-84: each creature gets its own link preview. Only the sharing tags: not indexed and not in the sitemap. */
async function creatureRoutes() {
  let creatures = [];
  try {
    creatures = JSON.parse(await readFile(path.resolve(ROOT, '../shared/creatures.json'), 'utf8')).creatures ?? [];
  } catch {
    return [];
  }
  const up = (e) => e.charAt(0).toUpperCase() + e.slice(1);
  const number = (v) => v.toLocaleString('pt-BR');
  const seen = new Set();
  const out = [];
  for (const c of creatures) {
    const slug = slugify(c.name);
    if (!slug || seen.has(slug)) continue;
    seen.add(slug);
    const best = ['physical', 'earth', 'fire', 'energy', 'ice', 'holy', 'death']
      .map((e) => [e, c.el?.[e] ?? 100])
      .filter(([, pct]) => pct > 100)
      .sort((a, b) => b[1] - a[1])[0];
    const protect = Object.entries(c.out ?? {}).sort((a, b) => b[1] - a[1]).slice(0, 2).map(([e]) => up(e));
    const text = [
      best ? `Bata com ${up(best[0])} (+${best[1] - 100}%).` : null,
      protect.length ? `Proteja-se de ${protect.join(' e ')}.` : null,
      `HP ${number(c.hp)}, XP ${number(c.exp)}. Drops e onde caçar.`,
    ].filter(Boolean).join(' ');
    out.push({
      path: `/creatures/${slug}`,
      title: `${c.name}: Tibia creature | BagParser`,
      description: `${c.name} in Tibia: weaknesses, damage, drops and where to hunt it.`,
      noindex: true,
      image: og('creatures', `${c.name}, Tibia creature`),
      kind: 'page',
      share: [`${c.name}: fraquezas, drops e onde caçar | BagParser`, text],
    });
  }
  return out;
}

async function quickhuntRoutes() {
  const dir = path.join(ROOT, 'src/content/quickhunts');
  const out = [];
  for (const file of (await readdir(dir)).filter((f) => f.endsWith('.md'))) {
    const text = await readFile(path.join(dir, file), 'utf8');
    const title = /^title:\s*(.+)$/m.exec(text)?.[1]?.trim() ?? file;
    const city = /^city:\s*(.+)$/m.exec(text)?.[1]?.trim();
    const level = /^level:\s*(.+)$/m.exec(text)?.[1]?.trim();
    out.push({
      path: `/quickhunts/${file.replace(/\.md$/, '')}`,
      title: `${title} — Tibia hunting guide | BagParser`,
      description: `${title}${city ? ` in ${city}` : ''}${level ? ` (level ${level})` : ''}: how to get there, route, loot and tips.`,
      priority: '0.6',
      changefreq: 'monthly',
      noindex: /\*\*Draft:\*\*/.test(text),
      image: og('quickhunts', title),
      kind: 'page',
      breadcrumb: [
        ['QuickHunts', '/quickhunts'],
        [title, `/quickhunts/${file.replace(/\.md$/, '')}`],
      ],
    });
  }
  return out;
}

const esc = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

/** D-77: news articles (English titles) read from the page source, for the Blog structured data */
async function newsArticles() {
  try {
    const src = await readFile(path.join(ROOT, 'src/pages/NewsPage.tsx'), 'utf8');
    const out = [];
    const re = /id: '([^']+)',\s*slug: '([^']+)',\s*date: '(\d{4}-\d{2}-\d{2})',[\s\S]*?title: \{[\s\S]*?en: '((?:[^'\\]|\\.)*)'[\s\S]*?excerpt: \{[\s\S]*?en: '((?:[^'\\]|\\.)*)'/g;
    for (const m of src.matchAll(re)) out.push({ id: m[1], date: m[3], title: m[4].replace(/\\'/g, "'"), excerpt: m[5].replace(/\\'/g, "'") });
    return out;
  } catch {
    return [];
  }
}
let NEWS = [];

/** schema.org data: the site (home), a free web app (tools), or a page with breadcrumbs */
function structuredData(route) {
  const url = SITE + route.path;
  const graph = [];
  if (route.path === '/') {
    graph.push(
      { '@type': 'WebSite', '@id': `${SITE}/#site`, name: 'BagParser', url: `${SITE}/`, inLanguage: LANGS },
      {
        '@type': 'WebApplication',
        name: 'BagParser',
        url: `${SITE}/`,
        applicationCategory: 'GameApplication',
        operatingSystem: 'Any',
        inLanguage: LANGS,
        offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
        description: route.description,
      },
    );
  } else if (route.kind === 'app') {
    graph.push({
      '@type': 'WebApplication',
      name: route.title.replace(/ \| BagParser$/, ''),
      url,
      applicationCategory: 'GameApplication',
      operatingSystem: 'Any',
      isPartOf: { '@id': `${SITE}/#site` },
      offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
      description: route.description,
    });
  } else if (route.kind === 'news') {
    graph.push({
      '@type': 'Blog',
      name: route.title,
      url,
      description: route.description,
      isPartOf: { '@id': `${SITE}/#site` },
      blogPost: NEWS.slice(0, 20).map((a) => ({
        '@type': 'BlogPosting',
        headline: a.title.replace(/^[^A-Za-z0-9]+/, '').slice(0, 110),
        description: a.excerpt,
        datePublished: a.date,
        url: `${url}#${a.id}`,
        author: { '@type': 'Organization', name: 'BagParser' },
      })),
    });
  } else {
    graph.push({ '@type': 'WebPage', name: route.title, url, description: route.description, isPartOf: { '@id': `${SITE}/#site` } });
  }
  const crumbs = route.breadcrumb ?? (route.path === '/' ? null : [[route.title.replace(/ [|—].*$/, ''), route.path]]);
  if (crumbs) {
    graph.push({
      '@type': 'BreadcrumbList',
      itemListElement: [['BagParser', '/'], ...crumbs].map(([name, p], i) => ({
        '@type': 'ListItem',
        position: i + 1,
        name,
        item: SITE + p,
      })),
    });
  }
  // "<" escaped so a title can never close the script tag
  return `<script type="application/ld+json">${JSON.stringify({ '@context': 'https://schema.org', '@graph': graph }).replace(/</g, '\\u003c')}</script>`;
}

function headFor(route) {
  const url = SITE + route.path;
  const image = route.image ?? DEFAULT_IMAGE;
  // No hreflang: the pre-rendered HTML is English and the language switches in the browser, so ?lang= URLs
  // would canonicalise to this same page (conflicting signals). Real per-language paths: see STATUS.
  const ogLocales = OG_LOCALES.slice(1)
    .map((l) => `<meta property="og:locale:alternate" content="${l}" />`)
    .join('\n    ');
  const jsonLd = route.noindex ? '' : structuredData(route);
  const [shareTitle, shareDescription] = route.share ?? SHARE_TEXT[route.path] ?? [route.title, route.description];
  return `<title>${esc(route.title)}</title>
    <meta name="description" content="${esc(route.description)}" />
    <link rel="canonical" href="${url}" />
    ${route.noindex ? '<meta name="robots" content="noindex, follow" />' : ''}
    <meta property="og:title" content="${esc(shareTitle)}" />
    <meta property="og:description" content="${esc(shareDescription)}" />
    <meta property="og:url" content="${url}" />
    <meta property="og:locale" content="${OG_LOCALES[0]}" />
    ${ogLocales}
    <meta property="og:image" content="${image.url}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="${esc(image.alt)}" />
    <meta name="twitter:title" content="${esc(shareTitle)}" />
    <meta name="twitter:description" content="${esc(shareDescription)}" />
    <meta name="twitter:image" content="${image.url}" />
    <meta name="twitter:image:alt" content="${esc(image.alt)}" />
    ${jsonLd}`;
}

async function main() {
  const template = await readFile(path.join(DIST, 'index.html'), 'utf8');
  // Remove the generic tags that each route replaces
  const base = template
    .replace(/<title>.*?<\/title>/s, '<!--SEO-->')
    .replace(/\s*<meta name="description"[^>]*>/, '')
    .replace(/\s*<meta property="og:(title|description|url|image|image:width|image:height)"[^>]*>/g, '')
    .replace(/\s*<meta name="twitter:image"[^>]*>/g, '');

  NEWS = await newsArticles();
  if (!NEWS.length) console.warn('seo: no news articles found in NewsPage.tsx');
  const routes = [...ROUTES, ...(await quickhuntRoutes()), ...(await huntingRoutes()), ...(await creatureRoutes())];
  for (const route of routes) {
    const html = base.replace('<!--SEO-->', headFor(route));
    const file = route.path === '/' ? path.join(DIST, 'index.html') : path.join(DIST, route.path.slice(1), 'index.html');
    await mkdir(path.dirname(file), { recursive: true });
    await writeFile(file, html);
  }

  const urls = routes
    .filter((r) => !r.noindex)
    .map(
      (r) => `  <url>
    <loc>${SITE}${r.path}</loc>
    <lastmod>${today}</lastmod>
    <changefreq>${r.changefreq}</changefreq>
    <priority>${r.priority}</priority>
  </url>`,
    )
    .join('\n');
  await writeFile(
    path.join(DIST, 'sitemap.xml'),
    `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>
`,
  );
  console.log(`seo: ${routes.length} route pages, ${routes.filter((r) => !r.noindex).length} in sitemap.xml`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
