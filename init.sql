-- BagParser schema (docs/SPEC.md §3)
-- Runs once, on the first start of an empty Postgres volume.
-- Items are NOT seeded here: the API upserts them from shared/items.json on every start.

CREATE TABLE items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL,
    npc_price INT NOT NULL DEFAULT 0,
    npc_vendor_category VARCHAR(50) NOT NULL,
    weight_oz DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    is_imbuement BOOLEAN NOT NULL DEFAULT FALSE,
    is_task_creature_product BOOLEAN NOT NULL DEFAULT FALSE,
    market_priority_alert BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_items_name ON items(LOWER(name));
CREATE INDEX idx_items_category ON items(npc_vendor_category);

CREATE TABLE hunting_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(80) NOT NULL,
    recommended_min_level INT NOT NULL,
    recommended_vocation VARCHAR(20) DEFAULT 'ALL',
    access_quest VARCHAR(200) NULL,
    map_coordinates VARCHAR(100) NULL,
    -- Added for the SpawnRank formula (SPEC §6): P and A are per-zone factors
    profitability_factor INT NOT NULL DEFAULT 3 CHECK (profitability_factor BETWEEN 1 AND 5),
    access_friction INT NOT NULL DEFAULT 0 CHECK (access_friction BETWEEN 0 AND 5),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE creatures (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    grizzly_task_points INT DEFAULT 0,
    is_weekly_bounty BOOLEAN DEFAULT FALSE,
    -- TibiaData race id (e.g. "dragonlord") and library image; filled by the API sync (D-14)
    race VARCHAR(100) NULL UNIQUE,
    image_url VARCHAR(255) NULL
);

CREATE TABLE spawn_densities (
    id SERIAL PRIMARY KEY,
    hunting_zone_id INT REFERENCES hunting_zones(id) ON DELETE CASCADE,
    creature_id INT REFERENCES creatures(id) ON DELETE CASCADE,
    density_tier INT NOT NULL CHECK (density_tier BETWEEN 1 AND 5),
    spawn_distribution_note VARCHAR(255) NULL,
    UNIQUE (hunting_zone_id, creature_id)
);

CREATE INDEX idx_spawn_density ON spawn_densities(creature_id, density_tier DESC);

CREATE TABLE bosses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    cooldown_hours INT DEFAULT 20
);

CREATE TABLE boss_drop_logs (
    id BIGSERIAL PRIMARY KEY,
    boss_id INT REFERENCES bosses(id) ON DELETE CASCADE,
    player_identifier_hash VARCHAR(64) NULL,
    payload_hash VARCHAR(64) NOT NULL UNIQUE,
    drop_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    raw_loot_line TEXT NOT NULL,
    total_npc_value INT NOT NULL DEFAULT 0,
    has_rare_item BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_boss_drop_logs_boss ON boss_drop_logs(boss_id);

CREATE TABLE boss_drop_aggregated_stats (
    boss_id INT REFERENCES bosses(id) ON DELETE CASCADE,
    item_id INT REFERENCES items(id) ON DELETE CASCADE,
    total_recorded_drops BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY(boss_id, item_id)
);

-- ---------------------------------------------------------------------------
-- SAMPLE DATA — illustrative only. Review/replace with curated data before launch.
-- ---------------------------------------------------------------------------

INSERT INTO bosses (name, cooldown_hours) VALUES
    ('Ferumbras', 20),
    ('Ghazbaran', 20),
    ('Morgaroth', 20),
    ('Orshabaal', 20),
    ('The Old Widow', 20),
    ('Zulazza the Corruptor', 20),
    ('Grand Master Oberon', 20),
    ('Scarlett Etzel', 20);

-- is_weekly_bounty stays FALSE: weekly tasks differ per character (D-34)
INSERT INTO creatures (name, grizzly_task_points, is_weekly_bounty) VALUES
    ('Dragon', 2, FALSE),
    ('Dragon Lord', 3, FALSE),
    ('Hero', 2, FALSE),
    ('Giant Spider', 2, FALSE),
    ('Wyvern', 1, FALSE),
    ('Hydra', 3, FALSE),
    ('Lizard Chosen', 3, FALSE),
    ('Lizard Dragon Priest', 3, FALSE),
    ('Lizard High Guard', 3, FALSE);

INSERT INTO hunting_zones (name, city, recommended_min_level, recommended_vocation, access_quest, profitability_factor, access_friction) VALUES
    ('Darashia Dragon Lair', 'Darashia', 60, 'ALL', NULL, 3, 0),
    ('Fenrock', 'Svargrond', 80, 'ALL', NULL, 3, 1),
    ('Goroma Dragon Lords', 'Liberty Bay', 120, 'ALL', 'Shattered Isles access', 4, 2),
    ('Edron Hero Cave', 'Edron', 70, 'ALL', NULL, 3, 0),
    ('Port Hope Giant Spiders', 'Port Hope', 90, 'ALL', NULL, 3, 0),
    ('Banuta Hydras', 'Port Hope', 130, 'ALL', 'Banuta access', 4, 3),
    ('Zzaion', 'Farmine', 150, 'ALL', 'Wrath of the Emperor (partial)', 4, 3);

INSERT INTO spawn_densities (hunting_zone_id, creature_id, density_tier, spawn_distribution_note)
SELECT z.id, c.id, v.tier, v.note
FROM (VALUES
    ('Darashia Dragon Lair', 'Dragon', 4, 'Several small lairs, short respawn'),
    ('Darashia Dragon Lair', 'Dragon Lord', 1, 'Occasional DL in the deeper floor'),
    ('Fenrock', 'Dragon', 3, 'Spread around the island'),
    ('Fenrock', 'Dragon Lord', 2, 'Lower floor'),
    ('Goroma Dragon Lords', 'Dragon Lord', 4, 'Dense DL caves'),
    ('Goroma Dragon Lords', 'Dragon', 2, NULL),
    ('Edron Hero Cave', 'Hero', 4, 'Circular route'),
    ('Port Hope Giant Spiders', 'Giant Spider', 3, NULL),
    ('Banuta Hydras', 'Hydra', 4, 'Lower floors'),
    ('Zzaion', 'Lizard Chosen', 3, NULL),
    ('Zzaion', 'Lizard Dragon Priest', 3, NULL),
    ('Zzaion', 'Lizard High Guard', 3, NULL)
) AS v(zone, creature, tier, note)
JOIN hunting_zones z ON z.name = v.zone
JOIN creatures c ON c.name = v.creature;
