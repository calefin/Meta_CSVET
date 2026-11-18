CREATE TABLE `trail_purchases` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`trailId` int NOT NULL,
	`pricePaid` decimal(8,2) NOT NULL,
	`status` enum('pending','active','refunded') NOT NULL DEFAULT 'pending',
	`transactionId` varchar(128),
	`purchasedAt` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `trail_purchases_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `trail_modules` ADD `cycleSize` int DEFAULT 10 NOT NULL;--> statement-breakpoint
ALTER TABLE `trail_modules` ADD `ebookUrl` varchar(512);--> statement-breakpoint
ALTER TABLE `trail_modules` ADD `ebookTitle` varchar(256);--> statement-breakpoint
ALTER TABLE `users` ADD `emailVerifiedAt` timestamp;--> statement-breakpoint
ALTER TABLE `users` ADD `emailVerificationToken` varchar(64);--> statement-breakpoint
ALTER TABLE `users` ADD `emailVerificationTokenExpiresAt` timestamp;