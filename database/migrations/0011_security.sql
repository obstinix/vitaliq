-- Security (features 46-50). Additive only.

-- TOTP two-factor authentication (feature 46). The secret is encrypted with
-- a key derived from APP_KEY; recovery codes are stored only as hashes.
CREATE TABLE user_two_factor (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  secret_enc VARCHAR(200) NOT NULL,
  enabled_at DATETIME NULL COMMENT 'NULL while enrolment is unconfirmed',
  last_used_step BIGINT UNSIGNED NULL COMMENT 'Blocks reuse of a code within its window',
  recovery_codes TEXT NULL COMMENT 'JSON list of SHA-256 hashes',
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signed-in sessions, so people can see and end them (feature 47).
CREATE TABLE user_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  key_hash CHAR(64) NOT NULL,
  device VARCHAR(80) NOT NULL COMMENT 'Browser and platform, from the user agent',
  device_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_sessions_key (key_hash),
  KEY ix_sessions_user (user_id, revoked_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account deletion requests (feature 49).
CREATE TABLE deletion_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  user_label VARCHAR(140) NOT NULL COMMENT 'Kept after the account is anonymised',
  reason VARCHAR(500) NULL,
  status ENUM('requested','completed','declined') NOT NULL DEFAULT 'requested',
  decision_note VARCHAR(500) NULL,
  processed_by_user_id INT UNSIGNED NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_deletion_status (status, created_at),
  CONSTRAINT fk_deletion_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_deletion_admin FOREIGN KEY (processed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
