-- Import batch history
CREATE TABLE IF NOT EXISTS import_batches (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(256) NOT NULL,
  source     ENUM('json','csv','manual') NOT NULL DEFAULT 'json',
  importedBy INT NOT NULL,
  questionCount INT NOT NULL DEFAULT 0,
  createdAt  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE questions ADD COLUMN batchId INT NULL;
ALTER TABLE questions ADD INDEX idx_q_batch_id (batchId);
