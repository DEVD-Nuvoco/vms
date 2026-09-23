<?php
/**
 * One-time bootstrap: provision LIEO logins for the HOD/N-1 matrix rows
 * loaded by database/lieo_seed_hod_n1_from_excel.sql.
 *
 * That seed script only inserts tbl_lieo_approval_matrix rows (raw SQL can't
 * generate passwords or send mail) — nobody can sign in until this runs.
 * "Resend credentials" on the Approval Matrix page can't substitute for this:
 * it only resets the password on an ACCOUNT THAT ALREADY EXISTS, it never
 * creates one. This calls the same lieo_provision_matrix_user() the app uses
 * when an admin saves a matrix row by hand, so behavior (password generation,
 * credentials email / local test-mail popup) matches exactly.
 *
 * Idempotent — safe to re-run after adding more matrix rows; already-
 * provisioned accounts are left alone (only profile fields refresh).
 *
 * Run: php database/provision_lieo_hod_n1_logins.php [PLANT]
 * Default plant: RCP.
 */
require_once dirname(__DIR__) . '/lieo/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$plant = lieo_ams_canonical_plant($argv[1] ?? 'RCP');
if ($plant === '') {
    fwrite(STDERR, "Usage: php provision_lieo_hod_n1_logins.php [PLANT]\n");
    exit(1);
}

$db = lieo_db();
$canon = lieo_sql_canonical_plant('plant');
$stmt = $db->prepare(
    "SELECT * FROM tbl_lieo_approval_matrix
     WHERE $canon = ? AND status = 'Active' AND approval_step IN ('hod','n1','security','timeoffice')
     ORDER BY approval_step, department"
);
$stmt->bind_param('s', $plant);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$created = 0;
$updated = 0;
$failed = [];
foreach ($rows as $row) {
    $r = lieo_provision_matrix_user(
        $row['approval_step'],
        $row['plant'],
        $row['department'],
        $row['emp_code'],
        $row['emp_name'],
        $row['emp_email'],
        true
    );
    if (!($r['ok'] ?? false)) {
        $failed[] = $row['emp_email'] . ' (' . $row['approval_step'] . '/' . $row['department'] . '): ' . ($r['message'] ?? '');
        continue;
    }
    if (($r['provisioned'] ?? '') === 'created') {
        $created++;
    } else {
        $updated++;
    }
}

echo "Plant: $plant\n";
echo "Logins created: $created, updated: $updated, failed: " . count($failed) . "\n";
if ($failed) {
    echo "\nFailed (commonly: this email already has a different role — one physical\n"
        . "person, one login, one role today; a pre-existing assignment under a\n"
        . "different role for the same email blocks a second one):\n";
    foreach ($failed as $f) {
        echo "  - $f\n";
    }
}
