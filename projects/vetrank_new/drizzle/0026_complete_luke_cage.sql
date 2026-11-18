CREATE TABLE `flashcard_reviews` (
	`id` int AUTO_INCREMENT NOT NULL,
	`userId` int NOT NULL,
	`flashcardId` int NOT NULL,
	`ease` float NOT NULL DEFAULT 2.5,
	`intervalDays` float NOT NULL DEFAULT 0,
	`repetitions` int NOT NULL DEFAULT 0,
	`lapses` int NOT NULL DEFAULT 0,
	`dueAt` timestamp NOT NULL DEFAULT (now()),
	`lastReviewedAt` timestamp,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `flashcard_reviews_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `flashcards` (
	`id` int AUTO_INCREMENT NOT NULL,
	`disciplineId` int NOT NULL,
	`subjectId` int,
	`topicId` int,
	`front` text NOT NULL,
	`back` text NOT NULL,
	`sourceQuestionId` int,
	`source` enum('question','import','manual') NOT NULL DEFAULT 'question',
	`active` boolean NOT NULL DEFAULT true,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `flashcards_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `idx_fcr_user_due` ON `flashcard_reviews` (`userId`,`dueAt`);--> statement-breakpoint
CREATE INDEX `idx_fcr_user_card` ON `flashcard_reviews` (`userId`,`flashcardId`);--> statement-breakpoint
CREATE INDEX `idx_fc_subject` ON `flashcards` (`active`,`subjectId`);--> statement-breakpoint
CREATE INDEX `idx_fc_discipline` ON `flashcards` (`active`,`disciplineId`);--> statement-breakpoint
CREATE INDEX `idx_fc_source_q` ON `flashcards` (`sourceQuestionId`);