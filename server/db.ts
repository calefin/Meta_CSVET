import { eq, and, inArray, count, sql } from "drizzle-orm";
import { drizzle as drizzleMysql } from "drizzle-orm/mysql2";
import { drizzle as drizzleSqlite } from "drizzle-orm/better-sqlite3";
import mysql from "mysql2/promise";
import * as _mysqlSchema from "../drizzle/schema";
import type {
  InsertUser, InsertInstitution, InsertDepartment, InsertCourse,
  InsertClass, InsertStudent, InsertQuizResponse, InsertExamScore,
  InsertStudentGrade, InsertAnswerSheetTemplate, InsertBlogPost,
} from "../drizzle/schema";
import { ENV } from './_core/env';

// Table references — default to MySQL schema, replaced with SQLite inside getDb() when needed.
// Exported as live ES-module bindings so routers see the updated SQLite tables after getDb() runs.
// eslint-disable-next-line prefer-const
export let users: any = _mysqlSchema.users;
// eslint-disable-next-line prefer-const
export let userProfiles: any = _mysqlSchema.userProfiles;
// eslint-disable-next-line prefer-const
export let userStorageQuotas: any = _mysqlSchema.userStorageQuotas;
// eslint-disable-next-line prefer-const
export let questions: any = _mysqlSchema.questions;
// eslint-disable-next-line prefer-const
export let questionImages: any = _mysqlSchema.questionImages;
// eslint-disable-next-line prefer-const
export let questionVersions: any = _mysqlSchema.questionVersions;
// eslint-disable-next-line prefer-const
export let exams: any = _mysqlSchema.exams;
// eslint-disable-next-line prefer-const
export let examQuestions: any = _mysqlSchema.examQuestions;
// eslint-disable-next-line prefer-const
export let examScores: any = _mysqlSchema.examScores;
// eslint-disable-next-line prefer-const
export let examTemplates: any = _mysqlSchema.examTemplates;
// eslint-disable-next-line prefer-const
export let generatedExams: any = _mysqlSchema.generatedExams;
// eslint-disable-next-line prefer-const
export let answerSheets: any = _mysqlSchema.answerSheets;
// eslint-disable-next-line prefer-const
export let answerSheetTemplates: any = _mysqlSchema.answerSheetTemplates;
// eslint-disable-next-line prefer-const
export let institutions: any = _mysqlSchema.institutions;
// eslint-disable-next-line prefer-const
export let departments: any = _mysqlSchema.departments;
// eslint-disable-next-line prefer-const
export let courses: any = _mysqlSchema.courses;
// eslint-disable-next-line prefer-const
export let classes: any = _mysqlSchema.classes;
// eslint-disable-next-line prefer-const
export let students: any = _mysqlSchema.students;
// eslint-disable-next-line prefer-const
export let studentClasses: any = _mysqlSchema.studentClasses;
// eslint-disable-next-line prefer-const
export let studentGrades: any = _mysqlSchema.studentGrades;
// eslint-disable-next-line prefer-const
export let sharingLinks: any = _mysqlSchema.sharingLinks;
// eslint-disable-next-line prefer-const
export let quizResponses: any = _mysqlSchema.quizResponses;
// eslint-disable-next-line prefer-const
export let blogPosts: any = _mysqlSchema.blogPosts;
// eslint-disable-next-line prefer-const
export let supportTickets: any = _mysqlSchema.supportTickets;
// eslint-disable-next-line prefer-const
export let projectQuotes: any = _mysqlSchema.projectQuotes;
// eslint-disable-next-line prefer-const
export let subscriptions: any = _mysqlSchema.subscriptions;
// eslint-disable-next-line prefer-const
export let billingProfiles: any = _mysqlSchema.billingProfiles;

// Using any for the DB type to avoid union-type incompatibility between MySQL/SQLite drizzle instances.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let _db: any = null;
let _isSqlite = false;

// Lazily create the drizzle instance so local tooling can run without a DB.
export async function getDb(): Promise<any> {
  if (!_db && process.env.DATABASE_URL) {
    try {
      const connectionString = process.env.DATABASE_URL;
      if (connectionString.startsWith("sqlite:")) {
        _isSqlite = true;
        const { default: Database } = await import("better-sqlite3");
        const sqliteDb = new Database(connectionString.slice("sqlite:".length));
        sqliteDb.pragma("journal_mode = WAL");
        sqliteDb.pragma("foreign_keys = ON");
        _db = drizzleSqlite(sqliteDb);
        // Override table references to SQLite schema so timestamps and types work correctly
        const s = await import("../drizzle/schema.sqlite");
        users = s.users; userProfiles = s.userProfiles; userStorageQuotas = s.userStorageQuotas;
        questions = s.questions; questionImages = s.questionImages; questionVersions = s.questionVersions;
        exams = s.exams; examQuestions = s.examQuestions; examScores = s.examScores;
        examTemplates = s.examTemplates; generatedExams = s.generatedExams;
        answerSheets = s.answerSheets; answerSheetTemplates = s.answerSheetTemplates;
        institutions = s.institutions; departments = s.departments; courses = s.courses;
        classes = s.classes; students = s.students; studentClasses = s.studentClasses;
        studentGrades = s.studentGrades; sharingLinks = s.sharingLinks;
        quizResponses = s.quizResponses; blogPosts = s.blogPosts;
        supportTickets = s.supportTickets; projectQuotes = s.projectQuotes;
        subscriptions = s.subscriptions;
        billingProfiles = s.billingProfiles;
      } else {
        const pool = mysql.createPool({
          uri: connectionString,
          waitForConnections: true,
          connectionLimit: 10,
          queueLimit: 0,
        });
        _db = drizzleMysql(pool);
      }
    } catch (error) {
      console.warn("[Database] Failed to connect:", error);
      _db = null;
    }
  }
  return _db;
}

export async function upsertUser(user: InsertUser): Promise<void> {
  if (!user.openId) {
    throw new Error("User openId is required for upsert");
  }

  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot upsert user: database not available");
    return;
  }

  try {
    const values: InsertUser = {
      openId: user.openId,
    };
    const updateSet: Record<string, unknown> = {};

    const textFields = ["name", "email", "loginMethod", "passwordHash"] as const;
    type TextField = (typeof textFields)[number];

    const assignNullable = (field: TextField) => {
      const value = user[field];
      if (value === undefined) return;
      const normalized = (value ?? null) as string | undefined;
      values[field] = normalized;
      updateSet[field] = normalized;
    };

    textFields.forEach(assignNullable);

    if (user.lastSignedIn !== undefined) {
      values.lastSignedIn = user.lastSignedIn;
      updateSet.lastSignedIn = user.lastSignedIn;
    }
    if (user.role !== undefined) {
      values.role = user.role;
      updateSet.role = user.role;
    } else if (user.openId === ENV.ownerOpenId) {
      values.role = 'admin';
      updateSet.role = 'admin';
    }

    if (!values.lastSignedIn) {
      values.lastSignedIn = new Date();
    }

    if (Object.keys(updateSet).length === 0) {
      updateSet.lastSignedIn = new Date();
    }

    const existing = await db.select().from(users).where(eq(users.openId, user.openId)).limit(1);
    if (existing.length > 0) {
      await db.update(users).set(updateSet).where(eq(users.openId, user.openId));
    } else {
      await db.insert(users).values(values);
    }
  } catch (error) {
    console.error("[Database] Failed to upsert user:", error);
    throw error;
  }
}

export async function getUserByOpenId(openId: string) {
  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot get user: database not available");
    return undefined;
  }

  const result = await db.select().from(users).where(eq(users.openId, openId)).limit(1);

  return result.length > 0 ? result[0] : undefined;
}

export async function getUserByEmail(email: string) {
  const db = await getDb();
  if (!db) {
    console.warn("[Database] Cannot get user: database not available");
    return undefined;
  }

  const normalizedEmail = email.trim().toLowerCase();
  const result = await db.select().from(users).where(eq(users.email, normalizedEmail)).limit(1);

  return result.length > 0 ? result[0] : undefined;
}

export async function createLocalUser(data: {
  email: string;
  passwordHash: string;
  name?: string | null;
}) {
  const db = await getDb();
  if (!db) {
    throw new Error("[Database] Cannot create user: database not available");
  }

  const normalizedEmail = data.email.trim().toLowerCase();
  const openId = `local:${normalizedEmail}`;

  await db.insert(users).values({
    openId,
    email: normalizedEmail,
    passwordHash: data.passwordHash,
    name: data.name ?? null,
    loginMethod: "email",
    emailVerified: "false",
    lastSignedIn: new Date(),
  });
}

export async function getUserById(userId: number) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

/**
 * User profile queries
 */
export async function getUserProfile(userId: number) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db
    .select()
    .from(userProfiles)
    .where(eq(userProfiles.userId, userId))
    .limit(1);

  return result.length > 0 ? result[0] : undefined;
}

export async function upsertUserProfile(userId: number, data: {
  phone?: string | null;
  university?: string | null;
  logoUrl?: string | null;
  logoStorageKey?: string | null;
}) {
  const db = await getDb();
  if (!db) return undefined;

  const existing = await getUserProfile(userId);
  if (existing) {
    await db.update(userProfiles).set(data).where(eq(userProfiles.userId, userId));
  } else {
    await db.insert(userProfiles).values({ userId, ...data });
  }
}

/**
 * Question queries
 */
export async function getUserQuestions(userId: number) {
  const db = await getDb();
  if (!db) return [];

  return await db.select().from(questions).where(eq(questions.userId, userId));
}

export async function getQuestionById(questionId: number) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db.select().from(questions).where(eq(questions.id, questionId)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

export async function createQuestion(data: any) {
  const db = await getDb();
  if (!db) return undefined;

  return await db.insert(questions).values(data);
}

/**
 * Exam queries
 */
export async function getUserExams(userId: number) {
  const db = await getDb();
  if (!db) return [];

  return await db.select().from(exams).where(eq(exams.userId, userId));
}

export async function getExamById(examId: number) {
  const db = await getDb();
  if (!db) return undefined;

  const result = await db.select().from(exams).where(eq(exams.id, examId)).limit(1);
  return result.length > 0 ? result[0] : undefined;
}

export async function createExam(data: any) {
  const db = await getDb();
  if (!db) return undefined;

  return await db.insert(exams).values(data);
}

// ─── Institutions ──────────────────────────────────────────────────────────

export async function getInstitutions(userId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(institutions).where(eq(institutions.userId, userId));
}

export async function getInstitutionById(id: number) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(institutions).where(eq(institutions.id, id)).limit(1);
  return result[0];
}

export async function createInstitution(data: InsertInstitution) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(institutions).values(data);
}

export async function updateInstitution(id: number, userId: number, data: Partial<InsertInstitution>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(institutions).set(data).where(and(eq(institutions.id, id), eq(institutions.userId, userId)));
}

export async function deleteInstitution(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(institutions).where(and(eq(institutions.id, id), eq(institutions.userId, userId)));
}

// ─── Departments ───────────────────────────────────────────────────────────

export async function getDepartments(institutionId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(departments).where(eq(departments.institutionId, institutionId));
}

export async function createDepartment(data: InsertDepartment) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(departments).values(data);
}

export async function updateDepartment(id: number, data: Partial<InsertDepartment>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(departments).set(data).where(eq(departments.id, id));
}

export async function deleteDepartment(id: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(departments).where(eq(departments.id, id));
}

// ─── Courses ───────────────────────────────────────────────────────────────

export async function getCourses(institutionId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(courses).where(eq(courses.institutionId, institutionId));
}

export async function createCourse(data: InsertCourse) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(courses).values(data);
}

export async function updateCourse(id: number, data: Partial<InsertCourse>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(courses).set(data).where(eq(courses.id, id));
}

export async function deleteCourse(id: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(courses).where(eq(courses.id, id));
}

// ─── Classes ───────────────────────────────────────────────────────────────

export async function getClasses(userId: number, includeArchived = false) {
  const db = await getDb();
  if (!db) return [];
  if (includeArchived) {
    return await db.select().from(classes).where(eq(classes.userId, userId));
  }
  return await db.select().from(classes).where(and(eq(classes.userId, userId), eq(classes.archived, "false")));
}

export async function getClassById(id: number) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(classes).where(eq(classes.id, id)).limit(1);
  return result[0];
}

export async function createClass(data: InsertClass) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(classes).values(data);
}

export async function updateClass(id: number, userId: number, data: Partial<InsertClass>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(classes).set(data).where(and(eq(classes.id, id), eq(classes.userId, userId)));
}

export async function archiveClass(id: number, userId: number, archived: boolean) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(classes)
    .set({ archived: archived ? "true" : "false" })
    .where(and(eq(classes.id, id), eq(classes.userId, userId)));
}

export async function deleteClass(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(classes).where(and(eq(classes.id, id), eq(classes.userId, userId)));
}

export async function getClassStudentCount(classId: number): Promise<number> {
  const db = await getDb();
  if (!db) return 0;
  const result = await db.select().from(studentClasses).where(eq(studentClasses.classId, classId));
  return result.length;
}

export async function getClassStudents(classId: number) {
  const db = await getDb();
  if (!db) return [];
  const links = await db.select().from(studentClasses).where(eq(studentClasses.classId, classId));
  if (links.length === 0) return [];
  const studentIds = links.map((l: any) => l.studentId);
  return await db.select().from(students).where(inArray(students.id, studentIds));
}

// ─── Students ──────────────────────────────────────────────────────────────

export async function getStudents(userId: number, classId?: number) {
  const db = await getDb();
  if (!db) return [];
  if (classId) {
    const links = await db.select().from(studentClasses).where(eq(studentClasses.classId, classId));
    if (links.length === 0) return [];
    const studentIds = links.map((l: any) => l.studentId);
    return await db.select().from(students).where(
      and(eq(students.userId, userId), inArray(students.id, studentIds))
    );
  }
  return await db.select().from(students).where(eq(students.userId, userId));
}

export async function getStudentById(id: number) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(students).where(eq(students.id, id)).limit(1);
  return result[0];
}

export async function createStudent(data: InsertStudent) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(students).values(data);
}

export async function updateStudent(id: number, userId: number, data: Partial<InsertStudent>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(students).set(data).where(and(eq(students.id, id), eq(students.userId, userId)));
}

export async function deleteStudent(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  await db.delete(studentClasses).where(eq(studentClasses.studentId, id));
  return await db.delete(students).where(and(eq(students.id, id), eq(students.userId, userId)));
}

export async function bulkDeleteStudents(ids: number[], userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  await db.delete(studentClasses).where(inArray(studentClasses.studentId, ids));
  return await db.delete(students).where(and(inArray(students.id, ids), eq(students.userId, userId)));
}

export async function assignStudentToClass(studentId: number, classId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  const existing = await db.select().from(studentClasses)
    .where(and(eq(studentClasses.studentId, studentId), eq(studentClasses.classId, classId)))
    .limit(1);
  if (existing.length === 0) {
    await db.insert(studentClasses).values({ studentId, classId });
  }
}

export async function removeStudentFromClass(studentId: number, classId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(studentClasses)
    .where(and(eq(studentClasses.studentId, studentId), eq(studentClasses.classId, classId)));
}

export async function getStudentClasses(studentId: number) {
  const db = await getDb();
  if (!db) return [];
  const links = await db.select().from(studentClasses).where(eq(studentClasses.studentId, studentId));
  if (links.length === 0) return [];
  const classIds = links.map((l: any) => l.classId);
  return await db.select().from(classes).where(inArray(classes.id, classIds));
}

export async function importStudents(userId: number, rows: Array<{
  studentId?: string;
  externalRef?: string;
  firstName: string;
  lastName: string;
  email?: string;
  classId?: number;
}>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  for (const row of rows) {
    const inserted = await db.insert(students).values({
      userId,
      studentId: row.studentId ?? null,
      externalRef: row.externalRef ?? null,
      firstName: row.firstName,
      lastName: row.lastName,
      email: row.email ?? null,
    });
    const raw = inserted as any;
    const newId = raw?.insertId ?? raw?.[0]?.insertId ?? (raw?.lastInsertRowid != null ? Number(raw.lastInsertRowid) : null);
    if (row.classId && newId) {
      await assignStudentToClass(newId, row.classId);
    }
  }
}

// ─── Sharing ───────────────────────────────────────────────────────────────

export async function createSharingLink(
  examId: number,
  userId: number,
  token: string,
  resultMode: "none" | "score" | "answer_key" | "all" = "none",
  timeLimitMinutes: number | null = null,
  classId: number | null = null,
  requireStudentId: boolean = false,
) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(sharingLinks).values({
    examId, userId, token, active: "true", resultMode,
    ...(timeLimitMinutes !== null ? { timeLimitMinutes } : {}),
    ...(classId !== null ? { classId } : {}),
    requireStudentId: requireStudentId as any,
  });
}

export async function getSharingLinks(userId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(sharingLinks).where(eq(sharingLinks.userId, userId));
}

export async function getSharingLinkByToken(token: string) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(sharingLinks)
    .where(and(eq(sharingLinks.token, token), eq(sharingLinks.active, "true")))
    .limit(1);
  return result[0];
}

export async function deactivateSharingLink(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(sharingLinks).set({ active: "false" })
    .where(and(eq(sharingLinks.id, id), eq(sharingLinks.userId, userId)));
}

export async function createQuizResponse(data: InsertQuizResponse) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(quizResponses).values(data);
}

export async function getQuizResponses(sharingLinkId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(quizResponses).where(eq(quizResponses.sharingLinkId, sharingLinkId));
}

// ─── Exam Scores ───────────────────────────────────────────────────────────

export async function upsertExamScores(examId: number, scores: Array<{ questionId: number; points: string }>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  await db.delete(examScores).where(eq(examScores.examId, examId));
  if (scores.length > 0) {
    await db.insert(examScores).values(scores.map((s) => ({ examId, questionId: s.questionId, points: s.points })));
  }
}

export async function getExamScores(examId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(examScores).where(eq(examScores.examId, examId));
}

// ─── Student Grades ────────────────────────────────────────────────────────

export async function upsertStudentGrade(data: InsertStudentGrade) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  const existing = await db.select().from(studentGrades)
    .where(and(eq(studentGrades.studentId, data.studentId), eq(studentGrades.examId, data.examId)))
    .limit(1);
  if (existing.length > 0) {
    return await db.update(studentGrades).set(data)
      .where(and(eq(studentGrades.studentId, data.studentId), eq(studentGrades.examId, data.examId)));
  }
  return await db.insert(studentGrades).values(data);
}

export async function getGradeBook(classId: number, userId: number) {
  const db = await getDb();
  if (!db) return [];

  const classStudents = await getClassStudents(classId);
  if (classStudents.length === 0) return [];

  const studentIds = classStudents.map((s: any) => s.id);
  const grades = await db.select().from(studentGrades).where(inArray(studentGrades.studentId, studentIds));

  const userExams = await db.select().from(exams)
    .where(and(eq(exams.userId, userId), eq(exams.classId, classId)));

  return classStudents.map((student: any) => ({
    student,
    grades: (grades as any[])
      .filter((g: any) => g.studentId === student.id)
      .map((g: any) => {
        const exam = (userExams as any[]).find((e: any) => e.id === g.examId);
        return { ...g, examName: exam?.name ?? "–" };
      }),
  }));
}

// ─── Answer Sheet Templates ─────────────────────────────────────────────────

export async function getAnswerSheetTemplates(userId: number) {
  const db = await getDb();
  if (!db) return [];
  return await db.select().from(answerSheetTemplates).where(eq(answerSheetTemplates.userId, userId));
}

export async function getAnswerSheetTemplateById(id: number) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(answerSheetTemplates).where(eq(answerSheetTemplates.id, id)).limit(1);
  return result[0];
}

export async function createAnswerSheetTemplate(data: InsertAnswerSheetTemplate) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(answerSheetTemplates).values(data);
}

export async function updateAnswerSheetTemplate(id: number, userId: number, data: Partial<InsertAnswerSheetTemplate>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(answerSheetTemplates).set(data)
    .where(and(eq(answerSheetTemplates.id, id), eq(answerSheetTemplates.userId, userId)));
}

export async function publishAnswerSheetTemplate(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(answerSheetTemplates)
    .set({ published: "true", publishedAt: new Date() })
    .where(and(eq(answerSheetTemplates.id, id), eq(answerSheetTemplates.userId, userId)));
}

export async function deleteAnswerSheetTemplate(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(answerSheetTemplates)
    .where(and(eq(answerSheetTemplates.id, id), eq(answerSheetTemplates.userId, userId)));
}

// ─── Admin ─────────────────────────────────────────────────────────────────

export async function getAllUsers(opts: { limit?: number; offset?: number; search?: string } = {}) {
  const db = await getDb();
  if (!db) return [];

  const { limit = 50, offset = 0 } = opts;
  let query = db.select({
    id: users.id,
    openId: users.openId,
    email: users.email,
    name: users.name,
    role: users.role,
    plan: users.plan,
    loginMethod: users.loginMethod,
    emailVerified: users.emailVerified,
    createdAt: users.createdAt,
    lastSignedIn: users.lastSignedIn,
  }).from(users);

  return await query.limit(limit).offset(offset).orderBy(sql`${users.createdAt} DESC`);
}

export async function countAllUsers(): Promise<number> {
  const db = await getDb();
  if (!db) return 0;
  const result = await db.select({ value: count() }).from(users);
  return result[0]?.value ?? 0;
}

export async function updateUserRoleById(userId: number, role: "user" | "admin") {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(users).set({ role }).where(eq(users.id, userId));
}

export async function deleteUserById(userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(users).where(eq(users.id, userId));
}

export async function getSystemStats() {
  const db = await getDb();
  if (!db) return { totalUsers: 0, totalQuestions: 0, totalExams: 0, totalStudents: 0, totalShares: 0, totalResponses: 0, newUsersThisMonth: 0 };

  const [
    usersCount,
    questionsCount,
    examsCount,
    studentsCount,
    sharesCount,
    responsesCount,
  ] = await Promise.all([
    db.select({ value: count() }).from(users),
    db.select({ value: count() }).from(questions),
    db.select({ value: count() }).from(exams),
    db.select({ value: count() }).from(students),
    db.select({ value: count() }).from(sharingLinks),
    db.select({ value: count() }).from(quizResponses),
  ]);

  const startOfMonth = new Date();
  startOfMonth.setDate(1);
  startOfMonth.setHours(0, 0, 0, 0);
  const newUsersResult = await db.select({ value: count() }).from(users)
    .where(sql`${users.createdAt} >= ${startOfMonth}`);

  return {
    totalUsers: usersCount[0]?.value ?? 0,
    totalQuestions: questionsCount[0]?.value ?? 0,
    totalExams: examsCount[0]?.value ?? 0,
    totalStudents: studentsCount[0]?.value ?? 0,
    totalShares: sharesCount[0]?.value ?? 0,
    totalResponses: responsesCount[0]?.value ?? 0,
    newUsersThisMonth: newUsersResult[0]?.value ?? 0,
  };
}

export async function getAllUserEmails(): Promise<Array<{ email: string; name: string | null }>> {
  const db = await getDb();
  if (!db) return [];
  const result = await db.select({ email: users.email, name: users.name }).from(users);
  return result.filter((u: any) => u.email) as Array<{ email: string; name: string | null }>;
}

// ─── Blog / CMS ─────────────────────────────────────────────────────────
export async function getBlogPosts(opts: { limit?: number; offset?: number; publishedOnly?: boolean } = {}) {
  const db = await getDb();
  if (!db) return [];
  const { limit = 20, offset = 0, publishedOnly = true } = opts;
  let q: any = db.select().from(blogPosts);
  if (publishedOnly) q = q.where(eq(blogPosts.published, "true"));
  return await q.limit(limit).offset(offset).orderBy(sql`${blogPosts.publishedAt} DESC`);
}

export async function getBlogPostById(id: number) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(blogPosts).where(eq(blogPosts.id, id)).limit(1);
  return result[0];
}

export async function createBlogPost(data: InsertBlogPost) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.insert(blogPosts).values(data);
}

export async function updateBlogPost(id: number, userId: number, data: Partial<InsertBlogPost>) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(blogPosts).set(data)
    .where(and(eq(blogPosts.id, id), eq(blogPosts.userId, userId)));
}

export async function publishBlogPost(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.update(blogPosts)
    .set({ published: "true", publishedAt: new Date() })
    .where(and(eq(blogPosts.id, id), eq(blogPosts.userId, userId)));
}

export async function deleteBlogPost(id: number, userId: number) {
  const db = await getDb();
  if (!db) throw new Error("Database not available");
  return await db.delete(blogPosts).where(and(eq(blogPosts.id, id), eq(blogPosts.userId, userId)));
}

export async function getBlogPostBySlug(slug: string) {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(blogPosts)
    .where(and(eq(blogPosts.slug, slug), eq(blogPosts.published, "true")))
    .limit(1);
  return result[0];
}
