<?php
/**
 * End-to-end LC/EG flow: create → Supervisor → N-1 → HOD → Security gate.
 *
 * Run: php database/run_clgp_e2e_flow.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['clgp_user_id'] = 1;

function clgp_e2e_actor(string $email): ?array
{
    $u = clgp_find_user_by_email($email);
    if (!$u || ($u['status'] ?? '') !== 'Active') {
        return null;
    }
    return [
        'clgp_user_id' => (int) $u['clgp_user_id'],
        'emp_code' => $u['emp_code'] ?? '',
        'full_name' => $u['full_name'],
        'role' => $u['role'],
        'plant' => $u['plant'] ?? '',
        'email' => $u['email'],
    ];
}

function clgp_e2e_session(array $actor): void
{
    $full = clgp_find_user_by_email($actor['email']);
    $_SESSION['clgp_role'] = $actor['role'];
    $_SESSION['clgp_user_id'] = $actor['clgp_user_id'];
    $_SESSION['clgp_user_name'] = $actor['full_name'];
    $_SESSION['clgp_emp_code'] = $actor['emp_code'];
    $_SESSION['clgp_plant'] = $actor['plant'];
    $_SESSION['clgp_department'] = $full['department'] ?? '';
}

function clgp_e2e_log(string $msg, array $extra = []): void
{
    echo $msg;
    if ($extra) {
        echo ' ' . json_encode($extra);
    }
    echo PHP_EOL;
}

$emails = [
    'timeoffice' => 'clgp.timeoffice@nuvoco.com',
    'supervisor' => 'clgp.supervisor@nuvoco.com',
    'n1' => 'clgp.n1@nuvoco.com',
    'hod' => 'clgp.hod@nuvoco.com',
    'security_nimbol' => 'clgp.security@nuvoco.com',
    'security_jcp' => 'clgp.security.jcp@nuvoco.com',
];

foreach ($emails as $key => $email) {
    if (!clgp_e2e_actor($email)) {
        clgp_e2e_log("FAIL: missing user $email — run: php database/seed_clgp_test_users.php");
        exit(1);
    }
}

// Workman for Nimbol
$workmanId = 0;
foreach (clgp_list_workmen('Active') as $w) {
    if ($w['workman_code'] === 'WM-CLGP-001' && $w['plant'] === 'Nimbol') {
        $workmanId = (int) $w['workman_id'];
        break;
    }
}
if ($workmanId < 1) {
    clgp_e2e_log('FAIL: workman WM-CLGP-001 not found — run seed script');
    exit(1);
}

$to = clgp_e2e_actor($emails['timeoffice']);
clgp_e2e_session($to);

clgp_e2e_log('1) Time Office creates Late Coming application');
$created = clgp_create_application([
    'workman_id' => $workmanId,
    'application_type' => 'Late Coming',
    'reason' => 'E2E test — transport delay',
    'created_by' => $to['clgp_user_id'],
]);
if (!$created['ok']) {
    clgp_e2e_log('FAIL create', $created);
    exit(1);
}
$appId = (int) $created['application_id'];
$appNo = $created['application_no'];
clgp_e2e_log('   Created', ['application_no' => $appNo, 'status' => clgp_get_application($appId)['status']]);

$chain = [
    ['supervisor', $emails['supervisor']],
    ['n1', $emails['n1']],
    ['hod', $emails['hod']],
];

$stepNum = 2;
foreach ($chain as [$role, $email]) {
    $actor = clgp_e2e_actor($email);
    clgp_e2e_session($actor);
    $app = clgp_get_application($appId);
    clgp_e2e_log("$stepNum) {$role} approves (pending: {$app['status']} / step {$app['current_step']})");

    $pending = clgp_list_applications(clgp_apply_session_plant_scope(['pending_for_role' => $role]));
    $found = false;
    foreach ($pending as $p) {
        if ((int) $p['application_id'] === $appId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        clgp_e2e_log("   WARN: app not in pending list for $role (check plant/dept/emp_code match)");
    } else {
        clgp_e2e_log('   OK: visible on Pending Approvals for this role');
    }

    $result = clgp_advance_application(
        $appId,
        $role,
        'approve',
        'E2E approved by ' . $role,
        [
            'clgp_user_id' => $actor['clgp_user_id'],
            'emp_code' => $actor['emp_code'],
            'full_name' => $actor['full_name'],
        ]
    );
    if (!$result['ok']) {
        clgp_e2e_log("   FAIL advance", $result);
        exit(1);
    }
    $app = clgp_get_application($appId);
    clgp_e2e_log('   After approve', ['status' => $app['status'], 'current_step' => $app['current_step']]);
    $stepNum++;
}

$app = clgp_get_application($appId);
if ($app['status'] !== 'Approved' || ($app['current_step'] ?? '') !== 'gate') {
    clgp_e2e_log('FAIL: expected Approved / gate after HOD', $app);
    exit(1);
}

$secN = clgp_e2e_actor($emails['security_nimbol']);
clgp_e2e_session($secN);
$plantApps = clgp_list_applications(clgp_apply_session_plant_scope([]));
$secNSees = false;
foreach ($plantApps as $p) {
    if ((int) $p['application_id'] === $appId && clgp_application_ready_for_gate($p)) {
        $secNSees = true;
        break;
    }
}
clgp_e2e_log("$stepNum) Security (Nimbol) — Approved apps at gate");
clgp_e2e_log($secNSees ? '   OK: Nimbol Security sees application ready to close' : '   FAIL: Nimbol Security does NOT see application');
$stepNum++;

$secJ = clgp_e2e_actor($emails['security_jcp']);
if ($secJ) {
    clgp_e2e_session($secJ);
    $plantAppsJ = clgp_list_applications(clgp_apply_session_plant_scope([]));
    $secJSees = false;
    foreach ($plantAppsJ as $p) {
        if ((int) $p['application_id'] === $appId) {
            $secJSees = true;
            break;
        }
    }
    clgp_e2e_log("$stepNum) Security (JCP) plant scope");
    clgp_e2e_log($secJSees ? '   FAIL: JCP Security incorrectly sees Nimbol application' : '   OK: JCP Security does not see Nimbol application');
    $stepNum++;
}

clgp_e2e_session($secN);
clgp_e2e_log("$stepNum) Security closes application (gate IN)");
$gate = clgp_gate_action($appId, 'in', $secN['clgp_user_id'], 'E2E gate close remark');
if (!$gate['ok']) {
    clgp_e2e_log('FAIL gate', $gate);
    exit(1);
}
$app = clgp_get_application($appId);
clgp_e2e_log('   Completed', [
    'status' => $app['status'],
    'gate_in_at' => $app['gate_in_at'],
    'application_no' => $appNo,
]);

$approvals = clgp_get_application_approvals($appId);
clgp_e2e_log("\n=== Approval audit trail ===");
foreach ($approvals as $a) {
    clgp_e2e_log("   {$a['step']}: {$a['action']} by {$a['approver_name']} ({$a['approver_emp_code']})");
}

if ($app['status'] === 'Gate_completed' && $secNSees) {
    clgp_e2e_log("\n=== E2E PASSED ===");
    clgp_e2e_log("Manual UI check: login at /vms/clgp/login.php with password 123456");
    clgp_e2e_log("Application $appNo should show Gate_completed in Time Office → All Applications");
    exit(0);
}

clgp_e2e_log("\n=== E2E FAILED ===");
exit(1);
