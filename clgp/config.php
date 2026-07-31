<?php
/**
 * CLGP module config — Phase 1 production (DB-backed).
 * App: Access control for Contract Workman Entry/Exit Pass
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/ui.php';

define('CLGP_APP_NAME', 'Access control for Contract Workman Entry/Exit Pass');
define('CLGP_APP_SHORT', 'CLGP');

/**
 * Absolute public base for CLGP (used in emails).
 * Override on non-prod if needed, e.g. define before requiring config.
 * Must include /clgp with no trailing slash.
 */
if (!defined('CLGP_PUBLIC_BASE_URL')) {
    define('CLGP_PUBLIC_BASE_URL', 'https://vms.nuvoco.in/clgp');
}

/** Phase 1 roles (no contractor login). */
$CLGP_ROLES = [
    'admin'      => ['label' => 'Admin',        'group' => 'admin', 'icon' => 'typcn-cog-outline'],
    'supervisor' => ['label' => 'Supervisor',   'group' => 'user',  'icon' => 'typcn-user-outline'],
    'n1'         => ['label' => 'N-1 Approver', 'group' => 'user',  'icon' => 'typcn-user-add-outline'],
    'hod'        => ['label' => 'HOD',          'group' => 'user',  'icon' => 'typcn-group-outline'],
    'timeoffice' => ['label' => 'Time Office',  'group' => 'user',  'icon' => 'typcn-time'],
    'security'   => ['label' => 'Security',     'group' => 'user',  'icon' => 'typcn-lock-closed-outline'],
    'hr'         => ['label' => 'HR Head',      'group' => 'user',  'icon' => 'typcn-contacts'],
];

/** Roles assignable via Approval Matrix (admin not included). */
$CLGP_APPROVAL_STEPS = [
    'timeoffice' => 'Time Office',
    'supervisor' => 'Supervisor',
    'n1'         => 'N-1 Approver',
    'hod'        => 'HOD',
    'security'   => 'Security',
    'hr'         => 'HR Head',
];

/** Matrix roles scoped to plant only (department stored as "All"). */
$CLGP_MATRIX_PLANT_ROLES = ['security', 'hr'];

/** LC/EG approval chain (Time Office only creates — does not approve). */
$CLGP_APP_CHAIN = ['supervisor', 'n1', 'hod'];

$CLGP_VENDOR_TYPES = ['Supply', 'Temporary', 'Measurement'];

$CLGP_PLANTS = ['Nimbol', 'Arasmeta', 'Mejia', 'Jojobera'];

$CLGP_DEPARTMENTS = ['Maintenance', 'Production', 'Projects', 'Electrical', 'Mechanical'];

$CLGP_SHIFTS = [
    'General (08:00–17:00)',
    'Shift A (06:00–14:00)',
    'Shift B (14:00–22:00)',
    'Shift C (22:00–06:00)',
];

function clgp_is_logged_in(): bool
{
    return !empty($_SESSION['clgp_role']) && !empty($_SESSION['clgp_user_id']);
}

function clgp_web_base(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/clgp/');
    if ($pos !== false) {
        return substr($script, 0, $pos + 5);
    }
    return '/vms/clgp';
}

function clgp_login_url(): string
{
    return clgp_web_base() . '/login.php';
}

function clgp_require_login(): void
{
    if (!clgp_is_logged_in()) {
        header('Location: ' . clgp_login_url());
        exit;
    }
    // Force password change
    if (!empty($_SESSION['clgp_must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['change_password.php', 'logout.php'], true)) {
            header('Location: ' . clgp_web_base() . '/change_password.php');
            exit;
        }
    }
}

function clgp_require_role(array $allowed): void
{
    clgp_require_login();
    if (!in_array($_SESSION['clgp_role'], $allowed, true)) {
        http_response_code(403);
        die('Access denied for this role.');
    }
}

function clgp_role_label(string $role): string
{
    global $CLGP_ROLES;
    return $CLGP_ROLES[$role]['label'] ?? ucfirst($role);
}

/** Two-letter initials for account avatar. */
function clgp_user_initials(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return mb_strtoupper(mb_substr($fullName, 0, 2));
}

function clgp_step_label(string $step): string
{
    global $CLGP_APPROVAL_STEPS, $CLGP_ROLES;
    $map = $CLGP_APPROVAL_STEPS + [
        'attestation' => 'Attestation',
        'gate' => 'Security (Gate)',
        'done' => 'Completed',
        'rejected' => 'Rejected',
    ];
    if (isset($CLGP_ROLES[$step]['label'])) {
        $map[$step] = $CLGP_ROLES[$step]['label'];
    }
    return $map[$step] ?? $step;
}

function clgp_matrix_needs_department(string $step): bool
{
    global $CLGP_MATRIX_PLANT_ROLES;
    return !in_array($step, $CLGP_MATRIX_PLANT_ROLES, true);
}

function clgp_status_badge(string $status): string
{
    $map = [
        'Pending_timeoffice' => 'warning',
        'Pending_supervisor' => 'warning',
        'Pending_n1'         => 'warning',
        'Pending_hod'        => 'warning',
        'Approved'           => 'info',
        'Attested'           => 'primary',
        'Gate_completed'     => 'success',
        'Rejected'           => 'danger',
        'Active'             => 'success',
        'Inactive'           => 'secondary',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge badge-' . $cls . '">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
}

function clgp_dashboard_url(string $role): string
{
    $path = 'approver/pending.php';
    if ($role === 'admin') {
        $path = 'admin/index.php';
    } elseif ($role === 'timeoffice') {
        $path = 'timeoffice/index.php';
    } elseif ($role === 'security') {
        $path = 'security/attendance.php';
    } elseif ($role === 'hr') {
        $path = 'hr/reactivation.php';
    }
    return clgp_web_base() . '/' . $path;
}

/** Directory depth of current script under /clgp/ (0 = file directly in clgp/). */
function clgp_path_depth_under_clgp(): int
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/clgp/');
    if ($pos === false) {
        return 1;
    }
    $after = substr($script, $pos + 6);
    if ($after === '' || strpos($after, '/') === false) {
        return 0;
    }
    return substr_count($after, '/');
}

function clgp_assets_prefix(): string
{
    return str_repeat('../', clgp_path_depth_under_clgp() + 1);
}

function clgp_root_prefix(): string
{
    $depth = clgp_path_depth_under_clgp();
    return $depth === 0 ? './' : str_repeat('../', $depth);
}

function clgp_nav_url(string $role, string $file): string
{
    $folders = [
        'admin' => 'admin',
        'timeoffice' => 'timeoffice',
        'security' => 'security',
        'hr' => 'hr',
        'supervisor' => 'approver',
        'n1' => 'approver',
        'hod' => 'approver',
    ];
    $folder = $folders[$role] ?? 'approver';
    return clgp_web_base() . '/' . $folder . '/' . ltrim($file, '/');
}

function clgp_generate_password(): string
{
    return (string) mt_rand(100000, 999999);
}

function clgp_mail_ready(): bool
{
    $emailSmtp = dirname(__DIR__) . '/emailSMTP.php';
    if (!is_file($emailSmtp)) {
        return false;
    }
    require_once $emailSmtp;
    return function_exists('sent_email');
}

/**
 * Absolute CLGP base URL for email links (email clients need full https://…).
 */
function clgp_public_base_url(): string
{
    $configured = defined('CLGP_PUBLIC_BASE_URL') ? trim((string) CLGP_PUBLIC_BASE_URL) : '';
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        return ($https ? 'https' : 'http') . '://' . $host . rtrim(clgp_web_base(), '/');
    }

    return 'https://vms.nuvoco.in/vms/clgp';
}

/** Absolute login URL for emails / deep links. */
function clgp_login_page_url(): string
{
    return clgp_public_base_url() . '/login.php';
}

/** Clickable portal CTA block appended to every CLGP email. */
function clgp_mail_portal_cta_html(): string
{
    $url = clgp_login_page_url();
    $safe = htmlspecialchars($url);
    return '<br><br>'
        . '<p style="margin:16px 0 8px;"><strong>Open CLGP portal:</strong></p>'
        . '<p style="margin:0 0 12px;">'
        . '<a href="' . $safe . '" '
        . 'style="background:#42bb52;color:#ffffff;padding:12px 20px;text-decoration:none;'
        . 'border-radius:4px;display:inline-block;font-weight:bold;font-family:Arial,sans-serif;font-size:14px;">'
        . 'Click here to login</a></p>'
        . '<p style="margin:0;font-size:12px;color:#64748b;">If the button does not work, copy and paste this link into your browser:<br>'
        . '<a href="' . $safe . '" style="color:#2563eb;word-break:break-all;">' . $safe . '</a></p>';
}

function clgp_send_mail(string $toEmail, string $toName, string $subject, string $bodyHtml): void
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !clgp_mail_ready()) {
        return;
    }
    $body = '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td style="font-family:Arial,sans-serif;font-size:14px;color:#0f172a;line-height:1.5;">'
        . $bodyHtml
        . clgp_mail_portal_cta_html()
        . '<br><br><em style="color:#64748b;font-size:12px;"><strong>Note:</strong> System-generated email from '
        . htmlspecialchars(CLGP_APP_SHORT)
        . '. Please do not reply.</em></td></tr></table>';
    @sent_email([$toEmail], [$toName !== '' ? $toName : $toEmail], [], [], $subject, $body, null);
}

function clgp_send_credentials_email(string $toEmail, string $toName, string $password): void
{
    $body = 'Dear ' . htmlspecialchars($toName) . ',<br><br>'
        . 'Your account has been created for <strong>' . htmlspecialchars(CLGP_APP_NAME) . '</strong>.<br><br>'
        . 'Login ID: <strong>' . htmlspecialchars($toEmail) . '</strong><br>'
        . 'Default Password: <strong>' . htmlspecialchars($password) . '</strong><br><br>'
        . 'Please use the button below to open the CLGP portal, then change your password after first login.';
    clgp_send_mail($toEmail, $toName, CLGP_APP_SHORT . ' :: Login Credentials', $body);
}

/** Resolve Approval Matrix assignee for plant/dept/step → [email, name] or null. */
function clgp_matrix_notify_recipient(string $plant, string $department, string $step): ?array
{
    $row = clgp_get_matrix_approver($plant, $department, $step);
    if (!$row) {
        return null;
    }
    $email = trim($row['emp_email'] ?? '');
    if ($email === '') {
        return null;
    }
    return [
        'email' => $email,
        'name' => trim($row['emp_name'] ?? '') ?: $email,
        'emp_code' => trim($row['emp_code'] ?? ''),
        'role' => $step,
    ];
}

function clgp_user_notify_recipient(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    $u = clgp_get_user($userId);
    if (!$u || ($u['status'] ?? '') !== 'Active') {
        return null;
    }
    $email = trim($u['email'] ?? '');
    if ($email === '') {
        return null;
    }
    return [
        'email' => $email,
        'name' => trim($u['full_name'] ?? '') ?: $email,
        'role' => $u['role'] ?? '',
    ];
}

/** @return list<array{email:string,name:string}> */
function clgp_list_role_notify_recipients(string $role): array
{
    $role = trim($role);
    $out = [];
    $stmt = clgp_db()->prepare(
        "SELECT full_name, email FROM tbl_clgp_user WHERE role=? AND status='Active' AND email<>''"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        if ($email === '') {
            continue;
        }
        $out[$email] = [
            'email' => $email,
            'name' => trim($row['full_name'] ?? '') ?: $email,
        ];
    }
    $stmt->close();
    return array_values($out);
}

function clgp_application_email_block(array $app): string
{
    $lines = [
        'Application No' => $app['application_no'] ?? '—',
        'Type' => $app['application_type'] ?? '—',
        'Workman' => trim(($app['workman_name'] ?? '') . ' (' . ($app['workman_code'] ?? '') . ')'),
        'Contractor' => $app['contractor_name'] ?? '—',
        'Plant / Dept' => trim(($app['plant'] ?? '') . ' / ' . ($app['department'] ?? '')),
        'Access' => $app['access_type'] ?? '—',
        'Reason' => $app['reason'] ?? '—',
        'Status' => str_replace('_', ' ', $app['status'] ?? '—'),
    ];
    $html = '<table cellpadding="4" cellspacing="0" border="0" style="border-collapse:collapse;">';
    foreach ($lines as $label => $value) {
        $html .= '<tr><td style="padding:3px 8px 3px 0;color:#64748b;"><strong>'
            . htmlspecialchars($label) . ':</strong></td><td style="padding:3px 0;">'
            . htmlspecialchars((string) $value) . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
}

function clgp_notify_application_created(array $app): void
{
    $to = clgp_matrix_notify_recipient($app['plant'] ?? '', $app['department'] ?? '', 'supervisor');
    if (!$to) {
        return;
    }
    $subject = CLGP_APP_SHORT . ' :: New application pending — ' . ($app['application_no'] ?? '');
    $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
        . 'A new <strong>Late Coming / Early Going</strong> application has been created and is pending your approval (Supervisor).<br><br>'
        . clgp_application_email_block($app)
        . '<br>Please sign in to CLGP to action it.';
    clgp_send_mail($to['email'], $to['name'], $subject, $body);
}

/**
 * After approve/reject — notify next role or Time Office (creator) on reject / HOD done → Security.
 */
function clgp_notify_application_action(array $app, string $actorRole, string $action, string $remark, array $result): void
{
    $appNo = $app['application_no'] ?? '';
    $actorLabel = clgp_role_label($actorRole);
    $remarkHtml = $remark !== '' ? '<br><strong>Remark:</strong> ' . htmlspecialchars($remark) : '';

    if ($action === 'reject') {
        $creator = clgp_user_notify_recipient((int) ($app['created_by'] ?? 0));
        if ($creator) {
            $subject = CLGP_APP_SHORT . ' :: Application rejected — ' . $appNo;
            $body = 'Dear ' . htmlspecialchars($creator['name']) . ',<br><br>'
                . 'Application <strong>' . htmlspecialchars($appNo) . '</strong> was <strong>rejected</strong> by '
                . htmlspecialchars($actorLabel) . '.'
                . $remarkHtml . '<br><br>'
                . clgp_application_email_block(array_merge($app, ['status' => 'Rejected']));
            clgp_send_mail($creator['email'], $creator['name'], $subject, $body);
        }
        return;
    }

    $status = $result['status'] ?? '';
    if ($status === 'Approved') {
        $to = clgp_matrix_notify_recipient($app['plant'] ?? '', $app['department'] ?? '', 'security');
        if ($to) {
            $subject = CLGP_APP_SHORT . ' :: Ready for gate — ' . $appNo;
            $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
                . 'HOD has approved application <strong>' . htmlspecialchars($appNo) . '</strong>. '
                . 'It is ready to <strong>close at gate</strong> (Security).'
                . $remarkHtml . '<br><br>'
                . clgp_application_email_block(array_merge($app, ['status' => 'Approved']));
            clgp_send_mail($to['email'], $to['name'], $subject, $body);
        }
        return;
    }

    $nextMap = [
        'Pending_n1' => 'n1',
        'Pending_hod' => 'hod',
        'Pending_supervisor' => 'supervisor',
    ];
    $nextStep = $nextMap[$status] ?? '';
    if ($nextStep === '') {
        return;
    }
    $to = clgp_matrix_notify_recipient($app['plant'] ?? '', $app['department'] ?? '', $nextStep);
    if (!$to) {
        return;
    }
    $subject = CLGP_APP_SHORT . ' :: Pending your approval — ' . $appNo;
    $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
        . 'Application <strong>' . htmlspecialchars($appNo) . '</strong> was approved by '
        . htmlspecialchars($actorLabel) . ' and is now pending <strong>'
        . htmlspecialchars(clgp_role_label($nextStep)) . '</strong>.'
        . $remarkHtml . '<br><br>'
        . clgp_application_email_block(array_merge($app, ['status' => $status]));
    clgp_send_mail($to['email'], $to['name'], $subject, $body);
}

function clgp_notify_gate_closed(array $app, string $gateAction, string $remark): void
{
    $creator = clgp_user_notify_recipient((int) ($app['created_by'] ?? 0));
    if (!$creator) {
        return;
    }
    $appNo = $app['application_no'] ?? '';
    $label = $gateAction === 'in' ? 'Gate IN' : 'Gate OUT';
    $subject = CLGP_APP_SHORT . ' :: Application closed at gate — ' . $appNo;
    $body = 'Dear ' . htmlspecialchars($creator['name']) . ',<br><br>'
        . 'Security has closed application <strong>' . htmlspecialchars($appNo) . '</strong> ('
        . htmlspecialchars($label) . ').'
        . ($remark !== '' ? '<br><strong>Gate remark:</strong> ' . htmlspecialchars($remark) : '')
        . '<br><br>'
        . clgp_application_email_block(array_merge($app, ['status' => 'Gate_completed']));
    clgp_send_mail($creator['email'], $creator['name'], $subject, $body);
}

function clgp_notify_reactivation_requested(array $contractor): void
{
    $recipients = clgp_list_role_notify_recipients('hr');
    if (!$recipients) {
        return;
    }
    $vendor = $contractor['vendor_name'] ?? '';
    $cname = $contractor['contractor_name'] ?? '';
    $subject = CLGP_APP_SHORT . ' :: Contractor reactivation requested';
    foreach ($recipients as $to) {
        $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
            . 'Admin has requested reactivation of contractor:<br><br>'
            . '<strong>Vendor:</strong> ' . htmlspecialchars($vendor) . '<br>'
            . '<strong>Contractor:</strong> ' . htmlspecialchars($cname) . '<br>'
            . '<strong>Deactivated at:</strong> ' . htmlspecialchars($contractor['deactivated_at'] ?? '—') . '<br>'
            . '<strong>Reason:</strong> ' . htmlspecialchars($contractor['deactivation_reason'] ?? '—') . '<br><br>'
            . 'Please sign in to CLGP → Reactivation Requests to approve.';
        clgp_send_mail($to['email'], $to['name'], $subject, $body);
    }
}
