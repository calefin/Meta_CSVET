CREATE TABLE `concursos` (
	`id` int AUTO_INCREMENT NOT NULL,
	`slug` varchar(160) NOT NULL,
	`sourceUrl` varchar(512) NOT NULL,
	`sourceName` varchar(64) NOT NULL DEFAULT 'PCI Concursos',
	`orgao` varchar(256) NOT NULL,
	`uf` varchar(2),
	`cidade` varchar(128),
	`cargos` json,
	`vagasVet` varchar(64),
	`salarioMax` decimal(10,2),
	`inscricoesInicio` timestamp,
	`inscricoesFim` timestamp,
	`taxa` varchar(128),
	`banca` varchar(128),
	`bancaUrl` varchar(512),
	`orgaoUrl` varchar(512),
	`editalNumero` varchar(64),
	`escolaridade` varchar(64),
	`etapas` varchar(256),
	`resumo` text,
	`publishedAt` timestamp,
	`active` boolean NOT NULL DEFAULT true,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `concursos_id` PRIMARY KEY(`id`),
	CONSTRAINT `concursos_slug_unique` UNIQUE(`slug`),
	CONSTRAINT `concursos_sourceUrl_unique` UNIQUE(`sourceUrl`)
);
--> statement-breakpoint
CREATE INDEX `idx_concursos_fim` ON `concursos` (`active`,`inscricoesFim`);--> statement-breakpoint
CREATE INDEX `idx_concursos_uf` ON `concursos` (`uf`);