<?php
/**
 * Add gate_remark for Security close (Early IN/OUT).
 * Run: php database/run_clgp_gate_remark.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

$db = clgp_db();
$col = $db->query("SHOW COLUMNS FROM tbl_clgp_application LIKE 'gate_remark'");
if ($col && $col->num_rows > 0) {
    echo "Column gate_remark already exists.\n";
    exit(0);
}
$ok = $db->query(
    "ALTER TABLE tbl_clgp_application
     ADD COLUMN gate_remark VARCHAR(500) NULL DEFAULT NULL AFTER security_at"
);
echo $ok ? "Added gate_remark column.\n" : 'Failed: ' . $db->error . "\n";
