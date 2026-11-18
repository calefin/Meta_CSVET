CREATE TABLE `coin_events` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`amount` int NOT NULL,
	`reason` varchar(128) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `coin_events_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `daily_chests` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`claimedDate` varchar(10) NOT NULL,
	`rewardType` varchar(32) NOT NULL,
	`rewardAmount` int NOT NULL DEFAULT 0,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `daily_chests_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `daily_roulette` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`spunDate` varchar(10) NOT NULL,
	`rewardType` varchar(32) NOT NULL,
	`rewardAmount` int NOT NULL DEFAULT 0,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `daily_roulette_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `import_batches` (
	`id` int AUTO_INCREMENT NOT NULL,
	`name` varchar(256) NOT NULL,
	`source` enum('json','csv','manual') NOT NULL DEFAULT 'json',
	`importedBy` int NOT NULL,
	`questionCount` int NOT NULL DEFAULT 0,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `import_batches_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `league_memberships` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`leagueId` int NOT NULL,
	`division` enum('iron','bronze','silver','gold','platinum','diamond','master') NOT NULL,
	`xpGained` int NOT NULL DEFAULT 0,
	`rank` int,
	`result` enum('promoted','stayed','relegated','none') NOT NULL DEFAULT 'none',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `league_memberships_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `leagues` (
	`id` int AUTO_INCREMENT NOT NULL,
	`weekKey` varchar(10) NOT NULL,
	`division` enum('iron','bronze','silver','gold','platinum','diamond','master') NOT NULL,
	`groupIndex` int NOT NULL,
	`status` enum('active','completed') NOT NULL DEFAULT 'active',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `leagues_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `mp_webhook_events` (
	`id` int AUTO_INCREMENT NOT NULL,
	`mpEventId` varchar(128),
	`type` varchar(64) NOT NULL,
	`action` varchar(64),
	`status` varchar(64),
	`userId` int,
	`externalId` varchar(128),
	`amount` float,
	`planType` varchar(16),
	`resolved` boolean NOT NULL DEFAULT false,
	`adminNote` text,
	`raw` json,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `mp_webhook_events_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `purchases` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`itemId` int NOT NULL,
	`productType` enum('gamification','course','content') NOT NULL DEFAULT 'gamification',
	`coinsSpent` int,
	`priceAtPurchase` int,
	`paymentRef` varchar(128),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `purchases_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `shop_items` (
	`id` int AUTO_INCREMENT NOT NULL,
	`slug` varchar(64) NOT NULL,
	`namePt` varchar(128) NOT NULL,
	`nameEn` varchar(128) NOT NULL,
	`descriptionPt` text,
	`descriptionEn` text,
	`productType` enum('gamification','course','content') NOT NULL DEFAULT 'gamification',
	`cost` int NOT NULL,
	`category` enum('utility','cosmetic','luck','education') NOT NULL,
	`itemType` varchar(64) NOT NULL,
	`icon` varchar(64),
	`imageUrl` varchar(512),
	`contentRef` json,
	`sortOrder` int NOT NULL DEFAULT 0,
	`active` boolean NOT NULL DEFAULT true,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `shop_items_id` PRIMARY KEY(`id`),
	CONSTRAINT `shop_items_slug_unique` UNIQUE(`slug`)
);
--> statement-breakpoint
CREATE TABLE `support_messages` (
	`id` int AUTO_INCREMENT NOT NULL,
	`ticketId` int NOT NULL,
	`authorId` int NOT NULL,
	`authorType` enum('user','admin') NOT NULL,
	`message` text NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `support_messages_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `support_tickets` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`subject` varchar(256) NOT NULL,
	`category` enum('pagamento','acesso','bug','conteudo','sugestao','outros') NOT NULL DEFAULT 'outros',
	`priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
	`status` enum('open','in_progress','waiting_user','resolved','closed') NOT NULL DEFAULT 'open',
	`adminNote` text,
	`assignedTo` int,
	`closedAt` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `support_tickets_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `badges` ADD `rarity` enum('common','rare','epic','legendary','hidden') DEFAULT 'common' NOT NULL;--> statement-breakpoint
ALTER TABLE `questions` ADD `batchId` int;--> statement-breakpoint
ALTER TABLE `trails` ADD `accessType` enum('subscription','purchase') DEFAULT 'subscription' NOT NULL;--> statement-breakpoint
ALTER TABLE `trails` ADD `price` decimal(8,2);--> statement-breakpoint
ALTER TABLE `trails` ADD `imageUrl` varchar(512);--> statement-breakpoint
ALTER TABLE `users` ADD `lastPracticeDate` varchar(10);--> statement-breakpoint
ALTER TABLE `users` ADD `coins` int DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `users` ADD `streakFreezes` int DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `users` ADD `streakRecoveryUsedAt` varchar(10);--> statement-breakpoint
ALTER TABLE `users` ADD `avatarUrl` varchar(512);--> statement-breakpoint
CREATE INDEX `idx_mp_events_type` ON `mp_webhook_events` (`type`);--> statement-breakpoint
CREATE INDEX `idx_mp_events_user` ON `mp_webhook_events` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_mp_events_resolved` ON `mp_webhook_events` (`resolved`);--> statement-breakpoint
CREATE INDEX `idx_msg_ticket` ON `support_messages` (`ticketId`);--> statement-breakpoint
CREATE INDEX `idx_ticket_user` ON `support_tickets` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_ticket_status` ON `support_tickets` (`status`);