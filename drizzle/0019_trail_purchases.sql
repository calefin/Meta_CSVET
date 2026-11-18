ALTER TABLE `trail_modules` ADD COLUMN `ebookUrl` varchar(512);
--> statement-breakpoint
ALTER TABLE `trail_modules` ADD COLUMN `ebookTitle` varchar(256);
--> statement-breakpoint
CREATE TABLE `trail_purchases` (
  `id` int AUTO_INCREMENT NOT NULL,
  `userId` int NOT NULL,
  `trailId` int NOT NULL,
  `pricePaid` decimal(8,2) NOT NULL,
  `status` enum('pending','active','refunded') NOT NULL DEFAULT 'pending',
  `transactionId` varchar(128),
  `purchasedAt` timestamp,
  `createdAt` timestamp NOT NULL DEFAULT NOW(),
  `updatedAt` timestamp NOT NULL DEFAULT NOW() ON UPDATE NOW(),
  CONSTRAINT `trail_purchases_pk` PRIMARY KEY(`id`)
);
