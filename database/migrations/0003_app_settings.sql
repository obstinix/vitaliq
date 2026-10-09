-- Key/value settings. The demo seeder records 'demo_data_seeded_at' here so
-- public statistics can say plainly that the records are synthetic.

CREATE TABLE app_settings (
  name VARCHAR(60) NOT NULL PRIMARY KEY,
  value VARCHAR(500) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
