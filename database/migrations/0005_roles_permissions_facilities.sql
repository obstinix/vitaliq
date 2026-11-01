-- Platform roles and granular permissions (additive).
-- New roles: 'lab' (laboratory staff) and 'officer' (public-health officer).
-- Enum values are only appended, so existing rows keep their meaning.

ALTER TABLE users
  MODIFY role ENUM('patient','phlebotomist','admin','qa','lab','officer') NOT NULL;

ALTER TABLE test_status_history
  MODIFY actor_role ENUM('guest','patient','phlebotomist','admin','system','lab','officer') NOT NULL;

-- Per-role and per-user overrides of the default permission bundles in
-- src/Auth/Permissions.php. granted = 0 revokes a default.
CREATE TABLE role_permissions (
  role VARCHAR(20) NOT NULL,
  permission VARCHAR(40) NOT NULL,
  granted TINYINT(1) NOT NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (role, permission),
  CONSTRAINT fk_roleperm_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
  user_id INT UNSIGNED NOT NULL,
  permission VARCHAR(40) NOT NULL,
  granted TINYINT(1) NOT NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, permission),
  CONSTRAINT fk_userperm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_userperm_by FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Facilities: labs, collection centres, hospitals (multi-facility, feature 40).
CREATE TABLE facilities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(12) NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind ENUM('laboratory','collection_centre','hospital','clinic') NOT NULL,
  city VARCHAR(80) NOT NULL,
  state_code CHAR(2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_facilities_code (code),
  CONSTRAINT fk_facilities_state FOREIGN KEY (state_code) REFERENCES states (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_facilities (
  user_id INT UNSIGNED NOT NULL,
  facility_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, facility_id),
  KEY ix_userfac_facility (facility_id),
  CONSTRAINT fk_userfac_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_userfac_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which facility handled a request (NULL for requests made before facilities existed).
ALTER TABLE test_requests
  ADD COLUMN facility_id INT UNSIGNED NULL AFTER state_code,
  ADD KEY ix_requests_facility (facility_id),
  ADD CONSTRAINT fk_requests_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL;

-- Per-user interface preferences: language, accessibility mode.
ALTER TABLE users
  ADD COLUMN locale ENUM('en','hi','mr') NOT NULL DEFAULT 'en' AFTER mobile,
  ADD COLUMN accessibility_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER locale;
