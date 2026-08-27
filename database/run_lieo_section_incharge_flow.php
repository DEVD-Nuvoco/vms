<?php
/**
 * LIEO process update: Section Incharge creates; chain SI → Time Office → N-1 → HOD → Security.
 * Plant department master + Time Office notification CC emails.
 *
 * Run: php database/run_lieo_section_incharge_flow.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$db = lieo_db();
echo "Applying Section Incharge / Time Office ownership schema…\n";

function lieo_mig_ok(mysqli $db, string $sql, string $label): void
{
    if (!$db->query($sql)) {
        echo "  skip/fail $label: " . $db->error . "\n";
    } else {
        echo "  ok $label\n";
    }
}

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_user
  MODIFY COLUMN role ENUM(
    'admin','section_incharge','timeoffice','n1','hod','security','hr','supervisor'
  ) NOT NULL
", 'user.role enum');

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_approval_matrix
  MODIFY COLUMN approval_step ENUM(
    'section_incharge','timeoffice','n1','hod','security','hr','supervisor'
  ) NOT NULL
", 'matrix.approval_step enum');

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_application
  MODIFY COLUMN status ENUM(
    'Pending_timeoffice',
    'Pending_n1',
    'Pending_hod',
    'Pending_supervisor',
    'Approved',
    'Attested',
    'Gate_completed',
    'Rejected'
  ) NOT NULL DEFAULT 'Pending_timeoffice'
", 'application.status enum');

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_application
  MODIFY COLUMN current_step ENUM(
    'section_incharge','timeoffice','n1','hod','supervisor','attestation','gate','done','rejected'
  ) NOT NULL DEFAULT 'timeoffice'
", 'application.current_step enum');

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_application_approval
  MODIFY COLUMN step ENUM(
    'section_incharge','timeoffice','n1','hod','supervisor','attestation','gate','done','rejected'
  ) NOT NULL
", 'approval.step enum');

lieo_mig_ok($db, "
CREATE TABLE IF NOT EXISTS tbl_lieo_plant_department (
  dept_id INT NOT NULL AUTO_INCREMENT,
  plant VARCHAR(100) NOT NULL,
  department_name VARCHAR(150) NOT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (dept_id),
  UNIQUE KEY uk_plant_dept (plant, department_name),
  KEY idx_plant_status (plant, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'tbl_lieo_plant_department');

lieo_mig_ok($db, "
CREATE TABLE IF NOT EXISTS tbl_lieo_plant_notify_email (
  notify_id INT NOT NULL AUTO_INCREMENT,
  plant VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notify_id),
  UNIQUE KEY uk_plant_email (plant, email),
  KEY idx_plant (plant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'tbl_lieo_plant_notify_email');

lieo_mig_ok($db, "
ALTER TABLE tbl_lieo_contractor
  ADD COLUMN plant VARCHAR(100) NULL AFTER contractor_name
", 'contractor.plant');

echo "Done.\n";
