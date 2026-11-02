-- Advanced capabilities (features 51-60). Additive only.

-- Health information assistant conversations (feature 51).
CREATE TABLE assistant_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  content TEXT NOT NULL,
  source ENUM('provider','safety','unavailable') NOT NULL DEFAULT 'provider',
  created_at DATETIME NOT NULL,
  KEY ix_assistant_user (user_id, id),
  CONSTRAINT fk_assistant_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Symptom questionnaire results (feature 54).
CREATE TABLE triage_assessments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  answers VARCHAR(2000) NOT NULL COMMENT 'JSON',
  outcome ENUM('emergency','urgent','test','self_care') NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_triage_user (user_id, created_at),
  CONSTRAINT fk_triage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurable alert rules (feature 55).
CREATE TABLE alert_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  event VARCHAR(40) NOT NULL,
  match_value VARCHAR(40) NULL COMMENT 'e.g. a test code or a flag; NULL matches every event of the type',
  recipient_role VARCHAR(20) NULL,
  recipient_user_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_alert_rules_event (event, is_active),
  CONSTRAINT fk_alert_rules_user FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_alert_rules_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alert_rule_hits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rule_id INT UNSIGNED NOT NULL,
  summary VARCHAR(300) NOT NULL,
  link_path VARCHAR(200) NULL,
  recipients INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_hits_rule (rule_id, created_at),
  CONSTRAINT fk_hits_rule FOREIGN KEY (rule_id) REFERENCES alert_rules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report verification codes (feature 56). Only a hash of each code is stored.
CREATE TABLE report_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('test_request','lab_order') NOT NULL,
  ref_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  issued_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  checks INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_verify_code (code_hash),
  UNIQUE KEY uq_verify_ref (kind, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public API keys (features 57, 60). Only a hash of each key is stored.
CREATE TABLE api_keys (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  key_prefix CHAR(12) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  scopes VARCHAR(200) NOT NULL COMMENT 'Space-separated',
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_api_keys_hash (key_hash),
  CONSTRAINT fk_api_keys_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
