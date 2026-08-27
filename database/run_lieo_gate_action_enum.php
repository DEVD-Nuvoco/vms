<?php
/**
 * Allow gate_in / gate_out on approval.action and backfill existing gate rows.
 * Run: php database/run_lieo_gate_action_enum.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$db = lieo_db();

$ok = $db->query(
    "ALTER TABLE tbl_lieo_application_approval
     MODIFY COLUMN action ENUM('approve','reject','gate_in','gate_out') NOT NULL"
);
echo $ok ? "Updated action enum (approve, reject, gate_in, gate_out).\n" : ('Enum alter failed: ' . $db->error . "\n");

// Backfill empty/invalid gate actions from application gate columns.
$res = $db->query(
    "SELECT a.approval_id, a.application_id, a.action, app.gate_in_at, app.gate_out_at
     FROM tbl_lieo_application_approval a
     INNER JOIN tbl_lieo_application app ON app.application_id = a.application_id
     WHERE a.step = 'gate' AND (a.action = '' OR a.action IS NULL OR a.action NOT IN ('gate_in','gate_out'))"
);
$fixed = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $newAction = !empty($row['gate_in_at']) ? 'gate_in' : (!empty($row['gate_out_at']) ? 'gate_out' : 'gate_in');
        $id = (int) $row['approval_id'];
        if ($db->query("UPDATE tbl_lieo_application_approval SET action='" . $db->real_escape_string($newAction) . "' WHERE approval_id={$id}")) {
            $fixed++;
        }
    }
}
echo "Backfilled {$fixed} gate approval row(s).\n";
