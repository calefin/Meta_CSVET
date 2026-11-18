ALTER TABLE `users` ADD COLUMN `emailVerifiedAt` timestamp;
--> statement-breakpoint
ALTER TABLE `users` ADD COLUMN `emailVerificationToken` varchar(64);
--> statement-breakpoint
ALTER TABLE `users` ADD COLUMN `emailVerificationTokenExpiresAt` timestamp;
--> statement-breakpoint
UPDATE `users` SET `emailVerifiedAt` = COALESCE(`lastSignedIn`, NOW()) WHERE `loginMethod` = 'email';
