<?php
/**
 * Split tbl_lieo_plant_notify_email's CC list by notification context
 * (reactivation / approval_matrix / department) instead of one shared
 * per-plant list, so Admin can keep separate CC people per notification.
 * Run: php database/run_lieo_notify_context.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$db = lieo_db();
$col = $db->query("SHOW COLUMNS FROM tbl_lieo_plant_notify_email LIKE 'context'");
if ($col && $col->num_rows > 0) {
    echo "Column context already exists.\n";
    exit(0);
}
$ok = $db->query(
    "ALTER TABLE tbl_lieo_plant_notify_email
     ADD COLUMN context VARCHAR(30) NOT NULL DEFAULT 'reactivation' AFTER plant"
);
if (!$ok) {
    echo 'Failed to add column: ' . $db->error . "\n";
    exit(1);
}
$ok = $db->query("ALTER TABLE tbl_lieo_plant_notify_email DROP INDEX uk_plant_email");
if (!$ok) {
    echo 'Failed to drop old unique key: ' . $db->error . "\n";
    exit(1);
}
$ok = $db->query(
    "ALTER TABLE tbl_lieo_plant_notify_email
     ADD UNIQUE KEY uk_plant_context_email (plant, context, email)"
);
echo $ok ? "Added context column and re-keyed (plant, context, email).\n" : 'Failed: ' . $db->error . "\n";
