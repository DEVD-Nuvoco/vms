<?php
/**
 * Admin-editable display names for LIEO roles. Only the label shown in the UI /
 * emails is stored here — role keys (n1, hod, security, timeoffice, admin) that
 * drive the flow are untouched.
 * Run: php database/run_lieo_role_labels.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$ok = lieo_db()->query(
    "CREATE TABLE IF NOT EXISTS tbl_lieo_role_label (
        role_key   VARCHAR(30) NOT NULL PRIMARY KEY,
        label      VARCHAR(40) NOT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo $ok ? "tbl_lieo_role_label ready.\n" : 'Failed: ' . lieo_db()->error . "\n";
