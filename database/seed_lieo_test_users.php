<?php
/**
 * Seed local-only LIEO test users (blank-password login).
 *
 * Requires repo-root .env with LIEO_LOCAL_DEV=1 (see .env-example).
 * Refuses to run if that flag is off — do not use on live.
 *
 * Run: php database/seed_lieo_test_users.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

if (!lieo_is_local_dev()) {
    fwrite(STDERR, "Refusing to seed: LIEO_LOCAL_DEV is not enabled (or this is not a local host).\n");
    fwrite(STDERR, "Copy .env-example to .env and set LIEO_LOCAL_DEV=1 on WAMP only.\n");
    exit(1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$plant = lieo_ams_canonical_plant(lieo_env('LIEO_LOCAL_PLANT', 'RCP'));
$dept = trim(lieo_env('LIEO_LOCAL_DEPT', 'Maintenance'));
if ($plant === '') {
    $plant = 'RCP';
}
if ($dept === '') {
    $dept = 'Maintenance';
}

$_SESSION['lieo_user_id'] = 1;
$_SESSION['lieo_plant'] = $plant;
$_SESSION['lieo_department'] = $dept;

function lieo_test_unlock_login(string $email): void
{
    $db = lieo_db();
    $email = trim($email);
    $stmt = $db->prepare(
        "UPDATE tbl_lieo_user SET must_change_password = 'f', status = 'Active' WHERE email = ?"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

function lieo_test_ensure_user(string $role, string $email, string $name, string $code, string $plant, string $dept): void
{
    $existing = lieo_find_user_by_email($email);
    if ($existing) {
        lieo_test_unlock_login($email);
        $uid = (int) $existing['lieo_user_id'];
        $stmt = lieo_db()->prepare(
            "UPDATE tbl_lieo_user SET full_name=?, role=?, emp_code=?, plant=?, department=?, status='Active'
             WHERE lieo_user_id=?"
        );
        $stmt->bind_param('sssssi', $name, $role, $code, $plant, $dept, $uid);
        $stmt->execute();
        $stmt->close();
        echo "user exists: $email ($role)\n";
        return;
    }
    $r = lieo_create_user([
        'full_name' => $name,
        'email' => $email,
        'role' => $role,
        'emp_code' => $code,
        'plant' => $plant,
        'department' => $dept,
    ], 'local-dev');
    if ($r['ok']) {
        lieo_test_unlock_login($email);
        echo "user created: $email ($role)\n";
    } else {
        echo "user failed: $email — " . ($r['message'] ?? 'error') . "\n";
    }
}

function lieo_test_save_matrix(string $step, string $code, string $name, string $email, string $dept, string $plant): void
{
    $r = lieo_save_matrix_rule([
        'plant' => $plant,
        'department' => $dept,
        'approval_step' => $step,
        'emp_code' => $code,
        'emp_name' => $name,
        'emp_email' => $email,
    ]);
    lieo_test_unlock_login($email);
    echo "matrix $step: " . ($r['ok'] ? 'ok' : ($r['message'] ?? 'fail')) . "\n";
}

echo "=== LIEO local test seed (plant $plant / $dept) ===\n";

lieo_test_ensure_user('admin', 'lieo.admin@local.test', 'LIEO Local Admin', 'LOCAL-ADM', $plant, $dept);

$_SESSION['lieo_role'] = 'admin';
lieo_test_save_matrix(
    'timeoffice',
    'LOCAL-TO',
    'LIEO Local Time Office',
    'lieo.timeoffice@local.test',
    $dept,
    $plant
);

$_SESSION['lieo_role'] = 'timeoffice';
$_SESSION['lieo_user_id'] = (int) (lieo_find_user_by_email('lieo.timeoffice@local.test')['lieo_user_id'] ?? 1);

lieo_test_save_matrix('section_incharge', 'LOCAL-SI', 'LIEO Local Section Incharge', 'lieo.si@local.test', $dept, $plant);
lieo_test_save_matrix('n1', 'LOCAL-N1', 'LIEO Local N-1', 'lieo.n1@local.test', $dept, $plant);
lieo_test_save_matrix('hod', 'LOCAL-HOD', 'LIEO Local HOD', 'lieo.hod@local.test', 'All', $plant);
lieo_test_save_matrix('security', 'LOCAL-SEC', 'LIEO Local Security', 'lieo.security@local.test', 'All', $plant);
lieo_test_save_matrix('hr', 'LOCAL-HR', 'LIEO Local HR Head', 'lieo.hr@local.test', 'All', $plant);

$cid = 0;
foreach (lieo_list_contractors(null, $plant) as $c) {
    if (strcasecmp($c['contractor_name'] ?? '', 'LIEO Local Contractor') === 0) {
        $cid = (int) $c['contractor_id'];
        break;
    }
}
if ($cid < 1) {
    $r = lieo_save_contractor([
        'contractor_name' => 'LIEO Local Contractor',
        'contractor_type' => 'Temporary',
        'supervisor_name' => 'Local Supervisor',
        'email' => 'lieo.contractor.sup@local.test',
        'contractor_mobile' => '9999900001',
        'supervisor_mobile' => '9999900002',
        'plant' => $plant,
    ]);
    $cid = (int) ($r['contractor_id'] ?? 0);
    echo 'contractor: ' . json_encode($r) . PHP_EOL;
} else {
    echo "contractor: exists ($cid)\n";
}

echo "\n=== Local login (blank password, WAMP only) ===\n";
echo str_pad('Role', 18) . "Email\n";
echo str_repeat('-', 52) . "\n";
foreach (lieo_local_test_accounts() as $acc) {
    echo str_pad($acc['label'], 18) . $acc['email'] . "\n";
}
echo "\nURL: http://localhost/vms/lieo/login.php\n";
echo "Leave password empty. Live server ignores this even if .env is copied.\n";
