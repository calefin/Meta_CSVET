import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb, users, subscriptions, userStorageQuotas, billingProfiles } from "../db";
import { eq, desc } from "drizzle-orm";
import { MercadoPagoConfig, PreApproval } from "mercadopago";
import { PLAN_PRICES, PLAN_LIMITS, PLAN_NAMES } from "@shared/plans";
import type { PlanKey } from "@shared/plans";

function getMPClient() {
  const token = process.env.MERCADOPAGO_ACCESS_TOKEN;
  if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MercadoPago não configurado neste ambiente." });
  return new MercadoPagoConfig({ accessToken: token });
}

export async function applyPlanToUser(db: any, userId: number, planKey: PlanKey) {
  const limits = PLAN_LIMITS[planKey];
  await db.update(users).set({ plan: planKey, updatedAt: new Date() }).where(eq(users.id, userId));
  if (limits.storageMB > 0) {
    const existing = await db.select().from(userStorageQuotas).where(eq(userStorageQuotas.userId, userId)).limit(1);
    if (existing.length) {
      await db.update(userStorageQuotas).set({ quotaLimitMB: limits.storageMB }).where(eq(userStorageQuotas.userId, userId));
    }
  }
}

const PayerSchema = z.object({
  fullName: z.string().min(3, "Nome completo obrigatório"),
  cpf: z.string().regex(/^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$/, "CPF inválido"),
  phone: z.string().min(10, "Telefone obrigatório"),
  country: z.string().length(2).default("BR"),
});

export const billingRouter = router({

  // ── Billing profile ────────────────────────────────────────────────────────

  getBillingProfile: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return null;
    const rows = await db.select().from(billingProfiles).where(eq(billingProfiles.userId, ctx.user.id)).limit(1);
    return rows[0] ?? null;
  }),

  upsertBillingProfile: protectedProcedure
    .input(PayerSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });
      const existing = await db.select({ id: billingProfiles.id }).from(billingProfiles)
        .where(eq(billingProfiles.userId, ctx.user.id)).limit(1);
      const cpfClean = input.cpf.replace(/\D/g, "");
      if (existing.length) {
        await db.update(billingProfiles)
          .set({ fullName: input.fullName, cpf: cpfClean, phone: input.phone, country: input.country, updatedAt: new Date() })
          .where(eq(billingProfiles.userId, ctx.user.id));
      } else {
        await db.insert(billingProfiles).values({
          userId: ctx.user.id, fullName: input.fullName, cpf: cpfClean,
          phone: input.phone, country: input.country,
        });
      }
      return { success: true };
    }),

  // ── Subscription ───────────────────────────────────────────────────────────

  getSubscription: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });
    const userRow = await db.select({ plan: users.plan }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
    const plan: PlanKey = userRow[0]?.plan ?? "free";
    const subRows = await db.select().from(subscriptions)
      .where(eq(subscriptions.userId, ctx.user.id))
      .orderBy(desc(subscriptions.createdAt))
      .limit(1);
    return { plan, subscription: subRows[0] ?? null };
  }),

  createCheckoutUrl: protectedProcedure
    .input(z.object({
      planKey: z.enum(["basic", "pro", "enterprise"]),
      payer: PayerSchema,
    }))
    .mutation(async ({ ctx, input }) => {
      const client = getMPClient();
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });

      // Save/update billing profile
      const cpfClean = input.payer.cpf.replace(/\D/g, "");
      const existing = await db.select({ id: billingProfiles.id }).from(billingProfiles)
        .where(eq(billingProfiles.userId, ctx.user.id)).limit(1);
      if (existing.length) {
        await db.update(billingProfiles)
          .set({ fullName: input.payer.fullName, cpf: cpfClean, phone: input.payer.phone, country: input.payer.country, updatedAt: new Date() })
          .where(eq(billingProfiles.userId, ctx.user.id));
      } else {
        await db.insert(billingProfiles).values({
          userId: ctx.user.id, fullName: input.payer.fullName, cpf: cpfClean,
          phone: input.payer.phone, country: input.payer.country,
        });
      }

      const siteBase = process.env.SITE_BASE ?? "http://localhost:3000";
      const price = PLAN_PRICES[input.planKey];
      const nameParts = input.payer.fullName.trim().split(" ");
      const firstName = nameParts[0];
      const lastName  = nameParts.slice(1).join(" ") || firstName;

      const preApproval = new PreApproval(client);
      const result = await preApproval.create({
        body: {
          reason: `Prof-Aid — Plano ${PLAN_NAMES[input.planKey]}`,
          auto_recurring: {
            frequency: 1,
            frequency_type: "months",
            transaction_amount: price,
            currency_id: "BRL",
          },
          payer_email: ctx.user.email!,
          back_url: `${siteBase}/billing`,
          external_reference: `${ctx.user.id}:${input.planKey}`,
          status: "pending",
          // Pass payer identity for MP's anti-fraud and pre-fill
          payer: {
            name:    firstName,
            surname: lastName,
            email:   ctx.user.email,
            identification: { type: "CPF", number: cpfClean },
            phone: {
              area_code: input.payer.phone.replace(/\D/g, "").slice(0, 2),
              number:    input.payer.phone.replace(/\D/g, "").slice(2),
            },
          },
        } as any,
      });

      if (!result.id || !result.init_point) {
        throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Falha ao criar link de pagamento." });
      }

      await db.insert(subscriptions).values({
        userId: ctx.user.id,
        planKey: input.planKey,
        mpPreapprovalId: result.id,
        status: "pending",
      });

      return { checkoutUrl: result.init_point };
    }),

  cancelSubscription: protectedProcedure.mutation(async ({ ctx }) => {
    const client = getMPClient();
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });
    const subRows = await db.select().from(subscriptions)
      .where(eq(subscriptions.userId, ctx.user.id))
      .orderBy(desc(subscriptions.createdAt)).limit(10);
    const activeSub = (subRows as any[]).find((s: any) => s.status === "authorized");
    if (!activeSub) throw new TRPCError({ code: "NOT_FOUND", message: "Nenhuma assinatura ativa encontrada." });
    const preApproval = new PreApproval(client);
    await preApproval.update({ id: activeSub.mpPreapprovalId, body: { status: "cancelled" } as any });
    await db.update(subscriptions)
      .set({ status: "cancelled", cancelledAt: new Date(), updatedAt: new Date() })
      .where(eq(subscriptions.id, activeSub.id));
    await applyPlanToUser(db, ctx.user.id, "free");
    return { success: true };
  }),

  // ── Admin ──────────────────────────────────────────────────────────────────

  adminCancelSubscription: protectedProcedure
    .input(z.object({ subscriptionId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      if (ctx.user.role !== "admin") throw new TRPCError({ code: "FORBIDDEN", message: "Unauthorized" });
      const client = getMPClient();
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });
      const subRows = await db.select().from(subscriptions).where(eq(subscriptions.id, input.subscriptionId)).limit(1);
      if (!subRows.length) throw new TRPCError({ code: "NOT_FOUND", message: "Assinatura não encontrada." });
      const sub = subRows[0] as any;
      const preApproval = new PreApproval(client);
      await preApproval.update({ id: sub.mpPreapprovalId, body: { status: "cancelled" } as any });
      await db.update(subscriptions)
        .set({ status: "cancelled", cancelledAt: new Date(), updatedAt: new Date() })
        .where(eq(subscriptions.id, sub.id));
      await applyPlanToUser(db, sub.userId, "free");
      return { success: true };
    }),

  adminStats: protectedProcedure.query(async ({ ctx }) => {
    if (ctx.user.role !== "admin") throw new TRPCError({ code: "FORBIDDEN", message: "Unauthorized" });
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB not available" });

    const allSubs  = await db.select().from(subscriptions);
    const allUsers = await db.select({ id: users.id, plan: users.plan, email: users.email, name: users.name }).from(users);

    const activeSubs = (allSubs as any[]).filter((s: any) => s.status === "authorized");
    const mrr = activeSubs.reduce((sum: number, s: any) => sum + (PLAN_PRICES[s.planKey as keyof typeof PLAN_PRICES] ?? 0), 0);
    const arr = mrr * 12;

    // Join recent subs with user info
    const userMap = Object.fromEntries((allUsers as any[]).map((u: any) => [u.id, u]));
    const recentSubs = (allSubs as any[])
      .sort((a: any, b: any) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
      .slice(0, 20)
      .map((s: any) => ({
        ...s,
        userEmail: userMap[s.userId]?.email ?? "—",
        userName:  userMap[s.userId]?.name  ?? "—",
      }));

    return {
      mrr,
      arr,
      activeSubscriptions: activeSubs.length,
      byPlan: {
        basic:      activeSubs.filter((s: any) => s.planKey === "basic").length,
        pro:        activeSubs.filter((s: any) => s.planKey === "pro").length,
        enterprise: activeSubs.filter((s: any) => s.planKey === "enterprise").length,
      },
      usersByPlan: {
        free:       (allUsers as any[]).filter((u: any) => u.plan === "free" || !u.plan).length,
        basic:      (allUsers as any[]).filter((u: any) => u.plan === "basic").length,
        pro:        (allUsers as any[]).filter((u: any) => u.plan === "pro").length,
        enterprise: (allUsers as any[]).filter((u: any) => u.plan === "enterprise").length,
      },
      recentSubs,
    };
  }),
});
