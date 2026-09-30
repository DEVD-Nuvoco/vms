<?php
/**
 * LIEO module config — Late IN / Early Out (DB-backed).
 * Public folder/URL is /lieo. Internal PHP names may still use lieo_*.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/env.php';
lieo_load_dotenv();

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/ui.php';

define('LIEO_APP_NAME', 'Late IN / Early Out');
define('LIEO_APP_SHORT', 'LIEO');

/**
 * Absolute public base for LIEO (used in emails).
 * Override on non-prod if needed, e.g. define before requiring config.
 * Must include /lieo with no trailing slash.
 */
if (!defined('LIEO_PUBLIC_BASE_URL')) {
    define('LIEO_PUBLIC_BASE_URL', 'https://vms.nuvoco.in/lieo');
}

/**
 * Live roles. 'section_incharge' and 'hr' are retired (blocked at login,
 * see login.php) — Section Incharge's job moved to N-1 (creates directly);
 * HR Head's job (contractor reactivation approval) moved to the HR
 * department HOD (see lieo_is_hr_hod(), approver/reactivation.php). Their
 * DB enum values are left in place for any historical row, just no longer
 * offered anywhere in the app.
 */
$LIEO_ROLES = [
    'admin'            => ['label' => 'Admin',             'group' => 'admin', 'icon' => 'typcn-cog-outline'],
    'timeoffice'       => ['label' => 'Time Office',       'group' => 'user',  'icon' => 'typcn-time'],
    'n1'               => ['label' => 'N-1 Approver',      'group' => 'user',  'icon' => 'typcn-user-add-outline'],
    'hod'              => ['label' => 'HOD',               'group' => 'user',  'icon' => 'typcn-group-outline'],
    'security'         => ['label' => 'Security',          'group' => 'user',  'icon' => 'typcn-lock-closed-outline'],
];

/** Roles assignable via Approval Matrix (admin not included). */
$LIEO_APPROVAL_STEPS = [
    'timeoffice'       => 'Time Office',
    'n1'               => 'N-1 Approver',
    'hod'              => 'HOD',
    'security'         => 'Security',
];

/** Built-in names; Admin → Role Names overrides the label only, never the key. */
$LIEO_ROLE_DEFAULT_LABELS = array_map(function ($r) { return $r['label']; }, $LIEO_ROLES);
foreach (lieo_list_role_label_overrides() as $roleKey => $roleLabel) {
    if (isset($LIEO_ROLES[$roleKey])) {
        $LIEO_ROLES[$roleKey]['label'] = $roleLabel;
    }
    if (isset($LIEO_APPROVAL_STEPS[$roleKey])) {
        $LIEO_APPROVAL_STEPS[$roleKey] = $roleLabel;
    }
}

/**
 * Steps Admin can assign on the Approval Matrix page. Time Office no longer
 * maintains the matrix at all (view-only/tracking role now) — Admin is the
 * only one left who assigns it, same as before, and that assignment stays a
 * direct/live save (spec only requires HR-department-HOD approval for
 * "the HOD, N-1 and Security role" — Time Office isn't in that list).
 */
$LIEO_ADMIN_MATRIX_STEPS = ['timeoffice', 'hod', 'n1', 'security'];

/**
 * Subset of $LIEO_ADMIN_MATRIX_STEPS that must go through the pending
 * tbl_lieo_user_request approval flow (HR-department HOD sign-off) instead of
 * saving live — see lieo_submit_user_request().
 */
$LIEO_ADMIN_REQUEST_GATED_STEPS = ['hod', 'n1', 'security'];

/**
 * Matrix roles scoped to plant only (department stored as "All").
 * 'hod' is per-department now (removed from this list) — see
 * lieo_list_matrix_departments_for_user() for how one person can still hold
 * HOD for more than one department.
 */
$LIEO_MATRIX_PLANT_ROLES = ['timeoffice', 'security'];

/**
 * Approval chain after create: N-1 creates the application directly (see
 * approver/create_application.php), HOD approves, then Security closes at
 * the gate. Only 'hod' remains a chain step.
 */
$LIEO_APP_CHAIN = ['hod'];

$LIEO_CONTRACTOR_TYPES = ['Supply', 'Temporary', 'Measurement'];
/** @deprecated use $LIEO_CONTRACTOR_TYPES */
$LIEO_VENDOR_TYPES = $LIEO_CONTRACTOR_TYPES;

$LIEO_PLANTS = ['Nimbol', 'Arasmeta', 'Mejia', 'Jojobera'];

$LIEO_DEPARTMENTS = ['Maintenance', 'Production', 'Projects', 'Electrical', 'Mechanical'];

function lieo_is_logged_in(): bool
{
    return !empty($_SESSION['lieo_role']) && !empty($_SESSION['lieo_user_id']);
}

function lieo_web_base(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/lieo/', '/lieo/'] as $seg) {
        $pos = strpos($script, $seg);
        if ($pos !== false) {
            return substr($script, 0, $pos + 5);
        }
    }
    return '/vms/lieo';
}

function lieo_login_url(): string
{
    return lieo_web_base() . '/login.php';
}

function lieo_require_login(): void
{
    if (!lieo_is_logged_in()) {
        header('Location: ' . lieo_login_url());
        exit;
    }
    // Force password change
    if (!empty($_SESSION['lieo_must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['change_password.php', 'logout.php'], true)) {
            header('Location: ' . lieo_web_base() . '/change_password.php');
            exit;
        }
    }
}

function lieo_require_role(array $allowed): void
{
    lieo_require_login();
    // A person who is both N-1 and Security (the one dual-role combo LIEO
    // allows — N-1 creates the application, Security closes it at the gate)
    // carries the second role in lieo_secondary_role, since their login row
    // only has one primary role.
    $roles = array_filter([$_SESSION['lieo_role'] ?? null, $_SESSION['lieo_secondary_role'] ?? null]);
    if (!array_intersect($roles, $allowed)) {
        http_response_code(403);
        die('Access denied for this role.');
    }
}

function lieo_role_label(string $role): string
{
    global $LIEO_ROLES;
    return $LIEO_ROLES[$role]['label'] ?? ucfirst($role);
}

/** Display label for stored application_type (DB keeps Late Coming / Early Going). */
function lieo_application_type_label(string $type): string
{
    $map = [
        'Late Coming' => 'Late IN',
        'Early Going' => 'Early Out',
    ];
    return $map[$type] ?? $type;
}

/** Two-letter initials for account avatar. */
function lieo_user_initials(string $fullName): string
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

function lieo_step_label(string $step): string
{
    global $LIEO_APPROVAL_STEPS, $LIEO_ROLES;
    $map = $LIEO_APPROVAL_STEPS + [
        'attestation' => 'Attestation',
        'gate' => 'Security (Gate)',
        'done' => 'Completed',
        'rejected' => 'Rejected',
    ];
    if (isset($LIEO_ROLES[$step]['label'])) {
        $map[$step] = $LIEO_ROLES[$step]['label'];
    }
    return $map[$step] ?? $step;
}

function lieo_matrix_needs_department(string $step): bool
{
    global $LIEO_MATRIX_PLANT_ROLES;
    return !in_array($step, $LIEO_MATRIX_PLANT_ROLES, true);
}

function lieo_status_badge(string $status): string
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

function lieo_dashboard_url(string $role): string
{
    $path = 'approver/pending.php';
    if ($role === 'admin') {
        $path = 'admin/index.php';
    } elseif ($role === 'timeoffice') {
        $path = 'timeoffice/index.php';
    } elseif ($role === 'hod' || $role === 'n1') {
        $path = 'approver/dashboard.php';
    } elseif ($role === 'security') {
        $path = 'security/attendance.php';
    }
    return lieo_web_base() . '/' . $path;
}

/** Directory depth of current script under /lieo/ (or legacy /lieo/). */
function lieo_path_depth_under_lieo(): int
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/lieo/');
    if ($pos === false) {
        $pos = strpos($script, '/lieo/');
    }
    if ($pos === false) {
        return 1;
    }
    $after = substr($script, $pos + 6);
    if ($after === '' || strpos($after, '/') === false) {
        return 0;
    }
    return substr_count($after, '/');
}

function lieo_assets_prefix(): string
{
    return str_repeat('../', lieo_path_depth_under_lieo() + 1);
}

function lieo_root_prefix(): string
{
    $depth = lieo_path_depth_under_lieo();
    return $depth === 0 ? './' : str_repeat('../', $depth);
}

function lieo_nav_url(string $role, string $file): string
{
    $folders = [
        'admin' => 'admin',
        'timeoffice' => 'timeoffice',
        'security' => 'security',
        'n1' => 'approver',
        'hod' => 'approver',
    ];
    $folder = $folders[$role] ?? 'approver';
    return lieo_web_base() . '/' . $folder . '/' . ltrim($file, '/');
}

function lieo_generate_password(): string
{
    return (string) mt_rand(100000, 999999);
}

function lieo_mail_ready(): bool
{
    if (lieo_is_test_mail_mode()) {
        return true;
    }
    $emailSmtp = dirname(__DIR__) . '/emailSMTP.php';
    if (!is_file($emailSmtp)) {
        return false;
    }
    $prev = error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    require_once $emailSmtp;
    error_reporting($prev);
    return function_exists('sent_email');
}

/**
 * Queue a mail for the local test-mail popup (no SMTP).
 *
 * @param array{context?:string,to_role?:string,cc_roles?:array<string,string>} $meta
 */
function lieo_queue_test_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml,
    array $ccEmails = [],
    array $meta = []
): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['lieo_test_mails']) || !is_array($_SESSION['lieo_test_mails'])) {
        $_SESSION['lieo_test_mails'] = [];
    }
    $_SESSION['lieo_test_mails'][] = [
        'to' => $toEmail,
        'to_name' => $toName,
        'to_role' => (string) ($meta['to_role'] ?? ''),
        'cc' => array_values($ccEmails),
        'cc_roles' => is_array($meta['cc_roles'] ?? null) ? $meta['cc_roles'] : [],
        'subject' => $subject,
        'context' => (string) ($meta['context'] ?? 'Notification'),
        'body' => $bodyHtml,
        'at' => date('Y-m-d H:i:s'),
    ];
}

/** @return list<array<string,mixed>> */
function lieo_consume_test_mails(): array
{
    if (empty($_SESSION['lieo_test_mails']) || !is_array($_SESSION['lieo_test_mails'])) {
        return [];
    }
    $mails = $_SESSION['lieo_test_mails'];
    $_SESSION['lieo_test_mails_last'] = $mails;
    unset($_SESSION['lieo_test_mails']);
    return $mails;
}

/** Re-queue the last preview so "Email test" can reopen it (local mode). */
function lieo_restore_last_test_mails(): bool
{
    if (empty($_SESSION['lieo_test_mails_last']) || !is_array($_SESSION['lieo_test_mails_last'])) {
        return false;
    }
    $_SESSION['lieo_test_mails'] = $_SESSION['lieo_test_mails_last'];
    return true;
}

/**
 * Build/send (or preview) a sample LIEO notification for Approval Matrix "Email test".
 *
 * @return array{ok:bool,message:string}
 */
function lieo_matrix_email_test(int $matrixId = 0): array
{
    $row = null;
    if ($matrixId > 0) {
        foreach (lieo_list_matrix() as $r) {
            if ((int) ($r['matrix_id'] ?? 0) === $matrixId) {
                $row = $r;
                break;
            }
        }
    }
    if (!$row) {
        $list = lieo_list_matrix();
        $row = $list[0] ?? null;
    }

    if ($row) {
        $toEmail = trim((string) ($row['emp_email'] ?? ''));
        $toName = trim((string) ($row['emp_name'] ?? '')) ?: $toEmail;
        $role = (string) ($row['approval_step'] ?? '');
        $roleLabel = lieo_role_label($role);
        $plant = (string) ($row['plant'] ?? '');
        $dept = (string) ($row['department'] ?? '');
        if ($toEmail === '') {
            return ['ok' => false, 'message' => 'Selected assignment has no email.'];
        }
        $subject = LIEO_APP_SHORT . ' :: Email test — ' . $roleLabel;
        $body = 'Dear ' . htmlspecialchars($toName) . ',<br><br>'
            . 'This is a <strong>LIEO email test</strong> for role <strong>' . htmlspecialchars($roleLabel) . '</strong>.<br><br>'
            . 'Plant: <strong>' . htmlspecialchars($plant) . '</strong><br>'
            . 'Department: <strong>' . htmlspecialchars($dept === 'All' ? 'All departments' : $dept) . '</strong><br><br>'
            . 'If you can read this in the preview (or inbox), mail routing is working.';
        $plantDept = trim($plant . ($dept !== '' && $dept !== 'All' ? ' / ' . $dept : ''));
        lieo_send_mail($toEmail, $toName, $subject, $body, [], [
            'context' => 'Email Test',
            'headline' => 'LIEO email test — ' . $roleLabel,
            'subhead' => 'Late IN / Early Out mail routing check.',
            'to_role' => $roleLabel,
            'cards' => [
                ['label' => 'Login ID', 'value' => $toEmail],
                ['label' => 'Role', 'value' => $roleLabel],
                ['label' => 'Plant / Dept', 'value' => $plantDept !== '' ? $plantDept : '—'],
            ],
        ]);
        if (lieo_is_test_mail_mode()) {
            return ['ok' => true, 'message' => 'Email test preview ready (not sent — local=1).'];
        }
        return ['ok' => true, 'message' => 'Email test sent to ' . $toEmail . '.'];
    }

    // No matrix rows yet — still allow a demo preview/send to the logged-in user.
    $toEmail = trim((string) ($_SESSION['lieo_user_email'] ?? ''));
    $toName = trim((string) ($_SESSION['lieo_user_name'] ?? 'LIEO User')) ?: 'LIEO User';
    if ($toEmail === '') {
        $toEmail = 'lieo.test@local.test';
    }
    $subject = LIEO_APP_SHORT . ' :: Email test (sample)';
    $body = 'Dear ' . htmlspecialchars($toName) . ',<br><br>'
        . 'This is a sample <strong>LIEO</strong> notification used for mail UI testing.<br><br>'
        . 'Add a LIEO Users assignment to test with a real role recipient.';
    lieo_send_mail($toEmail, $toName, $subject, $body, [], [
        'context' => 'Email Test',
        'headline' => 'LIEO email test (sample)',
        'subhead' => 'Late IN / Early Out mail UI check.',
        'to_role' => 'Tester',
        'cards' => [
            ['label' => 'Application', 'value' => 'LIEO'],
            ['label' => 'Mode', 'value' => lieo_is_test_mail_mode() ? 'Preview (local=1)' : 'SMTP send'],
            ['label' => 'Plant / Dept', 'value' => '—'],
        ],
    ]);
    if (lieo_is_test_mail_mode()) {
        return ['ok' => true, 'message' => 'Sample email test preview ready (not sent — local=1).'];
    }
    return ['ok' => true, 'message' => 'Sample email test sent to ' . $toEmail . '.'];
}

/**
 * Absolute LIEO base URL for email links (email clients need full https://…).
 */
function lieo_public_base_url(): string
{
    $configured = defined('LIEO_PUBLIC_BASE_URL') ? trim((string) LIEO_PUBLIC_BASE_URL) : '';
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        return ($https ? 'https' : 'http') . '://' . $host . rtrim(lieo_web_base(), '/');
    }

    return 'https://vms.nuvoco.in/lieo';
}

/** Absolute login URL for emails / deep links. */
function lieo_login_page_url(): string
{
    return lieo_public_base_url() . '/login.php';
}

function lieo_mail_logo_url(): string
{
    $base = lieo_public_base_url();
    $root = preg_replace('#/lieo$#', '', $base) ?: $base;
    return rtrim((string) $root, '/') . '/images/nuvoco-ori.png';
}

/**
 * Branded LIEO HTML email body (used for real send and local preview).
 * Colors follow prefers-color-scheme (system light/dark) where the client supports it.
 *
 * @param list<array{label:string,value:string}> $cards
 */
function lieo_mail_branded_html(string $headline, string $subhead, string $contentHtml, array $cards = []): string
{
    $logo = htmlspecialchars(lieo_mail_logo_url());
    $loginUrl = htmlspecialchars(lieo_login_page_url());
    $appName = htmlspecialchars(LIEO_APP_NAME);
    $appShort = htmlspecialchars(LIEO_APP_SHORT);
    $headline = htmlspecialchars($headline);
    $subhead = htmlspecialchars($subhead);

    $cardsHtml = '';
    if ($cards) {
        $cardsHtml .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;"><tr>';
        foreach ($cards as $card) {
            $label = htmlspecialchars(strtoupper((string) ($card['label'] ?? '')));
            $value = htmlspecialchars((string) ($card['value'] ?? '—'));
            $cardsHtml .= '<td width="33%" valign="top" style="padding:6px;">'
                . '<div class="lieo-mail-card" style="border:1px solid #e2e8f0;border-radius:10px;padding:14px 12px;background:#f8fafc;">'
                . '<div class="lieo-mail-card-label" style="font-size:10px;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:6px;">'
                . $label . '</div>'
                . '<div class="lieo-mail-card-value" style="font-size:15px;font-weight:700;color:#0f172a;word-break:break-word;">'
                . $value . '</div></div></td>';
        }
        $cardsHtml .= '</tr></table>';
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
  :root {
    --lieo-page: #f1f5f9;
    --lieo-surface: #ffffff;
    --lieo-card: #f8fafc;
    --lieo-border: #e2e8f0;
    --lieo-text: #0f172a;
    --lieo-muted: #64748b;
    --lieo-green: #42bb52;
    --lieo-green-dark: #2f9e40;
    --lieo-banner-text: #ffffff;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --lieo-page: #0b1220;
      --lieo-surface: #111827;
      --lieo-card: #1e293b;
      --lieo-border: #334155;
      --lieo-text: #f8fafc;
      --lieo-muted: #94a3b8;
    }
    .lieo-mail-card { background: #1e293b !important; border-color: #334155 !important; }
    .lieo-mail-card-label { color: #94a3b8 !important; }
    .lieo-mail-card-value { color: #f8fafc !important; }
    .lieo-mail-row-label { color: #94a3b8 !important; border-bottom-color: #334155 !important; }
    .lieo-mail-row-value { color: #f8fafc !important; border-bottom-color: #334155 !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:var(--lieo-page);font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:var(--lieo-page);padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="640" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;width:100%;background:var(--lieo-surface);border:1px solid var(--lieo-border);border-radius:12px;overflow:hidden;">
          <tr>
            <td style="padding:18px 22px;border-bottom:1px solid var(--lieo-border);">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td valign="middle">
                    <table cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td valign="middle" style="width:36px;height:36px;background:#42bb52;border-radius:8px;color:#fff;font-weight:700;text-align:center;line-height:36px;font-size:18px;">L</td>
                        <td style="padding-left:12px;">
                          <div style="font-size:18px;font-weight:700;color:#42bb52;">{$appShort} - Notification</div>
                          <div style="font-size:12px;color:var(--lieo-muted);">{$appName}</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td valign="middle" align="right">
                    <img src="{$logo}" alt="Nuvoco" width="88" style="display:block;border:0;height:auto;">
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 22px 8px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#42bb52;border-radius:10px;">
                <tr>
                  <td style="padding:18px 20px;color:#ffffff;">
                    <div style="font-size:20px;font-weight:700;line-height:1.3;">{$headline}</div>
                    <div style="font-size:13px;margin-top:6px;opacity:.95;">{$subhead}</div>
                  </td>
                  <td width="56" align="center" valign="middle" style="padding-right:16px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#2f9e40;color:#fff;font-weight:700;line-height:34px;text-align:center;">!</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 16px 8px;">{$cardsHtml}</td>
          </tr>
          <tr>
            <td style="padding:4px 22px 22px;color:var(--lieo-text);font-size:14px;line-height:1.55;">
              <div style="font-size:16px;font-weight:700;margin:8px 0 6px;color:var(--lieo-text);">Application Information</div>
              <div style="width:48px;height:3px;background:#42bb52;margin-bottom:14px;"></div>
              {$contentHtml}
              <div style="margin-top:22px;">
                <a href="{$loginUrl}" style="background:#42bb52;color:#ffffff;padding:12px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:700;font-size:14px;">Click here to login</a>
              </div>
              <p style="margin:14px 0 0;font-size:12px;color:var(--lieo-muted);">
                If the button does not work, copy this link:<br>
                <a href="{$loginUrl}" style="color:#42bb52;word-break:break-all;">{$loginUrl}</a>
              </p>
              <p style="margin:18px 0 0;font-size:12px;color:var(--lieo-muted);">
                <em><strong>Note:</strong> System-generated email from {$appShort}. Please do not reply.</em>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * @param array{
 *   context?:string,
 *   headline?:string,
 *   subhead?:string,
 *   cards?:list<array{label:string,value:string}>,
 *   to_role?:string,
 *   cc_roles?:array<string,string>
 * } $options
 */
function lieo_send_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml,
    array $ccEmails = [],
    array $options = []
): void {
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !lieo_mail_ready()) {
        return;
    }

    $headline = trim((string) ($options['headline'] ?? ''));
    if ($headline === '') {
        $headline = preg_replace('/^' . preg_quote(LIEO_APP_SHORT, '/') . '\s*::\s*/i', '', $subject) ?: $subject;
    }
    $subhead = trim((string) ($options['subhead'] ?? '')) ?: (LIEO_APP_NAME . ' requires your attention.');
    $cards = is_array($options['cards'] ?? null) ? $options['cards'] : [];
    $body = lieo_mail_branded_html($headline, $subhead, $bodyHtml, $cards);

    $cc = [];
    foreach ($ccEmails as $addr) {
        $addr = trim((string) $addr);
        if ($addr !== '' && strcasecmp($addr, $toEmail) !== 0) {
            $cc[] = $addr;
        }
    }
    $cc = array_values(array_unique($cc));

    if (lieo_is_test_mail_mode()) {
        lieo_queue_test_mail(
            $toEmail,
            $toName !== '' ? $toName : $toEmail,
            $subject,
            $body,
            $cc,
            [
                'context' => (string) ($options['context'] ?? 'Notification'),
                'to_role' => (string) ($options['to_role'] ?? ''),
                'cc_roles' => is_array($options['cc_roles'] ?? null) ? $options['cc_roles'] : [],
            ]
        );
        return;
    }

    @sent_email([$toEmail], [$toName !== '' ? $toName : $toEmail], $cc, [], $subject, $body, null);
}

function lieo_send_credentials_email(string $toEmail, string $toName, string $password, bool $isResend = false, string $role = '', array $cc = []): void
{
    $intro = $isResend
        ? 'Your login credentials for <strong>' . htmlspecialchars(LIEO_APP_NAME) . ' (' . htmlspecialchars(LIEO_APP_SHORT) . ')</strong> have been resent.'
        : 'Your account has been created for <strong>' . htmlspecialchars(LIEO_APP_NAME) . ' (' . htmlspecialchars(LIEO_APP_SHORT) . ')</strong>.';
    $subject = $isResend
        ? (LIEO_APP_SHORT . ' :: Login Credentials (Resent)')
        : (LIEO_APP_SHORT . ' :: Login Credentials');
    $body = 'Dear ' . htmlspecialchars($toName) . ',<br><br>'
        . $intro . '<br><br>'
        . 'Please use the button below to open the LIEO portal, then change your password after first login.';

    $roleLabel = $role !== '' ? lieo_role_label($role) : '';
    lieo_send_mail($toEmail, $toName, $subject, $body, $cc, [
        'context' => 'Login Credentials',
        'headline' => $isResend ? 'Login Credentials Resent' : 'New LIEO Login Created',
        'subhead' => 'Late IN / Early Out access credentials.',
        'to_role' => $roleLabel,
        'cards' => [
            ['label' => 'Login ID', 'value' => $toEmail],
            ['label' => 'Temp Password', 'value' => $password],
            ['label' => 'Role', 'value' => $roleLabel !== '' ? $roleLabel : 'LIEO User'],
        ],
    ]);
}

/** Notify an existing user that a LIEO role was assigned/updated (no password change). */
function lieo_send_role_assigned_email(
    string $toEmail,
    string $toName,
    string $role,
    string $plant = '',
    string $department = '',
    array $cc = []
): void {
    $roleLabel = lieo_role_label($role);
    $subject = LIEO_APP_SHORT . ' :: Role assigned — ' . $roleLabel;
    $body = 'Dear ' . htmlspecialchars($toName) . ',<br><br>'
        . 'You have been assigned as <strong>' . htmlspecialchars($roleLabel) . '</strong> in '
        . '<strong>' . htmlspecialchars(LIEO_APP_NAME) . '</strong>.<br><br>'
        . 'Use your existing LIEO login to sign in. If you forgot your password, ask Admin / Time Office to resend credentials.';

    $plantDept = trim($plant . ($department !== '' && $department !== 'All' ? ' / ' . $department : ''));
    lieo_send_mail($toEmail, $toName, $subject, $body, $cc, [
        'context' => 'Role Assignment',
        'headline' => 'Role assigned: ' . $roleLabel,
        'subhead' => 'Late IN / Early Out role assignment notification.',
        'to_role' => $roleLabel,
        'cards' => [
            ['label' => 'Login ID', 'value' => $toEmail],
            ['label' => 'Role', 'value' => $roleLabel],
            ['label' => 'Plant / Dept', 'value' => $plantDept !== '' ? $plantDept : '—'],
        ],
    ]);
}

/** Resolve Approval Matrix assignee for plant/dept/step → [email, name] or null. */
function lieo_matrix_notify_recipient(string $plant, string $department, string $step): ?array
{
    $row = lieo_get_matrix_approver($plant, $department, $step);
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

function lieo_user_notify_recipient(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    $u = lieo_get_user($userId);
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
function lieo_list_role_notify_recipients(string $role): array
{
    $role = trim($role);
    $out = [];
    $stmt = lieo_db()->prepare(
        "SELECT full_name, email FROM tbl_lieo_user WHERE role=? AND status='Active' AND email<>''"
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

function lieo_application_email_block(array $app): string
{
    $lines = [
        'Application No' => $app['application_no'] ?? '—',
        'Type' => lieo_application_type_label((string) ($app['application_type'] ?? '—')),
        'Workman' => trim(($app['workman_name'] ?? '') . ' (' . ($app['workman_code'] ?? '') . ')'),
        'Contractor' => $app['contractor_name'] ?? '—',
        'Plant / Dept' => trim(($app['plant'] ?? '') . ' / ' . ($app['department'] ?? '')),
        'Access' => $app['access_type'] ?? '—',
        'Reason' => $app['reason'] ?? '—',
        'Status' => str_replace('_', ' ', $app['status'] ?? '—'),
    ];
    $html = '<table width="100%" cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">';
    foreach ($lines as $label => $value) {
        $html .= '<tr>'
            . '<td class="lieo-mail-row-label" style="padding:8px 0;color:#64748b;width:38%;border-bottom:1px solid #e2e8f0;">'
            . htmlspecialchars($label) . '</td>'
            . '<td class="lieo-mail-row-value" style="padding:8px 0;color:#0f172a;font-weight:700;border-bottom:1px solid #e2e8f0;">'
            . htmlspecialchars((string) $value) . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
}

/** @return list<array{label:string,value:string}> */
function lieo_application_mail_cards(array $app): array
{
    $workman = trim((string) ($app['workman_name'] ?? ''));
    $code = trim((string) ($app['workman_code'] ?? ''));
    if ($workman !== '' && $code !== '') {
        $workmanLabel = $workman . ' (' . $code . ')';
    } else {
        $workmanLabel = $workman !== '' ? $workman : ($code !== '' ? $code : '—');
    }

    return [
        ['label' => 'Application No', 'value' => (string) ($app['application_no'] ?? '—')],
        ['label' => 'Workman', 'value' => $workmanLabel],
        ['label' => 'Plant / Dept', 'value' => trim(($app['plant'] ?? '') . ' / ' . ($app['department'] ?? '')) ?: '—'],
    ];
}

function lieo_notify_application_created(array $app): void
{
    $plant = $app['plant'] ?? '';
    $dept = $app['department'] ?? '';
    $to = lieo_matrix_notify_recipient($plant, $dept, 'hod');
    if ($to) {
        $subject = LIEO_APP_SHORT . ' :: New application pending — ' . ($app['application_no'] ?? '');
        $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
            . 'A new <strong>Late IN / Early Out</strong> application has been created by N-1 and is pending your approval (HOD).<br><br>'
            . lieo_application_email_block($app)
            . '<br>Please sign in to LIEO to action it.';
        lieo_send_mail($to['email'], $to['name'], $subject, $body, [], [
            'context' => 'Application Created',
            'headline' => 'New application pending HOD',
            'subhead' => 'Late IN / Early Out requires your approval.',
            'to_role' => 'HOD',
            'cards' => lieo_application_mail_cards($app),
        ]);
    }

    $contractorId = (int) ($app['contractor_id'] ?? 0);
    if ($contractorId > 0) {
        $c = lieo_get_contractor($contractorId);
        $supEmail = trim((string) ($c['email'] ?? ''));
        $supName = trim((string) ($c['supervisor_name'] ?? ''));
        if ($supEmail !== '') {
            $subject = LIEO_APP_SHORT . ' :: Application created (information) — ' . ($app['application_no'] ?? '');
            $body = 'Dear ' . htmlspecialchars($supName !== '' ? $supName : 'Supervisor') . ',<br><br>'
                . 'A Late IN / Early Out application has been created for your contractor. This mail is for information only; no approval is required from you.<br><br>'
                . lieo_application_email_block($app);
            lieo_send_mail($supEmail, $supName !== '' ? $supName : $supEmail, $subject, $body, [], [
                'context' => 'Application Information',
                'headline' => 'Application created (information only)',
                'subhead' => 'No action required from Contractor Supervisor.',
                'to_role' => 'Contractor Supervisor',
                'cards' => lieo_application_mail_cards($app),
            ]);
        }
    }
}

/**
 * After approve/reject — notify next role or Time Office (creator) on reject / HOD done → Security.
 */
function lieo_notify_application_action(array $app, string $actorRole, string $action, string $remark, array $result): void
{
    $appNo = $app['application_no'] ?? '';
    $actorLabel = lieo_role_label($actorRole);
    $remarkHtml = $remark !== '' ? '<br><strong>Remark:</strong> ' . htmlspecialchars($remark) : '';

    if ($action === 'reject') {
        $creator = lieo_user_notify_recipient((int) ($app['created_by'] ?? 0));
        if ($creator) {
            $subject = LIEO_APP_SHORT . ' :: Application rejected — ' . $appNo;
            $body = 'Dear ' . htmlspecialchars($creator['name']) . ',<br><br>'
                . 'Application <strong>' . htmlspecialchars($appNo) . '</strong> was <strong>rejected</strong> by '
                . htmlspecialchars($actorLabel) . '.'
                . $remarkHtml . '<br><br>'
                . lieo_application_email_block(array_merge($app, ['status' => 'Rejected']));
            lieo_send_mail($creator['email'], $creator['name'], $subject, $body, [], [
                'context' => 'Application Rejected',
                'headline' => 'Application rejected',
                'subhead' => 'Late IN / Early Out application was rejected.',
                'to_role' => lieo_role_label((string) ($creator['role'] ?? 'n1')),
                'cards' => lieo_application_mail_cards(array_merge($app, ['status' => 'Rejected'])),
            ]);
        }
        return;
    }

    $status = $result['status'] ?? '';
    if ($status === 'Approved') {
        $to = lieo_matrix_notify_recipient($app['plant'] ?? '', $app['department'] ?? '', 'security');
        if ($to) {
            $subject = LIEO_APP_SHORT . ' :: Ready for gate — ' . $appNo;
            $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
                . 'HOD has approved application <strong>' . htmlspecialchars($appNo) . '</strong>. '
                . 'It is ready to <strong>close at gate</strong> (Security).'
                . $remarkHtml . '<br><br>'
                . lieo_application_email_block(array_merge($app, ['status' => 'Approved']));
            lieo_send_mail($to['email'], $to['name'], $subject, $body, [], [
                'context' => 'Gate Ready',
                'headline' => 'Ready for gate close',
                'subhead' => 'Security action required at gate.',
                'to_role' => 'Security',
                'cards' => lieo_application_mail_cards(array_merge($app, ['status' => 'Approved'])),
            ]);
        }
        return;
    }

    // Only 'hod' is reachable going forward (N-1 creates -> HOD approves ->
    // gate); the Pending_n1/Pending_timeoffice entries stay mapped only so a
    // pre-redesign application still mid-flight in a legacy state can still
    // notify correctly rather than silently do nothing.
    $nextMap = [
        'Pending_n1' => 'n1',
        'Pending_hod' => 'hod',
        'Pending_timeoffice' => 'timeoffice',
    ];
    $nextStep = $nextMap[$status] ?? '';
    if ($nextStep === '') {
        return;
    }
    $to = lieo_matrix_notify_recipient($app['plant'] ?? '', $app['department'] ?? '', $nextStep);
    if (!$to) {
        return;
    }
    $subject = LIEO_APP_SHORT . ' :: Pending your approval — ' . $appNo;
    $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
        . 'Application <strong>' . htmlspecialchars($appNo) . '</strong> was approved by '
        . htmlspecialchars($actorLabel) . ' and is now pending <strong>'
        . htmlspecialchars(lieo_role_label($nextStep)) . '</strong>.'
        . $remarkHtml . '<br><br>'
        . lieo_application_email_block(array_merge($app, ['status' => $status]));
    lieo_send_mail($to['email'], $to['name'], $subject, $body, [], [
        'context' => 'Pending Approval',
        'headline' => 'Pending ' . lieo_role_label($nextStep) . ' approval',
        'subhead' => 'Late IN / Early Out requires your attention.',
        'to_role' => lieo_role_label($nextStep),
        'cards' => lieo_application_mail_cards(array_merge($app, ['status' => $status])),
    ]);
}

function lieo_notify_gate_closed(array $app, string $gateAction, string $remark): void
{
    $creator = lieo_user_notify_recipient((int) ($app['created_by'] ?? 0));
    if (!$creator) {
        return;
    }
    $appNo = $app['application_no'] ?? '';
    $label = $gateAction === 'in' ? 'Gate IN' : 'Gate OUT';
    $subject = LIEO_APP_SHORT . ' :: Application closed at gate — ' . $appNo;
    $body = 'Dear ' . htmlspecialchars($creator['name']) . ',<br><br>'
        . 'Security has closed application <strong>' . htmlspecialchars($appNo) . '</strong> ('
        . htmlspecialchars($label) . ').'
        . ($remark !== '' ? '<br><strong>Gate remark:</strong> ' . htmlspecialchars($remark) : '')
        . '<br><br>'
        . lieo_application_email_block(array_merge($app, ['status' => 'Gate_completed']));
    // Time Office of the application's plant is kept in CC on every gate IN/OUT.
    $cc = [];
    $ccRoles = [];
    foreach (lieo_list_role_notify_recipients_for_plant('timeoffice', (string) ($app['plant'] ?? '')) as $to) {
        if (strcasecmp($to['email'], $creator['email']) !== 0) {
            $cc[] = $to['email'];
            $ccRoles[$to['email']] = 'Time Office';
        }
    }
    lieo_send_mail($creator['email'], $creator['name'], $subject, $body, $cc, [
        'context' => 'Gate Closed',
        'headline' => 'Application closed at gate',
        'subhead' => $label . ' completed by Security.',
        'to_role' => lieo_role_label((string) ($creator['role'] ?? 'n1')),
        'cc_roles' => $ccRoles,
        'cards' => lieo_application_mail_cards(array_merge($app, ['status' => 'Gate_completed'])),
    ]);
}

function lieo_notify_reactivation_requested(array $contractor): void
{
    $plant = lieo_ams_canonical_plant($contractor['plant'] ?? ($_SESSION['lieo_plant'] ?? ''));
    $hrDept = $plant !== '' ? lieo_hr_department_name($plant) : null;
    $hr = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    $toList = $hr ? [$hr] : [];
    $cc = $plant !== '' ? lieo_list_plant_notify_emails($plant) : [];
    $cname = trim((string) ($contractor['contractor_name'] ?? ''));
    $subject = LIEO_APP_SHORT . ' :: Contractor reactivation requested';
    $ccRoles = [];
    foreach ($cc as $addr) {
        $ccRoles[$addr] = 'Notification CC';
    }
    foreach ($toList as $to) {
        $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
            . 'Time Office has requested reactivation of contractor:<br><br>'
            . '<strong>Contractor:</strong> ' . htmlspecialchars($cname) . '<br>'
            . '<strong>Plant:</strong> ' . htmlspecialchars($plant) . '<br>'
            . '<strong>Type:</strong> ' . htmlspecialchars((string) ($contractor['contractor_type'] ?? '')) . '<br>'
            . '<strong>Deactivated at:</strong> ' . htmlspecialchars($contractor['deactivated_at'] ?? '—') . '<br>'
            . '<strong>Reason:</strong> ' . htmlspecialchars($contractor['deactivation_reason'] ?? '—') . '<br><br>'
            . 'Please sign in to LIEO → Contractor Reactivation Requests to approve or reject.';
        lieo_send_mail($to['email'], $to['name'], $subject, $body, $cc, [
            'context' => 'Contractor Reactivation',
            'headline' => 'Contractor reactivation requested',
            'subhead' => 'HR department HOD decision required.',
            'to_role' => 'HOD',
            'cc_roles' => $ccRoles,
            'cards' => [
                ['label' => 'Contractor', 'value' => $cname !== '' ? $cname : '—'],
                ['label' => 'Plant', 'value' => $plant !== '' ? $plant : '—'],
                ['label' => 'Type', 'value' => (string) ($contractor['contractor_type'] ?? '—')],
            ],
        ]);
    }
}

function lieo_notify_reactivation_decided(array $contractor, string $decision): void
{
    $plant = lieo_ams_canonical_plant($contractor['plant'] ?? ($_SESSION['lieo_plant'] ?? ''));
    $cc = $plant !== '' ? lieo_list_plant_notify_emails($plant) : [];
    $cname = trim((string) ($contractor['contractor_name'] ?? ''));
    $ok = strtolower($decision) === 'approve';
    $subject = LIEO_APP_SHORT . ' :: Contractor reactivation ' . ($ok ? 'approved' : 'rejected');
    $to = lieo_list_role_notify_recipients('timeoffice');
    $hrDept = $plant !== '' ? lieo_hr_department_name($plant) : null;
    $hr = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    if ($hr) {
        $to[] = $hr;
    }
    $ccRoles = [];
    foreach ($cc as $addr) {
        $ccRoles[$addr] = 'Notification CC';
    }
    foreach ($to as $row) {
        $body = 'Dear ' . htmlspecialchars($row['name']) . ',<br><br>'
            . 'The HR department HOD has <strong>' . ($ok ? 'approved' : 'rejected') . '</strong> reactivation of contractor '
            . '<strong>' . htmlspecialchars($cname) . '</strong> (plant ' . htmlspecialchars($plant) . ').';
        lieo_send_mail($row['email'], $row['name'], $subject, $body, $cc, [
            'context' => 'Contractor Reactivation',
            'headline' => 'Contractor reactivation ' . ($ok ? 'approved' : 'rejected'),
            'subhead' => 'HR department HOD decision completed.',
            'to_role' => 'Time Office / HOD',
            'cc_roles' => $ccRoles,
            'cards' => [
                ['label' => 'Contractor', 'value' => $cname !== '' ? $cname : '—'],
                ['label' => 'Plant', 'value' => $plant !== '' ? $plant : '—'],
                ['label' => 'Decision', 'value' => $ok ? 'Approved' : 'Rejected'],
            ],
        ]);
    }
}

/** Notify the HR-department HOD that a create/update/delete user request needs their decision. */
function lieo_notify_user_request_submitted(array $request): void
{
    $plant = lieo_ams_canonical_plant($request['plant'] ?? '');
    $hrDept = $plant !== '' ? lieo_hr_department_name($plant) : null;
    $to = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    if (!$to) {
        return;
    }
    $roleLabel = lieo_role_label((string) ($request['approval_step'] ?? ''));
    $typeLabel = ucfirst((string) ($request['request_type'] ?? 'create'));
    $subject = LIEO_APP_SHORT . ' :: User ' . strtolower($typeLabel) . ' request pending — ' . $roleLabel;
    $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
        . 'Admin has submitted a <strong>' . htmlspecialchars($typeLabel) . '</strong> request for role '
        . '<strong>' . htmlspecialchars($roleLabel) . '</strong>, awaiting your approval.<br><br>'
        . 'Please sign in to LIEO → User Approval to review it.';
    lieo_send_mail($to['email'], $to['name'], $subject, $body, [], [
        'context' => 'User Request',
        'headline' => $typeLabel . ' request pending — ' . $roleLabel,
        'subhead' => 'HR department HOD decision required.',
        'to_role' => 'HOD',
        'cards' => [
            ['label' => 'Role', 'value' => $roleLabel],
            ['label' => 'Employee', 'value' => (string) ($request['emp_name'] ?? '—')],
            ['label' => 'Plant / Dept', 'value' => trim($plant . ' / ' . ($request['department'] ?? '')) ?: '—'],
        ],
    ]);
}

function lieo_notify_department_request_submitted(array $request): void
{
    $plant = lieo_ams_canonical_plant($request['plant'] ?? '');
    $hrDept = $plant !== '' ? lieo_hr_department_name($plant) : null;
    $to = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    if (!$to) {
        return;
    }
    $typeLabel = $request['request_type'] === 'edit' ? 'Update' : 'Add';
    $deptName = (string) ($request['department_name'] ?? '');
    $subject = LIEO_APP_SHORT . ' :: Department ' . strtolower($typeLabel) . ' request pending — ' . $deptName;
    $body = 'Dear ' . htmlspecialchars($to['name']) . ',<br><br>'
        . 'Admin has submitted an <strong>' . htmlspecialchars($typeLabel) . '</strong> request for department '
        . '<strong>' . htmlspecialchars($deptName) . '</strong> (plant ' . htmlspecialchars($plant) . '), awaiting your approval.<br><br>'
        . 'Please sign in to LIEO → Department Requests to review it.';
    lieo_send_mail($to['email'], $to['name'], $subject, $body, [], [
        'context' => 'Department Request',
        'headline' => $typeLabel . ' request pending — ' . $deptName,
        'subhead' => 'HR department HOD decision required.',
        'to_role' => 'HOD',
        'cards' => [
            ['label' => 'Department', 'value' => $deptName],
            ['label' => 'Plant', 'value' => $plant !== '' ? $plant : '—'],
            ['label' => 'Type', 'value' => $typeLabel],
        ],
    ]);
}

/** Notify the requesting Admin that the HR-department HOD decided a department request. */
function lieo_notify_department_request_decided(array $request, string $decision): void
{
    $requestedBy = lieo_user_notify_recipient((int) ($request['requested_by'] ?? 0));
    $to = $requestedBy ? [$requestedBy] : lieo_list_role_notify_recipients('admin');
    $deptName = (string) ($request['department_name'] ?? '');
    $ok = $decision === 'Approved';
    $subject = LIEO_APP_SHORT . ' :: Department request ' . strtolower($decision) . ' — ' . $deptName;
    foreach ($to as $row) {
        $body = 'Dear ' . htmlspecialchars($row['name']) . ',<br><br>'
            . 'Your request for department <strong>' . htmlspecialchars($deptName) . '</strong> ('
            . htmlspecialchars((string) ($request['plant'] ?? '')) . ') was <strong>'
            . htmlspecialchars(strtolower($decision)) . '</strong> by the HR department HOD.'
            . (!empty($request['decision_remark']) ? '<br><strong>Remark:</strong> ' . htmlspecialchars($request['decision_remark']) : '');
        lieo_send_mail($row['email'], $row['name'], $subject, $body, [], [
            'context' => 'Department Request',
            'headline' => 'Department request ' . strtolower($decision),
            'subhead' => 'HR department HOD decision completed.',
            'to_role' => 'Admin',
            'cards' => [
                ['label' => 'Department', 'value' => $deptName],
                ['label' => 'Plant', 'value' => (string) ($request['plant'] ?? '—')],
                ['label' => 'Decision', 'value' => $ok ? 'Approved' : 'Rejected'],
            ],
        ]);
    }
}

/** Notify the requesting Admin that the HR-department HOD decided a user request. */
function lieo_notify_user_request_decided(array $request, string $decision): void
{
    $requestedBy = lieo_user_notify_recipient((int) ($request['requested_by'] ?? 0));
    $to = $requestedBy ? [$requestedBy] : lieo_list_role_notify_recipients('admin');
    $roleLabel = lieo_role_label((string) ($request['approval_step'] ?? ''));
    $ok = $decision === 'Approved';
    $subject = LIEO_APP_SHORT . ' :: User request ' . strtolower($decision) . ' — ' . $roleLabel;
    foreach ($to as $row) {
        $body = 'Dear ' . htmlspecialchars($row['name']) . ',<br><br>'
            . 'Your request for role <strong>' . htmlspecialchars($roleLabel) . '</strong> ('
            . htmlspecialchars((string) ($request['emp_name'] ?? '')) . ') was <strong>'
            . htmlspecialchars(strtolower($decision)) . '</strong> by the HR department HOD.'
            . (!empty($request['decision_remark']) ? '<br><strong>Remark:</strong> ' . htmlspecialchars($request['decision_remark']) : '');
        lieo_send_mail($row['email'], $row['name'], $subject, $body, [], [
            'context' => 'User Request',
            'headline' => 'User request ' . strtolower($decision),
            'subhead' => 'HR department HOD decision completed.',
            'to_role' => 'Admin',
            'cards' => [
                ['label' => 'Role', 'value' => $roleLabel],
                ['label' => 'Employee', 'value' => (string) ($request['emp_name'] ?? '—')],
                ['label' => 'Decision', 'value' => $ok ? 'Approved' : 'Rejected'],
            ],
        ]);
    }
}
