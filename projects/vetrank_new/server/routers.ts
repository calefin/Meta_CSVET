import { COOKIE_NAME } from "@shared/const";
import { getSessionCookieOptions } from "./_core/cookies";
import { systemRouter } from "./_core/systemRouter";
import { publicProcedure, router } from "./_core/trpc";
import { questionsRouter, discursiveRouter } from "./routers/questions";
import { examsRouter } from "./routers/exams";
import { gamificationRouter } from "./routers/gamification";
import { leaderboardRouter } from "./routers/leaderboard";
import { subscriptionRouter } from "./routers/subscription";
import { adminRouter } from "./routers/admin";
import { coordinatorRouter } from "./routers/coordinator";
import { teacherRouter } from "./routers/teacher";
import { announcementsRouter } from "./routers/announcements";
import { updatesRouter } from "./routers/updates";
import { concursosRouter } from "./routers/concursos";
import { flashcardsRouter } from "./routers/flashcards";
import { reportsRouter } from "./routers/reports";
import { lgpdRouter, practiceRouter } from "./routers/lgpd";
import { paymentRouter } from "./routers/payment";
import { notificationsRouter } from "./routers/notifications";
import { trailsRouter } from "./routers/trails";
import { referralsRouter } from "./routers/referrals";
import { validationRouter } from "./routers/validation";
import { leaguesRouter } from "./routers/leagues";
import { plansRouter } from "./routers/plans";
import { marketingRouter } from "./routers/marketing";
import { ticketsRouter } from "./routers/tickets";
import { userProfileRouter } from "./routers/userProfile";
import { coursesRouter } from "./routers/courses";
import { blogRouter } from "./routers/blog";
import { contentRequestsRouter } from "./routers/contentRequests";

export const appRouter = router({
  system: systemRouter,
  auth: router({
    me: publicProcedure.query(opts => opts.ctx.user),
    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return { success: true } as const;
    }),
  }),
  questions: questionsRouter,
  discursive: discursiveRouter,
  exams: examsRouter,
  gamification: gamificationRouter,
  leaderboard: leaderboardRouter,
  subscription: subscriptionRouter,
  admin: adminRouter,
  coordinator: coordinatorRouter,
  teacher: teacherRouter,
  announcements: announcementsRouter,
  updates: updatesRouter,
  concursos: concursosRouter,
  flashcards: flashcardsRouter,
  reports: reportsRouter,
  lgpd: lgpdRouter,
  practice: practiceRouter,
  payment: paymentRouter,
  notifications: notificationsRouter,
  trails: trailsRouter,
  referrals: referralsRouter,
  validation: validationRouter,
  plans: plansRouter,
  marketing: marketingRouter,
  tickets: ticketsRouter,
  userProfile: userProfileRouter,
  leagues: leaguesRouter,
  courses: coursesRouter,
  blog: blogRouter,
  contentRequests: contentRequestsRouter,
});

export type AppRouter = typeof appRouter;
