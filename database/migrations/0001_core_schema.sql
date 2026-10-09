-- Vitaliq core schema (MariaDB 10.6+ / MySQL 8.0+)
-- Applied by: php bin/migrate.php

CREATE TABLE states (
  code CHAR(2) NOT NULL PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  kind ENUM('state','union_territory') NOT NULL,
  UNIQUE KEY uq_states_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role ENUM('patient','phlebotomist','admin','qa') NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  mobile CHAR(10) NULL,
  password_hash VARCHAR(255) NULL COMMENT 'NULL until an invited user sets a password',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  session_version INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incremented to sign out every session',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_mobile (mobile),
  KEY ix_users_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clinical/demographic record. Guests (new patients without an account)
-- have user_id NULL; registered patients have exactly one row.
CREATE TABLE patients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  full_name VARCHAR(100) NOT NULL,
  date_of_birth DATE NOT NULL,
  sex ENUM('female','male','other','undisclosed') NOT NULL DEFAULT 'undisclosed',
  mobile CHAR(10) NOT NULL,
  email VARCHAR(254) NULL,
  address_line VARCHAR(200) NULL,
  city VARCHAR(80) NULL,
  state_code CHAR(2) NULL,
  pincode CHAR(6) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_patients_user (user_id),
  KEY ix_patients_mobile (mobile),
  KEY ix_patients_name (full_name),
  CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_patients_state FOREIGN KEY (state_code) REFERENCES states (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE phlebotomists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'Portal login, optional',
  employee_code CHAR(8) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  mobile CHAR(10) NOT NULL,
  email VARCHAR(254) NULL,
  base_city VARCHAR(80) NOT NULL,
  state_code CHAR(2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL COMMENT 'Soft delete when referenced by history',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_phleb_code (employee_code),
  UNIQUE KEY uq_phleb_mobile (mobile),
  UNIQUE KEY uq_phleb_user (user_id),
  KEY ix_phleb_active (is_active, deleted_at),
  CONSTRAINT fk_phleb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_phleb_state FOREIGN KEY (state_code) REFERENCES states (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference CHAR(15) NOT NULL COMMENT 'VQ-YYMMDD-XXXXX',
  patient_id INT UNSIGNED NOT NULL,
  submitted_by_user_id INT UNSIGNED NULL,
  channel ENUM('guest','account') NOT NULL,
  test_type ENUM('rt_pcr','rapid_antigen') NOT NULL,
  reason ENUM('symptoms','exposure','travel','pre_procedure','screening','other') NOT NULL,
  symptom_onset DATE NULL,
  exposure ENUM('yes','no','unsure') NOT NULL,
  collection_mode ENUM('home','centre') NOT NULL,
  collection_address VARCHAR(200) NULL,
  city VARCHAR(80) NOT NULL,
  state_code CHAR(2) NOT NULL,
  pincode CHAR(6) NOT NULL,
  preferred_date DATE NOT NULL,
  preferred_slot ENUM('morning','afternoon','evening') NOT NULL,
  notes VARCHAR(500) NULL,
  status ENUM('submitted','assigned','sample_collected','in_progress','completed','result_available','cancelled','rejected') NOT NULL DEFAULT 'submitted',
  closed_reason VARCHAR(300) NULL,
  idempotency_key CHAR(64) NULL COMMENT 'One-time form token; blocks double submission',
  submitted_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_requests_reference (reference),
  UNIQUE KEY uq_requests_idempotency (idempotency_key),
  KEY ix_requests_status (status, submitted_at),
  KEY ix_requests_patient (patient_id, submitted_at),
  KEY ix_requests_submitted (submitted_at),
  KEY ix_requests_state (state_code),
  CONSTRAINT fk_requests_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE RESTRICT,
  CONSTRAINT fk_requests_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_requests_state FOREIGN KEY (state_code) REFERENCES states (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_request_symptoms (
  request_id INT UNSIGNED NOT NULL,
  symptom ENUM('fever','chills','sore_throat','cough','breathlessness','fatigue','body_aches','headache','congestion','loss_taste_smell','nausea','diarrhoea') NOT NULL,
  PRIMARY KEY (request_id, symptom),
  CONSTRAINT fk_symptoms_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- is_current is 1 for the active assignment and NULL otherwise, so the
-- unique key allows many historical rows but only one current row.
CREATE TABLE test_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  phlebotomist_id INT UNSIGNED NOT NULL,
  assigned_by_user_id INT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  is_current TINYINT(1) NULL DEFAULT 1,
  note VARCHAR(300) NULL,
  UNIQUE KEY uq_assignment_current (request_id, is_current),
  KEY ix_assignment_phleb (phlebotomist_id, is_current),
  CONSTRAINT fk_assign_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_phleb FOREIGN KEY (phlebotomist_id) REFERENCES phlebotomists (id) ON DELETE RESTRICT,
  CONSTRAINT fk_assign_by FOREIGN KEY (assigned_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_assign_current CHECK (is_current IS NULL OR is_current = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sample_collections (
  request_id INT UNSIGNED NOT NULL PRIMARY KEY,
  phlebotomist_id INT UNSIGNED NOT NULL,
  recorded_by_user_id INT UNSIGNED NULL,
  specimen ENUM('nasopharyngeal','oropharyngeal','nasal','saliva') NOT NULL,
  barcode VARCHAR(14) NOT NULL,
  collected_at DATETIME NOT NULL,
  note VARCHAR(300) NULL,
  UNIQUE KEY uq_sample_barcode (barcode),
  CONSTRAINT fk_sample_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_sample_phleb FOREIGN KEY (phlebotomist_id) REFERENCES phlebotomists (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sample_user FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_results (
  request_id INT UNSIGNED NOT NULL PRIMARY KEY,
  outcome ENUM('negative','positive','inconclusive','invalid') NOT NULL,
  ct_value DECIMAL(4,1) NULL COMMENT 'RT-PCR cycle threshold, optional',
  remarks VARCHAR(500) NULL,
  recorded_by_user_id INT UNSIGNED NULL,
  recorded_at DATETIME NOT NULL,
  released_by_user_id INT UNSIGNED NULL,
  released_at DATETIME NULL,
  CONSTRAINT fk_result_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_result_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_result_releaser FOREIGN KEY (released_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_result_ct CHECK (ct_value IS NULL OR (ct_value >= 0 AND ct_value <= 45))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_status_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  actor_role ENUM('guest','patient','phlebotomist','admin','system') NOT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_history_request (request_id, created_at),
  KEY ix_history_created (created_at),
  CONSTRAINT fk_history_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_history_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(140) NOT NULL,
  body VARCHAR(300) NOT NULL,
  link_path VARCHAR(200) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category ENUM('public_health','service') NOT NULL,
  title VARCHAR(160) NOT NULL,
  summary VARCHAR(400) NOT NULL,
  source_name VARCHAR(120) NULL,
  source_url VARCHAR(300) NULL,
  published_on DATE NOT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = synthetic demonstration notice',
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_announcements_pub (is_published, published_on),
  CONSTRAINT fk_announcements_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  author_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NULL COMMENT 'Never displayed publicly',
  topic ENUM('general','testing','prevention','website') NOT NULL,
  body VARCHAR(1000) NOT NULL,
  status ENUM('pending','approved','rejected','spam') NOT NULL DEFAULT 'pending',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  moderated_by_user_id INT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  moderation_note VARCHAR(300) NULL,
  KEY ix_comments_status (status, created_at),
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_comments_moderator FOREIGN KEY (moderated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only a SHA-256 hash of each token is stored.
CREATE TABLE password_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('reset','invite') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_tokens_hash (token_hash),
  KEY ix_tokens_user (user_id, used_at),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(1000) NULL COMMENT 'JSON, no secrets',
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_created (created_at),
  KEY ix_audit_actor (actor_user_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  bucket CHAR(64) NOT NULL PRIMARY KEY COMMENT 'SHA-256 of the bucket name',
  hits INT UNSIGNED NOT NULL,
  window_ends_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STQA laboratory --------------------------------------------------------

CREATE TABLE defects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  severity ENUM('critical','major','minor','trivial') NOT NULL,
  priority ENUM('high','medium','low') NOT NULL,
  module VARCHAR(40) NOT NULL,
  steps_to_reproduce TEXT NOT NULL,
  expected_behaviour TEXT NOT NULL,
  actual_behaviour TEXT NOT NULL,
  status ENUM('open','in_progress','resolved','closed','wont_fix') NOT NULL DEFAULT 'open',
  origin ENUM('development_testing','demo','user_report') NOT NULL,
  reported_by_user_id INT UNSIGNED NULL,
  resolution_notes TEXT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_defects_code (code),
  KEY ix_defects_status (status, severity),
  CONSTRAINT fk_defects_reporter FOREIGN KEY (reported_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qa_test_cases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  case_code VARCHAR(20) NOT NULL,
  module VARCHAR(40) NOT NULL,
  title VARCHAR(200) NOT NULL,
  kind ENUM('regex','manual') NOT NULL,
  case_type ENUM('positive','negative','boundary','malformed','whitespace','unicode','security','functional') NOT NULL,
  rule_key VARCHAR(40) NULL,
  preconditions VARCHAR(300) NULL,
  input_value VARCHAR(500) NULL,
  expected_match TINYINT(1) NULL COMMENT 'regex cases: 1 accept, 0 reject',
  expected_result VARCHAR(300) NOT NULL,
  origin ENUM('catalogue','user') NOT NULL DEFAULT 'catalogue',
  notes VARCHAR(500) NULL,
  defect_id INT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_cases_code (case_code),
  KEY ix_cases_module (module),
  CONSTRAINT fk_cases_defect FOREIGN KEY (defect_id) REFERENCES defects (id) ON DELETE SET NULL,
  CONSTRAINT fk_cases_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qa_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_type ENUM('regex_suite','manual','phpunit') NOT NULL,
  triggered_from ENUM('web','cli') NOT NULL,
  executed_by_user_id INT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  passed INT UNSIGNED NOT NULL DEFAULT 0,
  failed INT UNSIGNED NOT NULL DEFAULT 0,
  blocked INT UNSIGNED NOT NULL DEFAULT 0,
  environment VARCHAR(160) NULL,
  notes VARCHAR(500) NULL,
  KEY ix_runs_started (started_at),
  CONSTRAINT fk_runs_user FOREIGN KEY (executed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qa_run_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_id INT UNSIGNED NOT NULL,
  test_case_id INT UNSIGNED NULL,
  test_name VARCHAR(255) NOT NULL,
  module VARCHAR(60) NOT NULL,
  status ENUM('pass','fail','blocked','skipped') NOT NULL,
  actual_result VARCHAR(500) NULL,
  actual_match TINYINT(1) NULL,
  duration_ms DECIMAL(10,3) NULL,
  executed_at DATETIME NOT NULL,
  KEY ix_results_run (run_id),
  KEY ix_results_case (test_case_id, executed_at),
  CONSTRAINT fk_results_run FOREIGN KEY (run_id) REFERENCES qa_runs (id) ON DELETE CASCADE,
  CONSTRAINT fk_results_case FOREIGN KEY (test_case_id) REFERENCES qa_test_cases (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
