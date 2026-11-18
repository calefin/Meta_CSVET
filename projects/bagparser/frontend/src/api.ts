import type { CreatureStats } from './core/creatures';
import type { LootEntry, SheetAbility, SheetTraits } from './core/creatureView';
/** Thin client for the BagParser API (same origin, proxied by Nginx / Vite). */

export interface Creature {
  id: number;
  name: string;
  grizzly_task_points: number;
  is_weekly_bounty: boolean;
  is_boosted: boolean;
  image_url: string | null;
}

export interface SpawnRankRow {
  zone_id: number;
  slug?: string | null;
  exp_stars?: number | null;
  loot_stars?: number | null;
  access_quests?: string[];
  levels?: { knights: number | null; paladins: number | null; mages: number | null };
  zone: string;
  city: string;
  min_level: number;
  vocation: string;
  access_quest: string | null;
  note: string | null;
  components: { density: number; synergy: number; profitability: number; access_friction: number };
  score: number;
}

export interface Boss {
  id: number;
  name: string;
  cooldown_hours: number;
  kills: number;
  is_boosted: boolean;
}

export interface BossStats {
  boss: { id: number; name: string; cooldown_hours: number };
  kills: number;
  items: { name: string; drops: number; drop_rate: number; npc_price: number; market_priority_alert: boolean }[];
}

export interface Boosted {
  creature: { name: string; race: string; image_url: string } | null;
  boss: { name: string; image_url: string } | null;
}

export type SubmitStatus = 'recorded' | 'invalid_format' | 'unknown_boss' | 'duplicate' | 'rate_limited';

export class HttpError extends Error {
  constructor(public status: number) {
    super(`HTTP ${status}`);
  }
}

async function request<T>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, init);
  if (!res.ok) throw new HttpError(res.status);
  return (await res.json()) as T;
}

export const api = {
  creatures: () => request<Creature[]>('/api/creatures'),
  spawnRank: (creatureId: number) => request<SpawnRankRow[]>(`/api/spawnrank?creature_id=${creatureId}`),
  bosses: () => request<Boss[]>('/api/bosses'),
  bossStats: (id: number) => request<BossStats>(`/api/bosses/${id}/stats`),
  boosted: () => request<Boosted>('/api/boosted'),
  submitDrops: (text: string) =>
    request<{ results: { line: string; status: SubmitStatus }[] }>('/api/bosses/drops', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text }),
    }),
};

// ---------------------------------------------------------------------------
// Accounts and admin (D-24)
// ---------------------------------------------------------------------------

export type UserTier = 'free' | 'pro' | 'guild' | 'admin';

export interface User {
  id: number;
  email: string;
  display_name: string;
  tibia_character: string | null;
  role: 'user' | 'admin';
  tier?: UserTier;
  premium: boolean;
  premium_until: string | null;
  created_at: string;
  terms_version: string | null;
  terms_current: boolean;
  /** Only in /api/auth/me (D-39): false for accounts created with Google/Discord until a password is set */
  has_password?: boolean;
  identities?: LinkedLogin[];
  /** D-65: false until the e-mail is confirmed */
  email_verified?: boolean;
}

export type OAuthProvider = 'google' | 'discord';
export interface LinkedLogin {
  provider: OAuthProvider;
  email: string | null;
}

export interface AdminUser {
  id: number;
  email: string;
  display_name: string;
  tibia_character: string | null;
  role: 'user' | 'admin';
  status: 'active' | 'banned';
  ban_reason: string | null;
  premium: boolean;
  premium_until: string | null;
  /** "tester" etc. (D-50) */
  premium_note?: string | null;
  /** D-65 */
  email_verified_at?: string | null;
  created_at: string;
  last_login_at: string | null;
}

export interface PromoCode {
  id: number;
  code: string;
  kind: 'discount' | 'trial';
  percent_off: number | null;
  premium_days: number | null;
  max_uses: number | null;
  uses: number;
  expires_at: string | null;
  active: boolean;
  note: string | null;
  created_at: string;
  redemptions: number;
}

/** Stripe billing (D-51): every charge goes through Stripe Checkout */
export const billing = {
  status: () => send<{ enabled: boolean; publishable_key: string | null }>('GET', '/api/billing/status'),
  me: () => send<{ enabled: boolean; has_customer: boolean; status: string | null; cancel_at_period_end: boolean }>('GET', '/api/billing/me'),
  checkout: (period: 'monthly' | 'yearly', currency: 'brl' | 'usd', code?: string) =>
    send<{ url: string }>('POST', '/api/billing/checkout', { period, currency, code }),
  portal: () => send<{ url: string }>('POST', '/api/billing/portal'),
  /** D-63: undo a scheduled cancellation */
  resume: () => send<{ ok: true }>('POST', '/api/billing/resume'),
  /** D-69: Pix via Mercado Pago (Pro days, no renewal) */
  pixInfo: () => send<{ enabled: boolean; plans: Record<'monthly' | 'yearly', { days: number; amount: number }> }>('GET', '/api/billing/pix'),
  pixCreate: (plan: 'monthly' | 'yearly') => send<PixPayment>('POST', '/api/billing/pix', { plan }),
  pixStatus: (id: number) => send<PixPayment>('GET', `/api/billing/pix/${id}`),
};

/** D-71 + D-75: where bulletins and summaries go (Telegram chats and Discord channels) */
export interface NotifyTarget {
  id: number;
  kind: 'telegram' | 'discord';
  chat_type: string | null;
  title: string | null;
  webhook: string | null;
  world: string | null;
  lang: string;
  tz: string;
  hour: number;
  daily: boolean;
  personal: boolean;
  weekly: boolean;
  /** D-76: instant alerts (level, death, goal, boss cooldown) — Pro */
  alerts: boolean;
  /** D-76: guild followed for the XP ranking — Pro */
  guild: string | null;
}
export interface NotifyState {
  telegram: { enabled: boolean; bot: string | null; chat: NotifyTarget | null; groups: NotifyTarget[] };
  discord: NotifyTarget[];
}
export type NotifyPatch = Partial<Pick<NotifyTarget, 'daily' | 'weekly' | 'personal' | 'alerts' | 'hour' | 'tz' | 'title'>> & { world?: string | null; guild?: string | null };
export const notify = {
  get: () => send<NotifyState>('GET', '/api/notify'),
  telegramLink: () => send<{ url: string }>('POST', '/api/notify/telegram/link'),
  addDiscord: (url: string, label: string, lang: string, tz: string) => send<NotifyTarget>('POST', '/api/notify/discord', { url, label, lang, tz }),
  update: (id: number, patch: NotifyPatch) => send<NotifyTarget>('PATCH', `/api/notify/targets/${id}`, patch),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/notify/targets/${id}`),
  test: (id: number) => send<{ ok: true }>('POST', `/api/notify/targets/${id}/test`),
  telegramInfo: () => send<{ enabled: boolean; username: string | null }>('GET', '/api/telegram/info'),
  /** D-76: BossLedger kills of the account (cooldown alerts) */
  bossTimers: (timers: { character: string; boss: string; killed_at: string; cooldown_hours: number }[]) =>
    send<{ ok: true; timers: number }>('PUT', '/api/me/boss-timers', { timers }),
};

/** D-76: monthly retrospective */
export interface Retro {
  month: string;
  name: string;
  hunts: number;
  hours: number;
  xp: number;
  profit: number;
  best: { place: string; character: string | null; xp_h: number } | null;
  favorite: { place: string; hunts: number } | null;
  chars: { name: string; level_from: number; level_to: number; exp_gain: number }[];
  deaths: number;
}
export const retro = {
  get: (month?: string) => send<Retro>('GET', `/api/me/retro${month ? `?month=${encodeURIComponent(month)}` : ''}`),
};

export const promo = {
  check: (code: string) =>
    send<{ code: string; kind: 'discount' | 'trial'; percent_off: number | null; premium_days: number | null }>('POST', '/api/promo/check', { code }),
  redeem: (code: string) =>
    send<{ kind: string; applied: boolean; premium_days?: number; premium_until?: string; percent_off?: number }>('POST', '/api/promo/redeem', { code }),
};

export interface AdminStats {
  users: { total: number; premium: number; banned: number; admins: number; new_7d: number; new_30d: number; active_30d: number };
  traffic: { month: string; views: number; visitor_days: number; peak_day: number }[];
  top_paths: { path: string; views: number }[];
}

export interface AuditEntry {
  admin_email: string;
  target_email?: string | null;
  action: string;
  details: Record<string, unknown> | null;
  created_at: string;
}

/** Error with the API's `error` code (e.g. invalid_credentials, email_taken) */
export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    public data: Record<string, unknown> = {},
  ) {
    super(code);
  }
}

async function send<T>(method: string, url: string, body?: unknown): Promise<T> {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: method === 'GET' ? undefined : { 'Content-Type': 'application/json' },
    body: method === 'GET' ? undefined : JSON.stringify(body ?? {}),
  });
  const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, String(data.error ?? `http_${res.status}`), data);
  return data as T;
}

export const auth = {
  me: () => send<{ user: User | null }>('GET', '/api/auth/me'),
  login: (email: string, password: string, opts: { turnstile_token?: string; hp?: string } = {}) =>
    send<{ user: User }>('POST', '/api/auth/login', { email, password, ...opts }),
  register: (
    email: string,
    password: string,
    display_name: string,
    accept_terms: boolean,
    opts: { turnstile_token?: string; hp?: string } = {},
  ) =>
    send<{ user: User }>('POST', '/api/auth/register', { email, password, display_name, accept_terms, ...opts }),
  logout: () => send<{ ok: true }>('POST', '/api/auth/logout'),
  update: (patch: { display_name?: string; tibia_character?: string | null }) =>
    send<{ user: User }>('PATCH', '/api/auth/me', patch),
  changePassword: (current_password: string, new_password: string) =>
    send<{ ok: true }>('POST', '/api/auth/password', { current_password, new_password }),
  acceptTerms: () => send<{ user: User }>('POST', '/api/auth/accept-terms'),
  deleteAccount: (password: string, confirm_email?: string) =>
    send<{ ok: true }>('DELETE', '/api/auth/me', { password, confirm_email }),
  providers: () => send<{ providers: OAuthProvider[] }>('GET', '/api/auth/providers'),
  unlink: (provider: OAuthProvider) => send<{ ok: true }>('POST', `/api/auth/oauth/${provider}/unlink`),
  /** Full-page navigation (the provider's consent screen), not fetch */
  oauthUrl: (provider: OAuthProvider, opts: { next?: string; link?: boolean } = {}) =>
    `/api/auth/oauth/${provider}/start?${new URLSearchParams({
      ...(opts.next ? { next: opts.next } : {}),
      ...(opts.link ? { link: '1' } : {}),
    })}`,
  checkReset: (token: string) => send<{ email: string }>('GET', `/api/auth/reset?token=${encodeURIComponent(token)}`),
  reset: (token: string, password: string, lang?: string) => send<{ ok: true }>('POST', '/api/auth/reset', { token, password, lang }),
  /** D-65: e-mails a one-time link (same answer whether the account exists or not) */
  forgot: (email: string, lang: string) => send<{ ok: true }>('POST', '/api/auth/forgot', { email, lang }),
  verifyEmail: (token: string) => send<{ ok: true; email: string }>('POST', '/api/auth/verify-email', { token }),
  resendVerification: (lang: string) => send<{ ok: true; email?: string; retry_in?: number; already?: boolean }>('POST', '/api/auth/verify-email/resend', { lang }),
};

/** D-69: one Pix charge (amount in cents) */
export interface PixPayment {
  id: number;
  plan: 'monthly' | 'yearly';
  days: number;
  amount: number;
  status: string;
  qr_code: string | null;
  qr_base64: string | null;
  ticket_url: string | null;
  expires_at: string;
  approved_at: string | null;
  premium_until: string | null;
}

/** D-63: financial report (amounts in cents) */
export interface AdminFinance {
  enabled: boolean;
  months: { month: string; currency: string; gross: number; refunds: number; payments: number; payers: number }[];
  totals: { currency: string; gross: number; refunds: number; payers: number }[];
  subscriptions: { currency: string | null; interval: string | null; cancelling: boolean; n: number; amount: number }[];
  recent: { paid_at: string; kind: 'invoice' | 'refund'; amount: number; currency: string; interval: string | null; period_end: string | null; email: string | null; user_id: number | null; stripe_id: string; description: string | null; provider?: string }[];
  churn: { month: string; subscribed: number; cancel_scheduled: number; resumed: number; ended: number; payment_failed: number }[];
}

/** D-73: Mercado Pago notification (complaints, chargebacks, orders…) */
export interface AdminMpEvent {
  id: number;
  kind: 'order' | 'payment' | 'claim' | 'chargeback' | 'other';
  type: string;
  action: string | null;
  data_id: string | null;
  status: string | null;
  amount: number | null;
  deadline: string | null;
  summary: string | null;
  details: unknown;
  processed: boolean;
  error: string | null;
  received_at: string;
  pix_id: number | null;
  email: string | null;
}

/** D-62: admin dashboard */
export interface AdminOverview {
  /** D-62: settings that send visitors to the wrong place */
  warnings?: string[];
  /** D-65: e-mail sender */
  mail?: { mode: 'smtp' | 'log' | 'off'; from: string | null; support: string | null } | null;
  /** D-66 */
  ads?: { client: string | null; slot: string | null };
  /** D-68: last database backup */
  backup?: { ok: boolean; file: string; size: number; at: string; message: string } | null;
  billing: { paying: number; cancelling: number; problems: number; manual_premium: number; alerts_7d: number };
  tickets: { pending: number; open: number; answered: number; closed: number };
  errors: { open: number; last_24h: number; occurrences: number };
  content: { hunts: number; hunts_7d: number; payouts: number; characters: number };
}
export interface AdminPaymentUser {
  id: number;
  email: string;
  display_name: string;
  stripe_status: string | null;
  cancel_at_period_end: boolean;
  stripe_subscription_id: string | null;
  stripe_customer_id: string | null;
  premium_until: string | null;
  premium_note: string | null;
  tier: string;
  created_at: string;
  premium: boolean;
}
export type TicketStatus = 'pending' | 'open' | 'answered' | 'closed';
export interface AdminTicket {
  id: number;
  user_id: number | null;
  account: string | null;
  name: string;
  email: string | null;
  category: string;
  subject: string;
  message: string;
  status: TicketStatus;
  /** D-75: number of messages in the conversation and whether the answer can go by Telegram */
  replies: number;
  telegram: boolean;
  admin_notes: string | null;
  client_info: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}
export interface AdminError {
  id: number;
  kind: 'client' | 'server';
  message: string;
  stack: string | null;
  path: string | null;
  user_agent: string | null;
  version: string | null;
  count: number;
  first_seen: string;
  last_seen: string;
  resolved_at: string | null;
  last_user: string | null;
}

export const admin = {
  stats: () => send<AdminStats>('GET', '/api/admin/stats'),
  users: (q: string, filter: string, page: number) =>
    send<{ total: number; page: number; page_size: number; users: AdminUser[] }>(
      'GET',
      `/api/admin/users?q=${encodeURIComponent(q)}&filter=${filter}&page=${page}`,
    ),
  user: (id: number) =>
    send<{
      user: AdminUser;
      sessions: { created_at: string; last_seen_at: string; ip: string | null; user_agent: string | null }[];
      audit: AuditEntry[];
    }>('GET', `/api/admin/users/${id}`),
  update: (
    id: number,
    patch: { role?: string; status?: string; ban_reason?: string; premium_until?: string | null; display_name?: string },
  ) => send<{ user: AdminUser }>('PATCH', `/api/admin/users/${id}`, patch),
  resetLink: (id: number) => send<{ path: string; expires_hours: number }>('POST', `/api/admin/users/${id}/reset-link`),
  logoutAll: (id: number) => send<{ ok: true; sessions: number }>('POST', `/api/admin/users/${id}/logout`),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/admin/users/${id}`),
  grant: (id: number, days: number, note = 'tester') =>
    send<{ ok: true; premium_until: string }>('POST', `/api/admin/users/${id}/grant`, { days, note }),
  // D-62: dashboard, payments, tickets, errors
  overview: () => send<AdminOverview>('GET', '/api/admin/overview'),
  mpEvents: (kind: string) => send<{ events: AdminMpEvent[]; counts: Record<string, number> }>('GET', `/api/admin/mp-events?kind=${encodeURIComponent(kind)}`),
  mailTest: () => send<{ ok: true; to: string }>('POST', '/api/admin/mail-test'),
  mailCheck: () => send<{ steps: { step: string; ok: boolean; detail: string }[] }>('POST', '/api/admin/mail-check'),
  finance: () => send<AdminFinance>('GET', '/api/admin/finance'),
  financeSync: () => send<{ customers: number; invoices: number; failed: number }>('POST', '/api/admin/finance/sync'),
  payments: () => send<{ users: AdminPaymentUser[] }>('GET', '/api/admin/payments'),
  tickets: (status: string) => send<{ tickets: AdminTicket[] }>('GET', `/api/admin/tickets?status=${encodeURIComponent(status)}`),
  updateTicket: (id: number, patch: { status?: TicketStatus; admin_notes?: string | null }) =>
    send<{ ticket: Pick<AdminTicket, 'id' | 'status' | 'admin_notes' | 'updated_at'> }>('PATCH', `/api/admin/tickets/${id}`, patch),
  ticketReplies: (id: number) =>
    send<{ replies: { id: number; from_admin: boolean; body: string; via: string; created_at: string }[] }>('GET', `/api/admin/tickets/${id}/replies`),
  replyTicket: (id: number, text: string) => send<{ ok: true }>('POST', `/api/admin/tickets/${id}/reply`, { text }),
  errors: (status: 'open' | 'resolved' | 'all') => send<{ errors: AdminError[] }>('GET', `/api/admin/errors?status=${status}`),
  resolveError: (id: number, resolved: boolean) => send<{ ok: true }>('PATCH', `/api/admin/errors/${id}`, { resolved }),
  deleteError: (id: number) => send<{ ok: true }>('DELETE', `/api/admin/errors/${id}`),
  promoList: () => send<{ codes: PromoCode[] }>('GET', '/api/admin/promo'),
  billingLog: () =>
    send<{ entries: { id: number; kind: string; details: Record<string, unknown>; created_at: string; email: string | null }[] }>(
      'GET',
      '/api/admin/billing-log',
    ),
  promoCreate: (body: Record<string, unknown>) => send<{ id: number; code: string }>('POST', '/api/admin/promo', body),
  promoToggle: (id: number, active: boolean) => send<{ ok: true }>('PATCH', `/api/admin/promo/${id}`, { active }),
  promoDelete: (id: number) => send<{ ok: true }>('DELETE', `/api/admin/promo/${id}`),
  audit: () => send<AuditEntry[]>('GET', '/api/admin/audit'),
  yasirReports: (days = 7) => send<YasirAdminReport[]>('GET', `/api/admin/yasir?days=${days}`),
  deleteYasirReport: (r: Pick<YasirAdminReport, 'day' | 'world' | 'user_id'>) =>
    send<{ ok: true }>('DELETE', `/api/admin/yasir/${r.day}/${encodeURIComponent(r.world)}/${r.user_id}`),
  blockYasir: (userId: number, blocked: boolean) =>
    send<{ ok: true }>('POST', `/api/admin/users/${userId}/yasir-block`, { blocked }),
};

/** Anonymous page view for the monthly traffic report (fire and forget) */
export function trackPageView(path: string): void {
  if (!navigator.onLine) return;
  fetch('/api/pv', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path }),
    keepalive: true,
  }).catch(() => undefined);
}

// ---------------------------------------------------------------------------
// Character tracking (D-28)
// ---------------------------------------------------------------------------

export interface TrackedCharacter {
  id: number;
  name: string;
  verification_code: string;
  verified_at: string | null;
  created_at: string;
  last_refresh_at: string | null;
  world: string | null;
  vocation: string | null;
  level: number | null;
  residence: string | null;
  sex: string | null;
  guild: string | null;
  account_status: string | null;
  achievement_points: number | null;
  last_login: string | null;
  traded: boolean;
  /** Server tracking (Premium, D-49) */
  auto_track?: boolean;
  last_auto_at?: string | null;
}

export interface CharacterListItem extends TrackedCharacter {
  target_level: number | null;
  target_date: string | null;
  goal_achieved_at: string | null;
  experience: number | null;
}

export interface CharacterDetail {
  character: TrackedCharacter;
  snapshots: {
    taken_at: string;
    level: number;
    experience: number;
    exp_exact: boolean;
    achievement_points: number | null;
    world: string | null;
  }[];
  changes: { field: 'name' | 'world'; old_value: string | null; new_value: string; detected_at: string }[];
  deaths: { time: string; level: number; reason: string }[];
  goal: {
    target_level: number;
    target_date: string | null;
    start_level: number;
    start_exp: number;
    created_at: string;
    achieved_at: string | null;
  } | null;
  refresh_available_in: number;
}

export const characters = {
  list: () =>
    send<{ characters: CharacterListItem[]; limit: number; limits: { free: number; premium: number } }>(
      'GET',
      '/api/characters',
    ),
  add: (name: string) => send<{ id: number; existing?: boolean }>('POST', '/api/characters', { name }),
  verify: (id: number) => send<{ verified: true }>('POST', `/api/characters/${id}/verify`),
  refresh: (id: number) => send<{ ok: true }>('POST', `/api/characters/${id}/refresh`),
  get: (id: number) => send<CharacterDetail>('GET', `/api/characters/${id}`),
  rename: (id: number, new_name: string) => send<{ ok: true; name: string }>('POST', `/api/characters/${id}/rename`, { new_name }),
  setGoal: (id: number, target_level: number, target_date: string | null) =>
    send<{ ok: true }>('PUT', `/api/characters/${id}/goal`, { target_level, target_date }),
  clearGoal: (id: number) => send<{ ok: true }>('DELETE', `/api/characters/${id}/goal`),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/characters/${id}`),
  setTracking: (id: number, enabled: boolean) =>
    send<{ ok: true; auto_track: boolean }>('PUT', `/api/characters/${id}/tracking`, { enabled }),
};

// ---------------------------------------------------------------------------
// Hunt Analyser (D-49)
// ---------------------------------------------------------------------------

export type Vocation = 'knight' | 'paladin' | 'sorcerer' | 'druid' | 'monk';

/** List row (no item lists) */
export interface HuntRow {
  public_id: string;
  kind: 'solo' | 'party';
  visibility: 'private' | 'public';
  anonymous: boolean;
  started_at: string | null;
  ended_at: string | null;
  duration_s: number;
  raw_xp: number;
  xp: number;
  raw_xp_h: number;
  xp_h: number;
  loot: number;
  supplies: number;
  balance: number;
  balance_h: number;
  damage_h: number;
  healing_h: number;
  character_name: string | null;
  verified?: boolean;
  vocation: Vocation | null;
  level: number | null;
  world: string | null;
  place_slug: string | null;
  place_name: string | null;
  created_at: string;
  top_monster: string | null;
  has_server_log: boolean;
  has_input: boolean;
  // D-59
  title?: string | null;
  extra_expenses?: number;
  has_build?: boolean;
  wod_name?: string | null;
  set_name?: string | null;
  /** hunts shared with me: who shared */
  owner?: string;
  /** D-61: 1 for solo hunts */
  party_size?: number;
}

export interface HuntDetail extends HuntRow {
  damage: number;
  healing: number;
  monsters: { name: string; count: number }[];
  items: { name: string; count: number }[];
  party: { loot_type: string | null; members: { name: string; isLeader: boolean; loot: number; supplies: number; balance: number; damage: number; healing: number }[] } | null;
  input: import('./core/huntLog').InputAnalysis | null;
  server_log: import('./core/huntLog').ServerLogSummary | null;
  notes?: string | null;
  setup: Record<string, string | boolean>;
  description?: string | null;
  expenses?: import('./core/build').Expense[] | null;
  build?: import('./core/build').HuntBuild | null;
  shared_with?: Friend[];
}

/** D-59: saved equipment sets / Wheels of Destiny, hunt templates and friends */
export interface SavedBuild {
  id: number;
  kind: 'set' | 'wod';
  name: string;
  character_id: number | null;
  data: import('./core/build').SetData | import('./core/build').WodData;
  updated_at?: string;
}
export interface HuntTemplateData {
  description: string | null;
  place_slug: string | null;
  place_name: string | null;
  character_id: number | null;
  visibility: 'private' | 'public';
  anonymous: boolean;
  expenses: import('./core/build').Expense[];
  build: import('./core/build').HuntBuild | null;
  shared_with: number[];
}
export interface HuntTemplate {
  id: number;
  name: string;
  data: HuntTemplateData;
  updated_at: string;
}
export interface Friend {
  id: number;
  display_name: string;
  created_at?: string;
}

export const builds = {
  list: (characterId?: number | null) => send<{ builds: SavedBuild[] }>('GET', `/api/builds${characterId ? `?character_id=${characterId}` : ''}`),
  create: (b: { kind: 'set' | 'wod'; name: string; character_id: number | null; data: unknown }) => send<SavedBuild>('POST', '/api/builds', b),
  update: (id: number, b: { name?: string; data?: unknown }) => send<{ ok: true }>('PUT', `/api/builds/${id}`, b),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/builds/${id}`),
};

export const huntTemplates = {
  list: () => send<{ templates: HuntTemplate[] }>('GET', '/api/hunt-templates'),
  save: (name: string, data: HuntTemplateData) => send<{ id: number }>('PUT', '/api/hunt-templates', { name, data }),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/hunt-templates/${id}`),
};

export const friends = {
  list: () => send<{ friends: Friend[]; added_me: Friend[] }>('GET', '/api/friends'),
  add: (query: string) => send<Friend>('POST', '/api/friends', { query }),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/friends/${id}`),
};

/** Party hunt that can go into a payment distribution (D-60) */
export interface PayoutHuntRow {
  id: string;
  title: string | null;
  place_name: string | null;
  character_name: string | null;
  owner: string | null;
  started_at: string | null;
  duration_s: number;
  balance: number;
  extra_expenses: number;
  members: { name: string; balance: number; isLeader: boolean }[];
  expenses: import('./core/build').Expense[];
  anonymous: boolean;
  payout_ids: number[];
}

export interface SavedPayout {
  id: number;
  name: string;
  hunt_ids: string[];
  aliases: Record<string, string>;
  round_to: number;
  players: { name: string; chars: string[]; net: number }[];
  transfers: { from: string; to: string; amount: number; paid: boolean }[];
  total: number;
  created_at: string;
  settled_at: string | null;
  mine: boolean;
  owner?: string;
  shared_with?: Friend[];
}

export const payouts = {
  hunts: (days: number) => send<{ hunts: PayoutHuntRow[] }>('GET', `/api/payouts/hunts?days=${days}`),
  list: () => send<{ payouts: SavedPayout[] }>('GET', '/api/payouts'),
  create: (body: Omit<SavedPayout, 'id' | 'created_at' | 'settled_at' | 'mine' | 'owner' | 'shared_with'> & { shared_with: number[] }) =>
    send<{ id: number; hunt_ids: string[] }>('POST', '/api/payouts', body),
  update: (id: number, patch: { name?: string; paid?: boolean[]; shared_with?: number[] }) => send<{ ok: true }>('PATCH', `/api/payouts/${id}`, patch),
  remove: (id: number) => send<{ ok: true }>('DELETE', `/api/payouts/${id}`),
};

export interface HuntPlaceStat {
  place_slug: string;
  place_name: string;
  hunts: number;
  characters: number;
  raw_xp_h: number;
  xp_h: number;
  balance_h: number;
  min_level: number | null;
  max_level: number | null;
}

/** Weekly goal with streak (D-53) */
export interface HuntGoal {
  id: number;
  character_name: string | null;
  metric: 'xp' | 'profit';
  current_week: string;
  current: number;
  target: number;
  pct: number;
  reached: boolean;
  streak: number;
  best_streak: number;
  history: { week: string; value: number; reached: boolean }[];
}

export type HuntFilters = Partial<Record<'vocation' | 'min_level' | 'max_level' | 'place' | 'kind' | 'period' | 'sort' | 'metric' | 'page' | 'by', string>>;

const qs = (f: HuntFilters) => {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(f)) if (v) p.set(k, v);
  const s = p.toString();
  return s ? `?${s}` : '';
};

export const hunts = {
  save: (body: Record<string, unknown>) =>
    send<{ id: string; duplicate?: boolean; server_log_saved?: boolean }>('POST', '/api/hunts', body),
  shared: () => send<{ hunts: HuntRow[] }>('GET', '/api/hunts/mine?scope=shared'),
  mine: (character?: string) =>
    send<{ hunts: HuntRow[]; limit: number; premium: boolean }>('GET', `/api/hunts/mine${character ? `?character=${encodeURIComponent(character)}` : ''}`),
  get: (id: string) => send<{ hunt: HuntDetail; mine: boolean; shared?: boolean }>('GET', `/api/hunts/${encodeURIComponent(id)}`),
  update: (id: string, patch: Record<string, unknown>) => send<{ ok: true }>('PATCH', `/api/hunts/${encodeURIComponent(id)}`, patch),
  remove: (id: string) => send<{ ok: true }>('DELETE', `/api/hunts/${encodeURIComponent(id)}`),
  publicFeed: (f: HuntFilters) => send<{ hunts: HuntRow[]; page: number }>('GET', `/api/hunts/public${qs(f)}`),
  ranking: (f: HuntFilters) => send<{ metric: string; hunts: HuntRow[] }>('GET', `/api/hunts/ranking${qs(f)}`),
  places: (f: HuntFilters) =>
    send<{ places: (HuntPlaceStat & { vocation?: Vocation | null })[] }>('GET', `/api/hunts/places${qs(f)}`),
  performance: (character: string) =>
    send<{ character: string; others?: string[]; hunts: (HuntRow & { monsters: { name: string; count: number }[]; skills: { skill: string; from: string; to: string }[] | null })[] }>(
      'GET',
      `/api/hunts/performance?character=${encodeURIComponent(character)}`,
    ),
  weeklyBest: () => send<{ xp: HuntRow[]; profit: HuntRow[] }>('GET', '/api/hunts/weekly-best'),
  /** D-72 */
  weapons: () => send<{ hunts: import('./core/weaponCompare').WeaponHunt[] }>('GET', '/api/hunts/weapons'),
  goals: () => send<{ goals: HuntGoal[] }>('GET', '/api/hunts/goals'),
  setGoal: (metric: 'xp' | 'profit', target: number, character_name: string | null) =>
    send<{ id: number }>('PUT', '/api/hunts/goals', { metric, target, character_name }),
  removeGoal: (id: number) => send<{ ok: true }>('DELETE', `/api/hunts/goals/${id}`),
};

// ---------------------------------------------------------------------------
// Today in Tibia (D-30)
// ---------------------------------------------------------------------------

export const daily = {
  worlds: () => request<string[]>('/api/worlds'),
  dreamOffsets: () =>
    request<{ general: number; worlds: Record<string, { offset: number; uncertain: boolean }>; source: string | null }>(
      '/api/dreamscar',
    ),
  yasir: (world: string) =>
    send<{ world: string; day: string; status: YasirStatus; city: YasirCity | null; reports: number }>(
      'GET',
      `/api/yasir?world=${encodeURIComponent(world)}`,
    ),
  reportYasir: (world: string, present: boolean, city?: YasirCity) =>
    send<{ ok: true }>('POST', '/api/yasir', { world, present, city }),
  yasirPublic: (days = 30) => request<YasirPublic>(`/api/yasir/public?days=${days}`),
};

// ---------------------------------------------------------------------------
// Hunting places (D-42)
// ---------------------------------------------------------------------------

export interface HuntingPlaceSummary {
  slug: string;
  name: string;
  city: string;
  lvl_knights: number | null;
  lvl_paladins: number | null;
  lvl_mages: number | null;
  exp_stars: number | null;
  loot_stars: number | null;
  access_quests: string[];
  has_map: boolean;
  creatures: string[];
}

export interface HuntingPlaceDetail {
  slug: string;
  name: string;
  city: string;
  location: string | null;
  wiki_title: string;
  lvl_knights: number | null;
  lvl_paladins: number | null;
  lvl_mages: number | null;
  sk_knights: number | null;
  sk_paladins: number | null;
  sk_mages: number | null;
  exp_stars: number | null;
  loot_stars: number | null;
  exp_text: string | null;
  loot_text: string | null;
  coord_x: number | null;
  coord_y: number | null;
  coord_z: number | null;
  best_loot: string[];
  access_quests: string[];
  main_creature: string | null;
  creatures: { id: number; name: string; image_url: string | null; grizzly_task_points: number; density_tier: number }[];
}

/** D-80: creature sheet (TibiaWiki data joined with the hunting places) */
export interface CreatureSheet {
  name: string;
  title: string;
  kind: string | null;
  atk: string | null;
  maxHit: string | null;
  ab: SheetAbility[];
  loot: LootEntry[];
  tr: SheetTraits;
  loc: string;
  desc: string;
  beh: string;
  notes: string;
  strat: string;
  stats: CreatureStats | null;
  places: { slug: string; name: string; city: string | null; level: number }[];
}
export const creatureInfo = {
  get: (slug: string) => request<CreatureSheet>(`/api/creature-info/${encodeURIComponent(slug)}`),
};

export const huntingPlaces = {
  list: () => request<HuntingPlaceSummary[]>('/api/hunting-places'),
  get: (slug: string) => request<HuntingPlaceDetail>(`/api/hunting-places/${encodeURIComponent(slug)}`),
};

// ---------------------------------------------------------------------------
// Soul cores (D-41)
// ---------------------------------------------------------------------------

export interface SoulCore {
  creature: string;
  itemId: number;
  bestiaryClass: string | null;
  difficulty: string | null;
  /** Sprite resolved by the API (D-57) */
  image_url?: string | null;
}

export const soulcores = {
  catalog: () => request<{ total: number; soulcores: SoulCore[] }>('/api/soulcores'),
  owned: (charId: number) =>
    send<{ total: number; owned: string[]; last_added: string | null }>('GET', `/api/characters/${charId}/soulcores`),
  set: (charId: number, creature: string, owned: boolean) =>
    send<{ ok: true }>('PUT', `/api/characters/${charId}/soulcores`, { creature, owned }),
  bulk: (charId: number, creatures: string[], owned: boolean) =>
    send<{ ok: true; changed: number; unknown: string[] }>('POST', `/api/characters/${charId}/soulcores/bulk`, {
      creatures,
      owned,
    }),
};

// ---------------------------------------------------------------------------
// Yasir sightings (D-40)
// ---------------------------------------------------------------------------

export const YASIR_CITIES = ['Ankrahmun', 'Carlin', 'Liberty Bay', 'other'] as const;
export type YasirCity = (typeof YASIR_CITIES)[number];
export type YasirStatus = 'confirmed' | 'contested' | 'unconfirmed' | 'absent' | 'none';

export interface YasirOutcome {
  day: string;
  world: string;
  status: YasirStatus;
  city: YasirCity | null;
  support: number;
  against: number;
  reports: number;
}

export interface YasirPublic {
  day: string;
  today: YasirOutcome[];
  history: YasirOutcome[];
  stats: {
    confirmedDays: number;
    byWorld: { world: string; days: number; lastDay: string }[];
    byCity: Partial<Record<YasirCity, number>>;
    /** 0 = Monday … 6 = Sunday */
    byWeekday: number[];
    avgGapDays: { world: string; days: number }[];
  };
  window_days: number;
}

export interface YasirAdminReport {
  day: string;
  world: string;
  user_id: number;
  email: string | null;
  display_name: string | null;
  present: boolean;
  city: YasirCity | null;
  reported_at: string;
  verified_on_world: boolean;
  blocked: boolean;
  reputation: number;
  weight: number;
}

// ---------------------------------------------------------------------------
// Weekly tasks and drop sources (D-37)
// ---------------------------------------------------------------------------

export interface WeeklyTask {
  id: number;
  week_start: string;
  kind: 'creature' | 'item';
  name: string;
  target: number;
  progress: number;
  done_at: string | null;
}

export interface DropSources {
  item: string;
  creatures: { name: string; rate: number | null; kills: number | null; boss?: boolean; spawnrank_id: number | null; image_url?: string | null }[];
  places: {
    creature: string;
    creature_id: number;
    image_url?: string | null;
    zone: string;
    slug?: string | null;
    city: string;
    density: number;
    min_level: number;
    rate: number | null;
    score: number | null;
  }[];
  source: string;
}

export const weekly = {
  list: (charId: number) =>
    send<{ week_start: string; tasks: WeeklyTask[]; history: { week_start: string; total: number; done: number }[] }>(
      'GET',
      `/api/characters/${charId}/weekly`,
    ),
  add: (charId: number, kind: 'creature' | 'item', name: string, target: number) =>
    send<WeeklyTask>('POST', `/api/characters/${charId}/weekly`, { kind, name, target }),
  update: (charId: number, taskId: number, patch: { progress?: number; done?: boolean }) =>
    send<WeeklyTask>('PATCH', `/api/characters/${charId}/weekly/${taskId}`, patch),
  remove: (charId: number, taskId: number) => send<{ ok: true }>('DELETE', `/api/characters/${charId}/weekly/${taskId}`),
  drops: (item: string) => request<DropSources>(`/api/drops?item=${encodeURIComponent(item)}`),
  /** Drop finder search (D-58): creature products first */
  suggest: (q: string) =>
    request<{ items: { name: string; creature_product: boolean; npc_price: number }[] }>(`/api/drops/suggest?q=${encodeURIComponent(q)}`),
  creature: (name: string) => request<{ id: number; name: string } | null>(`/api/creatures/lookup?name=${encodeURIComponent(name)}`),
};
