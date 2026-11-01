-- Laboratory operations (features 11-20). Additive only.

-- Test catalogue. Reference intervals live in lab_reference_ranges so they
-- can differ by sex and age; the most specific matching row wins.
CREATE TABLE lab_tests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(100) NOT NULL,
  section ENUM('haematology','biochemistry','microbiology','serology','molecular') NOT NULL,
  specimen ENUM('whole_blood','serum','plasma','urine','swab','saliva') NOT NULL,
  result_type ENUM('numeric','qualitative') NOT NULL,
  unit VARCHAR(20) NULL,
  decimals TINYINT UNSIGNED NOT NULL DEFAULT 1,
  options VARCHAR(120) NULL COMMENT 'Qualitative choices, pipe-separated',
  abnormal_options VARCHAR(120) NULL COMMENT 'Qualitative choices that are flagged abnormal',
  turnaround_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_lab_tests_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_reference_ranges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  sex ENUM('any','female','male') NOT NULL DEFAULT 'any',
  age_min_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  age_max_years TINYINT UNSIGNED NOT NULL DEFAULT 120,
  low DECIMAL(12,4) NULL,
  high DECIMAL(12,4) NULL,
  critical_low DECIMAL(12,4) NULL,
  critical_high DECIMAL(12,4) NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_ranges_test (test_id),
  CONSTRAINT fk_ranges_test FOREIGN KEY (test_id) REFERENCES lab_tests (id) ON DELETE CASCADE,
  CONSTRAINT fk_ranges_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_ranges_age CHECK (age_min_years <= age_max_years),
  CONSTRAINT ck_ranges_order CHECK (low IS NULL OR high IS NULL OR low <= high)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multi-test orders (feature 11).
CREATE TABLE lab_orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_no CHAR(14) NOT NULL COMMENT 'LO-YYMMDD-NNNN',
  patient_id INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NULL COMMENT 'Originating testing request, if any',
  facility_id INT UNSIGNED NULL,
  ordered_by_user_id INT UNSIGNED NULL,
  priority ENUM('routine','urgent','stat') NOT NULL DEFAULT 'routine',
  status ENUM('ordered','in_progress','verified','released','cancelled') NOT NULL DEFAULT 'ordered',
  clinical_notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  released_at DATETIME NULL,
  released_by_user_id INT UNSIGNED NULL,
  UNIQUE KEY uq_lab_orders_no (order_no),
  KEY ix_lab_orders_status (status, created_at),
  KEY ix_lab_orders_patient (patient_id),
  CONSTRAINT fk_lab_orders_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE RESTRICT,
  CONSTRAINT fk_lab_orders_request FOREIGN KEY (request_id) REFERENCES test_requests (id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_orders_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_orders_by FOREIGN KEY (ordered_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_orders_rel FOREIGN KEY (released_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Samples and their lifecycle (features 13, 14, 18).
CREATE TABLE lab_samples (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  barcode CHAR(12) NOT NULL COMMENT '11 digits plus a Luhn check digit',
  specimen ENUM('whole_blood','serum','plasma','urine','swab','saliva') NOT NULL,
  status ENUM('awaiting_collection','collected','in_transit','received','in_analysis','stored','disposed','rejected') NOT NULL DEFAULT 'awaiting_collection',
  collected_at DATETIME NULL,
  received_at DATETIME NULL,
  storage_location VARCHAR(40) NULL,
  rejection_reason ENUM('haemolysed','clotted','insufficient_volume','wrong_container','unlabelled','mislabelled','leaked','delayed_transport','other') NULL,
  rejection_note VARCHAR(300) NULL,
  replaces_sample_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_lab_samples_barcode (barcode),
  KEY ix_lab_samples_order (order_id),
  KEY ix_lab_samples_status (status, created_at),
  CONSTRAINT fk_lab_samples_order FOREIGN KEY (order_id) REFERENCES lab_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_samples_replaces FOREIGN KEY (replaces_sample_id) REFERENCES lab_samples (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_sample_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sample_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  note VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_sample_events (sample_id, created_at),
  KEY ix_sample_events_created (created_at),
  CONSTRAINT fk_sample_events_sample FOREIGN KEY (sample_id) REFERENCES lab_samples (id) ON DELETE CASCADE,
  CONSTRAINT fk_sample_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per test on an order; results, validation flags and the
-- two-person verification (features 16, 17). The verifier must differ from
-- the person who entered the result; LaboratoryService::verifyItem() enforces
-- it (MariaDB does not allow a CHECK on columns with ON DELETE SET NULL).
CREATE TABLE lab_order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  sample_id INT UNSIGNED NULL,
  status ENUM('pending','entered','verified','rejected') NOT NULL DEFAULT 'pending',
  value_numeric DECIMAL(12,4) NULL,
  value_text VARCHAR(60) NULL,
  flag ENUM('normal','low','high','critical_low','critical_high','abnormal') NULL,
  ref_low DECIMAL(12,4) NULL,
  ref_high DECIMAL(12,4) NULL,
  unit VARCHAR(20) NULL,
  comment VARCHAR(300) NULL,
  entered_by_user_id INT UNSIGNED NULL,
  entered_at DATETIME NULL,
  verified_by_user_id INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  returned_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_lab_items_test (order_id, test_id),
  KEY ix_lab_items_status (status),
  KEY ix_lab_items_sample (sample_id),
  CONSTRAINT fk_lab_items_order FOREIGN KEY (order_id) REFERENCES lab_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_items_test FOREIGN KEY (test_id) REFERENCES lab_tests (id) ON DELETE RESTRICT,
  CONSTRAINT fk_lab_items_sample FOREIGN KEY (sample_id) REFERENCES lab_samples (id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_items_enterer FOREIGN KEY (entered_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_lab_items_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory with lots and expiry (feature 12). Stock is the sum of lots.
CREATE TABLE lab_inventory_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(24) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category ENUM('reagent','consumable','kit','control') NOT NULL,
  unit VARCHAR(20) NOT NULL,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_inventory_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_inventory_lots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  lot_no VARCHAR(40) NOT NULL,
  expires_on DATE NULL,
  quantity INT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL,
  UNIQUE KEY uq_inventory_lot (item_id, lot_no),
  KEY ix_inventory_lots_expiry (expires_on),
  CONSTRAINT fk_inventory_lots_item FOREIGN KEY (item_id) REFERENCES lab_inventory_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_inventory_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  lot_id INT UNSIGNED NULL,
  quantity_change INT NOT NULL,
  reason ENUM('receipt','consumption','adjustment','expired','wastage') NOT NULL,
  note VARCHAR(200) NULL,
  actor_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_movements_item (item_id, created_at),
  CONSTRAINT fk_movements_item FOREIGN KEY (item_id) REFERENCES lab_inventory_items (id) ON DELETE CASCADE,
  CONSTRAINT fk_movements_lot FOREIGN KEY (lot_id) REFERENCES lab_inventory_lots (id) ON DELETE SET NULL,
  CONSTRAINT fk_movements_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equipment, calibration and maintenance (feature 15).
CREATE TABLE lab_equipment (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  asset_tag VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  manufacturer VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  serial_no VARCHAR(60) NULL,
  facility_id INT UNSIGNED NULL,
  status ENUM('operational','maintenance','out_of_service','retired') NOT NULL DEFAULT 'operational',
  calibration_interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  last_calibrated_on DATE NULL,
  calibration_due_on DATE NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_equipment_tag (asset_tag),
  CONSTRAINT fk_equipment_facility FOREIGN KEY (facility_id) REFERENCES facilities (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_equipment_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT UNSIGNED NOT NULL,
  kind ENUM('calibration','maintenance','repair','qc_failure','status_change','note') NOT NULL,
  performed_on DATE NOT NULL,
  notes VARCHAR(500) NULL,
  actor_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY ix_equipment_logs (equipment_id, performed_on),
  CONSTRAINT fk_equipment_logs_eq FOREIGN KEY (equipment_id) REFERENCES lab_equipment (id) ON DELETE CASCADE,
  CONSTRAINT fk_equipment_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
