import { integer, real, sqliteTable, text } from "drizzle-orm/sqlite-core";

export const users = sqliteTable("users", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  openId: text("openId").notNull().unique(),
  email: text("email").unique(),
  passwordHash: text("password_hash"),
  name: text("name"),
  loginMethod: text("loginMethod").default("email").notNull(),
  role: text("role", { enum: ["user", "admin"] as const }).default("user").notNull(),
  plan: text("plan", { enum: ["free", "basic", "pro", "enterprise"] as const }).default("free").notNull(),
  emailVerified: text("email_verified", { enum: ["true", "false"] as const }).default("false").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  lastSignedIn: integer("lastSignedIn", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;

export const refreshTokens = sqliteTable("refresh_tokens", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  token: text("token").notNull().unique(),
  expiresAt: integer("expires_at", { mode: "timestamp" }).notNull(),
  createdAt: integer("created_at", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  revoked: text("revoked", { enum: ["true", "false"] as const }).default("false").notNull(),
});

export type RefreshToken = typeof refreshTokens.$inferSelect;
export type InsertRefreshToken = typeof refreshTokens.$inferInsert;

export const userProfiles = sqliteTable("user_profiles", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().unique().references(() => users.id),
  phone: text("phone"),
  university: text("university"),
  logoUrl: text("logo_url"),
  logoStorageKey: text("logo_storage_key"),
  disciplineColors: text("discipline_colors"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type UserProfile = typeof userProfiles.$inferSelect;
export type InsertUserProfile = typeof userProfiles.$inferInsert;

export const questions = sqliteTable("questions", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  type: text("type", { enum: ["multiple_choice", "multiple_choice_complex", "essay", "assertion_reason"] as const }).notNull(),
  statement: text("statement").notNull(),
  content: text("content"),
  alternatives: text("alternatives"),
  correctAnswer: text("correct_answer"),
  discipline: text("discipline"),
  subject: text("subject"),
  difficulty: text("difficulty", { enum: ["easy", "medium", "hard"] as const }),
  tags: text("tags"),
  sourceFormat: text("source_format"),
  totalImages: integer("total_images").default(0),
  importedAt: integer("imported_at", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Question = typeof questions.$inferSelect;
export type InsertQuestion = typeof questions.$inferInsert;

export const questionImages = sqliteTable("question_images", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  questionId: integer("question_id").notNull().references(() => questions.id),
  userId: integer("user_id").notNull().references(() => users.id),
  originalFileName: text("original_file_name").notNull(),
  storageKey: text("storage_key").notNull().unique(),
  storagePath: text("storage_path").notNull(),
  fileSize: integer("file_size").notNull(),
  mimeType: text("mime_type").notNull(),
  width: integer("width"),
  height: integer("height"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type QuestionImage = typeof questionImages.$inferSelect;
export type InsertQuestionImage = typeof questionImages.$inferInsert;

export const userStorageQuotas = sqliteTable("user_storage_quotas", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().unique().references(() => users.id),
  quotaLimitMB: integer("quota_limit_mb").default(100),
  usedStorageMB: integer("used_storage_mb").default(0),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type UserStorageQuota = typeof userStorageQuotas.$inferSelect;
export type InsertUserStorageQuota = typeof userStorageQuotas.$inferInsert;

export const institutions = sqliteTable("institutions", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  name: text("name").notNull(),
  type: text("type", { enum: ["university", "faculty", "school"] as const }).default("university").notNull(),
  address: text("address"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Institution = typeof institutions.$inferSelect;
export type InsertInstitution = typeof institutions.$inferInsert;

export const departments = sqliteTable("departments", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  institutionId: integer("institution_id").notNull().references(() => institutions.id),
  name: text("name").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Department = typeof departments.$inferSelect;
export type InsertDepartment = typeof departments.$inferInsert;

export const courses = sqliteTable("courses", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  institutionId: integer("institution_id").notNull().references(() => institutions.id),
  departmentId: integer("department_id").references(() => departments.id),
  name: text("name").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Course = typeof courses.$inferSelect;
export type InsertCourse = typeof courses.$inferInsert;

export const classes = sqliteTable("classes", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  institutionId: integer("institution_id").references(() => institutions.id),
  courseId: integer("course_id").references(() => courses.id),
  name: text("name").notNull(),
  semester: text("semester", { enum: ["1", "2"] as const }).notNull(),
  classYear: integer("class_year").notNull(),
  archived: text("archived", { enum: ["true", "false"] as const }).default("false").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Class = typeof classes.$inferSelect;
export type InsertClass = typeof classes.$inferInsert;

export const students = sqliteTable("students", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  studentId: text("student_id"),
  externalRef: text("external_ref"),
  firstName: text("first_name").notNull(),
  lastName: text("last_name").notNull(),
  email: text("email"),
  portalToken: text("portal_token").unique(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Student = typeof students.$inferSelect;
export type InsertStudent = typeof students.$inferInsert;

export const studentClasses = sqliteTable("student_classes", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  studentId: integer("student_id").notNull().references(() => students.id),
  classId: integer("class_id").notNull().references(() => classes.id),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type StudentClass = typeof studentClasses.$inferSelect;
export type InsertStudentClass = typeof studentClasses.$inferInsert;

export const exams = sqliteTable("exams", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  name: text("name").notNull(),
  course: text("course").notNull(),
  class: text("class").notNull(),
  campus: text("campus"),
  discipline: text("discipline").notNull(),
  professor: text("professor").notNull(),
  examDate: integer("exam_date", { mode: "timestamp" }),
  period: text("period"),
  instructions: text("instructions"),
  numberOfVersions: integer("number_of_versions").default(1),
  shuffleMode: text("shuffle_mode", { enum: ["all", "questions", "items", "none"] as const }).default("all").notNull(),
  printFontSize: text("print_font_size", { enum: ["small", "normal", "large"] as const }).default("normal").notNull(),
  printMargins: text("print_margins", { enum: ["compact", "normal", "wide"] as const }).default("normal").notNull(),
  institutionId: integer("institution_id").references(() => institutions.id),
  classId: integer("class_id").references(() => classes.id),
  headerType: text("header_type", { enum: ["standard", "custom"] as const }).default("standard").notNull(),
  customHeaderUrl: text("custom_header_url"),
  totalPoints: real("total_points"),
  examLanguage: text("exam_language", { enum: ["pt", "en", "es"] as const }).default("pt").notNull(),
  examType: text("exam_type", { enum: ["full", "answer_key_only"] as const }).default("full").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Exam = typeof exams.$inferSelect;
export type InsertExam = typeof exams.$inferInsert;

export const examQuestions = sqliteTable("exam_questions", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  examId: integer("exam_id").notNull().references(() => exams.id),
  questionId: integer("question_id").notNull().references(() => questions.id),
  order: integer("order").notNull(),
  isVoided: text("is_voided", { enum: ["true", "false"] as const }).default("false").notNull(),
  voidedAt: integer("voided_at", { mode: "timestamp" }),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type ExamQuestion = typeof examQuestions.$inferSelect;
export type InsertExamQuestion = typeof examQuestions.$inferInsert;

export const generatedExams = sqliteTable("generated_exams", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  examId: integer("exam_id").notNull().references(() => exams.id),
  version: integer("version").notNull(),
  pdfStorageKey: text("pdf_storage_key").notNull(),
  pdfUrl: text("pdf_url"),
  questionOrder: text("question_order"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type GeneratedExam = typeof generatedExams.$inferSelect;
export type InsertGeneratedExam = typeof generatedExams.$inferInsert;

export const answerSheets = sqliteTable("answer_sheets", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  examId: integer("exam_id").notNull().references(() => exams.id),
  imageStorageKey: text("image_storage_key").notNull(),
  imageUrl: text("image_url"),
  extractedAnswers: text("extracted_answers"),
  processingStatus: text("processing_status", { enum: ["pending", "processing", "completed", "failed"] as const }).default("pending"),
  errorMessage: text("error_message"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type AnswerSheet = typeof answerSheets.$inferSelect;
export type InsertAnswerSheet = typeof answerSheets.$inferInsert;

export const examScores = sqliteTable("exam_scores", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  examId: integer("exam_id").notNull().references(() => exams.id),
  questionId: integer("question_id").notNull().references(() => questions.id),
  points: real("points").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type ExamScore = typeof examScores.$inferSelect;
export type InsertExamScore = typeof examScores.$inferInsert;

export const studentGrades = sqliteTable("student_grades", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  studentId: integer("student_id").notNull().references(() => students.id),
  examId: integer("exam_id").notNull().references(() => exams.id),
  score: real("score"),
  maxScore: real("max_score"),
  answerSheetId: integer("answer_sheet_id").references(() => answerSheets.id),
  gradedAt: integer("graded_at", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type StudentGrade = typeof studentGrades.$inferSelect;
export type InsertStudentGrade = typeof studentGrades.$inferInsert;

export const answerSheetTemplates = sqliteTable("answer_sheet_templates", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  name: text("name").notNull(),
  config: text("config").notNull(),
  published: text("published", { enum: ["true", "false"] as const }).default("false").notNull(),
  publishedAt: integer("published_at", { mode: "timestamp" }),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type AnswerSheetTemplate = typeof answerSheetTemplates.$inferSelect;
export type InsertAnswerSheetTemplate = typeof answerSheetTemplates.$inferInsert;

export const questionVersions = sqliteTable("question_versions", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  questionId: integer("question_id").notNull().references(() => questions.id),
  userId: integer("user_id").notNull().references(() => users.id),
  versionNumber: integer("version_number").notNull(),
  snapshot: text("snapshot").notNull(),
  changeSummary: text("change_summary"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type QuestionVersion = typeof questionVersions.$inferSelect;
export type InsertQuestionVersion = typeof questionVersions.$inferInsert;

export const examTemplates = sqliteTable("exam_templates", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  name: text("name").notNull(),
  description: text("description"),
  discipline: text("discipline"),
  structure: text("structure").notNull(),
  shuffleMode: text("shuffle_mode", { enum: ["all", "questions", "items", "none"] as const }).default("all").notNull(),
  numberOfVersions: integer("number_of_versions").default(1),
  totalPoints: real("total_points"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type ExamTemplate = typeof examTemplates.$inferSelect;
export type InsertExamTemplate = typeof examTemplates.$inferInsert;

export const blogPosts = sqliteTable("blog_posts", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  title: text("title").notNull(),
  slug: text("slug").notNull().unique(),
  excerpt: text("excerpt"),
  markdown: text("markdown"),
  html: text("html"),
  published: text("published", { enum: ["true", "false"] as const }).default("false").notNull(),
  publishedAt: integer("published_at", { mode: "timestamp" }),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type BlogPost = typeof blogPosts.$inferSelect;
export type InsertBlogPost = typeof blogPosts.$inferInsert;

export const supportTickets = sqliteTable("support_tickets", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  title: text("title").notNull(),
  description: text("description").notNull(),
  status: text("status", { enum: ["open", "in_progress", "resolved", "closed"] as const }).default("open").notNull(),
  priority: text("priority", { enum: ["low", "medium", "high"] as const }).default("medium").notNull(),
  adminResponse: text("admin_response"),
  respondedAt: integer("responded_at", { mode: "timestamp" }),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type SupportTicket = typeof supportTickets.$inferSelect;
export type InsertSupportTicket = typeof supportTickets.$inferInsert;

export const projectQuotes = sqliteTable("project_quotes", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  name: text("name").notNull(),
  email: text("email").notNull(),
  phone: text("phone").notNull(),
  company: text("company").notNull(),
  role: text("role").notNull(),
  projectType: text("project_type").notNull(),
  estimatedVolume: text("estimated_volume").notNull(),
  timeframe: text("timeframe").notNull(),
  description: text("description").notNull(),
  features: text("features"),
  status: text("status", { enum: ["pending", "contacted", "closed", "declined"] as const }).default("pending").notNull(),
  adminNotes: text("admin_notes"),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type ProjectQuote = typeof projectQuotes.$inferSelect;
export type InsertProjectQuote = typeof projectQuotes.$inferInsert;

export const sharingLinks = sqliteTable("sharing_links", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  examId: integer("exam_id").notNull().references(() => exams.id),
  userId: integer("user_id").notNull().references(() => users.id),
  token: text("token").notNull().unique(),
  active: text("active", { enum: ["true", "false"] as const }).default("true").notNull(),
  resultMode: text("result_mode", { enum: ["none", "score", "answer_key", "all"] as const }).default("none").notNull(),
  timeLimitMinutes: integer("time_limit_minutes"),
  classId: integer("class_id").references(() => classes.id),
  requireStudentId: integer("require_student_id", { mode: "boolean" }).default(false).notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type SharingLink = typeof sharingLinks.$inferSelect;
export type InsertSharingLink = typeof sharingLinks.$inferInsert;

export const quizResponses = sqliteTable("quiz_responses", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  sharingLinkId: integer("sharing_link_id").notNull().references(() => sharingLinks.id),
  studentName: text("student_name").notNull(),
  studentEmail: text("student_email"),
  answers: text("answers").notNull(),
  score: real("score"),
  manualScores: text("manual_scores"),
  enrolledStudentId: integer("enrolled_student_id").references(() => students.id),
  submittedAt: integer("submitted_at", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type QuizResponse = typeof quizResponses.$inferSelect;
export type InsertQuizResponse = typeof quizResponses.$inferInsert;

export const subscriptions = sqliteTable("subscriptions", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().references(() => users.id),
  planKey: text("plan_key", { enum: ["basic", "pro", "enterprise"] as const }).notNull(),
  mpPreapprovalId: text("mp_preapproval_id").notNull().unique(),
  status: text("status", { enum: ["pending", "authorized", "paused", "cancelled"] as const }).default("pending").notNull(),
  currentPeriodEnd: integer("current_period_end", { mode: "timestamp" }),
  cancelledAt: integer("cancelled_at", { mode: "timestamp" }),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type Subscription = typeof subscriptions.$inferSelect;
export type InsertSubscription = typeof subscriptions.$inferInsert;

export const billingProfiles = sqliteTable("billing_profiles", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  userId: integer("user_id").notNull().unique().references(() => users.id),
  fullName: text("full_name").notNull(),
  cpf: text("cpf").notNull(),
  phone: text("phone").notNull(),
  country: text("country").default("BR").notNull(),
  createdAt: integer("createdAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
  updatedAt: integer("updatedAt", { mode: "timestamp" }).$defaultFn(() => new Date()).notNull(),
});

export type BillingProfile = typeof billingProfiles.$inferSelect;
export type InsertBillingProfile = typeof billingProfiles.$inferInsert;
