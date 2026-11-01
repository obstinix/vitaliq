-- Notification channels, preferences and broadcasts (features 31-37). Additive only.

-- Per-user opt-in for each delivery channel and category. In-app
-- notifications are always on; a missing row means "off".
CREATE TABLE notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  category VARCHAR(20) NOT NULL,
  channel ENUM('email','sms','whatsapp') NOT NULL,
  enabled TINYINT(1) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, category, channel),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per notification per outside channel, worked by bin/notify-worker.php.
-- 'simulated' = the log driver wrote the message locally; nothing left the server.
CREATE TABLE notification_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  notification_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  channel ENUM('email','sms','whatsapp') NOT NULL,
  recipient VARCHAR(254) NOT NULL,
  status ENUM('queued','sent','simulated','failed','skipped') NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  provider_ref VARCHAR(80) NULL,
  last_error VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY ix_deliveries_queue (status, next_attempt_at),
  KEY ix_deliveries_user (user_id, created_at),
  CONSTRAINT fk_deliveries_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
  CONSTRAINT fk_deliveries_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE broadcasts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(140) NOT NULL,
  body VARCHAR(300) NOT NULL,
  link_path VARCHAR(200) NULL,
  audience ENUM('everyone','patients','staff','role','state') NOT NULL,
  audience_value VARCHAR(20) NULL COMMENT 'role or state code',
  recipients INT UNSIGNED NOT NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_broadcasts_created (created_at),
  CONSTRAINT fk_broadcasts_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
