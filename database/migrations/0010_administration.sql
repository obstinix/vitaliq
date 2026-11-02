-- Administration (features 39-45). Additive only.

-- Staff scheduling (feature 42).
CREATE TABLE staff_shifts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  facility_id INT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  duty ENUM('bench','collection','reception','on_call','surveillance','admin') NOT NULL,
  note VARCHAR(200) NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_shifts_user (user_id, starts_at),
  KEY ix_shifts_time (starts_at, ends_at),
  CONSTRAINT fk_shifts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_shifts_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL,
  CONSTRAINT fk_shifts_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_shifts_order CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup register (feature 43). Files live in storage/backups, encrypted.
CREATE TABLE backups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(80) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  tables_count SMALLINT UNSIGNED NOT NULL,
  rows_count BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL COMMENT 'Of the encrypted file',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_backups_file (filename),
  CONSTRAINT fk_backups_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
