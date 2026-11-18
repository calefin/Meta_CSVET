/**
 * Plano de conteúdo: onde faltam questões x onde há procura.
 *
 * Unidade = assunto (topic). Disciplinas sem assuntos cadastrados entram como item único.
 * Sinais (janela de 90 dias):
 *  - procura da disciplina: respostas (prática + simulado) e alunos distintos na disciplina
 *    → assuntos vazios não têm respostas próprias, então herdam a procura da disciplina-mãe
 *  - pressão: respostas por questão disponível (alto = aluno esgota o banco)
 *  - pedidos de conteúdo pendentes (peso forte — é demanda explícita)
 *  - lacuna: quão longe o assunto está da meta de questões
 */
import { getDb } from "./db";

const WINDOW_DAYS = 90;
export const TOPIC_TARGET = 60;        // meta de objetivas por assunto
export const SUBJECT_TARGET = 200;     // meta para disciplina sem assuntos
export const DISCURSIVE_TARGET = 20;   // meta de discursivas por disciplina

export type PlanItem = {
  key: string;
  rank: number;
  score: number;
  disciplineId: number; disciplineName: string;
  subjectId: number; subjectName: string;
  topicId: number | null; topicName: string | null;
  supply: { objetivas: number; discursivasDisciplina: number };
  demand: { respostas90d: number; alunos90d: number; pressao: number; pedidos: number; pedidosDiscursivas: number };
  recomendado: { objetivas: number; discursivas: number };
  motivos: string[];
  requestIds: number[];
  requestNotes: string[];
};

function rows(r: unknown): any[] { return ((r as any)?.[0] ?? []) as any[]; }
const roundUp10 = (n: number) => Math.max(0, Math.ceil(n / 10) * 10);

export async function computeContentPlan(): Promise<PlanItem[]> {
  const db = await getDb();
  if (!db) return [];

  const [subjRows, topicRows, qByTopic, discBySubj, actBySubj, reqRows] = await Promise.all([
    db.execute(`
      SELECT s.id, s.namePt, s.disciplineId, d.namePt AS disciplineName
      FROM subjects s JOIN disciplines d ON d.id = s.disciplineId
      WHERE s.active = 1 AND d.active = 1`).then(rows),
    db.execute(`SELECT id, subjectId, namePt FROM topics WHERE active = 1`).then(rows),
    db.execute(`
      SELECT subjectId, topicId, COUNT(*) AS n FROM questions
      WHERE active = 1 AND status = 'approved' AND questionType <> 'discursive' AND subjectId IS NOT NULL
      GROUP BY subjectId, topicId`).then(rows),
    db.execute(`
      SELECT subjectId, COUNT(*) AS n FROM discursive_questions
      WHERE active = 1 AND subjectId IS NOT NULL GROUP BY subjectId`).then(rows),
    db.execute(`
      SELECT subjectId, SUM(n) AS respostas, COUNT(DISTINCT userId) AS alunos FROM (
        SELECT ps.subjectId, ps.userId, 1 AS n FROM practice_sessions ps
        WHERE ps.createdAt >= NOW() - INTERVAL ${WINDOW_DAYS} DAY AND ps.subjectId IS NOT NULL
        UNION ALL
        SELECT q.subjectId, e.userId, 1 AS n FROM exam_answers ea
        JOIN exams e ON e.id = ea.examId JOIN questions q ON q.id = ea.questionId
        WHERE ea.createdAt >= NOW() - INTERVAL ${WINDOW_DAYS} DAY AND q.subjectId IS NOT NULL
      ) t GROUP BY subjectId`).then(rows),
    db.execute(`
      SELECT id, subjectId, topicId, requestType, notes FROM content_requests
      WHERE status IN ('pending', 'in_progress')`).then(rows),
  ]);

  const qCount = new Map<string, number>();     // `${subjectId}:${topicId|0}`
  const qSubjectTotal = new Map<number, number>();
  for (const r of qByTopic) {
    qCount.set(`${r.subjectId}:${r.topicId ?? 0}`, Number(r.n));
    qSubjectTotal.set(Number(r.subjectId), (qSubjectTotal.get(Number(r.subjectId)) ?? 0) + Number(r.n));
  }
  const disc = new Map<number, number>(discBySubj.map(r => [Number(r.subjectId), Number(r.n)]));
  const act = new Map<number, { respostas: number; alunos: number }>(
    actBySubj.map(r => [Number(r.subjectId), { respostas: Number(r.respostas) || 0, alunos: Number(r.alunos) || 0 }]));
  const topicsBySubject = new Map<number, any[]>();
  for (const t of topicRows) {
    const list = topicsBySubject.get(Number(t.subjectId)) ?? [];
    list.push(t); topicsBySubject.set(Number(t.subjectId), list);
  }

  type Raw = Omit<PlanItem, "rank" | "score"> & { _gap: number };
  const raw: Raw[] = [];

  for (const s of subjRows) {
    const sid = Number(s.id);
    const a = act.get(sid) ?? { respostas: 0, alunos: 0 };
    const subjSupply = qSubjectTotal.get(sid) ?? 0;
    const pressao = a.respostas / Math.max(subjSupply, 10);
    const discSupply = disc.get(sid) ?? 0;
    const tops = topicsBySubject.get(sid) ?? [];
    const units = tops.length ? tops.map(t => ({ id: Number(t.id), name: String(t.namePt) })) : [{ id: null as number | null, name: null as string | null }];

    for (const u of units) {
      const supply = u.id == null ? subjSupply : (qCount.get(`${sid}:${u.id}`) ?? 0);
      const target = u.id == null ? SUBJECT_TARGET : TOPIC_TARGET;
      const reqs = reqRows.filter(r => Number(r.subjectId) === sid && (u.id == null || r.topicId == null || Number(r.topicId) === u.id));
      // Pedido sem assunto específico conta para todos os assuntos da disciplina, mas com peso menor
      const pedidos = reqs.reduce((acc, r) => acc + (r.topicId == null && u.id != null ? 0.5 : 1), 0);
      const pedidosDisc = reqs.filter(r => r.requestType === "discursive" || r.requestType === "both").length;
      const gap = Math.max(0, 1 - supply / target);

      const motivos: string[] = [];
      if (supply === 0) motivos.push("nenhuma questão cadastrada");
      else if (gap > 0) motivos.push(`${supply}/${target} questões (${Math.round(gap * 100)}% abaixo da meta)`);
      if (reqs.length) motivos.push(`${reqs.length} pedido(s) de aluno pendente(s)`);
      if (a.alunos > 0) motivos.push(`${a.alunos} aluno(s) e ${a.respostas} resposta(s) na disciplina em ${WINDOW_DAYS} dias`);
      if (pressao >= 5) motivos.push(`banco sendo esgotado: ${pressao.toFixed(1)} respostas por questão`);
      if (discSupply < DISCURSIVE_TARGET) motivos.push(`${discSupply} discursiva(s) na disciplina`);

      const recObj = gap > 0 || reqs.length ? Math.max(reqs.length ? 20 : 0, roundUp10(target - supply)) : 0;
      const recDisc = (pedidosDisc > 0 || discSupply < DISCURSIVE_TARGET) && (gap > 0 || pedidosDisc > 0)
        ? Math.min(10, Math.max(5, DISCURSIVE_TARGET - discSupply)) : 0;

      raw.push({
        key: `${sid}:${u.id ?? 0}`,
        disciplineId: Number(s.disciplineId), disciplineName: String(s.disciplineName),
        subjectId: sid, subjectName: String(s.namePt),
        topicId: u.id, topicName: u.name,
        supply: { objetivas: supply, discursivasDisciplina: discSupply },
        demand: { respostas90d: a.respostas, alunos90d: a.alunos, pressao: Number(pressao.toFixed(2)), pedidos, pedidosDiscursivas: pedidosDisc },
        recomendado: { objetivas: recObj, discursivas: recDisc },
        motivos,
        requestIds: reqs.map(r => Number(r.id)),
        requestNotes: reqs.map(r => String(r.notes ?? "").trim()).filter(Boolean).slice(0, 10),
        _gap: gap,
      });
    }
  }

  const max = (f: (r: Raw) => number) => Math.max(1, ...raw.map(f));
  const maxAlunos = max(r => Math.log1p(r.demand.alunos90d));
  const maxPressao = max(r => Math.min(r.demand.pressao, 30));
  const maxPedidos = max(r => r.demand.pedidos);

  const scored = raw.map(r => {
    const procura = Math.log1p(r.demand.alunos90d) / maxAlunos;         // 0–1
    const pressao = Math.min(r.demand.pressao, 30) / maxPressao;         // 0–1
    const pedidos = r.demand.pedidos / maxPedidos;                       // 0–1
    // Falta pesa multiplicando a procura: disciplina muito usada com assunto vazio sobe para o topo.
    const score = 100 * (0.40 * procura * r._gap + 0.20 * pressao * Math.max(r._gap, 0.3) + 0.30 * pedidos + 0.10 * r._gap);
    const { _gap, ...rest } = r;
    return { ...rest, score: Number(score.toFixed(1)) };
  }).filter(r => r.recomendado.objetivas > 0 || r.recomendado.discursivas > 0)
    .sort((a, b) => b.score - a.score);

  return scored.map((r, i) => ({ ...r, rank: i + 1 }));
}

/** Enunciados já existentes (para o gerador evitar duplicatas). */
export async function existingStems(subjectId: number, topicId: number | null, limit = 400) {
  const db = await getDb();
  if (!db) return { objetivas: [] as string[], discursivas: [] as string[] };
  const objs = rows(await db.execute(
    topicId == null
      ? `SELECT LEFT(textPt, 220) AS t FROM questions WHERE active = 1 AND subjectId = ${Number(subjectId)} ORDER BY id DESC LIMIT ${limit}`
      : `SELECT LEFT(textPt, 220) AS t FROM questions WHERE active = 1 AND subjectId = ${Number(subjectId)} AND topicId = ${Number(topicId)} ORDER BY id DESC LIMIT ${limit}`
  ));
  const discs = rows(await db.execute(
    `SELECT LEFT(textPt, 220) AS t FROM discursive_questions WHERE active = 1 AND subjectId = ${Number(subjectId)} ORDER BY id DESC LIMIT 200`
  ));
  return { objetivas: objs.map(r => String(r.t)), discursivas: discs.map(r => String(r.t)) };
}
