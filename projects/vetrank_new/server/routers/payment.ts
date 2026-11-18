import { z } from "zod";
import { router, protectedProcedure } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { users } from "../../drizzle/schema";
import { eq } from "drizzle-orm";
import { MercadoPagoConfig, Preference, PreApproval } from "mercadopago";

// ── Mercado Pago client ───────────────────────────────────────────────────────
function getMPClient() {
  const token = process.env.MP_ACCESS_TOKEN;
  if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_ACCESS_TOKEN not configured" });
  return new MercadoPagoConfig({ accessToken: token });
}

// ── Plan definitions ──────────────────────────────────────────────────────────
export const PLANS = {
  monthly: {
    id: "premium_monthly",
    title: "VetRank Premium — Mensal",
    description: "Acesso completo: simulados ilimitados, ranking e todos os recursos premium.",
    price: 39.90,
    currency: "BRL",
    durationDays: 30,
  },
  annual: {
    id: "premium_annual",
    title: "VetRank Premium — Anual",
    description: "Acesso completo por 12 meses. Equivalente a R$24,90/mês — 37% de desconto.",
    price: 299.00,
    currency: "BRL",
    durationDays: 365,
  },
} as const;

export type PlanKey = keyof typeof PLANS;

/**
 * Cancels a Mercado Pago preapproval unless it is already cancelled/finished.
 * Throws if MP refuses to cancel a subscription that is still able to charge — in that case we
 * must NOT go on and create a new one (that's exactly how double charges happen).
 */
export async function cancelPreapprovalIfAlive(preapproval: PreApproval, preapprovalId: string) {
  let status: string | undefined;
  try {
    const sub = await preapproval.get({ id: preapprovalId });
    status = (sub as any)?.status;
  } catch (err: any) {
    // 404 → it doesn't exist on MP anymore, nothing to cancel
    if (err?.status === 404 || err?.cause?.status === 404) return;
    console.warn(`[Payment] Could not fetch preapproval ${preapprovalId}:`, err?.message);
  }
  if (status === "cancelled" || status === "finished") return;
  try {
    await preapproval.update({ id: preapprovalId, body: { status: "cancelled" } as any });
    console.log(`[Payment] Cancelled stale preapproval ${preapprovalId} (was ${status ?? "unknown"})`);
  } catch (err: any) {
    throw new TRPCError({
      code: "INTERNAL_SERVER_ERROR",
      message: "Não foi possível encerrar sua assinatura anterior no Mercado Pago. Para evitar cobrança duplicada, contate o suporte.",
    });
  }
}

// ── Router ────────────────────────────────────────────────────────────────────
export const paymentRouter = router({
  // Save payer info (CPF, phone, name) before checkout
  savePayerInfo: protectedProcedure
    .input(z.object({
      name: z.string().min(3),
      cpf: z.string().regex(/^\d{11}$/, "CPF deve ter 11 dígitos"),
      phone: z.string().min(10),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db.update(users).set({
        name: input.name,
        cpf: input.cpf.replace(/\D/g, "").slice(0, 14),
        phone: input.phone,
      }).where(eq(users.id, ctx.user.id));
      return { success: true };
    }),

  // Create a Mercado Pago preference (checkout session)
  createPreference: protectedProcedure
    .input(z.object({
      plan: z.enum(["monthly", "annual"]),
      successUrl: z.string().url(),
      failureUrl: z.string().url(),
      pendingUrl: z.string().url(),
    }))
    .mutation(async ({ ctx, input }) => {
      const plan = PLANS[input.plan];
      const client = getMPClient();
      const preference = new Preference(client);

      // Fetch latest user data (CPF may have been saved by savePayerInfo)
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const [u] = await db.select({ name: users.name, email: users.email, cpf: users.cpf, phone: users.phone })
        .from(users).where(eq(users.id, ctx.user.id)).limit(1);

      const cpfClean = u?.cpf?.replace(/\D/g, "") ?? "";

      const result = await preference.create({
        body: {
          items: [{
            id: plan.id,
            title: plan.title,
            description: plan.description,
            quantity: 1,
            unit_price: plan.price,
            currency_id: plan.currency,
          }],
          payer: {
            email: u?.email ?? ctx.user.email ?? undefined,
            name: u?.name ?? ctx.user.name ?? undefined,
            ...(cpfClean.length === 11 ? {
              identification: { type: "CPF", number: cpfClean },
            } : {}),
            ...(u?.phone ? { phone: { number: u.phone.replace(/\D/g, "") } } : {}),
          },
          back_urls: {
            success: input.successUrl,
            failure: input.failureUrl,
            pending: input.pendingUrl,
          },
          auto_return: "approved",
          external_reference: JSON.stringify({
            userId: ctx.user.id,
            plan: input.plan,
            durationDays: plan.durationDays,
          }),
          statement_descriptor: "VETRANK",
          metadata: {
            user_id: ctx.user.id,
            plan: input.plan,
          },
        },
      });

      return {
        preferenceId: result.id,
        checkoutUrl: result.init_point,
        sandboxUrl: result.sandbox_init_point,
      };
    }),

  // Create a recurring subscription (monthly plan via MP Assinaturas plan URL)
  createSubscription: protectedProcedure
    .input(z.object({ successUrl: z.string().url() }))
    .mutation(async ({ ctx, input }) => {
      const planId = process.env.MP_PLAN_ID_MONTHLY;
      if (!planId || planId.includes(" ") || planId.startsWith("#")) {
        throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_PLAN_ID_MONTHLY não configurado. Adicione o ID do plano no .env do servidor." });
      }

      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const [u] = await db.select({
        email: users.email,
        plan: users.plan,
        subscriptionPlan: users.subscriptionPlan,
        premiumEndsAt: users.premiumEndsAt,
        mpPreapprovalId: users.mpPreapprovalId,
      }).from(users).where(eq(users.id, ctx.user.id)).limit(1);

      // Any paid access still running (monthly OR annual) blocks a new recurring subscription:
      // a monthly charge on top of an active annual plan is money taken for nothing.
      const hasActivePremium = u?.premiumEndsAt && u.premiumEndsAt > new Date()
        && (u.subscriptionPlan === "monthly" || u.subscriptionPlan === "annual");
      if (hasActivePremium) {
        throw new TRPCError({
          code: "BAD_REQUEST",
          message: u!.subscriptionPlan === "annual"
            ? "Você já tem o plano anual ativo. Não é necessário assinar o mensal."
            : "Você já tem uma assinatura mensal ativa. Cancele a atual antes de assinar novamente.",
        });
      }

      const client = getMPClient();
      const preapproval = new PreApproval(client);

      // Access expired but an old preapproval may still be alive in Mercado Pago (e.g. renewal
      // card declined and MP keeps retrying). Creating a second one would leave the user with
      // TWO recurring subscriptions → double charge as soon as the old one succeeds. Cancel it first.
      if (u?.mpPreapprovalId) {
        await cancelPreapprovalIfAlive(preapproval, u.mpPreapprovalId);
        await db.update(users).set({ mpPreapprovalId: null }).where(eq(users.id, ctx.user.id));
      }

      const extRef = JSON.stringify({
        userId: ctx.user.id,
        plan: "monthly",
        durationDays: 30,
      });

      // Call MP API to create a preapproval session.
      // We define the auto_recurring parameters manually and set status to 'pending'.
      // This allows generating a redirect URL (init_point) without requiring a card token upfront,
      // while securely binding our external_reference to the subscription.
      const result = await preapproval.create({
        body: {
          reason: "VetRank Premium — Mensal",
          payer_email: u?.email ?? ctx.user.email ?? undefined,
          back_url: input.successUrl,
          external_reference: extRef,
          status: "pending",
          auto_recurring: {
            frequency: 1,
            frequency_type: "months",
            transaction_amount: 39.90,
            currency_id: "BRL",
          },
        },
      });

      const subscriptionUrl = result.init_point;
      if (!subscriptionUrl) {
        throw new TRPCError({
          code: "INTERNAL_SERVER_ERROR",
          message: "Não foi possível gerar a URL de pagamento do Mercado Pago.",
        });
      }

      // Persist the preapproval ID so the user can cancel it later — without this,
      // cancelSubscription has no way to know which Mercado Pago subscription to cancel.
      if (result.id) {
        await db.update(users).set({ mpPreapprovalId: String(result.id) }).where(eq(users.id, ctx.user.id));
      }

      return {
        subscriptionId: result.id || null,
        subscriptionUrl,
      };
    }),

  // Cancel monthly subscription
  cancelSubscription: protectedProcedure
    .mutation(async ({ ctx }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const [u] = await db.select({ mpPreapprovalId: users.mpPreapprovalId })
        .from(users).where(eq(users.id, ctx.user.id)).limit(1);

      if (!u?.mpPreapprovalId) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Nenhuma assinatura recorrente ativa encontrada para cancelar." });
      }

      const client = getMPClient();
      const preapproval = new PreApproval(client);
      try {
        await preapproval.update({
          id: u.mpPreapprovalId,
          body: { status: "cancelled" } as any,
        });
      } catch (err: any) {
        throw new TRPCError({
          code: "INTERNAL_SERVER_ERROR",
          message: err?.message || "Não foi possível cancelar a assinatura no Mercado Pago. Tente novamente ou contate o suporte.",
        });
      }

      // Access remains valid until premiumEndsAt (already paid for); just stop future charges.
      await db.update(users).set({ mpPreapprovalId: null }).where(eq(users.id, ctx.user.id));

      return { success: true };
    }),

  // Get current user's subscription status
  myPlan: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const rows = await db.select({
      plan: users.plan,
      trialEndsAt: users.trialEndsAt,
      trialStartedAt: users.trialStartedAt,
      premiumEndsAt: users.premiumEndsAt,
      premiumStartedAt: users.premiumStartedAt,
    }).from(users).where(eq(users.id, ctx.user.id)).limit(1);

    const u = rows[0];
    if (!u) throw new TRPCError({ code: "NOT_FOUND" });

    // Admin, superuser, coordinator e teacher têm acesso vitalício
    const isStaff = ["admin", "superuser", "coordinator", "teacher"].includes(ctx.user.role);
    if (isStaff) {
      return {
        plan: u.plan,
        trialEndsAt: u.trialEndsAt,
        premiumEndsAt: u.premiumEndsAt,
        isTrialActive: false,
        isPremiumActive: true,
        hasUsedTrial: true,
        trialExpired: false,
        premiumExpired: false,
        hasAccess: true,
        isStaff: true,
      };
    }

    const now = new Date();
    const isTrialActive    = !!u.trialEndsAt && u.trialEndsAt > now;
    const isPremiumActive  = !!u.premiumEndsAt && u.premiumEndsAt > now;
    // Trial é único: qualquer trial anterior OU qualquer assinatura paga anterior conta como "usado".
    const hasUsedTrial     = !!u.trialEndsAt || !!u.trialStartedAt || !!u.premiumStartedAt;
    const trialExpired     = hasUsedTrial && !isTrialActive;
    // Já foi assinante pagante e o acesso venceu (não renovou)
    const premiumExpired   = !!u.premiumEndsAt && !isPremiumActive;
    // Acesso somente por data — plan="premium" sem data válida não conta
    const hasAccess        = isTrialActive || isPremiumActive;

    return {
      plan: u.plan,
      trialEndsAt: u.trialEndsAt,
      premiumEndsAt: u.premiumEndsAt,
      isTrialActive,
      isPremiumActive,
      hasUsedTrial,
      trialExpired,
      premiumExpired,
      hasAccess,
      isStaff: false,
    };
  }),

  // Start free trial (no payment required)
  startTrial: protectedProcedure.mutation(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const rows = await db.select({ plan: users.plan, trialEndsAt: users.trialEndsAt, trialStartedAt: users.trialStartedAt, premiumStartedAt: users.premiumStartedAt })
      .from(users).where(eq(users.id, ctx.user.id)).limit(1);
    const u = rows[0];
    if (!u) throw new TRPCError({ code: "NOT_FOUND" });

    if (u.trialEndsAt || u.trialStartedAt || u.premiumStartedAt) {
      throw new TRPCError({ code: "BAD_REQUEST", message: "O período de teste já foi utilizado. Escolha um plano Premium para reativar seu acesso." });
    }

    // 7 days — matches every UI copy ("Experimente grátis por 7 dias") and upsertUser's auto-trial.
    const trialEndsAt = new Date();
    trialEndsAt.setDate(trialEndsAt.getDate() + 7);

    await db.update(users).set({
      plan: "trial",
      trialStartedAt: new Date(),
      trialEndsAt,
    }).where(eq(users.id, ctx.user.id));

    return { success: true, trialEndsAt };
  }),
});
