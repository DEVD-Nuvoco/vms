<?php
/**
 * End-to-end LC/EG flow: create → Supervisor → N-1 → HOD → Security gate.
 *
 * Run: php database/run_lieo_e2e_flow.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['lieo_user_id'] = 1;

function lieo_e2e_actor(string $email): ?array
{
    $u = lieo_find_user_by_email($email);
    if (!$u || ($u['status'] ?? '') !== 'Active') {
        return null;
    }
    return [
        'lieo_user_id' => (int) $u['lieo_user_id'],
        'emp_code' => $u['emp_code'] ?? '',
        'full_name' => $u['full_name'],
        'role' => $u['role'],
        'plant' => $u['plant'] ?? '',
        'email' => $u['email'],
    ];
}

function lieo_e2e_session(array $actor): void
{
    $full = lieo_find_user_by_email($actor['email']);
    $_SESSION['lieo_role'] = $actor['role'];
    $_SESSION['lieo_user_id'] = $actor['lieo_user_id'];
    $_SESSION['lieo_user_name'] = $actor['full_name'];
    $_SESSION['lieo_emp_code'] = $actor['emp_code'];
    $_SESSION['lieo_plant'] = $actor['plant'];
    $_SESSION['lieo_department'] = $full['department'] ?? '';
}

function lieo_e2e_log(string $msg, array $extra = []): void
{
    echo $msg;
    if ($extra) {
        echo ' ' . json_encode($extra);
    }
    echo PHP_EOL;
}

$emails = [
    'timeoffice' => 'lieo.timeoffice@nuvoco.com',
    'supervisor' => 'lieo.supervisor@nuvoco.com',
    'n1' => 'lieo.n1@nuvoco.com',
    'hod' => 'lieo.hod@nuvoco.com',
    'security_nimbol' => 'lieo.security@nuvoco.com',
    'security_jcp' => 'lieo.security.jcp@nuvoco.com',
];

foreach ($emails as $key => $email) {
    if (!lieo_e2e_actor($email)) {
        lieo_e2e_log("FAIL: missing user $email — run: php database/seed_lieo_test_users.php");
        exit(1);
    }
}

// Workman for Nimbol
$workmanId = 0;
foreach (lieo_list_workmen('Active') as $w) {
    if ($w['workman_code'] === 'WM-LIEO-001' && $w['plant'] === 'Nimbol') {
        $workmanId = (int) $w['workman_id'];
        break;
    }
}
if ($workmanId < 1) {
    lieo_e2e_log('FAIL: workman WM-LIEO-001 not found — run seed script');
    exit(1);
}

$to = lieo_e2e_actor($emails['timeoffice']);
lieo_e2e_session($to);

lieo_e2e_log('1) Time Office creates Late Coming application');
$created = lieo_create_application([
    'workman_id' => $workmanId,
    'application_type' => 'Late Coming',
    'reason' => 'E2E test — transport delay',
    'created_by' => $to['lieo_user_id'],
]);
if (!$created['ok']) {
    lieo_e2e_log('FAIL create', $created);
    exit(1);
}
$appId = (int) $created['application_id'];
$appNo = $created['application_no'];
lieo_e2e_log('   Created', ['application_no' => $appNo, 'status' => lieo_get_application($appId)['status']]);

$chain = [
    ['supervisor', $emails['supervisor']],
    ['n1', $emails['n1']],
    ['hod', $emails['hod']],
];

$stepNum = 2;
foreach ($chain as [$role, $email]) {
    $actor = lieo_e2e_actor($email);
    lieo_e2e_session($actor);
    $app = lieo_get_application($appId);
    lieo_e2e_log("$stepNum) {$role} approves (pending: {$app['status']} / step {$app['current_step']})");

    $pending = lieo_list_applications(lieo_apply_session_plant_scope(['pending_for_role' => $role]));
    $found = false;
    foreach ($pending as $p) {
        if ((int) $p['application_id'] === $appId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        lieo_e2e_log("   WARN: app not in pending list for $role (check plant/dept/emp_code match)");
    } else {
        lieo_e2e_log('   OK: visible on Pending Approvals for this role');
    }

    $result = lieo_advance_application(
        $appId,
        $role,
        'approve',
        'E2E approved by ' . $role,
        [
            'lieo_user_id' => $actor['lieo_user_id'],
            'emp_code' => $actor['emp_code'],
            'full_name' => $actor['full_name'],
        ]
    );
    if (!$result['ok']) {
        lieo_e2e_log("   FAIL advance", $result);
        exit(1);
    }
    $app = lieo_get_application($appId);
    lieo_e2e_log('   After approve', ['status' => $app['status'], 'current_step' => $app['current_step']]);
    $stepNum++;
}

$app = lieo_get_application($appId);
if ($app['status'] !== 'Approved' || ($app['current_step'] ?? '') !== 'gate') {
    lieo_e2e_log('FAIL: expected Approved / gate after HOD', $app);
    exit(1);
}

$secN = lieo_e2e_actor($emails['security_nimbol']);
lieo_e2e_session($secN);
$plantApps = lieo_list_applications(lieo_apply_session_plant_scope([]));
$secNSees = false;
foreach ($plantApps as $p) {
    if ((int) $p['application_id'] === $appId && lieo_application_ready_for_gate($p)) {
        $secNSees = true;
        break;
    }
}
lieo_e2e_log("$stepNum) Security (Nimbol) — Approved apps at gate");
lieo_e2e_log($secNSees ? '   OK: Nimbol Security sees application ready to close' : '   FAIL: Nimbol Security does NOT see application');
$stepNum++;

$secJ = lieo_e2e_actor($emails['security_jcp']);
if ($secJ) {
    lieo_e2e_session($secJ);
    $plantAppsJ = lieo_list_applications(lieo_apply_session_plant_scope([]));
    $secJSees = false;
    foreach ($plantAppsJ as $p) {
        if ((int) $p['application_id'] === $appId) {
            $secJSees = true;
            break;
        }
    }
    lieo_e2e_log("$stepNum) Security (JCP) plant scope");
    lieo_e2e_log($secJSees ? '   FAIL: JCP Security incorrectly sees Nimbol application' : '   OK: JCP Security does not see Nimbol application');
    $stepNum++;
}

lieo_e2e_session($secN);
lieo_e2e_log("$stepNum) Security closes application (gate IN)");
$gate = lieo_gate_action($appId, 'in', $secN['lieo_user_id'], 'E2E gate close remark');
if (!$gate['ok']) {
    lieo_e2e_log('FAIL gate', $gate);
    exit(1);
}
$app = lieo_get_application($appId);
lieo_e2e_log('   Completed', [
    'status' => $app['status'],
    'gate_in_at' => $app['gate_in_at'],
    'application_no' => $appNo,
]);

$approvals = lieo_get_application_approvals($appId);
lieo_e2e_log("\n=== Approval audit trail ===");
foreach ($approvals as $a) {
    lieo_e2e_log("   {$a['step']}: {$a['action']} by {$a['approver_name']} ({$a['approver_emp_code']})");
}

if ($app['status'] === 'Gate_completed' && $secNSees) {
    lieo_e2e_log("\n=== E2E PASSED ===");
    lieo_e2e_log("Manual UI check: login at /vms/lieo/login.php with password 123456");
    lieo_e2e_log("Application $appNo should show Gate_completed in Time Office → All Applications");
    exit(0);
}

lieo_e2e_log("\n=== E2E FAILED ===");
exit(1);
