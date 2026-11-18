import { router, publicProcedure, protectedProcedure } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getBlogPosts, getBlogPostById, getBlogPostBySlug,
  createBlogPost, updateBlogPost, publishBlogPost, deleteBlogPost,
} from "../db";

function assertAdmin(role: string) {
  if (role !== "admin") throw new TRPCError({ code: "FORBIDDEN", message: "Apenas administradores podem gerenciar o blog." });
}

export const blogRouter = router({
  list: publicProcedure
    .input(z.object({
      limit:         z.number().min(1).max(100).optional(),
      offset:        z.number().min(0).optional(),
      publishedOnly: z.boolean().optional(),
    }).optional())
    .query(async ({ input }) => {
      const opts = input ?? {};
      return await getBlogPosts({ limit: opts.limit, offset: opts.offset, publishedOnly: opts.publishedOnly });
    }),

  get: publicProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ input }) => getBlogPostById(input.id)),

  getBySlug: publicProcedure
    .input(z.object({ slug: z.string() }))
    .query(async ({ input }) => getBlogPostBySlug(input.slug)),

  create: protectedProcedure
    .input(z.object({
      title:    z.string().min(3),
      slug:     z.string().min(3),
      excerpt:  z.string().optional(),
      markdown: z.string().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      assertAdmin(ctx.user.role);
      await createBlogPost({
        userId:    ctx.user.id,
        title:     input.title,
        slug:      input.slug,
        excerpt:   input.excerpt ?? null,
        markdown:  input.markdown ?? null,
        html:      null,
        published: "false",
      });
      return { success: true };
    }),

  update: protectedProcedure
    .input(z.object({
      id:       z.number(),
      title:    z.string().min(3).optional(),
      slug:     z.string().min(3).optional(),
      excerpt:  z.string().optional(),
      markdown: z.string().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      assertAdmin(ctx.user.role);
      const { id, ...data } = input;
      await updateBlogPost(id, ctx.user.id, data as any);
      return { success: true };
    }),

  publish: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      assertAdmin(ctx.user.role);
      await publishBlogPost(input.id, ctx.user.id);
      return { success: true };
    }),

  unpublish: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      assertAdmin(ctx.user.role);
      await updateBlogPost(input.id, ctx.user.id, { published: "false" } as any);
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      assertAdmin(ctx.user.role);
      await deleteBlogPost(input.id, ctx.user.id);
      return { success: true };
    }),
});
