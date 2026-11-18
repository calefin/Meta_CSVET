import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure, publicProcedure } from "../_core/trpc";
import { getDb } from "../db";
import { leads, emailCampaigns, users } from "../../drizzle/schema";
import { eq, desc, like, or, and, inArray } from "drizzle-orm";
import { sendNewsletterEmail } from "../email";
import fs from "fs";
import path from "path";

function marketingAccess(role: string | null | undefined) {
  if (role !== "admin" && role !== "coordinator" && role !== "superuser") {
    throw new TRPCError({ code: "FORBIDDEN", message: "Acesso negado" });
  }
}

function adminAccess(role: string | null | undefined) {
  if (role !== "admin" && role !== "superuser") {
    throw new TRPCError({ code: "FORBIDDEN", message: "Apenas admin" });
  }
}

// ─── Lead status labels ────────────────────────────────────────────────────────
export const LEAD_STATUS = ["new", "contacted", "converted", "lost"] as const;

export const marketingRouter = router({

  // ── Public: capture lead from landing page ─────────────────────────────────
  captureLead: publicProcedure
    .input(z.object({
      email: z.string().email().max(320),
      name: z.string().max(256).optional(),
      phone: z.string().max(20).optional(),
      source: z.string().max(64).default("landing"),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      // Check if already a registered user
      const existingUser = await db
        .select({ id: users.id })
        .from(users)
        .where(eq(users.email, input.email))
        .limit(1);

      if (existingUser.length > 0) {
        // Already has an account — just return OK silently
        return { ok: true };
      }

      try {
        await db.insert(leads).values({
          email: input.email,
          name: input.name || null,
          phone: input.phone || null,
          source: input.source,
          status: "new",
        });
      } catch {
        // Ignore duplicate email errors
      }
      return { ok: true };
    }),

  // ── Leads CRUD (admin + coordinator) ──────────────────────────────────────
  leads: protectedProcedure
    .input(z.object({
      page: z.number().default(1),
      limit: z.number().default(50),
      search: z.string().optional(),
      status: z.enum(["new", "contacted", "converted", "lost", "all"]).default("all"),
    }))
    .query(async ({ ctx, input }) => {
      marketingAccess(ctx.user.role);
      const db = await getDb();
      if (!db) return { leads: [], total: 0 };

      const offset = (input.page - 1) * input.limit;

      const conditions = [];
      if (input.status !== "all") {
        conditions.push(eq(leads.status, input.status));
      }
      if (input.search) {
        conditions.push(
          or(
            like(leads.email, `%${input.search}%`),
            like(leads.name, `%${input.search}%`)
          )
        );
      }

      const allLeads = await db
        .select()
        .from(leads)
        .where(conditions.length > 0 ? and(...conditions) : undefined)
        .orderBy(desc(leads.createdAt));

      const total = allLeads.length;
      const paginated = allLeads.slice(offset, offset + input.limit);

      return { leads: paginated, total };
    }),

  createLead: protectedProcedure
    .input(z.object({
      email: z.string().email().max(320),
      name: z.string().max(256).optional(),
      phone: z.string().max(20).optional(),
      notes: z.string().max(2000).optional(),
      source: z.string().max(64).default("manual"),
    }))
    .mutation(async ({ ctx, input }) => {
      marketingAccess(ctx.user.role);
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      try {
        const [result] = await db.insert(leads).values({
          email: input.email,
          name: input.name || null,
          phone: input.phone || null,
          notes: input.notes || null,
          source: input.source,
          status: "new",
        });
        return { id: (result as any).insertId };
      } catch {
        throw new TRPCError({ code: "CONFLICT", message: "Email já cadastrado como lead" });
      }
    }),

  updateLead: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().max(256).optional(),
      phone: z.string().max(20).optional(),
      status: z.enum(["new", "contacted", "converted", "lost"]).optional(),
      notes: z.string().max(2000).optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      marketingAccess(ctx.user.role);
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const { id, ...data } = input;
      await db.update(leads).set(data as any).where(eq(leads.id, id));
      return { ok: true };
    }),

  deleteLead: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      adminAccess(ctx.user.role);
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db.delete(leads).where(eq(leads.id, input.id));
      return { ok: true };
    }),

  importLeads: protectedProcedure
    .input(z.object({
      // CSV raw text: one "email,nome" per line
      csv: z.string().max(100_000),
      source: z.string().max(64).default("import"),
    }))
    .mutation(async ({ ctx, input }) => {
      adminAccess(ctx.user.role);
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const lines = input.csv
        .split("\n")
        .map((l) => l.trim())
        .filter((l) => l && !l.startsWith("#"));

      let imported = 0;
      let skipped = 0;

      for (const line of lines) {
        const [rawEmail, ...nameParts] = line.split(",");
        const email = rawEmail?.trim().toLowerCase();
        const name = nameParts.join(",").trim() || null;
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          skipped++;
          continue;
        }
        try {
          await db.insert(leads).values({
            email,
            name,
            source: input.source,
            status: "new",
          });
          imported++;
        } catch {
          skipped++; // duplicate
        }
      }
      return { imported, skipped };
    }),

  // ── Campaigns ─────────────────────────────────────────────────────────────
  sendCampaign: protectedProcedure
    .input(z.object({
      subject: z.string().min(3).max(256),
      body: z.string().min(10).max(50_000),
      bodyHtml: z.string().max(200_000).optional(), // HTML customizado (ex: peça do Figma/editor)
      // Targets: 'all' | 'free' | 'trial' | 'premium' = registered users
      //          'leads' = only leads
      //          'leads+all' | 'leads+free' = leads + users segment
      target: z.string().min(2).max(64),
      delayMs: z.number().min(0).max(5000).default(200), // delay entre envios (ms)
    }))
    .mutation(async ({ ctx, input }) => {
      marketingAccess(ctx.user.role);
      // Coordinator can't send to leads (only to users of their platform)
      if (ctx.user.role === "coordinator" && input.target.includes("leads")) {
        throw new TRPCError({ code: "FORBIDDEN", message: "Coordenador não pode enviar emails a leads" });
      }

      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const recipients: { email: string; name: string | null }[] = [];

      // Collect users
      const userTargets = ["all", "free", "trial", "premium"];
      const userSegment = userTargets.find((t) => input.target === t || input.target.endsWith(`+${t}`));
      if (userSegment) {
        let q = "SELECT email, name FROM users WHERE email IS NOT NULL AND email != ''";
        if (userSegment === "trial") q += " AND plan = 'trial'";
        else if (userSegment === "premium") q += " AND plan = 'premium'";
        else if (userSegment === "free") q += " AND (plan IS NULL OR plan = 'free')";

        const [rows] = await (db as any).execute(q) as any;
        for (const r of rows as any[]) {
          if (r.email) recipients.push({ email: r.email, name: r.name || null });
        }
      }

      // Collect leads
      if (input.target === "leads" || input.target.startsWith("leads+")) {
        const leadRows = await db
          .select({ email: leads.email, name: leads.name })
          .from(leads)
          .where(inArray(leads.status, ["new", "contacted"]));
        for (const r of leadRows) {
          // Avoid duplicates (lead might already be in users)
          if (!recipients.find((u) => u.email === r.email)) {
            recipients.push({ email: r.email, name: r.name });
          }
        }
      }

      let sent = 0;
      let failed = 0;
      for (const r of recipients) {
        try {
          await sendNewsletterEmail(r.email, input.subject, input.body, input.bodyHtml, r.name);
          sent++;
        } catch {
          failed++;
        }
        await new Promise((res) => setTimeout(res, input.delayMs ?? 200));
      }

      // Save campaign history
      await db.insert(emailCampaigns).values({
        createdBy: ctx.user.id,
        subject: input.subject,
        body: input.bodyHtml ? `[HTML] ${input.subject}` : input.body,
        target: input.target,
        recipientCount: recipients.length,
        sentCount: sent,
        failedCount: failed,
        status: failed === recipients.length ? "failed" : failed > 0 ? "partial" : "sent",
      });

      return { sent, failed, total: recipients.length };
    }),

  // Envio para inscrito específico (por email ou userId)
  sendToSpecific: protectedProcedure
    .input(z.object({
      email: z.string().email(),
      subject: z.string().min(3).max(256),
      body: z.string().min(1).max(50_000),
      bodyHtml: z.string().max(200_000).optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      marketingAccess(ctx.user.role);
      const db = await getDb();
      let name: string | null = null;
      if (db) {
        const userRow = await db.select({ name: users.name }).from(users).where(eq(users.email, input.email)).limit(1);
        if (userRow.length > 0 && userRow[0].name) {
          name = userRow[0].name;
        } else {
          const leadRow = await db.select({ name: leads.name }).from(leads).where(eq(leads.email, input.email)).limit(1);
          if (leadRow.length > 0 && leadRow[0].name) {
            name = leadRow[0].name;
          }
        }
      }
      await sendNewsletterEmail(input.email, input.subject, input.body, input.bodyHtml, name);
      return { sent: 1 };
    }),

  campaignHistory: protectedProcedure
    .input(z.object({ limit: z.number().default(20) }))
    .query(async ({ ctx }) => {
      marketingAccess(ctx.user.role);
      const db = await getDb();
      if (!db) return [];
      return db
        .select()
        .from(emailCampaigns)
        .orderBy(desc(emailCampaigns.sentAt))
        .limit(20);
    }),

  // ── Stats para dashboard ──────────────────────────────────────────────────
  leadStats: protectedProcedure.query(async ({ ctx }) => {
    marketingAccess(ctx.user.role);
    const db = await getDb();
    if (!db) return { total: 0, new: 0, contacted: 0, converted: 0, lost: 0 };

    const [rows] = await (db as any).execute(
      "SELECT status, COUNT(*) as cnt FROM leads GROUP BY status"
    ) as any;

    const result: Record<string, number> = { total: 0, new: 0, contacted: 0, converted: 0, lost: 0 };
    for (const r of rows as any[]) {
      result[r.status] = Number(r.cnt);
      result.total += Number(r.cnt);
    }
    return result;
  }),

  // tRPC query to load marketing templates
  getTemplates: protectedProcedure
    .query(async ({ ctx }) => {
      marketingAccess(ctx.user.role);
      const templatesDir = path.join(process.cwd(), 'marketing');
      const files = [
        { name: "email-outreach.html", label: "Email de Divulgação (Outreach)" },
        { name: "proposta_institucional.html", label: "Proposta Institucional" },
      ];
      const result = [];
      for (const f of files) {
        try {
          const p = path.join(templatesDir, f.name);
          if (fs.existsSync(p)) {
            const content = fs.readFileSync(p, 'utf-8');
            result.push({ name: f.name, label: f.label, content });
          }
        } catch (e) {
          // ignore error
        }
      }
      return result;
    }),
});
