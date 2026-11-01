-- Patient platform (features 1-10). Additive only.

-- Family profiles: a patients row managed by an account holder (feature 3).
ALTER TABLE patients
  ADD COLUMN managed_by_user_id INT UNSIGNED NULL AFTER user_id,
  ADD COLUMN relationship ENUM('self','spouse','child','parent','sibling','grandparent','other') NULL AFTER managed_by_user_id,
  ADD KEY ix_patients_managed (managed_by_user_id),
  ADD CONSTRAINT fk_patients_manager FOREIGN KEY (managed_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

-- Health passport (feature 2): the patient's own summary record.
CREATE TABLE health_profiles (
  patient_id INT UNSIGNED NOT NULL PRIMARY KEY,
  blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NOT NULL DEFAULT 'unknown',
  height_cm SMALLINT UNSIGNED NULL,
  weight_kg DECIMAL(5,1) NULL,
  allergies VARCHAR(500) NULL,
  conditions VARCHAR(500) NULL,
  medications VARCHAR(500) NULL,
  organ_donor ENUM('yes','no','undecided') NOT NULL DEFAULT 'undecided',
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_hprofile_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
  CONSTRAINT ck_hprofile_height CHECK (height_cm IS NULL OR (height_cm BETWEEN 30 AND 250)),
  CONSTRAINT ck_hprofile_weight CHECK (weight_kg IS NULL OR (weight_kg BETWEEN 1 AND 400))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE immunisations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  vaccine VARCHAR(80) NOT NULL,
  dose_label VARCHAR(30) NULL,
  given_on DATE NOT NULL,
  provider VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_immunisations_patient (patient_id, given_on),
  CONSTRAINT fk_immunisations_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments and calendar (features 4-5).
CREATE TABLE appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  booked_by_user_id INT UNSIGNED NULL,
  facility_id INT UNSIGNED NULL,
  kind ENUM('sample_collection','consultation','teleconsultation','vaccination','follow_up') NOT NULL,
  starts_at DATETIME NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  status ENUM('scheduled','confirmed','attended','cancelled','missed') NOT NULL DEFAULT 'scheduled',
  reason VARCHAR(300) NULL,
  cancel_reason VARCHAR(300) NULL,
  meeting_url VARCHAR(300) NULL COMMENT 'Teleconsultation room link',
  reminder_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_appointments_patient (patient_id, starts_at),
  KEY ix_appointments_start (starts_at, status),
  KEY ix_appointments_facility (facility_id, starts_at),
  CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_booker FOREIGN KEY (booked_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_appointments_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL,
  CONSTRAINT ck_appointments_duration CHECK (duration_min BETWEEN 5 AND 240)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QR health ID (feature 6). Only a SHA-256 hash of each token is stored.
CREATE TABLE health_id_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  label VARCHAR(60) NOT NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  use_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_healthid_hash (token_hash),
  KEY ix_healthid_patient (patient_id),
  CONSTRAINT fk_healthid_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
  CONSTRAINT fk_healthid_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encrypted document vault (feature 7). Files live in storage/vault,
-- encrypted with XSalsa20-Poly1305; the database holds only metadata.
CREATE TABLE patient_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  title VARCHAR(120) NOT NULL,
  category ENUM('lab_report','prescription','imaging','discharge_summary','insurance','vaccination','other') NOT NULL,
  original_name VARCHAR(160) NOT NULL,
  mime_type VARCHAR(60) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  storage_name CHAR(40) NOT NULL,
  sha256 CHAR(64) NOT NULL COMMENT 'Of the plaintext, for integrity checks',
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_documents_storage (storage_name),
  KEY ix_documents_patient (patient_id, deleted_at, created_at),
  CONSTRAINT fk_documents_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency contacts (feature 8).
CREATE TABLE emergency_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  relationship VARCHAR(40) NOT NULL,
  mobile CHAR(10) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY ix_contacts_patient (patient_id),
  CONSTRAINT fk_contacts_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback (feature 10).
CREATE TABLE patient_feedback (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  request_id INT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  category ENUM('booking','sample_collection','results','staff','website','other') NOT NULL,
  comments VARCHAR(1000) NULL,
  status ENUM('new','reviewed','actioned') NOT NULL DEFAULT 'new',
  admin_note VARCHAR(500) NULL,
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_feedback_status (status, created_at),
  KEY ix_feedback_user (user_id),
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_feedback_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE SET NULL,
  CONSTRAINT fk_feedback_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_feedback_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
