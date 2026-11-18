CREATE TABLE `exam_templates` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`name` varchar(255) NOT NULL,
	`description` text,
	`discipline` varchar(255),
	`structure` text NOT NULL,
	`shuffle_mode` enum('all','questions','items','none') NOT NULL DEFAULT 'all',
	`number_of_versions` int DEFAULT 1,
	`total_points` decimal(6,2),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `exam_templates_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `question_versions` (
	`id` int AUTO_INCREMENT NOT NULL,
	`question_id` int NOT NULL,
	`user_id` int NOT NULL,
	`version_number` int NOT NULL,
	`snapshot` text NOT NULL,
	`change_summary` varchar(255),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `question_versions_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `exam_questions` ADD `is_voided` enum('true','false') DEFAULT 'false' NOT NULL;--> statement-breakpoint
ALTER TABLE `exam_questions` ADD `voided_at` timestamp;--> statement-breakpoint
ALTER TABLE `exams` ADD `shuffle_mode` enum('all','questions','items','none') DEFAULT 'all' NOT NULL;--> statement-breakpoint
ALTER TABLE `exams` ADD `print_font_size` enum('small','normal','large') DEFAULT 'normal' NOT NULL;--> statement-breakpoint
ALTER TABLE `exams` ADD `print_margins` enum('compact','normal','wide') DEFAULT 'normal' NOT NULL;--> statement-breakpoint
ALTER TABLE `sharing_links` ADD `result_mode` enum('none','score','answer_key','all') DEFAULT 'none' NOT NULL;--> statement-breakpoint
ALTER TABLE `students` ADD `portal_token` varchar(64);--> statement-breakpoint
ALTER TABLE `user_profiles` ADD `discipline_colors` text;--> statement-breakpoint
ALTER TABLE `students` ADD CONSTRAINT `students_portal_token_unique` UNIQUE(`portal_token`);--> statement-breakpoint
ALTER TABLE `exam_templates` ADD CONSTRAINT `exam_templates_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `question_versions` ADD CONSTRAINT `question_versions_question_id_questions_id_fk` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `question_versions` ADD CONSTRAINT `question_versions_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;