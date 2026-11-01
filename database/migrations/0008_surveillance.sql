-- Public-health intelligence (features 21-30). Additive only.

-- Imported aggregate surveillance data (feature 28). Each file is parsed,
-- validated and previewed first; rows are written only on commit.
CREATE TABLE surveillance_imports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(160) NOT NULL,
  format ENUM('csv','json') NOT NULL,
  sha256 CHAR(64) NOT NULL,
  source_name VARCHAR(120) NOT NULL,
  rows_total INT UNSIGNED NOT NULL DEFAULT 0,
  rows_ok INT UNSIGNED NOT NULL DEFAULT 0,
  rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
  errors_json MEDIUMTEXT NULL COMMENT 'First 200 row errors',
  status ENUM('previewed','committed','rolled_back','discarded') NOT NULL DEFAULT 'previewed',
  uploaded_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  committed_at DATETIME NULL,
  KEY ix_imports_status (status, created_at),
  CONSTRAINT fk_imports_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE surveillance_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  import_id INT UNSIGNED NOT NULL,
  disease VARCHAR(20) NOT NULL,
  period_start DATE NOT NULL COMMENT 'Day the counts refer to',
  state_code CHAR(2) NOT NULL,
  facility_key INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'facility id, 0 when not facility-level (keeps the unique key exact)',
  facility_id INT UNSIGNED NULL,
  sex ENUM('any','female','male') NOT NULL DEFAULT 'any',
  age_band ENUM('all','0-17','18-44','45-64','65+') NOT NULL DEFAULT 'all',
  tests INT UNSIGNED NOT NULL,
  positives INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_records_cell (disease, period_start, state_code, facility_key, sex, age_band),
  KEY ix_records_import (import_id),
  KEY ix_records_period (disease, period_start),
  CONSTRAINT fk_records_import FOREIGN KEY (import_id) REFERENCES surveillance_imports (id) ON DELETE CASCADE,
  CONSTRAINT fk_records_state FOREIGN KEY (state_code) REFERENCES states (code),
  CONSTRAINT fk_records_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL,
  CONSTRAINT ck_records_counts CHECK (positives <= tests)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbreak thresholds and the alerts they raise (feature 23).
CREATE TABLE surveillance_thresholds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  disease VARCHAR(20) NOT NULL,
  state_code CHAR(2) NULL COMMENT 'NULL = every state separately',
  method ENUM('count','positivity','baseline') NOT NULL,
  window_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
  threshold DECIMAL(10,3) NOT NULL COMMENT 'count, percent, or SDs above baseline',
  min_tests INT UNSIGNED NOT NULL DEFAULT 20,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_thresholds_disease (disease, is_active),
  CONSTRAINT fk_thresholds_state FOREIGN KEY (state_code) REFERENCES states (code),
  CONSTRAINT fk_thresholds_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbreak_alerts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  threshold_id INT UNSIGNED NOT NULL,
  disease VARCHAR(20) NOT NULL,
  state_code CHAR(2) NOT NULL,
  window_start DATE NOT NULL,
  window_end DATE NOT NULL,
  tests INT UNSIGNED NOT NULL,
  positives INT UNSIGNED NOT NULL,
  observed DECIMAL(12,3) NOT NULL,
  expected DECIMAL(12,3) NULL,
  status ENUM('open','acknowledged','closed') NOT NULL DEFAULT 'open',
  note VARCHAR(500) NULL,
  acknowledged_by_user_id INT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_alert_window (threshold_id, state_code, window_end),
  KEY ix_alerts_status (status, created_at),
  CONSTRAINT fk_alerts_threshold FOREIGN KEY (threshold_id) REFERENCES surveillance_thresholds (id) ON DELETE CASCADE,
  CONSTRAINT fk_alerts_state FOREIGN KEY (state_code) REFERENCES states (code),
  CONSTRAINT fk_alerts_user FOREIGN KEY (acknowledged_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
