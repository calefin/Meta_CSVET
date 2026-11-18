#!/usr/bin/env node
/**
 * Share-card images (Open Graph / Twitter, 1200×630) per tool (D-43), drawn as HTML and rendered by a local
 * headless Chrome. Run by hand after changing the cards; the PNGs are committed (the Docker build does not run it).
 *
 * Usage: node scripts/og-images.mjs            (CHROME=/path/to/chrome to override the browser)
 *        ONLY=news,pricing node scripts/og-images.mjs   (renders just those cards)
 * Output: frontend/public/og/<name>.png
 */
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'frontend/public/og');
const ITEM = (id) => `https://static.tibia.com/images/charactertrade/objects/${id}.gif`;
const CREATURE = (race) => `https://static.tibia.com/images/library/${race}.gif`;
/** Image bundled with the site (frontend/public) */
const LOCAL = (rel) => pathToFileURL(path.join(ROOT, 'frontend/public', rel)).href;

/**
 * One card per shareable tool (D-84). Made to stand out in a WhatsApp preview: dark background, one colour per tool,
 * big sprites, a short headline in Portuguese. `art` = main sprite + up to two smaller ones (CipSoft, credited on the site).
 */
export const CARDS = [
  {
    name: 'home',
    accent: '#f5b301',
    kicker: 'Fansite de Tibia',
    title: 'Cace melhor, venda melhor',
    subtitle: 'Analise suas hunts, venda o loot no NPC certo e acompanhe seus personagens.',
    art: [CREATURE('demon'), ITEM(3386)],
    path: '/',
  },
  {
    name: 'hunt-analyser',
    accent: '#38bdf8',
    kicker: 'Hunt Analyser',
    title: 'Toda hunt salva e explicada',
    subtitle: 'XP/h, lucro, build, dano por criatura e divisão da party.',
    art: [CREATURE('wardragon'), ITEM(3079)],
    path: '/hunt-analyser',
  },
  {
    name: 'ranking',
    accent: '#fbbf24',
    kicker: 'Ranking de hunts',
    title: 'As melhores hunts da semana',
    subtitle: 'Top XP/h e lucro/h por vocação, com hunts reais da comunidade.',
    art: [ITEM(3079), CREATURE('hydra')],
    path: '/hunt-analyser/ranking',
  },
  {
    name: 'drops',
    accent: '#4ade80',
    kicker: 'Onde dropar',
    title: 'Quem dropa esse item?',
    subtitle: 'A melhor criatura para cada item e quantas kills você vai precisar.',
    art: [ITEM(3386), CREATURE('dragonlord')],
    path: '/drops',
  },
  {
    name: 'parser',
    accent: '#facc15',
    kicker: 'Analisador de loot',
    title: 'Venda cada item no lugar certo',
    subtitle: 'Cole o analisador: melhor NPC, rota de cidades e divisão da party.',
    art: [ITEM(2871), ITEM(3386)],
    path: '/parser',
  },
  {
    name: 'hunting',
    accent: '#34d399',
    kicker: 'Hunts por vocação',
    title: 'Onde caçar no seu level',
    subtitle: 'Melhor elemento para bater e para se proteger, em cada hunt.',
    art: [CREATURE('hydra'), CREATURE('dragon'), ITEM(10302)],
    path: '/hunting',
  },
  {
    name: 'creatures',
    accent: '#fb7185',
    kicker: 'Ficha de criaturas',
    title: 'Conheça cada criatura',
    subtitle: 'Com o que bater, do que se proteger, drops e onde caçar.',
    art: [CREATURE('dragonlord'), CREATURE('medusa'), CREATURE('behemoth')],
    path: '/creatures',
  },
  {
    name: 'spawnrank',
    accent: '#a3e635',
    kicker: 'Busca de bestiário',
    title: 'O melhor lugar para sua task',
    subtitle: 'Escolha a criatura e veja os locais de caça em ordem.',
    art: [CREATURE('dragon'), CREATURE('hydra'), ITEM(10302)],
    path: '/spawnrank',
  },
  {
    name: 'soulcores',
    accent: '#c084fc',
    kicker: 'Soul cores',
    title: 'Quais soul cores faltam?',
    subtitle: 'Marque o que seu personagem já tem e veja o que falta.',
    art: [CREATURE('nightmare'), ITEM(47411)],
    path: '/soulcores',
  },
  {
    name: 'yasir',
    accent: '#22d3ee',
    kicker: 'Yasir hoje',
    title: 'Onde está o Yasir hoje?',
    subtitle: 'Escolha o mundo e veja a cidade confirmada pelos jogadores.',
    art: [ITEM(5706), ITEM(3386)],
    path: '/yasir',
  },
  {
    name: 'characters',
    accent: '#60a5fa',
    kicker: 'Personagens',
    title: 'Sua meta de level com data',
    subtitle: 'Histórico de level, XP por dia, mortes e previsão de meta.',
    art: [CREATURE('hero'), ITEM(9660)],
    path: '/characters',
  },
  {
    name: 'bossledger',
    accent: '#f87171',
    kicker: 'Boss Tracker',
    title: 'Controle todos os seus bosses',
    subtitle: 'Cooldown por personagem e drops registrados pela comunidade.',
    art: [CREATURE('demon'), ITEM(9626)],
    path: '/bossledger',
  },
  {
    name: 'quickhunts',
    accent: '#fdba74',
    kicker: 'Tutoriais de hunt',
    title: 'Guias de hunt por vocação',
    subtitle: 'Do level 8 ao end-game: rota, loot e dicas.',
    art: [ITEM(22865), CREATURE('dragon')],
    path: '/quickhunts',
  },
  {
    name: 'news',
    accent: '#f472b6',
    kicker: 'Novidades',
    title: 'O que há de novo no BagParser',
    subtitle: 'Ferramentas novas, boletim diário e avisos no Telegram.',
    art: [LOCAL('bosses/plagueroot.webp'), ITEM(3386)],
    path: '/news',
  },
  {
    name: 'pricing',
    accent: '#fcd34d',
    kicker: 'BagParser Pro',
    title: 'Apoie o site e libere o Pro',
    subtitle: 'Server Log completo, 10 personagens, avisos na hora e sem anúncios.',
    art: [ITEM(3386), CREATURE('demon')],
    path: '/pricing',
  },
];

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');

function html(card, logoSvg) {
  const size = card.title.length > 24 ? 76 : 88;
  const [hero, small1, small2] = card.art;
  const sprite = (src, cls) => (src ? `<img class="${cls}" src="${src}" alt="" onerror="this.remove()">` : '');
  return `<!doctype html><html><head><meta charset="utf-8"><style>
  * { box-sizing: border-box; margin: 0; }
  html, body { width: 1200px; height: 630px; overflow: hidden; }
  body { font-family: 'Noto Sans', 'Segoe UI', system-ui, sans-serif; color: #fff; position: relative;
    background: radial-gradient(620px 520px at 925px 300px, ${card.accent}73, transparent 72%), linear-gradient(135deg, #0b1411, #13261f 58%, #0a120f); }
  .edge { position: absolute; left: 0; top: 0; bottom: 0; width: 18px; background: ${card.accent}; }
  .brand { position: absolute; left: 66px; top: 44px; display: flex; align-items: center; gap: 14px; font-size: 36px; font-weight: 800; }
  .tile { width: 66px; height: 66px; border-radius: 16px; background: #fff; display: grid; place-items: center; }
  .tile svg { width: 52px; height: 52px; }
  .text { position: absolute; left: 66px; top: 146px; width: 650px; }
  .kicker { display: inline-block; font-size: 28px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: ${card.accent}; }
  h1 { font-size: ${size}px; line-height: 1.04; font-weight: 900; margin-top: 12px; letter-spacing: -0.02em; }
  p { font-size: 33px; line-height: 1.28; color: #d5e0da; margin-top: 20px; }
  .url { position: absolute; left: 66px; bottom: 40px; padding: 10px 26px; border-radius: 999px; background: ${card.accent}; color: #0b1411; font-size: 30px; font-weight: 900; }
  img { position: absolute; image-rendering: pixelated; object-fit: contain; filter: drop-shadow(0 18px 26px rgba(0,0,0,.65)); }
  .hero { width: 400px; height: 400px; left: 730px; top: 74px; }
  .s1 { width: 150px; height: 150px; left: 760px; top: 440px; }
  .s2 { width: 150px; height: 150px; left: 985px; top: 440px; }
</style></head><body>
  <div class="edge"></div>
  <div class="brand"><span class="tile">${logoSvg}</span><span>BagParser</span></div>
  <div class="text">
    <div class="kicker">${esc(card.kicker)}</div>
    <h1>${esc(card.title)}</h1>
    <p>${esc(card.subtitle)}</p>
  </div>
  ${sprite(hero, 'hero')}${sprite(small1, 's1')}${sprite(small2, 's2')}
  <div class="url">bagparser.com${card.path === '/' ? '' : card.path}</div>
</body></html>`;
}

function findChrome() {
  const candidates = [
    process.env.CHROME,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ].filter(Boolean);
  const hit = candidates.find((c) => existsSync(c));
  if (!hit) throw new Error('Chrome not found: set CHROME=/path/to/chrome');
  return hit;
}

async function main() {
  const chrome = findChrome();
  const logo = (await readFile(path.join(ROOT, 'frontend/public/logo.svg'), 'utf8')).replace(/<\?xml[^>]*>/, '');
  const tmp = await mkdtemp(path.join(os.tmpdir(), 'og-'));
  await mkdir(OUT, { recursive: true });
  try {
    const only = (process.env.ONLY ?? '').split(',').map((s) => s.trim()).filter(Boolean);
    for (const card of CARDS.filter((c) => !only.length || only.includes(c.name))) {
      const file = path.join(tmp, `${card.name}.html`);
      await writeFile(file, html(card, logo));
      const png = path.join(OUT, `${card.name}.png`);
      execFileSync(chrome, [
        '--headless=new',
        '--disable-gpu',
        '--hide-scrollbars',
        '--force-device-scale-factor=1',
        '--window-size=1200,630',
        '--virtual-time-budget=8000',
        `--user-data-dir=${path.join(tmp, 'profile')}`,
        `--screenshot=${png}`,
        pathToFileURL(file).href,
      ], { stdio: 'ignore' });
      console.log(`og/${card.name}.png`);
    }
  } finally {
    await rm(tmp, { recursive: true, force: true });
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
