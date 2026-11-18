CREATE TABLE `answer_sheet_templates` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` int NOT NULL,
	`name` varchar(255) NOT NULL,
	`config` text NOT NULL,
	`published` enum('true','false') NOT NULL DEFAULT 'false',
	`published_at` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `answer_sheet_templates_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `answer_sheet_templates` ADD CONSTRAINT `answer_sheet_templates_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE no action ON UPDATE no action;