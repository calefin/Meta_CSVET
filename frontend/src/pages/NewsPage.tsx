import { useState } from 'react';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

export interface NewsArticle {
  id: string;
  slug: string;
  date: string;
  author: string;
  category: 'feature' | 'update' | 'guide' | 'fansite';
  title: Record<string, string>;
  excerpt: Record<string, string>;
  content: Record<string, string[]>;
}

/** **bold** and `code` inside a news paragraph (the texts were showing the asterisks) */
function inline(text: string) {
  return text.split(/(\*\*[^*]+\*\*|`[^`]+`)/g).map((part, i) =>
    part.startsWith('**') && part.endsWith('**') ? (
      <strong key={i}>{part.slice(2, -2)}</strong>
    ) : part.startsWith('`') && part.endsWith('`') && part.length > 2 ? (
      <code key={i}>{part.slice(1, -1)}</code>
    ) : (
      part
    ),
  );
}

export const NEWS_ARTICLES: NewsArticle[] = [
  {
    id: 'creature-sheets-hunts-by-vocation',
    slug: 'ficha-de-criaturas-hunts-por-vocacao-todos-os-bosses',
    date: '2026-11-08',
    author: 'BagParser Team',
    category: 'feature',
    title: {
      pt: '🐉 Ficha de criaturas, hunts por vocação com melhor elemento e todos os bosses',
      en: '🐉 Creature sheets, hunts by vocation with the best element and every boss',
      es: '🐉 Fichas de criaturas, hunts por vocación con el mejor elemento y todos los bosses',
      pl: '🐉 Karty potworów, expowiska wg profesji z najlepszym żywiołem i wszystkie bossy',
    },
    excerpt: {
      pt: 'Cada criatura agora tem a sua ficha: com o que bater, do que se proteger, fraquezas, drops e onde caçar. A nova tabela de hunts por vocação mostra o melhor elemento de cada local, e o Boss Tracker ganhou todos os bosses.',
      en: 'Every creature now has its own sheet: what to hit it with, what to protect from, weaknesses, drops and where to hunt it. The new hunts by vocation table shows the best element for each place, and the Boss Tracker now has every boss.',
      es: 'Cada criatura tiene ahora su ficha: con qué atacar, de qué protegerse, debilidades, botín y dónde cazar. La nueva tabla de hunts por vocación muestra el mejor elemento de cada lugar, y el Boss Tracker ya tiene todos los bosses.',
      pl: 'Każdy potwór ma teraz swoją kartę: czym uderzać, przed czym się chronić, słabości, łup i gdzie polować. Nowa tabela expowisk wg profesji pokazuje najlepszy żywioł, a Boss Tracker ma już wszystkie bossy.',
    },
    content: {
      pt: [
        '**Ficha de criatura:** em Hunts → Criaturas, ou clicando no nome da criatura nas ferramentas do site. No topo, com o que bater, o que evitar e do que se proteger; depois fraquezas e forças em barras, drops por raridade com o valor de NPC e onde caçar. Habilidades e detalhes ficam recolhidos no fim.',
        '**Hunts por vocação:** em Locais de caça, escolha a vocação e o seu level para ver XP/h, lucro/h, o melhor elemento para bater e do que se proteger. Só hunts solo. Toque no local para ver as criaturas. Os valores são relatos da comunidade e mudam com set, charms, stamina e mundo; **var** indica que ainda não há número definido.',
        '**Meu elemento:** só tem arma de um elemento? Escolha ele na tabela e as hunts em que ele faz mais dano aparecem primeiro.',
        '**Busca de Bestiário:** ao escolher uma criatura, aparece um resumo com HP, XP, com o que bater e o dano que ela causa, com botão para a ficha completa.',
        '**Boss Tracker:** agora com todos os bosses conhecidos e o cooldown atualizado. A coluna de valor passou a se chamar "Valor NPC (cada)", para não ser confundida com quantidade.',
        '**Sprites e nomes:** criaturas, bosses e drops com imagem, e os nomes do jogo não são mais trocados pelo tradutor automático do navegador.',
      ],
      en: [
        '**Creature sheet:** under Hunts → Creatures, or by clicking a creature name in the tools of the site. At the top, what to hit it with, what to avoid and what to protect from; then weaknesses and strengths as bars, drops by rarity with NPC value, and where to hunt it. Abilities and details are folded at the end.',
        '**Hunts by vocation:** in Hunting places, pick your vocation and level to see XP/h, profit/h, the best element to hit with and what to protect from. Solo hunts only. Tap a place to see its creatures. Values are community reports and change with set, charms, stamina and world; **var** means there is no defined number yet.',
        '**My element:** only have a weapon of one element? Pick it in the table and the hunts where it does the most damage come first.',
        '**Bestiary search:** after choosing a creature you get a summary with HP, XP, what to hit it with and the damage it deals, plus a button to the full sheet.',
        '**Boss Tracker:** now with every known boss and updated cooldowns. The value column is now called "NPC value (each)", so it is not mistaken for a quantity.',
        '**Sprites and names:** creatures, bosses and drops with images, and game names are no longer changed by the browser translator.',
      ],
      es: [
        '**Ficha de criatura:** en Hunts → Criaturas o haciendo clic en su nombre: con qué atacar, qué evitar, de qué protegerse, debilidades, botín por rareza y dónde cazar.',
        '**Hunts por vocación:** elige vocación y nivel para ver XP/h, ganancia/h, el mejor elemento y de qué protegerse. Solo hunts solo; los valores son reportes de la comunidad y **var** indica que aún no hay número definido.',
        '**Mi elemento**, resumen en la **búsqueda de Bestiario** y **Boss Tracker** con todos los bosses y cooldowns actualizados.',
        '**Sprites y nombres:** criaturas, bosses y botín con imagen, y el traductor del navegador ya no cambia los nombres del juego.',
      ],
      pl: [
        '**Karta potwora:** w Hunts → Potwory lub po kliknięciu nazwy: czym uderzać, czego unikać, przed czym się chronić, słabości, łup wg rzadkości i gdzie polować.',
        '**Expowiska wg profesji:** wybierz profesję i poziom, aby zobaczyć XP/h, zysk/h, najlepszy żywioł i ochronę. Tylko solo; wartości to zgłoszenia społeczności, a **var** oznacza brak określonej liczby.',
        '**Mój żywioł**, podsumowanie w **wyszukiwarce Bestiariusza** i **Boss Tracker** ze wszystkimi bossami i aktualnymi cooldownami.',
        '**Sprite i nazwy:** potwory, bossy i łup z obrazkami, a tłumacz przeglądarki nie zmienia już nazw z gry.',
      ],
    },
  },
  {
    id: 'alerts-guild-ranking-retro',
    slug: 'avisos-ranking-guild-seu-mes-no-tibia',
    date: '2026-11-07',
    author: 'BagParser Team',
    category: 'feature',
    title: {
      pt: '🔔 Avisos na hora, ranking de XP da guild, Yasir pelo bot e o seu mês no Tibia',
      en: '🔔 Instant alerts, guild XP ranking, Yasir from the bot and your month in Tibia',
      es: '🔔 Avisos al instante, ranking de XP de la guild, Yasir desde el bot y tu mes en Tibia',
      pl: '🔔 Powiadomienia na bieżąco, ranking XP gildii, Yasir z bota i Twój miesiąc w Tibii',
    },
    excerpt: {
      pt: 'Seu personagem subiu de level, morreu ou bateu a meta? O bot avisa na hora. Também chegaram o ranking de XP da guild no grupo, o relato do Yasir pelo Telegram, o card "Seu mês no Tibia" e o apoio com Stars ou Pix.',
      en: 'Did your character level up, die or reach the goal? The bot tells you right away. Also new: guild XP ranking in the group, Yasir reports from Telegram, the "Your month in Tibia" card and support with Stars or Pix.',
      es: '¿Tu personaje subió de nivel, murió o llegó a la meta? El bot te avisa al instante. Además: ranking de XP de la guild, Yasir por Telegram, la tarjeta "Tu mes en Tibia" y apoyo con Stars o Pix.',
      pl: 'Twoja postać awansowała, zginęła lub osiągnęła cel? Bot powie od razu. Nowość: ranking XP gildii, zgłoszenia Yasira z Telegrama, karta „Twój miesiąc w Tibii” i wsparcie przez Stars lub Pix.',
    },
    content: {
      pt: [
        '**Avisos na hora (Pro):** level novo, morte, meta alcançada e fim do cooldown de boss (do rastreador do BossLedger) chegam no seu Telegram ou no seu canal do Discord. Liga e desliga com `/avisos` ou em Minha conta.',
        '**Ranking de XP da guild (Pro):** mande `/guild Nome da Guild` no grupo (admins) e o boletim diário passa a trazer o top XP da guild no dia anterior, com quem perdeu level.',
        '**Soul core do boosted:** se a criatura boosted do dia ainda falta no soul core de algum personagem seu, o boletim avisa.',
        '**Yasir pelo bot:** `/yasir Liberty Bay` registra o relato; quando ele é confirmado no mundo, todo mundo que acompanha aquele mundo recebe o aviso.',
        '**Seu mês no Tibia:** hunts, horas, XP, lucro, melhor spawn e a evolução dos personagens, com um card para baixar ou compartilhar. No dia 1 o bot manda o resumo do mês anterior.',
        '**Apoie o BagParser:** `/apoiar` no bot, com Telegram Stars ou Pix. Quem apoia pode aparecer como "apoio de hoje" no boletim.',
      ],
      en: [
        '**Instant alerts (Pro):** new level, death, goal reached and end of boss cooldown (from the BossLedger tracker) arrive on your Telegram or Discord channel. Toggle with `/alerts` or in My account.',
        '**Guild XP ranking (Pro):** send `/guild Guild Name` in the group (admins) and the daily bulletin brings the guild top XP of the day before, and who lost levels.',
        '**Soul core of the boosted:** if today\'s boosted creature is still missing from one of your characters\' soul cores, the bulletin tells you.',
        '**Yasir from the bot:** `/yasir Liberty Bay` saves a report; when he is confirmed on the world, everyone following that world is told.',
        '**Your month in Tibia:** hunts, hours, XP, profit, best spawn and your characters\' progress, with a card to download or share. On the 1st the bot sends last month\'s summary.',
        '**Support BagParser:** `/donate` in the bot, with Telegram Stars or Pix. Supporters can appear as "today\'s supporter" in the bulletin.',
      ],
      es: [
        '**Avisos al instante (Pro):** nivel nuevo, muerte, meta alcanzada y fin del cooldown de boss, en tu Telegram o canal de Discord (`/alerts`).',
        '**Ranking de XP de la guild (Pro):** `/guild Nombre` en el grupo y el boletín trae el top XP del día anterior.',
        '**Soul core del boosted**, **Yasir por el bot** (`/yasir Liberty Bay`) y **Tu mes en Tibia** con tarjeta para compartir.',
        '**Apoya BagParser:** `/donate` en el bot, con Telegram Stars o Pix.',
      ],
      pl: [
        '**Powiadomienia na bieżąco (Pro):** nowy poziom, zgon, osiągnięty cel i koniec cooldownu bossa — na Telegramie lub Discordzie (`/alerts`).',
        '**Ranking XP gildii (Pro):** `/guild Nazwa` w grupie, a biuletyn pokaże top XP z poprzedniego dnia.',
        '**Soul core boostowanego potwora**, **Yasir z bota** (`/yasir Liberty Bay`) i **Twój miesiąc w Tibii** z kartą do udostępnienia.',
        '**Wesprzyj BagParser:** `/donate` w bocie, przez Telegram Stars lub Pix.',
      ],
    },
  },
  {
    id: 'telegram-bot-daily-bulletin',
    slug: 'bot-telegram-boletim-diario-discord',
    date: '2026-11-07',
    author: 'BagParser Team',
    category: 'feature',
    title: {
      pt: '✈️ Bot do Telegram e boletim diário do Tibia (Telegram e Discord)',
      en: '✈️ Telegram bot and a daily Tibia bulletin (Telegram and Discord)',
      es: '✈️ Bot de Telegram y boletín diario de Tibia (Telegram y Discord)',
      pl: '✈️ Bot na Telegramie i codzienny biuletyn Tibii (Telegram i Discord)',
    },
    excerpt: {
      pt: 'O @bagparser_bot manda todo dia o Tibia do seu mundo, a evolução dos seus personagens e as suas hunts de ontem, no privado, no grupo da guild ou no Discord. E o suporte agora também é pelo Telegram.',
      en: '@bagparser_bot sends today in Tibia for your world, your characters\' progress and yesterday\'s hunts every day, privately, in your guild group or on Discord. Support now works over Telegram too.',
      es: '@bagparser_bot envía cada día el Tibia de tu mundo, la evolución de tus personajes y tus hunts de ayer, en privado, en el grupo de la guild o en Discord. Y el soporte ahora también es por Telegram.',
      pl: '@bagparser_bot codziennie wysyła Tibię Twojego świata, postępy postaci i wczorajsze hunty — prywatnie, w grupie gildii lub na Discordzie. Pomoc techniczna działa teraz także przez Telegram.',
    },
    content: {
      pt: [
        'O BagParser agora tem um bot oficial no Telegram: **@bagparser_bot**. Ligue sua conta em **Minha conta → Boletins e resumos** com um toque.',
        '**O boletim diário traz:**',
        '• Criatura e boss boosted, cidade do Rashid, boss do Dream Scar e o Yasir do seu mundo (pelos relatos dos jogadores).',
        '• Os bosses raros mortos ontem no mundo e as criaturas mais caçadas.',
        '• As notícias do tibia.com dos últimos dias.',
        '• **Evolução dos seus personagens** (Pro): level, XP de ontem, média dos últimos 7 dias, mortes e quanto falta para a meta no ritmo atual.',
        '• As suas hunts de ontem, e toda segunda o resumo da semana.',
        '**Para a guild:** adicione o bot ao grupo e um admin manda `/boletim on Antica`. Grupos recebem só o boletim do Tibia, nunca as suas hunts. No Discord, cole o webhook de até 5 canais (o seu e o da guild).',
        '**Suporte pelo Telegram:** mande sua dúvida ao bot e a resposta chega no próprio chat.',
      ],
      en: [
        'BagParser now has an official Telegram bot: **@bagparser_bot**. Link your account in **My account → Bulletins and summaries** with one tap.',
        '**The daily bulletin brings:**',
        '• Boosted creature and boss, Rashid\'s city, the Dream Scar boss and Yasir on your world (from player reports).',
        '• Rare bosses killed yesterday on the world and the most hunted creatures.',
        '• The latest tibia.com news.',
        '• **Your characters\' progress** (Pro): level, yesterday\'s XP, 7-day average, deaths and how far your goal is at the current pace.',
        '• Your hunts of yesterday, and every Monday the weekly summary.',
        '**For your guild:** add the bot to the group and an admin sends `/bulletin on Antica`. Groups only get the Tibia bulletin, never your hunts. On Discord, paste the webhook of up to 5 channels (yours and your guild\'s).',
        '**Support over Telegram:** message the bot and the answer arrives in the same chat.',
      ],
      es: [
        'BagParser ahora tiene un bot oficial en Telegram: **@bagparser_bot**. Vincula tu cuenta en **Mi cuenta → Boletines y resúmenes** con un toque.',
        '• Boosted, Rashid, Dream Scar y Yasir de tu mundo; bosses raros muertos ayer y criaturas más cazadas; noticias de tibia.com.',
        '• **Evolución de tus personajes** (Pro): nivel, XP de ayer, media de 7 días, muertes y cuánto falta para tu meta.',
        '• Tus hunts de ayer y, cada lunes, el resumen semanal.',
        '**Para tu guild:** agrega el bot al grupo y un admin envía `/bulletin on Antica`. En Discord, hasta 5 canales.',
        '**Soporte por Telegram:** escribe al bot y la respuesta llega en el mismo chat.',
      ],
      pl: [
        'BagParser ma teraz oficjalnego bota na Telegramie: **@bagparser_bot**. Połącz konto w **Moje konto → Biuletyny i podsumowania** jednym dotknięciem.',
        '• Boosted, Rashid, Dream Scar i Yasir Twojego świata; rzadkie bossy zabite wczoraj i najczęściej polowane potwory; wiadomości z tibia.com.',
        '• **Postępy Twoich postaci** (Pro): poziom, wczorajszy XP, średnia z 7 dni, zgony i ile brakuje do celu.',
        '• Twoje wczorajsze hunty, a w każdy poniedziałek podsumowanie tygodnia.',
        '**Dla gildii:** dodaj bota do grupy, a admin wysyła `/bulletin on Antica`. Na Discordzie do 5 kanałów.',
        '**Pomoc przez Telegram:** napisz do bota, a odpowiedź przyjdzie w tym samym czacie.',
      ],
    },
  },
  {
    id: 'hunt-tracker-release',
    slug: 'hunt-analyser-tracker-e-comparador',
    date: '2026-11-05',
    author: 'BagParser Team',
    category: 'feature',
    title: {
      pt: '🏹 Novo Hunt Analyser Tracker: Análise de Combate, Comparador de Hunts e Projeções de Level',
      en: '🏹 New Hunt Analyser Tracker: Combat Analytics, Hunt Comparison & Level Projections',
      es: '🏹 Nuevo Hunt Analyser Tracker: Analítica de Combate, Comparador de Hunts y Proyecciones de Nivel',
      pl: '🏹 Nowy Hunt Analyser Tracker: Analityka Walki, Porównywarka Polowań i Projekcje Poziomu',
    },
    excerpt: {
      pt: 'Apresentamos o Hunt Analyser Tracker dedicado: monitore Raw XP, dano, cura, lucro líquido, salve sessões vinculadas aos seus personagens e compare caçadas lado a lado.',
      en: 'Introducing our dedicated Hunt Analyser Tracker: monitor Raw XP, combat damage, healing, net profit, save sessions linked to your characters and compare hunts side-by-side.',
      es: 'Presentamos el Hunt Analyser Tracker dedicado: monitoriza Raw XP, daño de combate, curación, beneficio neto, guarda sesiones vinculadas a tus personajes y compara cacerías.',
      pl: 'Przedstawiamy dedykowany Hunt Analyser Tracker: monitoruj Raw XP, obrażenia, leczenie, zysk netto, zapisuj sesje powiązane z postaciami i porównuj polowania obok siebie.',
    },
    content: {
      pt: [
        'Hoje temos o orgulho de lançar o novo **Hunt Analyser Tracker** no BagParser! Uma ferramenta totalmente separada do nosso tradicional Divisor de Loot, focada em fornecer análise de alta precisão para suas caçadas solo e em equipe.',
        '**Principais recursos:**',
        '• **Métricas Completas de Caça:** Leitura instantânea de Raw XP, XP/h com bônus, dano por segundo/hora, cura e balanço financeiro líquido.',
        '• **Projeção de Evolução do Char:** Calcule exatamente quanto de XP falta para o seu próximo level, quantas hunts semelhantes serão necessárias e quanto lucro você acumulará até lá!',
        '• **Comparativo de Hunts Lado a Lado:** Escolha duas sessões de caça salvas para comparar eficiência de XP/h, custo de supplies e balance com cálculo de porcentagem de diferença.',
        '• **Histórico por Personagem:** Salve e filtre suas caçadas pelo seu personagem verificado (como cavaleiros, paladinos e magos da sua conta).',
      ],
      en: [
        'Today we are thrilled to announce the brand new **Hunt Analyser Tracker** on BagParser! Completely distinct from our Loot Parser tool, this feature is built for deep performance tracking of your solo and team hunts.',
        '**Key highlights:**',
        '• **Comprehensive Combat & XP Analytics:** Instant breakdown of Raw XP, boosted XP/h, damage per hour, healing and net balance.',
        '• **Character Level Projections:** See exactly how much XP you need to hit your next level, how many hunts at your current pace are required, and estimated profit earned.',
        '• **Side-by-Side Hunt Comparison:** Compare two saved sessions to analyze differences in XP/h, supplies cost, and profit margins.',
        '• **Character Session History:** Keep track of your past hunts organized and filtered per verified character.',
      ],
      es: [
        '¡Hoy lanzamos el nuevo **Hunt Analyser Tracker** en BagParser! Una herramienta totalmente independiente del Divisor de Botín, pensada para analizar el rendimiento de tus cacerías.',
        '• **Métricas Completas:** Raw XP, XP/h, daño, curación y balance neto.',
        '• **Proyecciones de Nivel:** Cuántas cazas necesitas para subir de nivel y cuánto oro ganarás.',
        '• **Comparativa Cara a Cara:** Compara dos cacerías para ver cuál rinde mejor.',
      ],
      pl: [
        'Dzisiaj z dumą uruchamiamy **Hunt Analyser Tracker** na BagParser! Całkowicie oddzielne narzędzie od Kalkulatora Lootu, stworzone do głębokiej analizy polowań.',
        '• **Kompletne statystyki:** Raw XP, XP/h, obrażenia, leczenie i zysk netto.',
        '• **Projekcja awansu poziomu:** Ile polowań dzieli Cię od kolejnego levela.',
        '• **Porównanie polowań obok siebie:** Szybkie zestawienie dwóch sesji.',
      ],
    },
  },
  {
    id: 'v030-pro-guild-anti-bot',
    slug: 'bagparser-v030-pro-guilda-server-log',
    date: '2026-11-04',
    author: 'BagParser Team',
    category: 'update',
    title: {
      pt: '⚡ BagParser v0.3.0: Categorias Pro & Guilda, Suporte a Server Log e Proteção Anti-Bot',
      en: '⚡ BagParser v0.3.0: Pro & Guild Tiers, Server Log Drop Parser & Bot Protection',
      es: '⚡ BagParser v0.3.0: Categorías Pro y Guild, Soporte Server Log y Anti-Bot',
      pl: '⚡ BagParser v0.3.0: Plany Pro i Guild, Parsowanie Server Loga i Ochrona Anti-Bot',
    },
    excerpt: {
      pt: 'Novas categorias de apoiador com pagamento pelo Stripe, suporte a drops do Server Log, integração Cloudflare Turnstile e canal de suporte oficial.',
      en: 'New supporter tiers with Stripe payments, Server Log drop parsing, Cloudflare Turnstile anti-bot integration, and official support channel.',
      es: 'Nuevos planes de apoyo con pagos por Stripe, soporte para drops de Server Log y protección Cloudflare Turnstile.',
      pl: 'Nowe plany wspierających z płatnościami Stripe, parsowanie dropów z Server Loga i zabezpieczenia Cloudflare Turnstile.',
    },
    content: {
      pt: [
        'A versão 0.3.0 do BagParser chegou trazendo importantes novidades para sustentar o crescimento da nossa comunidade:',
        '• **Planos Apoiador Pro e Guilda:** Desbloqueie até 10 personagens monitorados, histórico diário automático de snapshots de XP pós-Server Save e rateio facilitado de loot para líderes de guilda.',
        '• **Suporte a Server Log:** Agora você pode colar diretamente as mensagens de drop do seu Server Log (`12:45 Loot of a demon: a demon shield, 2 platinum coins`) e o BagParser organizará a rota de venda automaticamente.',
        '• **Segurança Aprimorada:** Integração de Cloudflare Turnstile e Honeypot invisível para proteger contas e endpoints públicos contra bots sem atrapalhar a experiência do usuário.',
      ],
      en: [
        'Version 0.3.0 brings exciting enhancements to support our growing community:',
        '• **Supporter Pro & Guild Tiers:** Track up to 10 characters, automatic daily XP snapshots after Server Save, and bulk party transfer tools.',
        '• **Server Log Parsing:** You can now paste raw Server Log drop lines directly into the parser.',
        '• **Bot Protection:** Seamless Cloudflare Turnstile and honeypot defenses to keep our services reliable and fast.',
      ],
      es: [
        'La versión 0.3.0 incluye planes para gremios, lectura directa de drops del Server Log y mayor seguridad.',
      ],
      pl: [
        'Wersja 0.3.0 wprowadza plany dla gildii, bezpośrednie wklejanie dropów z Server Loga i lepszą ochronę.',
      ],
    },
  },
  {
    id: 'char-tracker-goals',
    slug: 'acompanhamento-personagens-metas',
    date: '2026-11-03',
    author: 'BagParser Team',
    category: 'feature',
    title: {
      pt: '🛡️ Acompanhamento de Personagens e Metas de Level (D-28)',
      en: '🛡️ Character Tracking & Level Goals (D-28)',
      es: '🛡️ Seguimiento de Personajes y Metas de Nivel (D-28)',
      pl: '🛡️ Śledzenie Postaci i Cele Poziomu (D-28)',
    },
    excerpt: {
      pt: 'Verifique a posse do seu personagem de forma 100% segura pelo comentário do tibia.com e acompanhe histórico de XP, mortes e estimativas de meta.',
      en: 'Verify character ownership securely via tibia.com comment, track XP history, recent deaths, and smart progress estimates towards your next goal.',
      es: 'Verifica tu personaje de forma segura con un código en tibia.com y sigue tu progreso, muertes y metas de nivel.',
      pl: 'Zweryfikuj postać bezpiecznie przez komentarz na tibia.com i śledź historię doświadczenia, zgony i postępy.',
    },
    content: {
      pt: [
        'Chegou a hora de aposentar planilhas manuais para acompanhar seu progresso no Tibia! Com o rastreador de personagens do BagParser, você conecta seus personagens em segundos:',
        '• **Segurança Total:** Basta colar um código de verificação temporário no comentário do personagem no site oficial tibia.com.',
        '• **Metas com Previsão Estimada:** Defina seu level dos sonhos (ex: Level 500, 1000) e o sistema calcula a data provável com base no seu ritmo real dos últimos 7 e 30 dias.',
        '• **Sugestões Personalizadas:** Dicas inteligentes de caça recomendando spots com base no seu level e vocação atual.',
      ],
      en: [
        'Retire your manual spreadsheets! BagParser Character Tracking lets you monitor your Tibia characters securely:',
        '• **100% Safe Verification:** Place a temporary verification code in your character comment on tibia.com.',
        '• **Goal ETA:** Set target levels and view estimated achievement dates calculated from your real 7-day and 30-day hunting pace.',
        '• **Smart Recommendations:** Tailored hunting spot suggestions calibrated for your current level and vocation.',
      ],
      es: [
        'Sigue la evolución de tus personajes con cálculo de ritmo y fechas estimadas de metas.',
      ],
      pl: [
        'Śledź postępy swoich postaci z wyliczaniem tempa expienia i przewidywanych dat celów.',
      ],
    },
  },
  {
    id: 'bagparser-launch',
    slug: 'lancamento-oficial-bagparser',
    date: '2026-11-01',
    author: 'BagParser Team',
    category: 'fansite',
    title: {
      pt: '🚀 Lançamento Oficial do BagParser: O Roteiro de Vendas e Divisor de Loot',
      en: '🚀 Official Launch of BagParser: The Ultimate Loot Routing & Party Split Toolkit',
      es: '🚀 Lanzamiento Oficial de BagParser: Rutas de Venta y Reparto de Party',
      pl: '🚀 Oficjalny Start BagParser: Trasy Sprzedaży i Podział Drużyny',
    },
    excerpt: {
      pt: 'Chega de carregar mochilas cheias sem saber onde vender. O BagParser identifica os melhores NPCs compradores, rotas de cidades e divide a conta da party.',
      en: 'Never wander lost with bags full of loot again. BagParser routes every item to the highest paying NPC, crafts optimal travel routes and splits party loot.',
      es: 'Descubre qué NPC compra cada ítem al mejor precio y reparte las ganancias de tu equipo al instante.',
      pl: 'Dowiedz się, który NPC płaci najwięcej za każdy przedmiot i natychmiast podziel zyski drużyny.',
    },
    content: {
      pt: [
        'É com enorme alegria que apresentamos o BagParser para toda a comunidade tibiana!',
        'Quantas vezes você terminou uma hunt de duas horas com sua equipe e passou 20 minutos tentando descobrir se a jóia vende em Venore, se a armadura vai para o Rashid ou se é melhor esperar o Yasir?',
        'O BagParser resolve isso em um clique: basta colar o log do Hunt Analyser ou Party Hunt Analyser. O algoritmo agrupa os itens por NPC comprador, destaca alertas de mercado/imbuements valiosos, gera o roteiro por cidades e calcula a transferência bancária exata entre os membros da party.',
      ],
      en: [
        'We are thrilled to welcome the Tibia community to BagParser!',
        'How often do you finish a big team hunt only to spend 20 minutes wondering where each creature product or armor sells?',
        'BagParser does the heavy lifting: paste your Hunt Analyser log to get immediate NPC grouping, market/imbuement alerts, town travel paths, and exact bank transfer instructions for each team member.',
      ],
      es: [
        '¡Bienvenidos a BagParser! Simplificamos el reparto de botín y la venta de ítems en el juego.',
      ],
      pl: [
        'Witamy w BagParser! Koniec z traceniem czasu na szukanie odpowiednich NPC do sprzedaży łupów.',
      ],
    },
  },
];

export function NewsPage() {
  const { t, locale } = useI18n();
  const [selectedTag, setSelectedTag] = useState<string>('all');
  const [expandedId, setExpandedId] = useState<string | null>(NEWS_ARTICLES[0].id);

  const lang = locale.startsWith('pt') ? 'pt' : locale.startsWith('es') ? 'es' : locale.startsWith('pl') ? 'pl' : 'en';

  const filtered = selectedTag === 'all'
    ? NEWS_ARTICLES
    : NEWS_ARTICLES.filter((a) => a.category === selectedTag);

  return (
    <div className="page news-page">
      <header className="chars-head">
        <div>
          <h1>📰 {t('news_title')}</h1>
          <p className="muted">{t('news_subtitle')}</p>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          <span className="tag" style={{ background: '#10b981', color: '#fff', fontWeight: 'bold' }}>
            ✓ {t('news_status_active')}
          </span>
        </div>
      </header>

      {/* Filter Chips */}
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', margin: '1rem 0' }}>
        {[
          { key: 'all', label: t('news_filter_all') },
          { key: 'feature', label: `🏹 ${t('news_filter_feature')}` },
          { key: 'update', label: `⚡ ${t('news_filter_update')}` },
          { key: 'guide', label: `📖 ${t('news_filter_guide')}` },
          { key: 'fansite', label: `⭐ ${t('news_filter_fansite')}` },
        ].map((c) => (
          <button
            key={c.key}
            type="button"
            className={selectedTag === c.key ? 'primary small' : 'secondary small'}
            onClick={() => setSelectedTag(c.key)}
            style={{ borderRadius: '20px', padding: '0.3rem 0.8rem' }}
          >
            {c.label}
          </button>
        ))}
      </div>

      {/* Articles Feed */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
        {filtered.map((art) => {
          const isExpanded = expandedId === art.id;
          const title = art.title[lang] || art.title.en;
          const excerpt = art.excerpt[lang] || art.excerpt.en;
          const content = art.content[lang] || art.content.en;

          return (
            <article key={art.id} id={art.id} className="card" style={{ borderLeft: '4px solid var(--color-accent, #3b82f6)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '0.5rem' }}>
                <span className="tag" style={{ textTransform: 'uppercase', fontSize: '0.75rem', fontWeight: 'bold' }}>
                  {art.category}
                </span>
                <span className="muted small">
                  📅 {new Date(`${art.date}T12:00:00`).toLocaleDateString(locale, { dateStyle: 'long' })} · {art.author}
                </span>
              </div>

              <h2 style={{ fontSize: '1.25rem', margin: '0.25rem 0 0.5rem 0' }}>
                {title}
              </h2>

              <p className="muted" style={{ lineHeight: 1.5, margin: '0.5rem 0' }}>
                {excerpt}
              </p>

              {isExpanded && (
                <div style={{ marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid rgba(255, 255, 255, 0.1)', lineHeight: 1.6 }}>
                  {content.map((p, idx) => (
                    <p key={idx} style={{ margin: '0.6rem 0' }}>
                      {inline(p)}
                    </p>
                  ))}
                </div>
              )}

              <div style={{ marginTop: '0.75rem', display: 'flex', justifyContent: 'flex-end' }}>
                <button
                  type="button"
                  className="secondary small"
                  onClick={() => setExpandedId(isExpanded ? null : art.id)}
                >
                  {isExpanded ? t('read_less') : `${t('read_more')} →`}
                </button>
              </div>
            </article>
          );
        })}
      </div>

      {/* Fansite Programme Badge Card */}
      <section className="card" style={{ marginTop: '2rem', background: 'rgba(255, 255, 255, 0.02)', textAlign: 'center', padding: '1.5rem' }}>
        <h3>⭐ {t('fansite_pledge_title')}</h3>
        <p className="muted small" style={{ maxWidth: '650px', margin: '0.5rem auto' }}>
          {t('fansite_pledge_desc')}
        </p>
        <div style={{ marginTop: '0.75rem' }}>
          <a href="/about" onClick={linkHandler('/about')} className="button secondary small">
            {t('about_title')} & {t('nav_support')} →
          </a>
        </div>
      </section>
    </div>
  );
}
