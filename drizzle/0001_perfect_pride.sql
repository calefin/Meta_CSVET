CREATE TABLE `classes` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`institution_id` int,
	`course_id` int,
	`name` varchar(255) NOT NULL,
	`semester` enum('1','2') NOT NULL,
	`class_year` int NOT NULL,
	`archived` enum('true','false') NOT NULL DEFAULT 'false',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `classes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `courses` (
	`id` int AUTO_INCREMENT NOT NULL,
	`institution_id` int NOT NULL,
	`department_id` int,
	`name` varchar(255) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `courses_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `departments` (
	`id` int AUTO_INCREMENT NOT NULL,
	`institution_id` int NOT NULL,
	`name` varchar(255) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `departments_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `exam_scores` (
	`id` int AUTO_INCREMENT NOT NULL,
	`exam_id` int NOT NULL,
	`question_id` int NOT NULL,
	`points` decimal(5,2) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `exam_scores_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `institutions` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`name` varchar(255) NOT NULL,
	`type` enum('university','faculty','school') NOT NULL DEFAULT 'university',
	`address` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `institutions_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `question_images` (
	`id` int AUTO_INCREMENT NOT NULL,
	`question_id` int NOT NULL,
	`user_id` int NOT NULL,
	`original_file_name` varchar(255) NOT NULL,
	`storage_key` varchar(255) NOT NULL,
	`storage_path` varchar(500) NOT NULL,
	`file_size` int NOT NULL,
	`mime_type` varchar(50) NOT NULL,
	`width` int,
	`height` int,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `question_images_id` PRIMARY KEY(`id`),
	CONSTRAINT `question_images_storage_key_unique` UNIQUE(`storage_key`)
);
--> statement-breakpoint
CREATE TABLE `quiz_responses` (
	`id` int AUTO_INCREMENT NOT NULL,
	`sharing_link_id` int NOT NULL,
	`student_name` varchar(255) NOT NULL,
	`student_email` varchar(320),
	`answers` text NOT NULL,
	`score` decimal(6,2),
	`submitted_at` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `quiz_responses_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `sharing_links` (
	`id` int AUTO_INCREMENT NOT NULL,
	`exam_id` int NOT NULL,
	`user_id` int NOT NULL,
	`token` varchar(64) NOT NULL,
	`active` enum('true','false') NOT NULL DEFAULT 'true',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `sharing_links_id` PRIMARY KEY(`id`),
	CONSTRAINT `sharing_links_token_unique` UNIQUE(`token`)
);
--> statement-breakpoint
CREATE TABLE `student_classes` (
	`id` int AUTO_INCREMENT NOT NULL,
	`student_id` int NOT NULL,
	`class_id` int NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `student_classes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `student_grades` (
	`id` int AUTO_INCREMENT NOT NULL,
	`student_id` int NOT NULL,
	`exam_id` int NOT NULL,
	`score` decimal(6,2),
	`max_score` decimal(6,2),
	`answer_sheet_id` int,
	`graded_at` timestamp NOT NULL DEFAULT (now()),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `student_grades_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `students` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`student_id` varchar(50),
	`external_ref` varchar(100),
	`first_name` varchar(100) NOT NULL,
	`last_name` varchar(100) NOT NULL,
	`email` varchar(320),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `students_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `user_storage_quotas` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`quota_limit_mb` int DEFAULT 100,
	`used_storage_mb` int DEFAULT 0,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `user_storage_quotas_id` PRIMARY KEY(`id`),
	CONSTRAINT `user_storage_quotas_user_id_unique` UNIQUE(`user_id`)
);
--> statement-breakpoint
ALTER TABLE `questions` MODIFY COLUMN `type` enum('multiple_choice','multiple_choice_complex','essay','assertion_reason') NOT NULL;--> statement-breakpoint
ALTER TABLE `exams` ADD `institution_id` int;--> statement-breakpoint
ALTER TABLE `exams` ADD `class_id` int;--> statement-breakpoint
ALTER TABLE `exams` ADD `header_type` enum('standard','custom') DEFAULT 'standard' NOT NULL;--> statement-breakpoint
ALTER TABLE `exams` ADD `custom_header_url` text;--> statement-breakpoint
ALTER TABLE `exams` ADD `total_points` decimal(6,2);--> statement-breakpoint
ALTER TABLE `exams` ADD `exam_language` enum('pt','en','es') DEFAULT 'pt' NOT NULL;--> statement-breakpoint
ALTER TABLE `exams` ADD `exam_type` enum('full','answer_key_only') DEFAULT 'full' NOT NULL;--> statement-breakpoint
ALTER TABLE `questions` ADD `content` text;--> statement-breakpoint
ALTER TABLE `questions` ADD `subject` varchar(255);--> statement-breakpoint
ALTER TABLE `questions` ADD `total_images` int DEFAULT 0;--> statement-breakpoint
ALTER TABLE `classes` ADD CONSTRAINT `classes_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `classes` ADD CONSTRAINT `classes_institution_id_institutions_id_fk` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `classes` ADD CONSTRAINT `classes_course_id_courses_id_fk` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `courses` ADD CONSTRAINT `courses_institution_id_institutions_id_fk` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `courses` ADD CONSTRAINT `courses_department_id_departments_id_fk` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `departments` ADD CONSTRAINT `departments_institution_id_institutions_id_fk` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `exam_scores` ADD CONSTRAINT `exam_scores_exam_id_exams_id_fk` FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `exam_scores` ADD CONSTRAINT `exam_scores_question_id_questions_id_fk` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `institutions` ADD CONSTRAINT `institutions_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `question_images` ADD CONSTRAINT `question_images_question_id_questions_id_fk` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `question_images` ADD CONSTRAINT `question_images_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `quiz_responses` ADD CONSTRAINT `quiz_responses_sharing_link_id_sharing_links_id_fk` FOREIGN KEY (`sharing_link_id`) REFERENCES `sharing_links`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `sharing_links` ADD CONSTRAINT `sharing_links_exam_id_exams_id_fk` FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `sharing_links` ADD CONSTRAINT `sharing_links_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `student_classes` ADD CONSTRAINT `student_classes_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `student_classes` ADD CONSTRAINT `student_classes_class_id_classes_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `student_grades` ADD CONSTRAINT `student_grades_student_id_students_id_fk` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `student_grades` ADD CONSTRAINT `student_grades_exam_id_exams_id_fk` FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `student_grades` ADD CONSTRAINT `student_grades_answer_sheet_id_answer_sheets_id_fk` FOREIGN KEY (`answer_sheet_id`) REFERENCES `answer_sheets`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `students` ADD CONSTRAINT `students_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `user_storage_quotas` ADD CONSTRAINT `user_storage_quotas_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `exams` ADD CONSTRAINT `exams_institution_id_institutions_id_fk` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `exams` ADD CONSTRAINT `exams_class_id_classes_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE no action ON UPDATE no action;