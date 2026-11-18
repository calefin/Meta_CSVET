import { z } from "zod";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { blogPosts } from "../../drizzle/schema";
import { eq, and, desc } from "drizzle-orm";
import fs from "fs";
import path from "path";
import { extractDOIs, verifyAllDOIs, removeInvalidDOILinks } from "../doiVerifier";

const staffProcedure = protectedProcedure.use(({ ctx, next }) => {
  const allowed = ["teacher", "coordinator", "superuser", "admin"];
  if (!allowed.includes(ctx.user.role)) {
    throw new TRPCError({ code: "FORBIDDEN" });
  }
  return next({ ctx });
});

const adminProcedure = protectedProcedure.use(({ ctx, next }) => {
  if (ctx.user.role !== "admin") throw new TRPCError({ code: "FORBIDDEN" });
  return next({ ctx });
});

export const blogRouter = router({
  // ── Public: list active blog posts ────────────────────────────────────────
  list: publicProcedure
    .input(z.object({
      limit: z.number().min(1).max(50).default(12),
      offset: z.number().default(0),
      category: z.string().optional(),
    }))
    .query(async ({ input }) => {
      let results: any[] = [];
      const db = await getDb();

      if (db) {
        try {
          const conditions = [eq(blogPosts.active, true)];
          if (input.category) {
            conditions.push(eq(blogPosts.category, input.category));
          }
          results = await db
            .select({
              id: blogPosts.id,
              slug: blogPosts.slug,
              title: blogPosts.title,
              summary: blogPosts.summary,
              author: blogPosts.author,
              category: blogPosts.category,
              coverImageUrl: blogPosts.coverImageUrl,
              createdAt: blogPosts.createdAt,
              updatedAt: blogPosts.updatedAt,
            })
            .from(blogPosts)
            .where(and(...conditions))
            .orderBy(desc(blogPosts.createdAt))
            .limit(input.limit)
            .offset(input.offset);
        } catch (dbErr) {
          console.error("DB blog list failed, trying fallback:", dbErr);
        }
      }

      // Fallback to local JSON seed file if DB is empty or fails
      if (results.length === 0) {
        try {
          const seedFilePath = path.resolve(import.meta.dirname, "../../data/blog_posts_seed.json");
          if (fs.existsSync(seedFilePath)) {
            const localPosts = JSON.parse(fs.readFileSync(seedFilePath, "utf8"));
            let filtered = localPosts.filter((p: any) => p.active);
            if (input.category) {
              filtered = filtered.filter((p: any) => p.category === input.category);
            }
            results = filtered.slice(input.offset, input.offset + input.limit);
          }
        } catch (localErr) {
          console.error("Error reading local blog seed fallback:", localErr);
        }
      }

      return results;
    }),

  // ── Public: get a single blog post by its slug ─────────────────────────────
  getBySlug: publicProcedure
    .input(z.object({
      slug: z.string().min(1),
    }))
    .query(async ({ input }) => {
      let post: any = null;
      const db = await getDb();

      if (db) {
        try {
          const results = await db
            .select()
            .from(blogPosts)
            .where(and(eq(blogPosts.slug, input.slug), eq(blogPosts.active, true)))
            .limit(1);
          if (results.length > 0) {
            post = results[0];
          }
        } catch (dbErr) {
          console.error("DB blog getBySlug failed, trying fallback:", dbErr);
        }
      }

      // Fallback to local JSON seed file if DB is empty or fails
      if (!post) {
        try {
          const seedFilePath = path.resolve(import.meta.dirname, "../../data/blog_posts_seed.json");
          if (fs.existsSync(seedFilePath)) {
            const localPosts = JSON.parse(fs.readFileSync(seedFilePath, "utf8"));
            post = localPosts.find((p: any) => p.slug === input.slug && p.active);
          }
        } catch (localErr) {
          console.error("Error reading local blog seed fallback:", localErr);
        }
      }

      if (!post) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Artigo não encontrado." });
      }
      return post;
    }),

  // ── Staff: create a blog post ──────────────────────────────────────────────
  create: staffProcedure
    .input(z.object({
      slug: z.string().min(1).max(128),
      title: z.string().min(3).max(256),
      body: z.string().min(10),
      summary: z.string().min(10),
      category: z.string().max(64).default("scientific"),
      author: z.string().max(256).default("Equipe VetRank"),
      coverImageUrl: z.string().url().max(512).optional().nullable(),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      try {
        await db.insert(blogPosts).values({
          slug: input.slug.toLowerCase().trim().replace(/\s+/g, "-"),
          title: input.title,
          body: input.body,
          summary: input.summary,
          category: input.category,
          author: input.author,
          coverImageUrl: input.coverImageUrl || null,
        });
        return { success: true };
      } catch (err: any) {
        if (err.code === "ER_DUP_ENTRY") {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Já existe um artigo com este link (slug). Escolha outro título ou link.",
          });
        }
        throw err;
      }
    }),

  // ── Staff: update a blog post ──────────────────────────────────────────────
  update: staffProcedure
    .input(z.object({
      id: z.number(),
      slug: z.string().min(1).max(128).optional(),
      title: z.string().min(3).max(256).optional(),
      body: z.string().min(10).optional(),
      summary: z.string().min(10).optional(),
      category: z.string().max(64).optional(),
      author: z.string().max(256).optional(),
      coverImageUrl: z.string().url().max(512).optional().nullable(),
      active: z.boolean().optional(),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const { id, ...updateData } = input;
      if (updateData.slug) {
        updateData.slug = updateData.slug.toLowerCase().trim().replace(/\s+/g, "-");
      }

      await db
        .update(blogPosts)
        .set(updateData as any)
        .where(eq(blogPosts.id, id));

      return { success: true };
    }),

  // ── Staff: delete a blog post (soft delete) ─────────────────────────────────
  delete: staffProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db
        .update(blogPosts)
        .set({ active: false })
        .where(eq(blogPosts.id, input.id));
      return { success: true };
    }),

  // ── Admin: verify DOI references in a single post ────────────────────────────
  verifyReferences: adminProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const [post] = await db
        .select({ id: blogPosts.id, title: blogPosts.title, body: blogPosts.body })
        .from(blogPosts)
        .where(eq(blogPosts.id, input.id))
        .limit(1);
      if (!post) throw new TRPCError({ code: "NOT_FOUND" });

      const dois = extractDOIs(post.body);
      if (dois.length === 0) return { postId: input.id, title: post.title, total: 0, valid: [], notFound: [], errors: [] };

      const results = await verifyAllDOIs(dois);
      return {
        postId: input.id,
        title: post.title,
        total: dois.length,
        valid: results.filter(r => r.status === "valid").map(r => ({ doi: r.doi, title: r.title })),
        notFound: results.filter(r => r.status === "not_found").map(r => r.doi),
        errors: results.filter(r => r.status === "error").map(r => r.doi),
      };
    }),

  // ── Admin: remove invalid DOIs from a post body ───────────────────────────
  fixInvalidDOIs: adminProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const [post] = await db
        .select({ id: blogPosts.id, title: blogPosts.title, body: blogPosts.body })
        .from(blogPosts)
        .where(eq(blogPosts.id, input.id))
        .limit(1);
      if (!post) throw new TRPCError({ code: "NOT_FOUND" });

      const dois = extractDOIs(post.body);
      if (dois.length === 0) return { fixed: 0, message: "Nenhum DOI encontrado neste artigo." };

      const results = await verifyAllDOIs(dois);
      const invalidDois = results.filter(r => r.status === "not_found").map(r => r.doi);
      if (invalidDois.length === 0) return { fixed: 0, message: `Todos os ${dois.length} DOIs verificados são válidos.` };

      const cleanedBody = removeInvalidDOILinks(post.body, invalidDois);
      await db.update(blogPosts).set({ body: cleanedBody }).where(eq(blogPosts.id, input.id));

      return { fixed: invalidDois.length, invalidDois, message: `${invalidDois.length} referência(s) inválida(s) removida(s).` };
    }),

  // ── Admin: scan ALL posts for invalid DOIs ────────────────────────────────
  verifyAllPosts: adminProcedure
    .mutation(async () => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const posts = await db
        .select({ id: blogPosts.id, title: blogPosts.title, body: blogPosts.body })
        .from(blogPosts)
        .where(eq(blogPosts.active, true));

      const report: { postId: number; title: string; fixed: number; invalidDois: string[] }[] = [];

      for (const post of posts) {
        const dois = extractDOIs(post.body);
        if (dois.length === 0) continue;

        const results = await verifyAllDOIs(dois);
        const invalidDois = results.filter(r => r.status === "not_found").map(r => r.doi);
        if (invalidDois.length === 0) continue;

        const cleanedBody = removeInvalidDOILinks(post.body, invalidDois);
        await db.update(blogPosts).set({ body: cleanedBody }).where(eq(blogPosts.id, post.id));
        report.push({ postId: post.id, title: post.title, fixed: invalidDois.length, invalidDois });
      }

      return { postsScanned: posts.length, postsFixed: report.length, report };
    }),

  // ── Staff: list all blog posts (including inactive drafts) ──────────────────
  listAll: staffProcedure
    .input(z.object({
      limit: z.number().min(1).max(50).default(50),
      offset: z.number().default(0),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      return await db
        .select()
        .from(blogPosts)
        .orderBy(desc(blogPosts.createdAt))
        .limit(input.limit)
        .offset(input.offset);
    }),
});
