CREATE TABLE `course_certificates` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`courseId` int NOT NULL,
	`validationCode` varchar(64) NOT NULL,
	`issuedAt` timestamp NOT NULL DEFAULT (now()),
	`pdfUrl` varchar(512),
	`hash` varchar(64),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `course_certificates_id` PRIMARY KEY(`id`),
	CONSTRAINT `course_certificates_validationCode_unique` UNIQUE(`validationCode`)
);
--> statement-breakpoint
CREATE TABLE `course_lessons` (
	`id` int AUTO_INCREMENT NOT NULL,
	`moduleId` int NOT NULL,
	`courseId` int NOT NULL,
	`title` varchar(256) NOT NULL,
	`type` enum('video','ebook','questions') NOT NULL,
	`order` int NOT NULL,
	`isRequired` boolean NOT NULL DEFAULT true,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `course_lessons_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `course_modules` (
	`id` int AUTO_INCREMENT NOT NULL,
	`courseId` int NOT NULL,
	`title` varchar(256) NOT NULL,
	`order` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `course_modules_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `course_progress` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`courseId` int NOT NULL,
	`lessonId` int NOT NULL,
	`status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
	`watchedPercent` int NOT NULL DEFAULT 0,
	`completedAt` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `course_progress_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `course_purchases` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`courseId` int NOT NULL,
	`pricePaid` decimal(8,2) NOT NULL,
	`status` enum('pending','active','refunded','cancelled') NOT NULL DEFAULT 'pending',
	`transactionId` varchar(128),
	`purchasedAt` timestamp NOT NULL DEFAULT (now()),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `course_purchases_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `courses` (
	`id` int AUTO_INCREMENT NOT NULL,
	`slug` varchar(128) NOT NULL,
	`title` varchar(256) NOT NULL,
	`description` text,
	`objectives` text,
	`prerequisites` text,
	`thumbnailUrl` varchar(512),
	`trailerUrl` varchar(512),
	`instructorId` int,
	`instructorName` varchar(256),
	`instructorBio` text,
	`instructorAvatarUrl` varchar(512),
	`level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
	`category` varchar(128),
	`durationHours` int NOT NULL DEFAULT 0,
	`price` decimal(8,2) NOT NULL,
	`status` enum('draft','published') NOT NULL DEFAULT 'draft',
	`active` boolean NOT NULL DEFAULT true,
	`createdBy` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `courses_id` PRIMARY KEY(`id`),
	CONSTRAINT `courses_slug_unique` UNIQUE(`slug`)
);
--> statement-breakpoint
CREATE TABLE `ebook_download_logs` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`lessonEbookId` int NOT NULL,
	`courseId` int NOT NULL,
	`ipAddress` varchar(64) NOT NULL,
	`userAgent` text,
	`downloadHash` varchar(64) NOT NULL,
	`downloadedAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `ebook_download_logs_id` PRIMARY KEY(`id`),
	CONSTRAINT `ebook_download_logs_downloadHash_unique` UNIQUE(`downloadHash`)
);
--> statement-breakpoint
CREATE TABLE `lesson_ebooks` (
	`id` int AUTO_INCREMENT NOT NULL,
	`lessonId` int NOT NULL,
	`fileUrl` varchar(512) NOT NULL,
	`fileName` varchar(256) NOT NULL,
	`allowDownload` boolean NOT NULL DEFAULT false,
	`order` int NOT NULL DEFAULT 1,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `lesson_ebooks_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `lesson_question_attempts` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`lessonId` int NOT NULL,
	`courseId` int NOT NULL,
	`questionsJson` json NOT NULL,
	`answersJson` json NOT NULL,
	`totalQuestions` int NOT NULL,
	`correctAnswers` int NOT NULL DEFAULT 0,
	`score` int NOT NULL DEFAULT 0,
	`passed` boolean NOT NULL DEFAULT false,
	`startedAt` timestamp NOT NULL DEFAULT (now()),
	`finishedAt` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `lesson_question_attempts_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `lesson_questions_config` (
	`id` int AUTO_INCREMENT NOT NULL,
	`lessonId` int NOT NULL,
	`filtersJson` json NOT NULL,
	`minQuestions` int NOT NULL DEFAULT 10,
	`minPassingScore` int NOT NULL DEFAULT 70,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `lesson_questions_config_id` PRIMARY KEY(`id`),
	CONSTRAINT `lesson_questions_config_lessonId_unique` UNIQUE(`lessonId`)
);
--> statement-breakpoint
CREATE TABLE `lesson_videos` (
	`id` int AUTO_INCREMENT NOT NULL,
	`lessonId` int NOT NULL,
	`videoUrl` varchar(512) NOT NULL,
	`provider` enum('vimeo','panda','bunny','youtube','other') NOT NULL DEFAULT 'other',
	`durationSeconds` int NOT NULL DEFAULT 0,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `lesson_videos_id` PRIMARY KEY(`id`),
	CONSTRAINT `lesson_videos_lessonId_unique` UNIQUE(`lessonId`)
);
--> statement-breakpoint
CREATE INDEX `idx_certificates_user_course` ON `course_certificates` (`userId`,`courseId`);--> statement-breakpoint
CREATE INDEX `idx_certificates_validation_code` ON `course_certificates` (`validationCode`);--> statement-breakpoint
CREATE INDEX `idx_course_lessons_module` ON `course_lessons` (`moduleId`,`order`);--> statement-breakpoint
CREATE INDEX `idx_course_lessons_course` ON `course_lessons` (`courseId`);--> statement-breakpoint
CREATE INDEX `idx_course_modules_course` ON `course_modules` (`courseId`,`order`);--> statement-breakpoint
CREATE INDEX `idx_course_progress_user_lesson` ON `course_progress` (`userId`,`lessonId`);--> statement-breakpoint
CREATE INDEX `idx_course_progress_user_course` ON `course_progress` (`userId`,`courseId`);--> statement-breakpoint
CREATE INDEX `idx_course_purchases_user_course` ON `course_purchases` (`userId`,`courseId`);--> statement-breakpoint
CREATE INDEX `idx_course_purchases_status` ON `course_purchases` (`status`);--> statement-breakpoint
CREATE INDEX `idx_course_purchases_transaction` ON `course_purchases` (`transactionId`);--> statement-breakpoint
CREATE INDEX `idx_courses_status` ON `courses` (`status`,`active`);--> statement-breakpoint
CREATE INDEX `idx_courses_category` ON `courses` (`category`);--> statement-breakpoint
CREATE INDEX `idx_courses_level` ON `courses` (`level`);--> statement-breakpoint
CREATE INDEX `idx_ebook_logs_user` ON `ebook_download_logs` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_ebook_logs_ebook` ON `ebook_download_logs` (`lessonEbookId`);--> statement-breakpoint
CREATE INDEX `idx_lesson_ebooks_lesson` ON `lesson_ebooks` (`lessonId`,`order`);--> statement-breakpoint
CREATE INDEX `idx_lqa_user_lesson` ON `lesson_question_attempts` (`userId`,`lessonId`);