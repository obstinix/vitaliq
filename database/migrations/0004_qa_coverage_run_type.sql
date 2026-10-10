-- Distinguish "catalogue cases evaluated from a PHPUnit report" (coverage)
-- from the raw PHPUnit import, so the lab labels each run correctly.

ALTER TABLE qa_runs
  MODIFY run_type ENUM('regex_suite','manual','phpunit','coverage') NOT NULL;
