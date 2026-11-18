CREATE TABLE `email_campaigns` (
	`id` int AUTO_INCREMENT NOT NULL,
	`createdBy` int NOT NULL,
	`subject` varchar(256) NOT NULL,
	`body` text NOT NULL,
	`target` varchar(64) NOT NULL,
	`recipientCount` int NOT NULL DEFAULT 0,
	`sentCount` int NOT NULL DEFAULT 0,
	`failedCount` int NOT NULL DEFAULT 0,
	`status` enum('sent','failed','partial') NOT NULL DEFAULT 'sent',
	`sentAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `email_campaigns_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `leads` (
	`id` int AUTO_INCREMENT NOT NULL,
	`email` varchar(320) NOT NULL,
	`name` varchar(256),
	`phone` varchar(20),
	`source` varchar(64) NOT NULL DEFAULT 'manual',
	`status` enum('new','contacted','converted','lost') NOT NULL DEFAULT 'new',
	`notes` text,
	`convertedUserId` int,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `leads_id` PRIMARY KEY(`id`),
	CONSTRAINT `leads_email_unique` UNIQUE(`email`)
);
--> statement-breakpoint
CREATE TABLE `topics` (
	`id` int AUTO_INCREMENT NOT NULL,
	`subjectId` int NOT NULL,
	`disciplineId` int NOT NULL,
	`slug` varchar(96) NOT NULL,
	`namePt` varchar(128) NOT NULL,
	`nameEn` varchar(128) NOT NULL,
	`active` boolean NOT NULL DEFAULT true,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `topics_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `questions` ADD `topicId` int;--> statement-breakpoint
ALTER TABLE `users` ADD `passwordHash` varchar(255);--> statement-breakpoint
ALTER TABLE `users` ADD `passwordResetToken` varchar(64);--> statement-breakpoint
ALTER TABLE `users` ADD `passwordResetTokenExpiresAt` timestamp;--> statement-breakpoint
ALTER TABLE `users` ADD `tosAcceptedIp` varchar(64);--> statement-breakpoint
CREATE INDEX `idx_leads_status` ON `leads` (`status`);--> statement-breakpoint
CREATE INDEX `idx_leads_email` ON `leads` (`email`);--> statement-breakpoint
CREATE INDEX `idx_topics_subject` ON `topics` (`subjectId`);--> statement-breakpoint
CREATE INDEX `idx_topics_discipline` ON `topics` (`disciplineId`);