CREATE TABLE `content_requests` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int,
	`email` varchar(256),
	`name` varchar(256),
	`requestType` enum('multiple_choice','discursive','both') NOT NULL,
	`disciplineId` int NOT NULL,
	`subjectId` int NOT NULL,
	`topicId` int,
	`subjectTag` varchar(256),
	`notes` text,
	`status` enum('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `content_requests_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `idx_content_req_user` ON `content_requests` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_content_req_status` ON `content_requests` (`status`);