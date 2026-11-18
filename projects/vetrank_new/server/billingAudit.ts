/**
 * Auditoria de cobranças — cruza o que o Mercado Pago tem com o nosso banco e avisa os admins
 * por e-mail quando encontra risco de cobrança indevida/duplicada. SOMENTE LEITURA: não cancela,
 * não estorna, não altera nada.
 *
 * Verificações:
 *  1. Mais de uma assinatura recorrente ativa para a mesma pessoa      → cobrança dupla todo mês
 *  2. Assinatura mensal ativa para quem já tem plano anual ativo        → cobrança indevida
 *  3. Assinatura ativa no MP que o nosso banco não conhece              → usuário não consegue cancelar pelo site
 *  4. Duas cobranças aprovadas do mesmo valor, mesma pessoa, < 20 dias  → possível cobrança duplicada
 *  5. Cobrança aprovada no MP sem acesso liberado no VetRank            → pagou e não recebeu
 */
import { getDb } from "./db";
import { notifyAdmins, escapeHtml } from "./adminNotify";

const MP = "https://api.mercadopago.com";
const LOOKBACK_DAYS = 40;
const DUP_WINDOW_DAYS = 20;

type Finding = { check: number; severity: "alta" | "média"; who: string; detail: string };

async function mpGet(path: string): Promise<any> {
  const token = process.env.MP_ACCESS_TOKEN;
  if (!token) throw new Error("MP_ACCESS_TOKEN not configured");
  const r = await fetch(`${MP}${path}`, { headers: { Authorization: `Bearer ${token}` } });
  if (!r.ok) throw new Error(`MP ${path.split("?")[0]} → HTTP ${r.status}`);
  return r.json();
}

async function mpSearchAll(path: string, max = 1000): Promise<any[]> {
  const out: any[] = [];
  for (let offset = 0; offset < max; offset += 50) {
    const sep = path.includes("?") ? "&" : "?";
    const j = await mpGet(`${path}${sep}limit=50&offset=${offset}`);
    const results: any[] = j?.results ?? [];
    out.push(...results);
    const total = Number(j?.paging?.total ?? 0);
    if (results.length < 50 || out.length >= total) break;
  }
  return out;
}

function parseRef(raw: unknown): { userId?: number; plan?: string; type?: string } | null {
  try { return typeof raw === "string" && raw.startsWith("{") ? JSON.parse(raw) : null; } catch { return null; }
}

export async function runBillingAudit(): Promise<{ findings: Finding[]; checked: { subscriptions: number; payments: number } }> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  const findings: Finding[] = [];

  // Usuários indexados por id e por e-mail
  const [userRows] = await db.execute(
    `SELECT id, LOWER(email) AS email, plan, subscriptionPlan, premiumEndsAt, mpPreapprovalId FROM users
     WHERE email IS NOT NULL AND email NOT LIKE 'merged+%'`
  ) as any;
  const byId = new Map<number, any>();
  const byEmail = new Map<string, any>();
  for (const u of userRows as any[]) { byId.set(Number(u.id), u); if (u.email) byEmail.set(u.email, u); }
  const resolveUser = (ref: any, email?: string) =>
    (ref?.userId && byId.get(Number(ref.userId))) || (email && byEmail.get(String(email).toLowerCase())) || null;
  const label = (u: any, fallback?: string) => u ? `#${u.id} ${u.email}` : (fallback || "desconhecido");
  const annualActive = (u: any) => u?.subscriptionPlan === "annual" && u?.premiumEndsAt && new Date(u.premiumEndsAt) > new Date();

  // ── Assinaturas recorrentes ativas ─────────────────────────────────────────
  const subs = [
    ...await mpSearchAll(`/preapproval/search?status=authorized`),
    ...await mpSearchAll(`/preapproval/search?status=paused`),
  ];
  const subsByPerson = new Map<string, { user: any; subs: any[] }>();
  for (const s of subs) {
    const user = resolveUser(parseRef(s.external_reference), s.payer_email);
    const key = user ? `u${user.id}` : `p${s.payer_id ?? s.payer_email ?? s.id}`;
    const entry = subsByPerson.get(key) ?? { user, subs: [] };
    entry.subs.push(s);
    subsByPerson.set(key, entry);

    if (user && annualActive(user) && s.status === "authorized") {
      findings.push({ check: 2, severity: "alta", who: label(user),
        detail: `Assinatura mensal ${s.id} ativa com plano anual válido até ${new Date(user.premiumEndsAt).toLocaleDateString("pt-BR")}. Cancelar no MP.` });
    }
    if (user && user.mpPreapprovalId !== s.id) {
      findings.push({ check: 3, severity: "média", who: label(user),
        detail: `Assinatura ${s.id} (${s.status}) ativa no MP, mas o VetRank tem ${user.mpPreapprovalId ?? "nenhuma"} registrada — o usuário não consegue cancelar pelo site.` });
    }
    if (!user) {
      findings.push({ check: 3, severity: "média", who: s.payer_email || `payer ${s.payer_id}`,
        detail: `Assinatura ${s.id} (${s.status}) sem usuário correspondente no VetRank.` });
    }
  }
  for (const { user, subs: list } of subsByPerson.values()) {
    if (list.length > 1) {
      findings.push({ check: 1, severity: "alta", who: label(user, list[0]?.payer_email),
        detail: `${list.length} assinaturas ativas: ${list.map(s => `${s.id} (${s.status}, próx. ${s.next_payment_date?.slice(0, 10) ?? "-"})`).join("; ")}. Manter só uma.` });
    }
  }

  // ── Cobranças aprovadas recentes ────────────────────────────────────────────
  const payments = await mpSearchAll(
    `/v1/payments/search?sort=date_created&criteria=desc&range=date_created&begin_date=NOW-${LOOKBACK_DAYS}DAYS&end_date=NOW&status=approved`
  );
  const byPayer = new Map<string, any[]>();
  for (const p of payments) {
    const key = String(p?.payer?.id ?? p?.payer?.email ?? "");
    if (!key) continue;
    (byPayer.get(key) ?? byPayer.set(key, []).get(key)!).push(p);
  }
  for (const list of byPayer.values()) {
    list.sort((a, b) => String(a.date_approved ?? a.date_created).localeCompare(String(b.date_approved ?? b.date_created)));
    for (let i = 1; i < list.length; i++) {
      const a = list[i - 1], b = list[i];
      const days = (new Date(b.date_approved ?? b.date_created).getTime() - new Date(a.date_approved ?? a.date_created).getTime()) / 86400000;
      if (days < DUP_WINDOW_DAYS && Number(a.transaction_amount) === Number(b.transaction_amount)) {
        const user = resolveUser(parseRef(b.external_reference), b?.payer?.email);
        findings.push({ check: 4, severity: "alta", who: label(user, b?.payer?.email),
          detail: `Cobranças ${a.id} e ${b.id} de R$ ${Number(b.transaction_amount).toFixed(2)} com ${days.toFixed(1)} dia(s) de diferença. Verificar e estornar se for duplicada.` });
      }
    }
  }

  // Cobrança aprovada que não liberou acesso (webhook perdido)
  const [ourRows] = await db.execute(
    `SELECT externalId FROM payments WHERE createdAt >= NOW() - INTERVAL ${LOOKBACK_DAYS + 5} DAY`
  ) as any;
  const known = new Set((ourRows as any[]).map(r => String(r.externalId)));
  for (const p of payments) {
    if (known.has(String(p.id))) continue;
    const ref = parseRef(p.external_reference);
    if (ref?.type === "trail" || ref?.type === "course") continue; // compras avulsas têm fluxo próprio
    const user = resolveUser(ref, p?.payer?.email);
    const hasAccess = user?.premiumEndsAt && new Date(user.premiumEndsAt) > new Date();
    if (!hasAccess) {
      findings.push({ check: 5, severity: "alta", who: label(user, p?.payer?.email),
        detail: `Pagamento ${p.id} de R$ ${Number(p.transaction_amount).toFixed(2)} aprovado em ${String(p.date_approved ?? p.date_created).slice(0, 10)}, mas a conta não tem acesso premium ativo.` });
    }
  }

  return { findings, checked: { subscriptions: subs.length, payments: payments.length } };
}

const CHECK_TITLES: Record<number, string> = {
  1: "Mais de uma assinatura ativa (cobrança dupla mensal)",
  2: "Mensal ativa com anual válido (cobrança indevida)",
  3: "Assinatura que o site não consegue cancelar",
  4: "Possível cobrança duplicada",
  5: "Pagou e não recebeu acesso",
};

export async function runBillingAuditAndNotify(opts: { alwaysEmail?: boolean } = {}) {
  const result = await runBillingAudit();
  const { findings, checked } = result;
  if (findings.length > 0 || opts.alwaysEmail) {
    const high = findings.filter(f => f.severity === "alta").length;
    const groups = [1, 2, 4, 5, 3].map(n => {
      const items = findings.filter(f => f.check === n);
      if (!items.length) return "";
      return `<h3 style="color:#4ade80;font-size:15px;margin:18px 0 6px;">${CHECK_TITLES[n]} (${items.length})</h3><ul>${
        items.map(f => `<li><b>${escapeHtml(f.who)}</b> — ${escapeHtml(f.detail)}</li>`).join("")}</ul>`;
    }).join("");
    await notifyAdmins(
      findings.length ? `🔎 Auditoria de cobranças: ${findings.length} ponto(s), ${high} de alta prioridade` : "🔎 Auditoria de cobranças: tudo certo",
      `<p>Verificadas ${checked.subscriptions} assinatura(s) ativa(s) e ${checked.payments} cobrança(s) aprovada(s) dos últimos ${LOOKBACK_DAYS} dias.</p>
       ${groups || "<p>Nenhum problema encontrado.</p>"}
       <p style="color:#9ca3af;font-size:12px;">A auditoria só lê dados; nenhum cancelamento ou estorno foi feito automaticamente.</p>`
    );
  }
  return result;
}
