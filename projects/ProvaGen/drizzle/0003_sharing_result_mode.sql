ALTER TABLE `sharing_links` ADD `result_mode` enum('none','score','answer_key','all') NOT NULL DEFAULT 'none';
