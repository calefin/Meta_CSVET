-- Réplica mínima da tabela `users` do ProvaGen (Drizzle), só para o Docker
-- local conseguir testar as migrations do gestão contra as mesmas FKs que
-- vão existir de verdade no banco compartilhado de produção. Não é gerenciada
-- por este app — em produção essa tabela já existe, criada pelo ProvaGen.
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `openId` VARCHAR(255) NOT NULL UNIQUE,
  `email` VARCHAR(320) UNIQUE,
  `password_hash` VARCHAR(255),
  `name` TEXT,
  `loginMethod` VARCHAR(64) NOT NULL DEFAULT 'email',
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `plan` ENUM('free','basic','pro','enterprise') NOT NULL DEFAULT 'free',
  `email_verified` ENUM('true','false') NOT NULL DEFAULT 'false',
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastSignedIn` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`openId`, `email`, `name`, `role`, `plan`)
VALUES ('test:owner-1', 'professor.teste@example.com', 'Professor de Teste', 'user', 'pro');
