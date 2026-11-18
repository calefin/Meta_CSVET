import { decimal, int, mysqlEnum, mysqlTable, text, timestamp, tinyint, varchar, year } from "drizzle-orm/mysql-core";

/**
 * Core user table backing auth flow.
 * Extend this file with additional tables as your product grows.
 * Columns use camelCase to match both database fields and generated types.
 */
export const users = mysqlTable("users", {
  /**
   * Surrogate primary key. Auto-incremented numeric value managed by the database.
   * Use this for relations between tables.
   */
  id: int("id").autoincrement().primaryKey(),
  /** Universal login identifier for OAuth/local users */
  openId: varchar("openId", { length: 255 }).notNull().unique(),
  /** Email address for authentication */
  email: varchar("email", { length: 320 }).unique(),
  /** Hashed password using secure PBKDF2 */
  passwordHash: varchar("password_hash", { length: 255 }),
  name: text("name"),
  loginMethod: varchar("loginMethod", { length: 64 }).default("email").notNull(),
  role: mysqlEnum("role", ["user", "admin"]).default("user").notNull(),
  plan: mysqlEnum("plan", ["free", "basic", "pro", "enterprise"]).default("free").notNull(),
  emailVerified: mysqlEnum("email_verified", ["true", "false"]).default("false").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull(),
});

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;

/**
 * Refresh tokens for secure authentication
 */
export const refreshTokens = mysqlTable("refresh_tokens", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  token: varchar("token", { length: 255 }).notNull().unique(),
  expiresAt: timestamp("expires_at").notNull(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
  revoked: mysqlEnum("revoked", ["true", "false"]).default("false").notNull(),
});

export type RefreshToken = typeof refreshTokens.$inferSelect;
export type InsertRefreshToken = typeof refreshTokens.$inferInsert;

/**
 * User profile with institution details and logo
 */
export const userProfiles = mysqlTable("user_profiles", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().unique().references(() => users.id),
  phone: varchar("phone", { length: 20 }),
  university: varchar("university", { length: 255 }),
  logoUrl: text("logo_url"),
  logoStorageKey: varchar("logo_storage_key", { length: 255 }),
  disciplineColors: text("discipline_colors"), // JSON: { "Matemática": "#3b82f6", ... }
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type UserProfile = typeof userProfiles.$inferSelect;
export type InsertUserProfile = typeof userProfiles.$inferInsert;

/**
 * Question bank - stores all imported and created questions
 */
export const questions = mysqlTable("questions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  // Type of question: multiple_choice, essay, assertion_reason
  type: mysqlEnum("type", [
    "multiple_choice",
    "multiple_choice_complex",
    "essay",
    "assertion_reason",
  ]).notNull(),
  statement: text("statement").notNull(), // Question text/enunciation
  // Normalized JSON content structure for different question types
  content: text("content"), // JSON with complete question structure
  alternatives: text("alternatives"), // JSON array of alternatives (for backward compatibility)
  correctAnswer: text("correct_answer"), // Correct answer(s) - can be JSON
  discipline: varchar("discipline", { length: 255 }), // Subject/discipline
  subject: varchar("subject", { length: 255 }), // Specific topic/subject within discipline
  difficulty: mysqlEnum("difficulty", ["easy", "medium", "hard"]),
  tags: varchar("tags", { length: 500 }), // Comma-separated tags for organization
  sourceFormat: varchar("source_format", { length: 50 }), // XLSX, DOCX, PDF, JSON, manual
  totalImages: int("total_images").default(0), // Number of images attached
  importedAt: timestamp("imported_at").defaultNow().notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Question = typeof questions.$inferSelect;
export type InsertQuestion = typeof questions.$inferInsert;

/**
 * Question images - tracks images uploaded with questions
 */
export const questionImages = mysqlTable("question_images", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("question_id").notNull().references(() => questions.id),
  userId: int("user_id").notNull().references(() => users.id),
  originalFileName: varchar("original_file_name", { length: 255 }).notNull(),
  storageKey: varchar("storage_key", { length: 255 }).notNull().unique(), // Local storage key
  storagePath: varchar("storage_path", { length: 500 }).notNull(), // Full path on server
  fileSize: int("file_size").notNull(), // Size in bytes
  mimeType: varchar("mime_type", { length: 50 }).notNull(),
  width: int("width"), // Image dimensions
  height: int("height"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type QuestionImage = typeof questionImages.$inferSelect;
export type InsertQuestionImage = typeof questionImages.$inferInsert;

/**
 * User storage quota - manages storage limits per user
 */
export const userStorageQuotas = mysqlTable("user_storage_quotas", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().unique().references(() => users.id),
  quotaLimitMB: int("quota_limit_mb").default(100), // Default 100MB per user
  usedStorageMB: int("used_storage_mb").default(0), // Used storage in MB
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type UserStorageQuota = typeof userStorageQuotas.$inferSelect;
export type InsertUserStorageQuota = typeof userStorageQuotas.$inferInsert;

/**
 * Institutions - universities, faculties, schools
 */
export const institutions = mysqlTable("institutions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  name: varchar("name", { length: 255 }).notNull(),
  type: mysqlEnum("type", ["university", "faculty", "school"]).default("university").notNull(),
  address: text("address"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Institution = typeof institutions.$inferSelect;
export type InsertInstitution = typeof institutions.$inferInsert;

/**
 * Departments - within institutions
 */
export const departments = mysqlTable("departments", {
  id: int("id").autoincrement().primaryKey(),
  institutionId: int("institution_id").notNull().references(() => institutions.id),
  name: varchar("name", { length: 255 }).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Department = typeof departments.$inferSelect;
export type InsertDepartment = typeof departments.$inferInsert;

/**
 * Courses - linked to institution and optionally department
 */
export const courses = mysqlTable("courses", {
  id: int("id").autoincrement().primaryKey(),
  institutionId: int("institution_id").notNull().references(() => institutions.id),
  departmentId: int("department_id").references(() => departments.id),
  name: varchar("name", { length: 255 }).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Course = typeof courses.$inferSelect;
export type InsertCourse = typeof courses.$inferInsert;

/**
 * Classes / Turmas - with semester and year
 */
export const classes = mysqlTable("classes", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  institutionId: int("institution_id").references(() => institutions.id),
  courseId: int("course_id").references(() => courses.id),
  name: varchar("name", { length: 255 }).notNull(),
  semester: mysqlEnum("semester", ["1", "2"]).notNull(),
  classYear: int("class_year").notNull(),
  archived: mysqlEnum("archived", ["true", "false"]).default("false").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Class = typeof classes.$inferSelect;
export type InsertClass = typeof classes.$inferInsert;

/**
 * Students
 */
export const students = mysqlTable("students", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  studentId: varchar("student_id", { length: 50 }),
  externalRef: varchar("external_ref", { length: 100 }),
  firstName: varchar("first_name", { length: 100 }).notNull(),
  lastName: varchar("last_name", { length: 100 }).notNull(),
  email: varchar("email", { length: 320 }),
  portalToken: varchar("portal_token", { length: 64 }).unique(), // token for student grade portal
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Student = typeof students.$inferSelect;
export type InsertStudent = typeof students.$inferInsert;

/**
 * Student-Class junction table
 */
export const studentClasses = mysqlTable("student_classes", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("student_id").notNull().references(() => students.id),
  classId: int("class_id").notNull().references(() => classes.id),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type StudentClass = typeof studentClasses.$inferSelect;
export type InsertStudentClass = typeof studentClasses.$inferInsert;

/**
 * Public sharing links for quizzes
 */
export const sharingLinks = mysqlTable("sharing_links", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("exam_id").notNull().references(() => exams.id),
  userId: int("user_id").notNull().references(() => users.id),
  token: varchar("token", { length: 64 }).notNull().unique(),
  active: mysqlEnum("active", ["true", "false"]).default("true").notNull(),
  // Controls what the student sees after submitting
  resultMode: mysqlEnum("result_mode", ["none", "score", "answer_key", "all"]).default("none").notNull(),
  timeLimitMinutes: int("time_limit_minutes"), // null = no limit
  classId: int("class_id").references(() => classes.id), // null = public; set = restricted to class
  requireStudentId: tinyint("require_student_id").default(0).notNull(), // 1 = require enrollment number
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type SharingLink = typeof sharingLinks.$inferSelect;
export type InsertSharingLink = typeof sharingLinks.$inferInsert;

/**
 * Public quiz responses from students
 */
export const quizResponses = mysqlTable("quiz_responses", {
  id: int("id").autoincrement().primaryKey(),
  sharingLinkId: int("sharing_link_id").notNull().references(() => sharingLinks.id),
  studentName: varchar("student_name", { length: 255 }).notNull(),
  studentEmail: varchar("student_email", { length: 320 }),
  answers: text("answers").notNull(), // JSON: [{questionId, answer}]
  score: decimal("score", { precision: 6, scale: 2 }),
  manualScores: text("manual_scores"), // JSON: {questionId: points} — for essay manual grading
  enrolledStudentId: int("enrolled_student_id").references(() => students.id), // set when requireStudentId=1
  submittedAt: timestamp("submitted_at").defaultNow().notNull(),
});

export type QuizResponse = typeof quizResponses.$inferSelect;
export type InsertQuizResponse = typeof quizResponses.$inferInsert;

/**
 * Exam/Test - stores exam configurations and metadata
 */
export const exams = mysqlTable("exams", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  name: varchar("name", { length: 255 }).notNull(),
  course: varchar("course", { length: 255 }).notNull(),
  class: varchar("class", { length: 100 }).notNull(),
  campus: varchar("campus", { length: 255 }),
  discipline: varchar("discipline", { length: 255 }).notNull(),
  professor: varchar("professor", { length: 255 }).notNull(),
  examDate: timestamp("exam_date"),
  period: varchar("period", { length: 50 }),
  instructions: text("instructions"),
  numberOfVersions: int("number_of_versions").default(1),
  shuffleMode: mysqlEnum("shuffle_mode", ["all", "questions", "items", "none"]).default("all").notNull(),
  printFontSize: mysqlEnum("print_font_size", ["small", "normal", "large"]).default("normal").notNull(),
  printMargins: mysqlEnum("print_margins", ["compact", "normal", "wide"]).default("normal").notNull(),
  institutionId: int("institution_id").references(() => institutions.id),
  classId: int("class_id").references(() => classes.id),
  headerType: mysqlEnum("header_type", ["standard", "custom"]).default("standard").notNull(),
  customHeaderUrl: text("custom_header_url"),
  totalPoints: decimal("total_points", { precision: 6, scale: 2 }),
  examLanguage: mysqlEnum("exam_language", ["pt", "en", "es"]).default("pt").notNull(),
  examType: mysqlEnum("exam_type", ["full", "answer_key_only"]).default("full").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Exam = typeof exams.$inferSelect;
export type InsertExam = typeof exams.$inferInsert;

/**
 * Exam questions - junction table linking exams to questions
 */
export const examQuestions = mysqlTable("exam_questions", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("exam_id").notNull().references(() => exams.id),
  questionId: int("question_id").notNull().references(() => questions.id),
  order: int("order").notNull(),
  isVoided: mysqlEnum("is_voided", ["true", "false"]).default("false").notNull(),
  voidedAt: timestamp("voided_at"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ExamQuestion = typeof examQuestions.$inferSelect;
export type InsertExamQuestion = typeof examQuestions.$inferInsert;

/**
 * Generated exam PDFs - stores information about generated exam versions
 */
export const generatedExams = mysqlTable("generated_exams", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("exam_id").notNull().references(() => exams.id),
  version: int("version").notNull(), // Version number
  pdfStorageKey: varchar("pdf_storage_key", { length: 255 }).notNull(), // Storage key for PDF
  pdfUrl: text("pdf_url"), // Public URL for PDF
  questionOrder: text("question_order"), // JSON array of question IDs in this version
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type GeneratedExam = typeof generatedExams.$inferSelect;
export type InsertGeneratedExam = typeof generatedExams.$inferInsert;

/**
 * Answer sheets/gabaritos - stores uploaded answer sheet images
 */
export const answerSheets = mysqlTable("answer_sheets", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("exam_id").notNull().references(() => exams.id),
  imageStorageKey: varchar("image_storage_key", { length: 255 }).notNull(), // Storage key for image
  imageUrl: text("image_url"), // Public URL for image
  extractedAnswers: text("extracted_answers"), // JSON with extracted answers from image
  processingStatus: mysqlEnum("processing_status", ["pending", "processing", "completed", "failed"]).default("pending"),
  errorMessage: text("error_message"), // Error message if processing failed
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type AnswerSheet = typeof answerSheets.$inferSelect;
export type InsertAnswerSheet = typeof answerSheets.$inferInsert;

/**
 * Per-question scoring for exams
 */
export const examScores = mysqlTable("exam_scores", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("exam_id").notNull().references(() => exams.id),
  questionId: int("question_id").notNull().references(() => questions.id),
  points: decimal("points", { precision: 5, scale: 2 }).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ExamScore = typeof examScores.$inferSelect;
export type InsertExamScore = typeof examScores.$inferInsert;

/**
 * Student exam grades (manual or from answer sheet processing)
 */
export const studentGrades = mysqlTable("student_grades", {
  id: int("id").autoincrement().primaryKey(),
  studentId: int("student_id").notNull().references(() => students.id),
  examId: int("exam_id").notNull().references(() => exams.id),
  score: decimal("score", { precision: 6, scale: 2 }),
  maxScore: decimal("max_score", { precision: 6, scale: 2 }),
  answerSheetId: int("answer_sheet_id").references(() => answerSheets.id),
  gradedAt: timestamp("graded_at").defaultNow().notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type StudentGrade = typeof studentGrades.$inferSelect;
export type InsertStudentGrade = typeof studentGrades.$inferInsert;

/**
 * Custom answer sheet templates
 */
export const answerSheetTemplates = mysqlTable("answer_sheet_templates", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  name: varchar("name", { length: 255 }).notNull(),
  config: text("config").notNull(), // JSON — header, questions, style
  published: mysqlEnum("published", ["true", "false"]).default("false").notNull(),
  publishedAt: timestamp("published_at"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type AnswerSheetTemplate = typeof answerSheetTemplates.$inferSelect;
export type InsertAnswerSheetTemplate = typeof answerSheetTemplates.$inferInsert;

/**
 * Question version history — snapshot saved on every update
 */
export const questionVersions = mysqlTable("question_versions", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("question_id").notNull().references(() => questions.id),
  userId: int("user_id").notNull().references(() => users.id),
  versionNumber: int("version_number").notNull(),
  snapshot: text("snapshot").notNull(), // Full JSON of question state at save time
  changeSummary: varchar("change_summary", { length: 255 }),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type QuestionVersion = typeof questionVersions.$inferSelect;
export type InsertQuestionVersion = typeof questionVersions.$inferInsert;

/**
 * Exam templates — reusable exam structures (discipline + difficulty distribution)
 */
export const examTemplates = mysqlTable("exam_templates", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  name: varchar("name", { length: 255 }).notNull(),
  description: text("description"),
  discipline: varchar("discipline", { length: 255 }),
  // JSON: [{ difficulty: "easy", count: 5 }, { difficulty: "medium", count: 3 }, ...]
  structure: text("structure").notNull(),
  shuffleMode: mysqlEnum("shuffle_mode", ["all", "questions", "items", "none"]).default("all").notNull(),
  numberOfVersions: int("number_of_versions").default(1),
  totalPoints: decimal("total_points", { precision: 6, scale: 2 }),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type ExamTemplate = typeof examTemplates.$inferSelect;
export type InsertExamTemplate = typeof examTemplates.$inferInsert;

/**
 * Blog posts — simple CMS for SEO-managed educational posts
 */
export const blogPosts = mysqlTable("blog_posts", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  title: varchar("title", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  excerpt: varchar("excerpt", { length: 512 }),
  markdown: text("markdown"),
  html: text("html"),
  published: mysqlEnum("published", ["true", "false"]).default("false").notNull(),
  publishedAt: timestamp("published_at"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type BlogPost = typeof blogPosts.$inferSelect;
export type InsertBlogPost = typeof blogPosts.$inferInsert;

/**
 * Support tickets — helpdesk messages managed by admin
 */
export const supportTickets = mysqlTable("support_tickets", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  title: varchar("title", { length: 255 }).notNull(),
  description: text("description").notNull(),
  status: mysqlEnum("status", ["open", "in_progress", "resolved", "closed"]).default("open").notNull(),
  priority: mysqlEnum("priority", ["low", "medium", "high"]).default("medium").notNull(),
  adminResponse: text("admin_response"),
  respondedAt: timestamp("responded_at"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type SupportTicket = typeof supportTickets.$inferSelect;
export type InsertSupportTicket = typeof supportTickets.$inferInsert;

/**
 * Project quotes — enterprise requests for test systems or custom question banks
 */
export const projectQuotes = mysqlTable("project_quotes", {
  id: int("id").autoincrement().primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  email: varchar("email", { length: 255 }).notNull(),
  phone: varchar("phone", { length: 50 }).notNull(),
  company: varchar("company", { length: 255 }).notNull(),
  role: varchar("role", { length: 255 }).notNull(),
  projectType: varchar("project_type", { length: 100 }).notNull(),
  estimatedVolume: varchar("estimated_volume", { length: 100 }).notNull(),
  timeframe: varchar("timeframe", { length: 100 }).notNull(),
  description: text("description").notNull(),
  features: text("features"),
  status: mysqlEnum("status", ["pending", "contacted", "closed", "declined"]).default("pending").notNull(),
  adminNotes: text("admin_notes"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type ProjectQuote = typeof projectQuotes.$inferSelect;
export type InsertProjectQuote = typeof projectQuotes.$inferInsert;
/**
 * MercadoPago recurring subscriptions
 */
export const subscriptions = mysqlTable("subscriptions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().references(() => users.id),
  planKey: mysqlEnum("plan_key", ["basic", "pro", "enterprise"]).notNull(),
  mpPreapprovalId: varchar("mp_preapproval_id", { length: 255 }).notNull().unique(),
  status: mysqlEnum("status", ["pending", "authorized", "paused", "cancelled"]).default("pending").notNull(),
  currentPeriodEnd: timestamp("current_period_end"),
  cancelledAt: timestamp("cancelled_at"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Subscription = typeof subscriptions.$inferSelect;
export type InsertSubscription = typeof subscriptions.$inferInsert;

/**
 * Billing profile — payer identity collected before MP checkout
 */
export const billingProfiles = mysqlTable("billing_profiles", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("user_id").notNull().unique().references(() => users.id),
  fullName: varchar("full_name", { length: 255 }).notNull(),
  cpf: varchar("cpf", { length: 14 }).notNull(),
  phone: varchar("phone", { length: 20 }).notNull(),
  country: varchar("country", { length: 2 }).default("BR").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type BillingProfile = typeof billingProfiles.$inferSelect;
export type InsertBillingProfile = typeof billingProfiles.$inferInsert;
