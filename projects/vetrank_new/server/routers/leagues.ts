import { TRPCError } from "@trpc/server";
import { and, desc, eq, sql } from "drizzle-orm";
import { z } from "zod";
import { leagues, leagueMemberships, users } from "../../drizzle/schema";
import { getDb, addCoins } from "../db";
import { protectedProcedure, router } from "../_core/trpc";

export type Division = "iron" | "bronze" | "silver" | "gold" | "platinum" | "diamond" | "master";

const DIVISION_ORDER: Division[] = ["iron", "bronze", "silver", "gold", "platinum", "diamond", "master"];
const DIVISION_LABELS: Record<Division, { pt: string; icon: string; color: string }> = {
  iron:     { pt: "Ferro",    icon: "⛓️",  color: "#9ca3af" },
  bronze:   { pt: "Bronze",   icon: "🥉",  color: "#cd7f32" },
  silver:   { pt: "Prata",    icon: "🥈",  color: "#94a3b8" },
  gold:     { pt: "Ouro",     icon: "🥇",  color: "#f59e0b" },
  platinum: { pt: "Platina",  icon: "💠",  color: "#38bdf8" },
  diamond:  { pt: "Diamante", icon: "💎",  color: "#67e8f9" },
  master:   { pt: "Mestre",   icon: "👑",  color: "#a855f7" },
};

function getWeekKey(date = new Date()): string {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
  const week1 = new Date(d.getFullYear(), 0, 4);
  const weekNum = 1 + Math.round(((d.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7);
  return `${d.getFullYear()}-${String(weekNum).padStart(2, "0")}`;
}

// Calcula divisão do usuário baseado em XP total histórico
function divisionForXp(xp: number): Division {
  if (xp >= 15000) return "master";
  if (xp >= 9000)  return "diamond";
  if (xp >= 5500)  return "platinum";
  if (xp >= 3000)  return "gold";
  if (xp >= 1500)  return "silver";
  if (xp >= 700)   return "bronze";
  return "iron";
}

// Garante que o usuário está em uma liga ativa na semana atual
async function ensureLeagueMembership(userId: number, userXp: number): Promise<{
  leagueId: number; division: Division; groupIndex: number;
}> {
  const db = await getDb();
  if (!db) throw new Error("DB unavailable");

  const weekKey = getWeekKey();
  const division = divisionForXp(userXp);

  // Já está em liga esta semana?
  const [existing] = await db
    .select({ leagueId: leagueMemberships.leagueId, division: leagueMemberships.division, groupIndex: leagues.groupIndex })
    .from(leagueMemberships)
    .innerJoin(leagues, eq(leagueMemberships.leagueId, leagues.id))
    .where(and(eq(leagueMemberships.userId, userId), eq(leagues.weekKey, weekKey), eq(leagues.status, "active")))
    .limit(1);

  if (existing) return { leagueId: existing.leagueId, division: existing.division as Division, groupIndex: existing.groupIndex };

  // Encontra grupo com vaga (<30 membros) na mesma divisão desta semana
  const groupsThisWeek = await db
    .select({ leagueId: leagues.id, groupIndex: leagues.groupIndex, memberCount: sql<number>`count(${leagueMemberships.id})` })
    .from(leagues)
    .leftJoin(leagueMemberships, eq(leagueMemberships.leagueId, leagues.id))
    .where(and(eq(leagues.weekKey, weekKey), eq(leagues.division, division), eq(leagues.status, "active")))
    .groupBy(leagues.id, leagues.groupIndex)
    .having(sql`count(${leagueMemberships.id}) < 30`)
    .limit(1);

  let leagueId: number;
  let groupIndex: number;

  if (groupsThisWeek.length > 0 && groupsThisWeek[0]) {
    leagueId = groupsThisWeek[0].leagueId;
    groupIndex = groupsThisWeek[0].groupIndex;
  } else {
    // Cria novo grupo
    const lastGroup = await db
      .select({ groupIndex: leagues.groupIndex })
      .from(leagues)
      .where(and(eq(leagues.weekKey, weekKey), eq(leagues.division, division)))
      .orderBy(desc(leagues.groupIndex))
      .limit(1);
    groupIndex = (lastGroup[0]?.groupIndex ?? -1) + 1;
    const [result] = await db.insert(leagues).values({ weekKey, division, groupIndex, status: "active" });
    leagueId = (result as any).insertId;
  }

  await db.insert(leagueMemberships).values({ userId, leagueId, division, xpGained: 0, result: "none" });
  return { leagueId, division, groupIndex };
}

export const leaguesRouter = router({
  myLeague: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const user = await db.select({ xp: users.xp, name: users.name, avatarUrl: users.avatarUrl })
      .from(users).where(eq(users.id, ctx.user.id)).limit(1);
    if (!user[0]) throw new TRPCError({ code: "NOT_FOUND" });

    const { leagueId, division } = await ensureLeagueMembership(ctx.user.id, user[0].xp);

    // Busca todos os membros do grupo com XP semanal
    const weekKey = getWeekKey();
    const members = await db
      .select({
        userId: leagueMemberships.userId,
        xpGained: leagueMemberships.xpGained,
        name: users.name,
        avatarUrl: users.avatarUrl,
        level: users.level,
      })
      .from(leagueMemberships)
      .innerJoin(users, eq(users.id, leagueMemberships.userId))
      .where(eq(leagueMemberships.leagueId, leagueId))
      .orderBy(desc(leagueMemberships.xpGained));

    const ranked = members.map((m, i) => ({ ...m, rank: i + 1, isMe: m.userId === ctx.user.id }));
    const myRank = ranked.find(m => m.isMe);
    const totalMembers = ranked.length;
    const promotionZone = Math.ceil(totalMembers * 0.25);
    const relegationZone = Math.floor(totalMembers * 0.75);

    // Calcula fim da semana (domingo 23:59:59)
    const now = new Date();
    const dayOfWeek = now.getDay(); // 0=dom, 1=seg...
    const daysUntilSunday = dayOfWeek === 0 ? 0 : 7 - dayOfWeek;
    const endOfWeek = new Date(now);
    endOfWeek.setDate(now.getDate() + daysUntilSunday);
    endOfWeek.setHours(23, 59, 59, 0);

    return {
      division,
      divisionLabel: DIVISION_LABELS[division],
      weekKey,
      endOfWeek: endOfWeek.toISOString(),
      members: ranked,
      myRank: myRank?.rank ?? null,
      promotionZone,
      relegationZone,
      totalMembers,
    };
  }),

  history: protectedProcedure
    .input(z.object({ limit: z.number().default(10) }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const rows = await db
        .select({
          weekKey: leagues.weekKey,
          division: leagueMemberships.division,
          xpGained: leagueMemberships.xpGained,
          rank: leagueMemberships.rank,
          result: leagueMemberships.result,
        })
        .from(leagueMemberships)
        .innerJoin(leagues, eq(leagueMemberships.leagueId, leagues.id))
        .where(and(eq(leagueMemberships.userId, ctx.user.id), eq(leagues.status, "completed")))
        .orderBy(desc(leagues.weekKey))
        .limit(input.limit);

      return rows.map(r => ({
        ...r,
        divisionLabel: DIVISION_LABELS[r.division as Division],
      }));
    }),

  // Endpoint chamado pelo job semanal para fechar ligas e aplicar promoção/rebaixamento
  closeWeeklyLeagues: protectedProcedure.mutation(async ({ ctx }) => {
    if (ctx.user.role !== "admin") throw new TRPCError({ code: "FORBIDDEN" });

    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const weekKey = getWeekKey(new Date(Date.now() - 7 * 86400000)); // semana passada
    const activeLeagues = await db.select().from(leagues)
      .where(and(eq(leagues.weekKey, weekKey), eq(leagues.status, "active")));

    for (const league of activeLeagues) {
      const members = await db
        .select({ id: leagueMemberships.id, userId: leagueMemberships.userId, xpGained: leagueMemberships.xpGained })
        .from(leagueMemberships)
        .where(eq(leagueMemberships.leagueId, league.id))
        .orderBy(desc(leagueMemberships.xpGained));

      const total = members.length;
      const promotionCutoff = Math.ceil(total * 0.25);
      const relegationCutoff = Math.floor(total * 0.75);

      for (let i = 0; i < members.length; i++) {
        const member = members[i]!;
        const rank = i + 1;
        let result: "promoted" | "stayed" | "relegated" = "stayed";
        let coinsReward = 0;

        const currentDiv = league.division as Division;
        const divIdx = DIVISION_ORDER.indexOf(currentDiv);

        if (rank <= promotionCutoff && divIdx < DIVISION_ORDER.length - 1) {
          result = "promoted";
          coinsReward = rank === 1 ? 200 : 100;
        } else if (rank > relegationCutoff && currentDiv !== "iron") {
          result = "relegated";
        } else {
          coinsReward = 30; // recompensa por participar
        }

        await db.update(leagueMemberships)
          .set({ rank, result })
          .where(eq(leagueMemberships.id, member.id));

        if (coinsReward > 0) {
          await addCoins(member.userId, coinsReward, `Liga ${DIVISION_LABELS[currentDiv].pt} — semana ${weekKey}`);
        }
      }

      await db.update(leagues).set({ status: "completed" }).where(eq(leagues.id, league.id));
    }

    return { closed: activeLeagues.length };
  }),

  // Atualiza XP semanal do usuário na liga (chamado após ganhar XP)
  syncLeagueXp: protectedProcedure
    .input(z.object({ xpGained: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return;

      const weekKey = getWeekKey();
      await db.execute(sql`
        UPDATE league_memberships lm
        INNER JOIN leagues l ON lm.leagueId = l.id
        SET lm.xpGained = lm.xpGained + ${input.xpGained}
        WHERE lm.userId = ${ctx.user.id} AND l.weekKey = ${weekKey} AND l.status = 'active'
      `);
    }),
});
