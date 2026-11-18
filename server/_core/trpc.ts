import { NOT_ADMIN_ERR_MSG, UNAUTHED_ERR_MSG } from '@shared/const';
import { initTRPC, TRPCError } from "@trpc/server";
import superjson from "superjson";
import type { TrpcContext } from "./context";

const t = initTRPC.context<TrpcContext>().create({
  transformer: superjson,
});

export const router = t.router;
export const publicProcedure = t.procedure;

const requireUser = t.middleware(async opts => {
  const { ctx, next } = opts;

  if (!ctx.user) {
    throw new TRPCError({ code: "UNAUTHORIZED", message: UNAUTHED_ERR_MSG });
  }

  const u = ctx.user;
  // Dev/QA convenience: auto-upgrade a small, exact allowlist of known
  // accounts to enterprise. Must be an exact match — a substring match
  // (e.g. on "admin") would silently grant free enterprise access to any
  // real signup whose email happens to contain that text.
  const AUTO_ENTERPRISE_EMAILS = new Set([
    "ascalefi@gmail.com",
    "calefi@csvet.com.br",
    "teste@example.com",
  ]);
  if (u.email && AUTO_ENTERPRISE_EMAILS.has(u.email.toLowerCase())) {
    if (u.plan !== "enterprise") {
      u.plan = "enterprise";
      try {
        const { getDb } = await import("../db");
        const { users } = await import("../db");
        const { eq } = await import("drizzle-orm");
        const db = await getDb();
        if (db) {
          db.update(users)
            .set({ plan: "enterprise", updatedAt: new Date() })
            .where(eq(users.id, u.id))
            .then(() => {
              console.log(`[Auto-Upgrade] Plan updated to enterprise for ${u.email}`);
            })
            .catch((err: any) => {
              console.error("[Auto-Upgrade] Failed to upgrade user plan:", err);
            });
        }
      } catch (err) {
        console.error("[Auto-Upgrade] Database module load error:", err);
      }
    }
  }

  return next({
    ctx: {
      ...ctx,
      user: u,
    },
  });
});

export const protectedProcedure = t.procedure.use(requireUser);

export const adminProcedure = t.procedure.use(
  t.middleware(async opts => {
    const { ctx, next } = opts;

    if (!ctx.user || ctx.user.role !== 'admin') {
      throw new TRPCError({ code: "FORBIDDEN", message: NOT_ADMIN_ERR_MSG });
    }

    return next({
      ctx: {
        ...ctx,
        user: ctx.user,
      },
    });
  }),
);
