import { relations } from "drizzle-orm";
import {
  users,
  userProfiles,
  questions,
  questionImages,
  questionVersions,
  userStorageQuotas,
  exams,
  examQuestions,
  generatedExams,
  answerSheets,
  institutions,
  departments,
  courses,
  classes,
  students,
  studentClasses,
  sharingLinks,
  quizResponses,
  examScores,
  studentGrades,
} from "./schema";

export const usersRelations = relations(users, ({ one, many }) => ({
  profile: one(userProfiles, { fields: [users.id], references: [userProfiles.userId] }),
  questions: many(questions),
  exams: many(exams),
  institutions: many(institutions),
  classes: many(classes),
  students: many(students),
  storageQuota: one(userStorageQuotas, { fields: [users.id], references: [userStorageQuotas.userId] }),
}));

export const institutionsRelations = relations(institutions, ({ one, many }) => ({
  user: one(users, { fields: [institutions.userId], references: [users.id] }),
  departments: many(departments),
  courses: many(courses),
  classes: many(classes),
}));

export const departmentsRelations = relations(departments, ({ one, many }) => ({
  institution: one(institutions, { fields: [departments.institutionId], references: [institutions.id] }),
  courses: many(courses),
}));

export const coursesRelations = relations(courses, ({ one, many }) => ({
  institution: one(institutions, { fields: [courses.institutionId], references: [institutions.id] }),
  department: one(departments, { fields: [courses.departmentId], references: [departments.id] }),
  classes: many(classes),
}));

export const classesRelations = relations(classes, ({ one, many }) => ({
  user: one(users, { fields: [classes.userId], references: [users.id] }),
  institution: one(institutions, { fields: [classes.institutionId], references: [institutions.id] }),
  course: one(courses, { fields: [classes.courseId], references: [courses.id] }),
  studentClasses: many(studentClasses),
}));

export const studentsRelations = relations(students, ({ one, many }) => ({
  user: one(users, { fields: [students.userId], references: [users.id] }),
  studentClasses: many(studentClasses),
  grades: many(studentGrades),
}));

export const studentClassesRelations = relations(studentClasses, ({ one }) => ({
  student: one(students, { fields: [studentClasses.studentId], references: [students.id] }),
  class: one(classes, { fields: [studentClasses.classId], references: [classes.id] }),
}));

export const examsRelations = relations(exams, ({ one, many }) => ({
  user: one(users, { fields: [exams.userId], references: [users.id] }),
  institution: one(institutions, { fields: [exams.institutionId], references: [institutions.id] }),
  class: one(classes, { fields: [exams.classId], references: [classes.id] }),
  examQuestions: many(examQuestions),
  generatedExams: many(generatedExams),
  answerSheets: many(answerSheets),
  sharingLinks: many(sharingLinks),
  examScores: many(examScores),
}));

export const examQuestionsRelations = relations(examQuestions, ({ one }) => ({
  exam: one(exams, { fields: [examQuestions.examId], references: [exams.id] }),
  question: one(questions, { fields: [examQuestions.questionId], references: [questions.id] }),
}));

export const questionsRelations = relations(questions, ({ one, many }) => ({
  user: one(users, { fields: [questions.userId], references: [users.id] }),
  images: many(questionImages),
  examScores: many(examScores),
  versions: many(questionVersions),
}));

export const questionVersionsRelations = relations(questionVersions, ({ one }) => ({
  question: one(questions, { fields: [questionVersions.questionId], references: [questions.id] }),
  user: one(users, { fields: [questionVersions.userId], references: [users.id] }),
}));

export const examScoresRelations = relations(examScores, ({ one }) => ({
  exam: one(exams, { fields: [examScores.examId], references: [exams.id] }),
  question: one(questions, { fields: [examScores.questionId], references: [questions.id] }),
}));

export const sharingLinksRelations = relations(sharingLinks, ({ one, many }) => ({
  exam: one(exams, { fields: [sharingLinks.examId], references: [exams.id] }),
  user: one(users, { fields: [sharingLinks.userId], references: [users.id] }),
  responses: many(quizResponses),
}));

export const quizResponsesRelations = relations(quizResponses, ({ one }) => ({
  sharingLink: one(sharingLinks, { fields: [quizResponses.sharingLinkId], references: [sharingLinks.id] }),
}));

export const studentGradesRelations = relations(studentGrades, ({ one }) => ({
  student: one(students, { fields: [studentGrades.studentId], references: [students.id] }),
  exam: one(exams, { fields: [studentGrades.examId], references: [exams.id] }),
  answerSheet: one(answerSheets, { fields: [studentGrades.answerSheetId], references: [answerSheets.id] }),
}));
