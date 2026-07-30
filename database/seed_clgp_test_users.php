<?php
/**
 * Seed CLGP test users + approval matrix for end-to-end flow testing.
 * Run: php database/seed_clgp_test_users.php
 *
 * All test accounts use password: 123456 (must-change flag cleared for convenience).
 */
require_once dirname(__DIR__) . '/clgp/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['clgp_user_id'] = 1;

const CLGP_TEST_PASSWORD = '123456';
const CLGP_TEST_PLANT = 'Nimbol';
const CLGP_TEST_DEPT = 'Maintenance';

/** @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string}> step, code, name, email, dept, plant */
function clgp_test_matrix_rows(): array
{
    $p = CLGP_TEST_PLANT;
    return [
        ['timeoffice', '30001111', 'CLGP Time Office', 'clgp.timeoffice@nuvoco.com', CLGP_TEST_DEPT, $p],
        ['supervisor', '30001234', 'CLGP Supervisor', 'clgp.supervisor@nuvoco.com', CLGP_TEST_DEPT, $p],
        ['n1', '30005678', 'CLGP N1 Approver', 'clgp.n1@nuvoco.com', CLGP_TEST_DEPT, $p],
        ['hod', '30009999', 'CLGP HOD', 'clgp.hod@nuvoco.com', CLGP_TEST_DEPT, $p],
        ['security', '30008801', 'CLGP Security Nimbol', 'clgp.security@nuvoco.com', 'All', $p],
        ['hr', '30007701', 'CLGP HR Head', 'clgp.hr@nuvoco.com', 'All', $p],
        // Second plant — different Security user (one Security row per plant)
        ['security', '30008802', 'CLGP Security JCP', 'clgp.security.jcp@nuvoco.com', 'All', 'JCP'],
    ];
}

function clgp_test_set_password_no_force_change(string $email): void
{
    $db = clgp_db();
    $email = trim($email);
    $pass = CLGP_TEST_PASSWORD;
    $stmt = $db->prepare(
        "UPDATE tbl_clgp_user u
         INNER JOIN tbl_logindetail l ON l.id = u.login_id
         SET u.must_change_password = 'f', l.userPassword = ?
         WHERE u.email = ?"
    );
    $stmt->bind_param('ss', $pass, $email);
    $stmt->execute();
    $stmt->close();
}

function clgp_test_ensure_admin(): void
{
    $email = 'clgp.admin@nuvoco.com';
    if (clgp_find_user_by_email($email)) {
        clgp_test_set_password_no_force_change($email);
        echo "admin: exists ($email)\n";
        return;
    }
    $r = clgp_create_user([
        'full_name' => 'CLGP System Admin',
        'email' => $email,
        'role' => 'admin',
        'emp_code' => '',
        'plant' => CLGP_TEST_PLANT,
        'department' => CLGP_TEST_DEPT,
    ], CLGP_TEST_PASSWORD);
    if ($r['ok']) {
        clgp_test_set_password_no_force_change($email);
    }
    echo 'admin: ' . json_encode($r) . PHP_EOL;
}

function clgp_test_ensure_contractor_workman(): int
{
    $contractors = clgp_list_contractors('Active');
    $cid = 0;
    foreach ($contractors as $c) {
        if (strcasecmp($c['vendor_name'], 'CLGP Demo Contractor') === 0) {
            $cid = (int) $c['contractor_id'];
            break;
        }
    }
    if ($cid < 1) {
        $r = clgp_save_contractor([
            'vendor_name' => 'CLGP Demo Contractor',
            'contractor_name' => 'Demo Works Pvt Ltd',
            'vendor_type' => 'Temporary',
            'supervisor_name' => 'Site Supervisor',
            'email' => 'contractor.demo@nuvoco.com',
            'contractor_mobile' => '9999900001',
            'supervisor_mobile' => '9999900002',
        ]);
        $cid = (int) ($r['contractor_id'] ?? 0);
        echo 'contractor: ' . json_encode($r) . PHP_EOL;
    }

    $workmen = clgp_list_workmen('Active');
    foreach ($workmen as $w) {
        if ($w['workman_code'] === 'WM-CLGP-001' && $w['plant'] === CLGP_TEST_PLANT) {
            echo "workman: exists WM-CLGP-001\n";
            return (int) $w['workman_id'];
        }
    }

    $r = clgp_save_workman([
        'workman_code' => 'WM-CLGP-001',
        'workman_name' => 'Ramesh Workman',
        'contractor_id' => $cid,
        'plant' => CLGP_TEST_PLANT,
        'department' => CLGP_TEST_DEPT,
        'shift' => 'General (08:00–17:00)',
    ]);
    echo 'workman: ' . json_encode($r) . PHP_EOL;
    return (int) ($r['workman_id'] ?? 0);
}

echo "=== CLGP test seed ===\n";

clgp_test_ensure_admin();

foreach (clgp_test_matrix_rows() as $row) {
    [$step, $code, $name, $email, $dept, $plant] = $row;
    $r = clgp_save_matrix_rule([
        'plant' => $plant,
        'department' => $dept,
        'approval_step' => $step,
        'emp_code' => $code,
        'emp_name' => $name,
        'emp_email' => $email,
    ]);
    if ($r['ok']) {
        clgp_test_set_password_no_force_change($email);
    }
    echo "matrix $step: " . ($r['ok'] ? 'ok' : $r['message']) . "\n";
}

$workmanId = clgp_test_ensure_contractor_workman();

// One sample application at Time Office step (skip if one already pending today)
$pending = clgp_list_applications(['status' => 'Pending_timeoffice', 'plant' => CLGP_TEST_PLANT]);
if (!$pending && $workmanId > 0) {
    $toUser = clgp_find_user_by_email('clgp.timeoffice@nuvoco.com');
    $createdBy = $toUser ? (int) $toUser['clgp_user_id'] : 1;
    $app = clgp_create_application([
        'workman_id' => $workmanId,
        'application_type' => 'Late Coming',
        'reason' => 'Test seed — bus delay',
        'created_by' => $createdBy,
    ]);
    echo 'sample app: ' . json_encode($app) . PHP_EOL;
} else {
    echo "sample app: skipped (pending exists or no workman)\n";
}

// Deactivate duplicate legacy demo logins that conflict with matrix emp codes (optional cleanup)
$legacy = [
    'timeoffice@nuvoco.com',
    'supervisor@nuvoco.com',
    'n1@nuvoco.com',
    'hod@nuvoco.com',
    'security@nuvoco.com',
    'hr@nuvoco.com',
];
foreach ($legacy as $oldEmail) {
    $u = clgp_find_user_by_email($oldEmail);
    if ($u) {
        clgp_set_user_status((int) $u['clgp_user_id'], 'Inactive');
        echo "deactivated legacy: $oldEmail\n";
    }
}

echo "\n=== Login credentials (password: " . CLGP_TEST_PASSWORD . ") ===\n";
echo str_pad('Role', 14) . str_pad('Email', 32) . "Plant / Dept\n";
echo str_repeat('-', 72) . "\n";
echo str_pad('Admin', 14) . str_pad('clgp.admin@nuvoco.com', 32) . CLGP_TEST_PLANT . " / " . CLGP_TEST_DEPT . "\n";
foreach (clgp_test_matrix_rows() as $row) {
    [$step, , , $email, $dept, $plant] = $row;
    $label = clgp_role_label($step);
    $deptShow = $dept === 'All' ? $plant . ' (all depts)' : $plant . ' / ' . $dept;
    echo str_pad($label, 14) . str_pad($email, 32) . $deptShow . "\n";
}
echo "\nFlow: Sign in as Time Office → Pending Approvals → approve through chain → Attest → Security gate.\n";
echo "HR: clgp.hr@nuvoco.com — contractor reactivation.\n";
echo "URL: /vms/clgp/login.php\n";
