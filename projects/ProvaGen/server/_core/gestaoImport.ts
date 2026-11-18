import { and, eq } from "drizzle-orm";
import { getDb } from "../db";
import { gestaoAdviseeAccounts, gestaoAdvisees, gestaoClassGroups } from "../../drizzle/gestaoSchema";
import { classes, students } from "../../drizzle/schema";
import { assignStudentToClass, createClass } from "../db";

/**
 * Importação de turma/aluno do módulo de gestão pro ProvaGen — a resposta
 * pra "como a turma da gestão vira turma pra gerar prova" (ver plano de
 * integração). Owner na gestão = a própria linha de `users` do ProvaGen, o
 * que significa que ownerUserId aqui é sempre ctx.user.id de quem chama —
 * não existe um mapeamento à parte entre os dois sistemas.
 */

export type GestaoClassSummary = {
  id: number;
  name: string;
  semester: string;
  studentCount: number;
};

export async function listGestaoClasses(ownerUserId: number): Promise<GestaoClassSummary[]> {
  const db = await getDb();
  if (!db) return [];

  const groups = await db
    .select()
    .from(gestaoClassGroups)
    .where(and(eq(gestaoClassGroups.ownerUserId, ownerUserId), eq(gestaoClassGroups.active, true)));

  const withCounts: GestaoClassSummary[] = [];
  for (const g of groups) {
    const roster = await db.select().from(gestaoAdvisees).where(eq(gestaoAdvisees.classGroupId, g.id));
    withCounts.push({ id: g.id, name: g.name, semester: g.semester, studentCount: roster.length });
  }
  return withCounts;
}

/**
 * Cria uma turma nova no ProvaGen a partir de uma turma da gestão, e
 * importa o roster como students — sem duplicar em reimportações (usa
 * students.externalRef = "gestao:advisee:{id}" pra saber quem já veio de lá).
 */
export async function importGestaoClass(ownerUserId: number, classGroupId: number, classYear: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const group = await db
    .select()
    .from(gestaoClassGroups)
    .where(and(eq(gestaoClassGroups.id, classGroupId), eq(gestaoClassGroups.ownerUserId, ownerUserId)))
    .limit(1);
  if (!group.length) throw new Error("Turma não encontrada (ou não pertence a este professor)");

  const roster = await db.select().from(gestaoAdvisees).where(eq(gestaoAdvisees.classGroupId, classGroupId));

  const semester = group[0].semester === "2" ? "2" : "1";
  const insertedClass = await createClass({
    userId: ownerUserId,
    name: group[0].name,
    semester,
    classYear,
  });
  const raw = insertedClass as any;
  const newClassId: number | null =
    raw?.insertId ?? raw?.[0]?.insertId ?? (raw?.lastInsertRowid != null ? Number(raw.lastInsertRowid) : null);
  if (!newClassId) throw new Error("Falha ao criar a turma no ProvaGen");

  let imported = 0;
  let skipped = 0;
  for (const advisee of roster) {
    const externalRef = `gestao:advisee:${advisee.id}`;

    const existing = await db
      .select()
      .from(students)
      .where(and(eq(students.userId, ownerUserId), eq(students.externalRef, externalRef)))
      .limit(1);

    let studentId: number | null = existing[0]?.id ?? null;

    if (!studentId) {
      const account = await db
        .select()
        .from(gestaoAdviseeAccounts)
        .where(eq(gestaoAdviseeAccounts.id, advisee.userId))
        .limit(1);
      const name = (account[0]?.name ?? "Aluno sem nome").trim();
      const parts = name.split(" ");
      const firstName = parts[0] || name;
      const lastName = parts.length > 1 ? parts.slice(1).join(" ") : "";

      const insertedStudent = await db.insert(students).values({
        userId: ownerUserId,
        studentId: advisee.registration ?? null,
        externalRef,
        firstName,
        lastName,
        email: account[0]?.email ?? null,
      });
      const rawStudent = insertedStudent as any;
      studentId =
        rawStudent?.insertId ?? rawStudent?.[0]?.insertId ??
        (rawStudent?.lastInsertRowid != null ? Number(rawStudent.lastInsertRowid) : null);
      imported++;
    } else {
      skipped++;
    }

    if (studentId) {
      await assignStudentToClass(studentId, newClassId);
    }
  }

  return { classId: newClassId, imported, skipped, total: roster.length };
}
