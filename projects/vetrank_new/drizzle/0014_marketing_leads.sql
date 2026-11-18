CREATE TABLE `leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(320) NOT NULL,
  `name` varchar(256) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source` varchar(64) NOT NULL DEFAULT 'manual',
  `status` enum('new','contacted','converted','lost') NOT NULL DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `convertedUserId` int DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_email_unique` (`email`),
  KEY `idx_leads_status` (`status`),
  KEY `idx_leads_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdBy` int NOT NULL,
  `subject` varchar(256) NOT NULL,
  `body` text NOT NULL,
  `target` varchar(64) NOT NULL,
  `recipientCount` int NOT NULL DEFAULT 0,
  `sentCount` int NOT NULL DEFAULT 0,
  `failedCount` int NOT NULL DEFAULT 0,
  `status` enum('sent','failed','partial') NOT NULL DEFAULT 'sent',
  `sentAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
