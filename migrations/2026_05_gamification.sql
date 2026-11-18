-- ============================================================
-- Migration: Gamificação completa (streaks, coins, loja, ligas)
-- Data: 2026-05-23
-- Aplicar uma vez no banco de produção (MySQL 8)
-- ============================================================

-- ─── 1. Novas colunas em USERS ────────────────────────────────────────────────
ALTER TABLE users
  ADD COLUMN lastPracticeDate       VARCHAR(10)          NULL        COMMENT 'YYYY-MM-DD — data da última sessão com 5+ questões',
  ADD COLUMN coins                  INT         NOT NULL DEFAULT 0   COMMENT 'Saldo de VetCoins',
  ADD COLUMN streakFreezes          INT         NOT NULL DEFAULT 0   COMMENT 'Congelamentos de streak disponíveis (máx 2)',
  ADD COLUMN streakRecoveryUsedAt   VARCHAR(10)          NULL        COMMENT 'YYYY-MM — mês em que usou recuperação de streak';

-- ─── 2. Nova coluna em BADGES ────────────────────────────────────────────────
ALTER TABLE badges
  ADD COLUMN rarity ENUM('common','rare','epic','legendary','hidden') NOT NULL DEFAULT 'common'
  AFTER icon;

-- ─── 3. Tabela COIN_EVENTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS coin_events (
  id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userId     INT          NOT NULL,
  amount     INT          NOT NULL COMMENT 'Positivo = ganho, negativo = gasto',
  reason     VARCHAR(128) NOT NULL,
  createdAt  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ce_user (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Tabela SHOP_ITEMS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS shop_items (
  id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(64)  NOT NULL UNIQUE,
  namePt        VARCHAR(128) NOT NULL,
  nameEn        VARCHAR(128) NOT NULL,
  descriptionPt TEXT,
  descriptionEn TEXT,
  productType   ENUM('gamification','course','content') NOT NULL DEFAULT 'gamification',
  cost          INT          NOT NULL COMMENT 'VetCoins (gamification) ou centavos BRL (curso)',
  category      ENUM('utility','cosmetic','luck','education') NOT NULL,
  itemType      VARCHAR(64)  NOT NULL COMMENT 'freeze, recovery, chest_rare, course_id, etc.',
  icon          VARCHAR(64),
  imageUrl      VARCHAR(512),
  contentRef    JSON,
  sortOrder     INT          NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  createdAt     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. Tabela PURCHASES ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS purchases (
  id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userId          INT          NOT NULL,
  itemId          INT          NOT NULL,
  productType     ENUM('gamification','course','content') NOT NULL DEFAULT 'gamification',
  coinsSpent      INT          NULL COMMENT 'Para itens de gamificação',
  priceAtPurchase INT          NULL COMMENT 'Centavos BRL para compras pagas',
  paymentRef      VARCHAR(128) NULL COMMENT 'ID da transação Mercado Pago',
  createdAt       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pur_user (userId),
  INDEX idx_pur_item (itemId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. Tabela DAILY_CHESTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_chests (
  id           INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userId       INT         NOT NULL,
  claimedDate  VARCHAR(10) NOT NULL COMMENT 'YYYY-MM-DD',
  rewardType   VARCHAR(32) NOT NULL COMMENT 'coins, freeze, xp_bonus',
  rewardAmount INT         NOT NULL DEFAULT 0,
  createdAt    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dc_user_date (userId, claimedDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. Tabela DAILY_ROULETTE ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_roulette (
  id           INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userId       INT         NOT NULL,
  spunDate     VARCHAR(10) NOT NULL COMMENT 'YYYY-MM-DD',
  rewardType   VARCHAR(32) NOT NULL,
  rewardAmount INT         NOT NULL DEFAULT 0,
  createdAt    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dr_user_date (userId, spunDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 8. Tabela LEAGUES ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leagues (
  id          INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
  weekKey     VARCHAR(10) NOT NULL COMMENT 'YYYY-WW',
  division    ENUM('iron','bronze','silver','gold','platinum','diamond','master') NOT NULL,
  groupIndex  INT         NOT NULL COMMENT 'Índice do grupo dentro da divisão',
  status      ENUM('active','completed') NOT NULL DEFAULT 'active',
  createdAt   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lg_week_div (weekKey, division, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 9. Tabela LEAGUE_MEMBERSHIPS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS league_memberships (
  id         INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userId     INT      NOT NULL,
  leagueId   INT      NOT NULL,
  division   ENUM('iron','bronze','silver','gold','platinum','diamond','master') NOT NULL,
  xpGained   INT      NOT NULL DEFAULT 0,
  `rank`     INT      NULL,
  result     ENUM('promoted','stayed','relegated','none') NOT NULL DEFAULT 'none',
  createdAt  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lm_user (userId),
  INDEX idx_lm_league (leagueId),
  UNIQUE KEY uq_lm_user_league (userId, leagueId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Verificação final ───────────────────────────────────────────────────────
SELECT 'Migration aplicada com sucesso.' AS status;
