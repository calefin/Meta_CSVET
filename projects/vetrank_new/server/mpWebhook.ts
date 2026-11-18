/**
 * Mercado Pago webhook handler.
 * Events handled:
 *  payment                      → one-time (annual Checkout Pro)
 *  subscription_preapproval     → subscription status (monthly Assinaturas)
 *  subscription_authorized_payment → recurring monthly charge
 *  claim                        → Reclamações (buyer disputes)
 *  chargebacks                  → Contestações (chargebacks)
 *  fraud_alert                  → Alertas de fraude
 *  merchant_order               → Perfil de pago (order aggregator)
 */
import type { Request, Response } from "express";
import { getDb } from "./db";
import { users, payments, mpWebhookEvents, trailPurchases, trailEnrollments, trailModules, trailModuleProgress } from "../drizzle/schema";
import { and, eq, gte, sql } from "drizzle-orm";
import { MercadoPagoConfig, Payment, PreApproval } from "mercadopago";
import crypto from "crypto";
import { sendPaymentReceiptEmail } from "./email";
import { notifyAdmins, escapeHtml } from "./adminNotify";

const MONTHLY_PRICE = 39.90;

type DbType = NonNullable<Awaited<ReturnType<typeof getDb>>>;

// ── Signature verification ────────────────────────────────────────────────────
function verifyWebhookSignature(req: Request): boolean {
  const secret = process.env.MP_WEBHOOK_SECRET;
  if (!secret) return true;
  const signature = req.headers["x-signature"] as string | undefined;
  const requestId = req.headers["x-request-id"] as string | undefined;
  if (!signature) return false;
  const parts = Object.fromEntries(signature.split(",").map(p => p.split("=")));
  const ts = parts["ts"];
  const v1 = parts["v1"];
  if (!ts || !v1) return false;
  const dataId = (req.body?.data?.id ?? "").toString();
  const manifest = `id:${dataId};request-id:${requestId ?? ""};ts:${ts};`;
  const expected = crypto.createHmac("sha256", secret).update(manifest).digest("hex");
  try {
    return crypto.timingSafeEqual(Buffer.from(v1), Buffer.from(expected));
  } catch { return false; }
}

// ── Log all webhook events for admin panel ────────────────────────────────────
async function logEvent(db: DbType, opts: {
  type: string; action?: string; status?: string; userId?: number;
  externalId?: string; amount?: number; planType?: string; raw?: unknown;
}) {
  try {
    await db.insert(mpWebhookEvents).values({
      type: opts.type,
      action: opts.action ?? undefined,
      status: opts.status ?? undefined,
      userId: opts.userId ?? undefined,
      externalId: opts.externalId ?? undefined,
      amount: opts.amount ?? undefined,
      planType: opts.planType ?? undefined,
      raw: opts.raw as any,
    });
  } catch { /* never block webhook on log failure */ }

  // Eventos que exigem ação humana → e-mail imediato para os admins
  const ALERT_TYPES: Record<string, string> = {
    claim: "Reclamação aberta no Mercado Pago",
    chargebacks: "Chargeback (contestação) no Mercado Pago",
    fraud_alert: "Alerta de fraude no Mercado Pago",
    cancel_failed: "Falha ao cancelar assinatura — risco de cobrança dupla",
    charge_while_annual: "Cobrança mensal com plano anual ativo — avaliar reembolso",
  };
  const title = ALERT_TYPES[opts.type] ?? (opts.status === "needs_review" ? `Evento de pagamento para revisar (${opts.type})` : null);
  if (title) {
    notifyAdmins(`🚨 ${title}`, `
      <p><b>Tipo:</b> ${escapeHtml(opts.type)}${opts.action ? ` / ${escapeHtml(opts.action)}` : ""}</p>
      <p><b>ID Mercado Pago:</b> ${escapeHtml(opts.externalId ?? "-")}</p>
      <p><b>Usuário VetRank:</b> ${opts.userId ? `#${opts.userId}` : "não identificado"}</p>
      ${opts.amount != null ? `<p><b>Valor:</b> R$ ${Number(opts.amount).toFixed(2).replace(".", ",")}</p>` : ""}
      <p>Confira no painel do Mercado Pago e em Admin → Financeiro.</p>
    `).catch(() => {});
  }
}

type TxType = Parameters<Parameters<DbType["transaction"]>[0]>[0];

// ── Activate/extend premium access ───────────────────────────────────────────
async function activatePremium(db: DbType | TxType, userId: number, durationDays: number) {
  const [current] = await db.select({ premiumEndsAt: users.premiumEndsAt })
    .from(users).where(eq(users.id, userId)).limit(1);
  const base = current?.premiumEndsAt && current.premiumEndsAt > new Date()
    ? current.premiumEndsAt
    : new Date();
  const premiumEndsAt = new Date(base);
  premiumEndsAt.setDate(premiumEndsAt.getDate() + durationDays);
  await db.update(users).set({ plan: "premium", premiumStartedAt: new Date(), premiumEndsAt })
    .where(eq(users.id, userId));
  return premiumEndsAt;
}

// Mensal: uma única cobrança real chega por até 3 caminhos (subscription_preapproval "authorized",
// subscription_authorized_payment e payment). Qualquer crédito mensal dentro desta janela é
// considerado a MESMA cobrança — renovações legítimas ficam ~30 dias distantes.
const MONTHLY_DEDUP_WINDOW_DAYS = 20;

/**
 * Credits premium days + records the approved payment atomically.
 * Serialised per user with SELECT ... FOR UPDATE so two webhook deliveries arriving at the same
 * time (MP sends payment + authorized_payment almost simultaneously) can't both pass the dedup check.
 * Returns null when the charge was already credited.
 */
async function creditPremium(db: DbType, opts: {
  userId: number;
  durationDays: number;
  chargeKey: string;
  planType: "monthly" | "annual";
  amount: number;
  paymentMethod?: string;
  metadata: Record<string, unknown>;
  extraUserFields?: Record<string, unknown>;
}): Promise<{ premiumEndsAt: Date; receiptNumber: string } | null> {
  return db.transaction(async (tx) => {
    await tx.execute(sql`SELECT id FROM users WHERE id = ${opts.userId} FOR UPDATE`);

    const [dup] = await tx.select({ id: payments.id }).from(payments)
      .where(and(eq(payments.externalId, opts.chargeKey), eq(payments.status, "approved")))
      .limit(1);
    if (dup) return null;

    if (opts.planType === "monthly") {
      const since = new Date(Date.now() - MONTHLY_DEDUP_WINDOW_DAYS * 86400000);
      const [recent] = await tx.select({ id: payments.id }).from(payments)
        .where(and(
          eq(payments.userId, opts.userId),
          eq(payments.planType, "monthly"),
          eq(payments.status, "approved"),
          gte(payments.createdAt, since),
        )).limit(1);
      if (recent) return null;
    }

    const premiumEndsAt = await activatePremium(tx, opts.userId, opts.durationDays);
    await tx.update(users).set({ subscriptionPlan: opts.planType, ...(opts.extraUserFields ?? {}) })
      .where(eq(users.id, opts.userId));
    const receiptNumber = generateReceiptNumber();
    await tx.insert(payments).values({
      userId: opts.userId,
      amount: opts.amount,
      currency: "BRL",
      status: "approved",
      paymentMethod: opts.paymentMethod,
      planType: opts.planType,
      externalId: opts.chargeKey,
      metadata: { ...opts.metadata, receipt_number: receiptNumber },
    });
    return { premiumEndsAt, receiptNumber };
  });
}

// ── Cancel a user's recurring monthly subscription on MP (best effort) ───────
async function cancelUserPreapproval(db: DbType, client: MercadoPagoConfig, userId: number, reason: string, keepId?: string) {
  const [u] = await db.select({ mpPreapprovalId: users.mpPreapprovalId })
    .from(users).where(eq(users.id, userId)).limit(1);
  const id = u?.mpPreapprovalId;
  if (!id || id === keepId) return;
  try {
    await new PreApproval(client).update({ id, body: { status: "cancelled" } as any });
    console.log(`[MP Webhook] Cancelled preapproval ${id} for user ${userId} (${reason})`);
  } catch (e: any) {
    console.error(`[MP Webhook] 🚨 Failed to cancel preapproval ${id} for user ${userId} (${reason}) — CANCEL MANUALLY to avoid double charge:`, e?.message);
    await logEvent(db, { type: "cancel_failed", status: "needs_review", userId, externalId: id, raw: { reason, error: e?.message } });
    return;
  }
  if (!keepId) await db.update(users).set({ mpPreapprovalId: null }).where(eq(users.id, userId));
}

// ── Fetch an authorized_payment (recurring charge) — NOT the same id space as /v1/payments ──
async function fetchAuthorizedPayment(token: string, id: string): Promise<{
  status?: string; amount?: number; paymentId?: string; preapprovalId?: string; externalReference?: string; payerEmail?: string;
} | null> {
  try {
    const r = await fetch(`https://api.mercadopago.com/authorized_payments/${encodeURIComponent(id)}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!r.ok) return null;
    const j: any = await r.json();
    return {
      status: j?.payment?.status ?? j?.status,
      amount: j?.transaction_amount ?? undefined,
      paymentId: j?.payment?.id != null ? String(j.payment.id) : undefined,
      preapprovalId: j?.preapproval_id ?? undefined,
      externalReference: j?.external_reference ?? undefined,
      payerEmail: j?.payer?.email ?? undefined,
    };
  } catch { return null; }
}

// ── Parse external_reference safely ──────────────────────────────────────────
function parseRef(raw: unknown): { userId: number; plan?: string; durationDays?: number; type?: string; courseId?: number; trailId?: number } | null {
  try { return JSON.parse(raw as string); }
  catch { return null; }
}

// ── Generate receipt number ───────────────────────────────────────────────────
function generateReceiptNumber(): string {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const rand = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `VR-${y}${m}-${rand}`;
}

// ── Fetch user info for email sending ─────────────────────────────────────────
async function fetchUserInfo(db: DbType, userId: number) {
  const [u] = await db.select({ email: users.email, name: users.name })
    .from(users).where(eq(users.id, userId)).limit(1);
  return u ?? null;
}

// Idempotency (MP delivers webhooks at-least-once) is handled inside creditPremium().

// ── Main handler ──────────────────────────────────────────────────────────────
export async function handleMPWebhook(req: Request, res: Response) {
  try {
    const token = process.env.MP_ACCESS_TOKEN;
    if (!token) {
      console.error("[MP Webhook] MP_ACCESS_TOKEN not configured");
      return res.status(500).json({ error: "Not configured" });
    }

    if (!verifyWebhookSignature(req)) {
      console.warn("[MP Webhook] Invalid signature — rejected");
      return res.status(401).json({ error: "Invalid signature" });
    }

    const { type, action, data } = req.body as {
      type: string; action?: string; data?: { id?: string };
    };

    if (!data?.id) return res.status(200).json({ received: true });

    const client = new MercadoPagoConfig({ accessToken: token });
    const db = await getDb();
    if (!db) return res.status(500).json({ error: "DB unavailable" });

    console.log(`[MP Webhook] type=${type} action=${action ?? "-"} id=${data.id}`);

    // ── PAYMENT (annual one-time or subscription charge) ──────────────────────
    if (type === "payment") {
      const paymentClient = new Payment(client);
      const payment = await paymentClient.get({ id: data.id });
      const status = payment.status;
      const externalRef = payment.external_reference;
      const ref = parseRef(externalRef);

      await logEvent(db, {
        type, action, status: status ?? undefined,
        userId: ref?.userId,
        externalId: String(data.id),
        amount: payment.transaction_amount ?? undefined,
        planType: ref?.plan,
        raw: req.body,
      });

      if (status !== "approved" || !ref) {
        if (ref) {
          await db.insert(payments).values({
            userId: ref.userId,
            amount: payment.transaction_amount ?? 0,
            currency: "BRL",
            status: status === "rejected" ? "rejected" : "pending",
            planType: (ref.plan ?? "annual") as "monthly" | "annual",
            externalId: String(data.id),
            failureReason: (payment as any).status_detail ?? undefined,
            metadata: { mp_payment_id: data.id, status },
          }).catch(() => {});
        }
        return res.status(200).json({ received: true, status });
      }

      // ── Compra de trilha avulsa ───────────────────────────────────────────────
      if (ref.type === "trail" && ref.trailId) {
        const now = new Date();
        await db.update(trailPurchases).set({
          status: "active",
          transactionId: String(data.id),
          purchasedAt: now,
        }).where(and(
          eq(trailPurchases.userId, ref.userId),
          eq(trailPurchases.trailId, ref.trailId),
          eq(trailPurchases.status, "pending"),
        )).catch(() => {});

        // Garantir que um registro ativo exista (idempotente)
        const [existingPurchase] = await db.select({ id: trailPurchases.id })
          .from(trailPurchases)
          .where(and(eq(trailPurchases.userId, ref.userId), eq(trailPurchases.trailId, ref.trailId), eq(trailPurchases.status, "active")))
          .limit(1);
        if (!existingPurchase) {
          await db.insert(trailPurchases).values({
            userId: ref.userId, trailId: ref.trailId,
            pricePaid: String(payment.transaction_amount ?? 0) as any,
            status: "active", transactionId: String(data.id), purchasedAt: now,
          }).catch(() => {});
        }

        // Criar matrícula automaticamente
        const [existingEnrollment] = await db.select({ id: trailEnrollments.id })
          .from(trailEnrollments)
          .where(and(eq(trailEnrollments.userId, ref.userId), eq(trailEnrollments.trailId, ref.trailId)))
          .limit(1);
        if (!existingEnrollment) {
          const mods = await db.select().from(trailModules)
            .where(eq(trailModules.trailId, ref.trailId))
            .orderBy(trailModules.order);
          if (mods.length > 0) {
            const [res] = await db.insert(trailEnrollments).values({
              userId: ref.userId, trailId: ref.trailId, status: "enrolled", currentModuleId: mods[0].id,
            });
            const enrollmentId = (res as { insertId: number }).insertId;
            for (let i = 0; i < mods.length; i++) {
              await db.insert(trailModuleProgress).values({
                enrollmentId, moduleId: mods[i].id, userId: ref.userId,
                status: i === 0 ? "available" : "locked",
              }).catch(() => {});
            }
          }
        }

        await db.insert(payments).values({
          userId: ref.userId, amount: payment.transaction_amount ?? 0,
          currency: "BRL", status: "approved",
          paymentMethod: payment.payment_type_id ?? undefined,
          planType: "annual", externalId: String(data.id),
          metadata: { mp_payment_id: data.id, trail_id: ref.trailId },
        }).catch(() => {});

        const userInfo = await fetchUserInfo(db, ref.userId);
        if (userInfo?.email) {
          const receiptNumber = generateReceiptNumber();
          await sendPaymentReceiptEmail({
            to: userInfo.email,
            name: userInfo.name ?? "Veterinário(a)",
            receiptNumber,
            planLabel: "Trilha VetRank — Compra Avulsa",
            amount: payment.transaction_amount ?? 0,
            paymentMethod: payment.payment_type_id ?? undefined,
            paidAt: now,
          }).catch(e => console.warn("[MP Webhook] Receipt email failed:", e?.message));
        }
        console.log(`[MP Webhook] ✅ Trail purchase approved — user ${ref.userId} → trail ${ref.trailId}`);
        return res.status(200).json({ received: true, activated: true });
      }

      // ── Compra de curso avulso ────────────────────────────────────────────────
      if (ref.type === "course" && ref.courseId) {
        await db.execute(sql`
          INSERT INTO course_purchases (userId, courseId, pricePaid, status, transactionId, purchasedAt, createdAt, updatedAt)
          VALUES (${ref.userId}, ${ref.courseId}, ${payment.transaction_amount ?? 0}, 'active', ${String(data.id)}, NOW(), NOW(), NOW())
          ON DUPLICATE KEY UPDATE status = 'active', transactionId = VALUES(transactionId), updatedAt = NOW()
        `);
        await db.insert(payments).values({
          userId: ref.userId,
          amount: payment.transaction_amount ?? 0,
          currency: "BRL",
          status: "approved",
          paymentMethod: payment.payment_type_id ?? undefined,
          planType: "annual",
          externalId: String(data.id),
          metadata: { mp_payment_id: data.id, course_id: ref.courseId },
        }).catch(() => {});
        console.log(`[MP Webhook] ✅ Course purchase approved — user ${ref.userId} → course ${ref.courseId}`);
        return res.status(200).json({ received: true, activated: true });
      }

      const planType = (ref.plan === "monthly" ? "monthly" : "annual") as "monthly" | "annual";

      const payerName  = [(payment.payer as any)?.first_name, (payment.payer as any)?.last_name].filter(Boolean).join(" ") || undefined;
      const payerCpf   = (payment.payer as any)?.identification?.number;
      const payerPhone = (payment.payer as any)?.phone?.number;
      const extra: Record<string, string> = {};
      if (payerName)  extra.name  = payerName;
      if (payerCpf)   extra.cpf   = payerCpf;
      if (payerPhone) extra.phone = payerPhone;

      const credited = await creditPremium(db, {
        userId: ref.userId,
        durationDays: ref.durationDays ?? (planType === "monthly" ? 30 : 365),
        chargeKey: String(data.id),
        planType,
        amount: payment.transaction_amount ?? 0,
        paymentMethod: payment.payment_type_id ?? undefined,
        metadata: { mp_payment_id: data.id, payer_email: (payment.payer as any)?.email, payer_cpf: payerCpf, payer_name: payerName },
        extraUserFields: extra,
      });
      if (!credited) {
        console.log(`[MP Webhook] Payment ${data.id} already credited — skipping duplicate.`);
        return res.status(200).json({ received: true, duplicate: true });
      }
      const { premiumEndsAt, receiptNumber } = credited;

      // Upgrade mensal → anual: encerra a recorrência, senão o cartão continua sendo cobrado todo mês.
      if (planType === "annual") {
        await cancelUserPreapproval(db, client, ref.userId, "upgraded to annual");
      }

      // Send receipt email
      const userInfo = await fetchUserInfo(db, ref.userId);
      if (userInfo?.email) {
        const planLabel = ref.plan === "monthly" ? "VetRank Premium Mensal" : "VetRank Premium Anual";
        await sendPaymentReceiptEmail({
          to: userInfo.email,
          name: userInfo.name ?? "Veterinário(a)",
          receiptNumber,
          planLabel,
          amount: payment.transaction_amount ?? 0,
          paymentMethod: payment.payment_type_id ?? undefined,
          paidAt: new Date(),
          premiumEndsAt,
        }).catch(e => console.warn("[MP Webhook] Receipt email failed:", e?.message));
      }

      console.log(`[MP Webhook] ✅ Payment approved — user ${ref.userId} premium until ${premiumEndsAt.toISOString()}`);
      return res.status(200).json({ received: true, activated: true });
    }

    // ── SUBSCRIPTION STATUS (monthly Assinaturas) ─────────────────────────────
    if (type === "subscription_preapproval") {
      const preapproval = new PreApproval(client);
      const sub = await preapproval.get({ id: data.id });
      const status = (sub as any).status as string;

      // payer_email can live at different paths depending on MP SDK version
      const payerEmail: string | undefined =
        (sub as any).payer_email ??
        (sub as any).payer?.email ??
        (sub as any).summarized?.payer_email ??
        undefined;

      // Resolve user: JSON external_reference (API-created subs) → payer email lookup
      const ref = parseRef((sub as any).external_reference);
      let userId = ref?.userId;
      if (!userId && payerEmail) {
        const [found] = await db.select({ id: users.id }).from(users)
          .where(eq(users.email, payerEmail)).limit(1);
        userId = found?.id;
      }

      await logEvent(db, {
        type, action, status, userId,
        externalId: String(data.id),
        planType: "monthly",
        raw: req.body,
      });

      if (!userId) {
        // Log all available sub fields so admin can debug
        console.warn(`[MP Webhook] Subscription ${data.id} — user not found. status=${status} payer_email=${payerEmail} keys=${Object.keys(sub as any).join(",")}`);
        return res.status(200).json({ received: true });
      }

      if (status === "authorized") {
        // Fetch current user plan
        const [user] = await db.select({ plan: users.plan, subscriptionPlan: users.subscriptionPlan, premiumEndsAt: users.premiumEndsAt })
          .from(users).where(eq(users.id, userId)).limit(1);

        const isAnnualActive = user?.plan === "premium" && user?.subscriptionPlan === "annual" && user?.premiumEndsAt && user.premiumEndsAt > new Date();

        if (isAnnualActive) {
          // User already paid for the year — a recurring monthly sub on top of it would charge them
          // every month for nothing. Cancel this preapproval right away.
          try {
            await preapproval.update({ id: String(data.id), body: { status: "cancelled" } as any });
            console.log(`[MP Webhook] Preapproval ${data.id} cancelled — user ${userId} already has an active annual plan`);
          } catch (e: any) {
            console.error(`[MP Webhook] 🚨 Could not cancel preapproval ${data.id} for annual user ${userId} — CANCEL MANUALLY:`, e?.message);
            await logEvent(db, { type: "cancel_failed", status: "needs_review", userId, externalId: String(data.id), raw: { reason: "annual active" } });
          }
        } else {
          // A different, older preapproval still alive for this user = two recurring charges. Kill the old one.
          await cancelUserPreapproval(db, client, userId, `superseded by ${data.id}`, String(data.id));
          // Persist the preapproval ID so the user can cancel later (also covers subscriptions
          // that weren't created through our own createSubscription mutation).
          await db.update(users).set({ subscriptionPlan: "monthly", mpPreapprovalId: String(data.id) }).where(eq(users.id, userId));

          const credited = await creditPremium(db, {
            userId, durationDays: 30, chargeKey: String(data.id), planType: "monthly",
            amount: MONTHLY_PRICE, paymentMethod: "subscription",
            metadata: { subscription_id: data.id, mp_status: status, payer_email: payerEmail },
          });
          if (!credited) {
            console.log(`[MP Webhook] Subscription ${data.id} already credited — skipping duplicate.`);
            return res.status(200).json({ received: true, duplicate: true });
          }
          const { premiumEndsAt, receiptNumber } = credited;

          // Send receipt email
          const userInfo = await fetchUserInfo(db, userId);
          if (userInfo?.email) {
            await sendPaymentReceiptEmail({
              to: userInfo.email,
              name: userInfo.name ?? "Veterinário(a)",
              receiptNumber,
              planLabel: "VetRank Premium Mensal",
              amount: MONTHLY_PRICE,
              paymentMethod: "Assinatura recorrente",
              paidAt: new Date(),
              premiumEndsAt,
            }).catch(e => console.warn("[MP Webhook] Receipt email failed:", e?.message));
          }
          console.log(`[MP Webhook] ✅ Subscription authorized — user ${userId} until ${premiumEndsAt.toISOString()}`);
        }
      }

      if (status === "cancelled" || status === "paused") {
        // Clear the stored preapproval ID (e.g. user cancelled directly from the MP app/dashboard)
        // so createSubscription no longer blocks a new signup and the cancel button hides in the UI.
        // Only if it's the one we have on file — a late "cancelled" for an OLD sub must not wipe the new one.
        // "paused" keeps the ID: a paused sub can resume charging, so createSubscription must still
        // find it and cancel it before creating a new one.
        if (status === "cancelled") {
          await db.update(users).set({ mpPreapprovalId: null })
            .where(and(eq(users.id, userId), eq(users.mpPreapprovalId, String(data.id))));
        }
        console.log(`[MP Webhook] ⚠️ Subscription ${status} — user ${userId} — access expires naturally`);
      }

      return res.status(200).json({ received: true, status });
    }

    // ── AUTOMATIC MONTHLY CHARGE ──────────────────────────────────────────────
    if (type === "subscription_authorized_payment") {
      // data.id here is an *authorized_payment* (invoice) id, which lives at /authorized_payments,
      // not /v1/payments. Read it from the right endpoint; fall back to the old lookup if MP
      // ever sends a plain payment id under this topic.
      let chargeStatus: string | undefined;
      let chargeAmount: number | undefined;
      let realPaymentId: string | undefined;
      let preapprovalId: string | undefined;
      let rawRef: ReturnType<typeof parseRef> = null;
      let payerEmail: string | undefined;
      let metaUserId: number | undefined;

      const ap = await fetchAuthorizedPayment(token, String(data.id));
      if (ap) {
        chargeStatus = ap.status; chargeAmount = ap.amount; realPaymentId = ap.paymentId;
        preapprovalId = ap.preapprovalId; rawRef = parseRef(ap.externalReference); payerEmail = ap.payerEmail;
      } else {
        const payment = await new Payment(client).get({ id: data.id });
        chargeStatus = payment.status ?? undefined;
        chargeAmount = payment.transaction_amount ?? undefined;
        realPaymentId = String(data.id);
        rawRef = parseRef((payment as any).external_reference);
        payerEmail = (payment.payer as any)?.email ?? undefined;
        if ((payment as any).metadata?.user_id) metaUserId = Number((payment as any).metadata.user_id);
      }

      // Resolve userId: external_reference → stored preapproval id → metadata.user_id → payer email
      let resolvedUserId: number | undefined = rawRef?.userId;
      if (!resolvedUserId && preapprovalId) {
        const [found] = await db.select({ id: users.id }).from(users)
          .where(eq(users.mpPreapprovalId, preapprovalId)).limit(1);
        resolvedUserId = found?.id;
      }
      if (!resolvedUserId && metaUserId) resolvedUserId = metaUserId;
      if (!resolvedUserId && payerEmail) {
        const [found] = await db.select({ id: users.id }).from(users)
          .where(eq(users.email, payerEmail)).limit(1);
        resolvedUserId = found?.id;
      }

      await logEvent(db, {
        type, action, status: chargeStatus,
        userId: resolvedUserId,
        externalId: String(data.id),
        amount: chargeAmount ?? MONTHLY_PRICE,
        planType: "monthly",
        raw: req.body,
      });

      if (chargeStatus !== "approved") return res.status(200).json({ received: true });

      if (!resolvedUserId) {
        console.warn(`[MP Webhook] subscription_authorized_payment ${data.id} — user not found (payer_email: ${payerEmail})`);
        return res.status(200).json({ received: true });
      }

      // Check if user has an active annual plan
      const [user] = await db.select({ plan: users.plan, subscriptionPlan: users.subscriptionPlan, premiumEndsAt: users.premiumEndsAt })
        .from(users).where(eq(users.id, resolvedUserId)).limit(1);
      const isAnnualActive = user?.plan === "premium" && user?.subscriptionPlan === "annual" && user?.premiumEndsAt && user.premiumEndsAt > new Date();
      if (isAnnualActive) {
        // Money was actually taken: stop future charges and flag for refund review instead of silently ignoring.
        await cancelUserPreapproval(db, client, resolvedUserId, "monthly charge while annual active");
        await logEvent(db, {
          type: "charge_while_annual", status: "needs_review", userId: resolvedUserId,
          externalId: realPaymentId ?? String(data.id), amount: chargeAmount, planType: "monthly",
          raw: { reason: "Cobrança mensal recebida com plano anual ativo — avaliar reembolso" },
        });
        console.warn(`[MP Webhook] 🚨 Monthly charge ${data.id} for user ${resolvedUserId} who has annual active — preapproval cancelled, flagged for refund review`);
        return res.status(200).json({ received: true, flagged: true });
      }

      {
        // Dedup on the REAL payment id: the same charge also arrives as a `payment` notification.
        const credited = await creditPremium(db, {
          userId: resolvedUserId, durationDays: 30,
          chargeKey: realPaymentId ?? String(data.id), planType: "monthly",
          amount: chargeAmount ?? MONTHLY_PRICE, paymentMethod: "subscription",
          metadata: { mp_payment_id: realPaymentId, authorized_payment_id: data.id, preapproval_id: preapprovalId, payer_email: payerEmail },
        });
        if (!credited) {
          console.log(`[MP Webhook] Charge ${data.id} already credited — skipping duplicate.`);
          return res.status(200).json({ received: true, duplicate: true });
        }
        const { premiumEndsAt, receiptNumber } = credited;
        const payment = { transaction_amount: chargeAmount };

        // Send receipt email
        const userInfo = await fetchUserInfo(db, resolvedUserId);
        if (userInfo?.email) {
          await sendPaymentReceiptEmail({
            to: userInfo.email,
            name: userInfo.name ?? "Veterinário(a)",
            receiptNumber,
            planLabel: "VetRank Premium Mensal (renovação)",
            amount: payment.transaction_amount ?? MONTHLY_PRICE,
            paymentMethod: "Assinatura recorrente",
            paidAt: new Date(),
            premiumEndsAt,
          }).catch(e => console.warn("[MP Webhook] Receipt email failed:", e?.message));
        }

        console.log(`[MP Webhook] ✅ Monthly charge — user ${resolvedUserId} extended until ${premiumEndsAt.toISOString()}`);
      }
      return res.status(200).json({ received: true, extended: true });
    }

    // ── RECLAMAÇÃO (buyer dispute) ────────────────────────────────────────────
    if (type === "claim") {
      await logEvent(db, {
        type, action, status: action,
        externalId: String(data.id),
        raw: req.body,
      });
      console.log(`[MP Webhook] ⚠️ Claim ${data.id} — action: ${action} — requires admin review`);
      return res.status(200).json({ received: true });
    }

    // ── CONTESTAÇÃO (chargeback) ──────────────────────────────────────────────
    if (type === "chargebacks") {
      await logEvent(db, {
        type, action, status: action,
        externalId: String(data.id),
        raw: req.body,
      });
      // Flag payment as disputed
      await db.update(payments).set({
        status: "cancelled",
        notes: `Chargeback aberto em ${new Date().toISOString()}`,
      }).where(eq(payments.externalId, String(data.id))).catch(() => {});
      console.log(`[MP Webhook] 🚨 Chargeback ${data.id} — flagged in payments table`);
      return res.status(200).json({ received: true });
    }

    // ── ALERTA DE FRAUDE ──────────────────────────────────────────────────────
    if (type === "fraud_alert") {
      await logEvent(db, {
        type, action, status: "fraud",
        externalId: String(data.id),
        raw: req.body,
      });
      console.log(`[MP Webhook] 🚨 Fraud alert ${data.id} — logged for admin review`);
      return res.status(200).json({ received: true });
    }

    // ── PERFIL DE PAGO / MERCHANT ORDER (informativo) ─────────────────────────
    if (type === "merchant_order") {
      await logEvent(db, { type, action, externalId: String(data.id), raw: req.body });
      return res.status(200).json({ received: true });
    }

    // ── Qualquer outro evento — log e ignora ──────────────────────────────────
    await logEvent(db, { type, action, externalId: String(data.id), raw: req.body });
    return res.status(200).json({ received: true });

  } catch (error) {
    console.error("[MP Webhook] Error:", error);
    return res.status(500).json({ error: "Internal error" });
  }
}
