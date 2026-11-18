CREATE TABLE `project_quotes` (
	`id` int AUTO_INCREMENT NOT NULL,
	`name` varchar(255) NOT NULL,
	`email` varchar(255) NOT NULL,
	`phone` varchar(50) NOT NULL,
	`company` varchar(255) NOT NULL,
	`role` varchar(255) NOT NULL,
	`project_type` varchar(100) NOT NULL,
	`estimated_volume` varchar(100) NOT NULL,
	`timeframe` varchar(100) NOT NULL,
	`description` text NOT NULL,
	`status` enum('pending','contacted','closed','declined') NOT NULL DEFAULT 'pending',
	`admin_notes` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `project_quotes_id` PRIMARY KEY(`id`)
);
