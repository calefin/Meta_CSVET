/**
 * Telegram bot (D-75) — pure parts: command parsing, bot texts (pt/en) and a tiny Bot API client.
 * The bot itself (updates, support, linking) lives in telegramBot.ts.
 */
import { createHash } from 'node:crypto';
import { tgEscape } from './bulletin.js';

export interface TgUser {
  id: number;
  is_bot?: boolean;
  first_name?: string;
  last_name?: string;
  username?: string;
  language_code?: string;
}
export interface TgChat {
  id: number;
  type: 'private' | 'group' | 'supergroup' | 'channel';
  title?: string;
  username?: string;
  first_name?: string;
}
export interface TgMessage {
  message_id: number;
  chat: TgChat;
  from?: TgUser;
  sender_chat?: TgChat;
  text?: string;
  caption?: string;
  photo?: unknown[];
  document?: { file_name?: string };
  video?: unknown;
  voice?: unknown;
  sticker?: unknown;
  reply_to_message?: TgMessage;
  migrate_to_chat_id?: number;
  successful_payment?: { currency: string; total_amount: number; invoice_payload: string; telegram_payment_charge_id: string };
}
export interface TgUpdate {
  update_id: number;
  message?: TgMessage;
  channel_post?: TgMessage;
  callback_query?: { id: string; from: TgUser; data?: string; message?: TgMessage };
  my_chat_member?: { chat: TgChat; from: TgUser; old_chat_member: { status: string }; new_chat_member: { status: string } };
  pre_checkout_query?: { id: string; from: TgUser; currency: string; total_amount: number; invoice_payload: string };
}

/** Portuguese command names with English aliases → canonical name */
const COMMANDS: Record<string, string> = {
  start: 'start',
  boletim: 'boletim',
  bulletin: 'boletim',
  semanal: 'semanal',
  weekly: 'semanal',
  mundo: 'mundo',
  world: 'mundo',
  hora: 'hora',
  hour: 'hora',
  config: 'config',
  settings: 'config',
  hunts: 'hunts',
  suporte: 'suporte',
  support: 'suporte',
  parar: 'parar',
  stop: 'parar',
  ajuda: 'ajuda',
  help: 'ajuda',
  // D-76
  yasir: 'yasir',
  guild: 'guild',
  guilda: 'guild',
  avisos: 'avisos',
  alerts: 'avisos',
  apoiar: 'apoiar',
  apoio: 'apoiar',
  donate: 'apoiar',
  paysupport: 'paysupport',
};

/**
 * "/boletim@bagparser_bot on Antica" → { cmd: 'boletim', args: ['on', 'Antica'], rest: 'on Antica' }.
 * null for plain text, unknown commands go through as cmd '?' and commands addressed to another bot are ignored.
 */
export function parseCommand(text: string | undefined, botUsername: string | null): { cmd: string; args: string[]; rest: string } | null {
  if (!text) return null;
  const m = /^\/([A-Za-z0-9_]{1,32})(?:@([A-Za-z0-9_]{3,40}))?(?:\s+([\s\S]*))?$/.exec(text.trim());
  if (!m) return null;
  if (m[2] && botUsername && m[2].toLowerCase() !== botUsername.toLowerCase()) return null;
  const rest = (m[3] ?? '').trim();
  return { cmd: COMMANDS[m[1].toLowerCase()] ?? '?', args: rest ? rest.split(/\s+/) : [], rest };
}

export function onOff(v: string | undefined): boolean | null {
  const s = (v ?? '').toLowerCase();
  if (['on', 'ligar', 'liga', 'ligado', 'sim', 'yes', '1'].includes(s)) return true;
  if (['off', 'desligar', 'desliga', 'desligado', 'nao', 'não', 'no', '0'].includes(s)) return false;
  return null;
}

/** "8", "8h", "08:00" → 8 */
export function parseHour(v: string | undefined): number | null {
  const m = /^(\d{1,2})(?:h|:00)?$/i.exec((v ?? '').trim());
  if (!m) return null;
  const h = Number(m[1]);
  return h >= 0 && h <= 23 ? h : null;
}

/** "/yasir Liberty Bay Antica" → { present, city, rest }; null = not a report */
export function parseYasir(args: string[]): { present: boolean; city: string | null; rest: string[] } | null {
  const low = args.map((a) => a.toLowerCase());
  if (!low.length) return null;
  if (['nao', 'não', 'no', 'nope', 'ausente'].includes(low[0])) return { present: false, city: null, rest: args.slice(1) };
  if (low[0] === 'liberty' && low[1] === 'bay') return { present: true, city: 'Liberty Bay', rest: args.slice(2) };
  if (low[0] === 'carlin') return { present: true, city: 'Carlin', rest: args.slice(1) };
  if (low[0] === 'ankrahmun') return { present: true, city: 'Ankrahmun', rest: args.slice(1) };
  if (['outra', 'other', 'outro'].includes(low[0])) return { present: true, city: 'other', rest: args.slice(1) };
  return null;
}

/** Stars and Pix amounts offered by /apoiar */
export const DONATE_STARS = [50, 150, 500] as const;
export const DONATE_PIX_CENTS = [500, 1000, 2500] as const;

export const botLang = (code: string | null | undefined): 'pt' | 'en' => ((code ?? '').toLowerCase().startsWith('pt') ? 'pt' : 'en');

export function hourLabel(hour: number, tz: string, lang: string): string {
  const pt = lang.startsWith('pt');
  if (tz === 'America/Sao_Paulo') return pt ? `${hour}h (Brasília)` : `${String(hour).padStart(2, '0')}:00 (Brasília)`;
  return `${String(hour).padStart(2, '0')}:00 (${tz.replace(/_/g, ' ')})`;
}

export const displayName = (u: TgUser | undefined): string =>
  [u?.first_name, u?.last_name].filter(Boolean).join(' ').slice(0, 60) || (u?.username ? `@${u.username}` : 'Telegram');

/** Secret Telegram sends back in X-Telegram-Bot-Api-Secret-Token (derived from the token unless set) */
export const webhookSecret = (token: string, explicit?: string): string =>
  explicit && /^[A-Za-z0-9_-]{16,256}$/.test(explicit) ? explicit : createHash('sha256').update(`bagparser-telegram:${token}`).digest('hex').slice(0, 48);

/** What kind of non-text content a message carries (support tickets note it) */
export function attachmentLabel(m: TgMessage, lang: string): string | null {
  const pt = lang.startsWith('pt');
  if (m.photo) return pt ? '[imagem]' : '[image]';
  if (m.video) return pt ? '[vídeo]' : '[video]';
  if (m.voice) return pt ? '[áudio]' : '[voice]';
  if (m.document) return `[${pt ? 'arquivo' : 'file'}${m.document.file_name ? `: ${m.document.file_name.slice(0, 60)}` : ''}]`;
  if (m.sticker) return '[sticker]';
  return null;
}

// ---------------------------------------------------------------------------
// Bot texts
// ---------------------------------------------------------------------------

const TEXTS = {
  pt: {
    welcome:
      'Oi! Eu sou o bot do <b>BagParser</b> 🎒\n\n/boletim: o Tibia de hoje (boosted, Rashid, Dream Scar, Yasir, bosses mortos ontem e notícias)\n/boletim on Antica: receber o boletim todo dia\n/suporte: falar com a gente\n\nPara receber também o resumo das suas hunts, ligue sua conta em {site}/account → Telegram.',
    linked:
      '✅ Telegram ligado à conta <b>{name}</b>.\n\nTodo dia às {hour} chega o boletim com o Tibia do dia e as suas hunts de ontem; toda segunda, o resumo da semana.\n\n/mundo Antica: escolher o mundo\n/hora 8: mudar o horário\n/config: ver tudo\n/suporte: falar com a gente',
    link_invalid: 'Esse link expirou ou já foi usado. Gere outro em {site}/account → Telegram.',
    help_private:
      '<b>Comandos</b>\n/boletim: o Tibia de hoje\n/boletim on [Mundo] ou off: boletim diário\n/semanal on ou off: resumo das suas hunts toda segunda\n/mundo Antica: mundo do boletim (Yasir, bosses e criaturas)\n/hora 8: horário do boletim\n/hunts: suas últimas hunts\n/config: ver suas escolhas\n/yasir: ver ou relatar o Yasir\n/avisos on ou off: avisos na hora (Pro)\n/guild Nome: ranking de XP da guild (Pro)\n/apoiar: apoiar o BagParser 💚\n/suporte: falar com a gente\n/parar: parar de receber mensagens',
    help_group:
      '<b>BagParser no grupo</b>\n/boletim: o Tibia de hoje\n/boletim on Antica: boletim todo dia neste grupo (admins)\n/boletim off: desligar (admins)\n/mundo Antica e /hora 8: mudar (admins)\n/guild Nome: ranking de XP da guild (admins, Pro)\n/yasir: ver ou relatar o Yasir\n/config: ver a configuração',
    group_welcome:
      'Oi, {title}! 🎒 Eu sou o bot do BagParser.\n\nUm admin pode ligar o boletim diário com <code>/boletim on Mundo</code> (ex.: <code>/boletim on Antica</code>). Todo dia às 8h (Brasília) eu mando boosted, Rashid, Dream Scar, Yasir, bosses mortos e criaturas mais caçadas no mundo e as notícias do Tibia.\n\n/boletim: ver o de hoje agora',
    config:
      '⚙️ <b>Configuração</b>\nBoletim diário: {daily}\nHorário: {hour}\nMundo: {world}{weekly}{account}\n\nMudar: /boletim on|off · /mundo Antica · /hora 8{weeklyHelp}',
    weekly_line: '\nResumo semanal (segunda): {weekly}',
    weekly_help: ' · /semanal on|off',
    account_line: '\nConta: {account}',
    no_account: 'não ligada (ligue em {site}/account para ver suas hunts)',
    on: 'ligado',
    off: 'desligado',
    none: 'nenhum',
    daily_on: '✅ Boletim diário ligado: todo dia às {hour}{world}. Este é o de hoje:',
    daily_off: '🔕 Boletim diário desligado. Para voltar: /boletim on',
    weekly_on: '✅ Resumo semanal ligado: toda segunda às 10h (Brasília).',
    weekly_off: '🔕 Resumo semanal desligado.',
    needs_account: 'Isso usa os dados da sua conta: ligue-a em {site}/account → Telegram.',
    world_set: '🌍 Mundo do boletim: <b>{world}</b>.',
    world_cleared: '🌍 Boletim sem mundo (sem Yasir, bosses e criaturas do mundo).',
    world_unknown: 'Não achei o mundo "{world}". Exemplo: /mundo Antica',
    hour_set: '⏰ Boletim às {hour}.',
    hour_invalid: 'Mande a hora de 0 a 23. Exemplo: /hora 8',
    stopped: '🔕 Pronto, não mando mais boletins nem resumos. Para voltar: /boletim on',
    admins_only: 'Só administradores do grupo podem mudar isso.',
    not_subscribed: 'O boletim ainda não está ligado aqui. Um admin pode ligar com /boletim on Antica.',
    hunts_title: '⚔️ <b>Suas últimas hunts</b>',
    hunts_none: 'Nenhuma hunt nos últimos 7 dias. Envie suas hunts em {site}/hunt-analyser.',
    support_prompt: 'Escreva sua dúvida, problema ou sugestão (pode mandar print). A resposta chega aqui mesmo.',
    support_ask: 'Quer mandar esta mensagem para o suporte do BagParser?',
    support_send: '📨 Enviar ao suporte',
    support_cancel: 'Cancelar',
    support_cancelled: 'Ok, não enviei. /ajuda mostra o que eu sei fazer.',
    support_sent: '📨 Recebido! Ticket #{id}. A resposta chega aqui mesmo.',
    support_added: '📨 Adicionado ao ticket #{id}.',
    support_expired: 'Essa mensagem expirou. Mande de novo.',
    support_group: 'Para falar com o suporte, me chame no privado: {link}',
    support_reply: '💬 <b>Suporte BagParser</b> (ticket #{id}):\n\n{text}\n\n<i>Responda aqui para continuar a conversa.</i>',
    rate_limited: 'Muitas mensagens seguidas. Espere um pouco.',
    unlinked: '🔌 Sua conta do BagParser foi desligada deste Telegram. Os boletins do Tibia continuam se estiverem ligados (/config).',
    test_hint: 'Mensagem de teste pedida no site.',
    bulletin_hint: '/config para mudar',
    unknown: 'Não conheço esse comando. /ajuda mostra a lista.',
    yasir_status: '🏺 Yasir em {world} hoje: {status}\n\nViu? Responda com /yasir Carlin, /yasir Ankrahmun, /yasir Liberty Bay, /yasir outra ou /yasir nao (vale para o mundo do boletim; outro mundo: /yasir Carlin Antica).',
    yasir_need_world: 'Escolha o mundo antes: /mundo Antica (ou /yasir Carlin Antica).',
    yasir_ok: '✅ Obrigado! Relato do Yasir em {world} registrado: {what}. Quando ele for confirmado, aviso quem acompanha {world}.',
    yasir_rate: 'Muitos relatos seguidos. Tente mais tarde.',
    guild_set: '🏆 Guild {guild} ({world}) seguida. Amanhã sai o primeiro ranking de XP no boletim.',
    guild_off: '🏆 Ranking da guild desligado.',
    guild_unknown: 'Não achei a guild "{guild}". Escreva o nome como no tibia.com: /guild Nome da Guild',
    guild_pro: '🏆 O ranking de XP da guild é do plano Pro: ligue uma conta Pro em {site}/account → Telegram e mande /guild de novo.',
    alerts_on: '🔔 Avisos na hora ligados: level, morte, meta e fim do cooldown de boss (Pro).',
    alerts_off: '🔕 Avisos na hora desligados.',
    donate: '💚 <b>Apoie o BagParser</b>\n\nO site é feito por um jogador e se mantém com apoio. Com Telegram Stars (direto aqui) ou Pix.\n\nQuem apoia pode aparecer como "apoio de hoje" no boletim (você escolhe depois).',
    donate_pix_account: 'Para Pix, ligue sua conta em {site}/account → Telegram (o Mercado Pago pede um e-mail).',
    donate_title: 'Apoio ao BagParser',
    donate_desc: 'Ajuda a manter o BagParser no ar (servidor, dados e novas funções). Não compra nada no jogo.',
    donate_pix: '💚 Pix de <b>{amount}</b> (copia e cola, vale 30 min):\n\n<code>{code}</code>{link}\n\nQuando o pagamento cair eu aviso aqui.',
    donate_pix_error: 'O Pix não está disponível agora. Tente Telegram Stars ou mais tarde.',
    donate_thanks: '💚 Muito obrigado pelo apoio de <b>{amount}</b>! Quer aparecer como "apoio de hoje" no boletim?',
    donate_public_yes: 'Sim, como {name}',
    donate_public_no: 'Não, anônimo',
    donate_public_done: '💚 Combinado! Seu nome entra no rodízio dos boletins nos próximos 30 dias.',
    donate_anon_done: '💚 Combinado, fica anônimo. Obrigado!',
    paysupport: 'Problema com um pagamento (Stars ou Pix)? Escreva aqui o que aconteceu, com a data e o valor, e respondemos neste chat.',
  },
  en: {
    welcome:
      'Hi! I am the <b>BagParser</b> bot 🎒\n\n/bulletin: today in Tibia (boosted, Rashid, Dream Scar, Yasir, bosses killed yesterday and news)\n/bulletin on Antica: get the bulletin every day\n/support: talk to us\n\nTo also get your hunt summaries, link your account at {site}/account → Telegram.',
    linked:
      '✅ Telegram linked to <b>{name}</b>.\n\nEvery day at {hour} you get the bulletin with today in Tibia and your hunts of yesterday; every Monday, the weekly summary.\n\n/world Antica: choose the world\n/hour 8: change the time\n/settings: see everything\n/support: talk to us',
    link_invalid: 'This link expired or was already used. Create another one at {site}/account → Telegram.',
    help_private:
      '<b>Commands</b>\n/bulletin: today in Tibia\n/bulletin on [World] or off: daily bulletin\n/weekly on or off: your hunt summary every Monday\n/world Antica: bulletin world (Yasir, bosses and creatures)\n/hour 8: bulletin time\n/hunts: your latest hunts\n/settings: your choices\n/yasir: see or report Yasir\n/alerts on or off: instant alerts (Pro)\n/guild Name: guild XP ranking (Pro)\n/donate: support BagParser 💚\n/support: talk to us\n/stop: stop all messages',
    help_group:
      '<b>BagParser in this group</b>\n/bulletin: today in Tibia\n/bulletin on Antica: daily bulletin here (admins)\n/bulletin off: turn it off (admins)\n/world Antica and /hour 8: change (admins)\n/guild Name: guild XP ranking (admins, Pro)\n/yasir: see or report Yasir\n/settings: see the settings',
    group_welcome:
      'Hi, {title}! 🎒 I am the BagParser bot.\n\nAn admin can turn on the daily bulletin with <code>/bulletin on World</code> (e.g. <code>/bulletin on Antica</code>). Every day at 08:00 I post the boosted pair, Rashid, Dream Scar, Yasir, bosses killed and most hunted creatures on the world, and Tibia news.\n\n/bulletin: see today\'s now',
    config:
      '⚙️ <b>Settings</b>\nDaily bulletin: {daily}\nTime: {hour}\nWorld: {world}{weekly}{account}\n\nChange: /bulletin on|off · /world Antica · /hour 8{weeklyHelp}',
    weekly_line: '\nWeekly summary (Monday): {weekly}',
    weekly_help: ' · /weekly on|off',
    account_line: '\nAccount: {account}',
    no_account: 'not linked (link it at {site}/account to see your hunts)',
    on: 'on',
    off: 'off',
    none: 'none',
    daily_on: '✅ Daily bulletin on: every day at {hour}{world}. Here is today\'s:',
    daily_off: '🔕 Daily bulletin off. To turn it back on: /bulletin on',
    weekly_on: '✅ Weekly summary on: every Monday at 10:00 (Brasília).',
    weekly_off: '🔕 Weekly summary off.',
    needs_account: 'This uses your account data: link it at {site}/account → Telegram.',
    world_set: '🌍 Bulletin world: <b>{world}</b>.',
    world_cleared: '🌍 Bulletin without a world (no Yasir, bosses or creatures of a world).',
    world_unknown: 'I could not find the world "{world}". Example: /world Antica',
    hour_set: '⏰ Bulletin at {hour}.',
    hour_invalid: 'Send an hour from 0 to 23. Example: /hour 8',
    stopped: '🔕 Done, no more bulletins or summaries. To come back: /bulletin on',
    admins_only: 'Only group admins can change this.',
    not_subscribed: 'The bulletin is not on here yet. An admin can turn it on with /bulletin on Antica.',
    hunts_title: '⚔️ <b>Your latest hunts</b>',
    hunts_none: 'No hunts in the last 7 days. Send your hunts at {site}/hunt-analyser.',
    support_prompt: 'Write your question, problem or suggestion (screenshots welcome). The answer arrives right here.',
    support_ask: 'Send this message to BagParser support?',
    support_send: '📨 Send to support',
    support_cancel: 'Cancel',
    support_cancelled: 'Ok, not sent. /help shows what I can do.',
    support_sent: '📨 Got it! Ticket #{id}. The answer arrives right here.',
    support_added: '📨 Added to ticket #{id}.',
    support_expired: 'That message expired. Please send it again.',
    support_group: 'To talk to support, message me privately: {link}',
    support_reply: '💬 <b>BagParser support</b> (ticket #{id}):\n\n{text}\n\n<i>Reply here to continue the conversation.</i>',
    rate_limited: 'Too many messages in a row. Please wait a little.',
    unlinked: '🔌 Your BagParser account was unlinked from this Telegram. Tibia bulletins go on if they are on (/settings).',
    test_hint: 'Test message requested on the site.',
    bulletin_hint: '/settings to change',
    unknown: 'I do not know this command. /help lists them.',
    yasir_status: '🏺 Yasir on {world} today: {status}\n\nSeen him? Reply with /yasir Carlin, /yasir Ankrahmun, /yasir Liberty Bay, /yasir other or /yasir no (for the bulletin world; another world: /yasir Carlin Antica).',
    yasir_need_world: 'Choose the world first: /world Antica (or /yasir Carlin Antica).',
    yasir_ok: '✅ Thanks! Yasir report on {world} saved: {what}. When he is confirmed, everyone following {world} is told.',
    yasir_rate: 'Too many reports in a row. Try later.',
    guild_set: "🏆 Following guild {guild} ({world}). The first XP ranking comes in tomorrow's bulletin.",
    guild_off: '🏆 Guild ranking off.',
    guild_unknown: 'I could not find the guild "{guild}". Write the name as on tibia.com: /guild Guild Name',
    guild_pro: '🏆 The guild XP ranking is a Pro feature: link a Pro account at {site}/account → Telegram and send /guild again.',
    alerts_on: '🔔 Instant alerts on: level, death, goal and end of boss cooldown (Pro).',
    alerts_off: '🔕 Instant alerts off.',
    donate: '💚 <b>Support BagParser</b>\n\nThe site is made by a player and runs on support. With Telegram Stars (right here) or Pix.\n\nSupporters can appear as "today\'s supporter" in the bulletin (you choose afterwards).',
    donate_pix_account: 'For Pix, link your account at {site}/account → Telegram (Mercado Pago needs an e-mail).',
    donate_title: 'Support BagParser',
    donate_desc: 'Helps keep BagParser running (server, data and new features). It buys nothing in the game.',
    donate_pix: '💚 Pix of <b>{amount}</b> (copy and paste, valid for 30 min):\n\n<code>{code}</code>{link}\n\nI will tell you here when it is paid.',
    donate_pix_error: 'Pix is not available right now. Try Telegram Stars or later.',
    donate_thanks: '💚 Thank you so much for the <b>{amount}</b>! Do you want to appear as "today\'s supporter" in the bulletin?',
    donate_public_yes: 'Yes, as {name}',
    donate_public_no: 'No, anonymous',
    donate_public_done: '💚 Done! Your name joins the bulletin rotation for the next 30 days.',
    donate_anon_done: '💚 Done, you stay anonymous. Thank you!',
    paysupport: 'Problem with a payment (Stars or Pix)? Write here what happened, with the date and amount, and we answer in this chat.',
  },
} as const;

export type BotText = keyof (typeof TEXTS)['pt'];

/** Bot text with {placeholders}; values are inserted as given (escape user content before). */
export function tt(lang: string, key: BotText, vars: Record<string, string | number> = {}): string {
  const base: string = TEXTS[botLang(lang)][key];
  return base.replace(/\{(\w+)\}/g, (_, k: string) => (k in vars ? String(vars[k]) : `{${k}}`));
}

export { tgEscape };

/** Command menus registered with setMyCommands (default = Portuguese, English for English clients) */
export const COMMAND_MENU = {
  pt: [
    { command: 'boletim', description: 'O Tibia de hoje (on/off para o diário)' },
    { command: 'hunts', description: 'Suas últimas hunts' },
    { command: 'mundo', description: 'Mundo do boletim' },
    { command: 'hora', description: 'Horário do boletim' },
    { command: 'config', description: 'Ver suas escolhas' },
    { command: 'yasir', description: 'Ver ou relatar o Yasir' },
    { command: 'avisos', description: 'Avisos na hora (Pro)' },
    { command: 'guild', description: 'Ranking de XP da guild (Pro)' },
    { command: 'apoiar', description: 'Apoiar o BagParser 💚' },
    { command: 'suporte', description: 'Falar com o suporte' },
    { command: 'parar', description: 'Parar de receber mensagens' },
  ],
  en: [
    { command: 'bulletin', description: 'Today in Tibia (on/off for daily)' },
    { command: 'hunts', description: 'Your latest hunts' },
    { command: 'world', description: 'Bulletin world' },
    { command: 'hour', description: 'Bulletin time' },
    { command: 'settings', description: 'Your choices' },
    { command: 'yasir', description: 'See or report Yasir' },
    { command: 'alerts', description: 'Instant alerts (Pro)' },
    { command: 'guild', description: 'Guild XP ranking (Pro)' },
    { command: 'donate', description: 'Support BagParser 💚' },
    { command: 'support', description: 'Talk to support' },
    { command: 'stop', description: 'Stop all messages' },
  ],
};

// ---------------------------------------------------------------------------
// Bot API client
// ---------------------------------------------------------------------------

export interface TgResult<T = unknown> {
  ok: boolean;
  result?: T;
  error_code?: number;
  description?: string;
  parameters?: { retry_after?: number; migrate_to_chat_id?: number };
}

export type TgCall = <T = unknown>(method: string, params?: Record<string, unknown>, timeoutMs?: number) => Promise<TgResult<T>>;

export function createTelegramApi(token: string, base = 'https://api.telegram.org'): TgCall {
  return async <T>(method: string, params: Record<string, unknown> = {}, timeoutMs = 15_000): Promise<TgResult<T>> => {
    try {
      const res = await fetch(`${base.replace(/\/+$/, '')}/bot${token}/${method}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(params),
        redirect: 'error',
        signal: AbortSignal.timeout(timeoutMs),
      });
      return (await res.json()) as TgResult<T>;
    } catch (err) {
      // Never let the URL (it carries the token) reach the logs
      return { ok: false, description: (err as Error).name === 'TimeoutError' ? 'timeout' : 'network error' };
    }
  };
}

/** The chat is gone for good: blocked, kicked, deleted */
export const chatGone = (r: TgResult): boolean =>
  r.error_code === 403 || (r.error_code === 400 && /chat not found|user is deactivated|bot was kicked|not enough rights to send|have no rights to send/i.test(r.description ?? ''));
