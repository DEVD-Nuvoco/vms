<?php
require_once dirname(__DIR__) . '/config.php';
lieo_require_login();

$role = $_SESSION['lieo_role'];
$userName = $_SESSION['lieo_user_name'];
$userEmail = $_SESSION['lieo_user_email'] ?? '';
$userPlant = trim($_SESSION['lieo_plant'] ?? '');
$userDept = trim($_SESSION['lieo_department'] ?? '');
$lieoChangePasswordUrl = lieo_web_base() . '/change_password.php';
$lieoLogoutUrl = lieo_web_base() . '/logout.php';
$pageTitle = $pageTitle ?? LIEO_APP_SHORT;
$activeNav = $activeNav ?? '';
$lieoAssets = lieo_assets_prefix();
$lieoRoot = lieo_root_prefix();

$adminNav = [
    'dashboard' => ['label' => 'Dashboard',         'url' => lieo_nav_url('admin', 'index.php'),           'icon' => 'typcn-chart-area-outline'],
    'matrix'    => ['label' => 'Time Office Users', 'url' => lieo_nav_url('admin', 'approval_matrix.php'), 'icon' => 'typcn-flow-merge'],
];

$sectionInchargeNav = [
    'dashboard' => ['label' => 'Dashboard',           'url' => lieo_nav_url('section_incharge', 'index.php'),              'icon' => 'typcn-chart-area-outline'],
    'create'    => ['label' => 'Create Application',  'url' => lieo_nav_url('section_incharge', 'create_application.php'), 'icon' => 'typcn-document-add'],
    'list'      => ['label' => 'Application Tracking', 'url' => lieo_nav_url('section_incharge', 'applications.php'),      'icon' => 'typcn-th-list'],
    'history'   => ['label' => 'My History',          'url' => lieo_nav_url('section_incharge', 'history.php'),            'icon' => 'typcn-time'],
];

$timeofficeNav = [
    'dashboard'  => ['label' => 'Dashboard',              'url' => lieo_nav_url('timeoffice', 'index.php'),              'icon' => 'typcn-chart-area-outline'],
    'pending'    => ['label' => 'Pending Approvals',      'url' => lieo_nav_url('timeoffice', 'pending.php'),            'icon' => 'typcn-tick-outline'],
    'list'       => ['label' => 'Application Tracking',   'url' => lieo_nav_url('timeoffice', 'applications.php'),       'icon' => 'typcn-th-list'],
    'matrix'     => ['label' => 'Approval Matrix',        'url' => lieo_nav_url('timeoffice', 'approval_matrix.php'),    'icon' => 'typcn-flow-merge'],
    'departments'=> ['label' => 'Department Master',      'url' => lieo_nav_url('timeoffice', 'departments.php'),        'icon' => 'typcn-th-large'],
    'contractors'=> ['label' => 'Contractor Master',      'url' => lieo_nav_url('timeoffice', 'contractors.php'),        'icon' => 'typcn-briefcase'],
    'notify'     => ['label' => 'Notification Mail',      'url' => lieo_nav_url('timeoffice', 'notification_mail.php'),  'icon' => 'typcn-mail'],
    'users'      => ['label' => 'Roles',                  'url' => lieo_nav_url('timeoffice', 'users.php'),              'icon' => 'typcn-group-outline'],
    'history'    => ['label' => 'My History',             'url' => lieo_nav_url('timeoffice', 'history.php'),            'icon' => 'typcn-time'],
];

$approverNav = [
    'dashboard' => ['label' => 'Dashboard',          'url' => lieo_nav_url($role, 'dashboard.php'), 'icon' => 'typcn-chart-area-outline'],
    'pending'   => ['label' => 'Pending Approvals',  'url' => lieo_nav_url($role, 'pending.php'),   'icon' => 'typcn-tick-outline'],
    'list'      => ['label' => 'Application Tracking','url' => lieo_nav_url($role, 'applications.php'), 'icon' => 'typcn-th-list'],
    'history'   => ['label' => 'My History',         'url' => lieo_nav_url($role, 'history.php'),   'icon' => 'typcn-time'],
];

$securityNav = [
    'attendance' => ['label' => 'Gate Attendance', 'url' => lieo_nav_url('security', 'attendance.php'), 'icon' => 'typcn-arrow-forward-outline'],
    'history'    => ['label' => 'My History',      'url' => lieo_nav_url('security', 'history.php'),    'icon' => 'typcn-time'],
];

$hrNav = [
    'reactivation' => ['label' => 'Reactivation Requests', 'url' => lieo_nav_url('hr', 'reactivation.php'), 'icon' => 'typcn-refresh'],
];

if ($role === 'admin') {
    $navItems = $adminNav;
} elseif ($role === 'section_incharge') {
    $navItems = $sectionInchargeNav;
} elseif ($role === 'timeoffice') {
    $navItems = $timeofficeNav;
} elseif ($role === 'security') {
    $navItems = $securityNav;
} elseif ($role === 'hr') {
    $navItems = $hrNav;
} else {
    $navItems = $approverNav;
}

$userInitials = lieo_user_initials($userName);
$userScope = trim($userPlant . ($userPlant && $userDept ? ' · ' : '') . $userDept);
$isChangePasswordPage = ($pageTitle === 'Change Password');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(LIEO_APP_SHORT) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/typicons.font@2.1.2/src/font/typicons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= $lieoAssets ?>css/azia.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        :root {
            --lieo-green: #42bb52;
            --lieo-green-dark: #38a644;
            --lieo-surface: #f1f5f9;
            --lieo-border: #e2e8f0;
            --lieo-text: #0f172a;
            --lieo-muted: #64748b;
        }
        body.lieo-app {
            background: var(--lieo-surface);
            padding-top: 64px;
        }
        .lieo-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            height: 64px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        }
        .lieo-topbar-inner {
            min-height: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-top: 0;
            padding-bottom: 0;
        }
        .lieo-layout-row {
            margin-left: 0;
            margin-right: 0;
        }
        .lieo-sidebar {
            position: fixed;
            top: 64px;
            left: 0;
            bottom: 0;
            width: 240px;
            z-index: 1030;
            background: #1e293b;
            min-height: 0;
            height: calc(100vh - 64px);
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem .75rem;
            -webkit-overflow-scrolling: touch;
        }
        .lieo-sidebar .nav-link {
            color: #cbd5e1;
            padding: .65rem .85rem;
            border-left: 3px solid transparent;
            border-radius: .5rem;
            margin-bottom: .25rem;
            font-size: .875rem;
            font-weight: 500;
        }
        .lieo-sidebar .nav-link:hover, .lieo-sidebar .nav-link.active {
            color: #fff;
            background: rgba(66,187,82,.12);
            border-left-color: var(--lieo-green);
        }
        .lieo-sidebar .nav-link i { margin-right: 8px; width: 20px; }
        .lieo-main {
            margin-left: 240px;
            width: calc(100% - 240px);
            max-width: none;
            flex: 0 0 auto;
            padding: 1.25rem 1.5rem 2rem;
            min-height: calc(100vh - 64px);
        }
        .lieo-flash {
            margin-left: 240px;
            width: calc(100% - 240px);
        }
        @media (max-width: 767.98px) {
            body.lieo-app { padding-top: 64px; }
            .lieo-sidebar {
                position: relative;
                top: auto;
                left: auto;
                width: 100%;
                height: auto;
                min-height: 0;
                max-height: none;
            }
            .lieo-main,
            .lieo-flash {
                margin-left: 0;
                width: 100%;
            }
        }
        .lieo-brand-block { min-width: 0; flex: 1 1 auto; }
        .lieo-brand-short {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--lieo-green);
            letter-spacing: .02em;
        }
        .lieo-brand-page {
            font-size: .8125rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width:  min(420px, 40vw);
        }
        .lieo-role-badge { background: var(--lieo-green); color: #fff; font-size: 11px; font-weight: 600; }
        .lieo-title { color: var(--lieo-green); font-weight: 700; }
        input[type="date"],
        input[type="datetime-local"],
        input[type="time"] {
            cursor: pointer;
        }
        .btn-lieo {
            background: var(--lieo-green);
            color: #fff;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            font-size: .8125rem;
            padding: .4rem .85rem;
            box-shadow: 0 1px 2px rgba(66, 187, 82, .25);
        }
        .btn-lieo:hover { background: var(--lieo-green-dark); color: #fff; }
        .card-stat { border-left: 4px solid var(--lieo-green); }
        .lieo-main-inner { width: 100%; max-width: none; }
        .lieo-page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .lieo-page-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--lieo-text);
            margin: 0 0 .35rem;
            letter-spacing: -.02em;
        }
        .lieo-page-lead {
            margin: 0;
            font-size: .9375rem;
            color: var(--lieo-muted);
            max-width: 42rem;
            line-height: 1.5;
        }
        .lieo-panel {
            background: #fff;
            border: 1px solid var(--lieo-border);
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
            overflow: hidden;
        }
        .lieo-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--lieo-border);
            background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
        }
        .lieo-panel-title {
            font-size: .9375rem;
            font-weight: 700;
            color: var(--lieo-text);
            margin: 0;
        }
        .lieo-panel-subtitle {
            font-size: .8125rem;
            color: var(--lieo-muted);
            margin-top: .2rem;
        }
        .lieo-panel-count {
            font-size: .75rem;
            font-weight: 700;
            color: var(--lieo-green-dark);
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            padding: .25rem .55rem;
            border-radius: 999px;
            min-width: 1.75rem;
            text-align: center;
        }
        .lieo-panel-body { padding: 0; }
        .lieo-panel-body.padded { padding: 1.25rem; }
        .lieo-empty-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
        }
        .lieo-empty-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto .75rem;
            border-radius: 50%;
            background: #f1f5f9;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .lieo-empty-message {
            margin: 0;
            font-size: .9375rem;
            font-weight: 600;
            color: #475569;
        }
        .lieo-empty-hint {
            margin: .35rem 0 0;
            font-size: .8125rem;
            color: var(--lieo-muted);
        }
        .lieo-table-wrap { overflow-x: auto; }
        .lieo-panel .table,
        .lieo-table {
            margin-bottom: 0;
            font-size: .875rem;
        }
        .lieo-panel .table thead th,
        .lieo-table thead th,
        .lieo-history-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: .6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid var(--lieo-border);
            border-top: none;
            white-space: nowrap;
            padding: .65rem .75rem;
        }
        .lieo-panel .table tbody td,
        .lieo-table tbody td {
            padding: .65rem .75rem;
            vertical-align: middle;
            border-color: #f1f5f9;
            color: #334155;
        }
        .lieo-panel .table tbody tr:hover,
        .lieo-table tbody tr:hover {
            background: #fafbfc;
        }
        .lieo-panel .dataTables_wrapper .dataTables_length,
        .lieo-panel .dataTables_wrapper .dataTables_filter,
        .lieo-panel .dataTables_wrapper .dataTables_info,
        .lieo-panel .dataTables_wrapper .dataTables_paginate {
            font-size: .8125rem;
            color: var(--lieo-muted);
            padding: .75rem 1.25rem;
        }
        .lieo-panel .dataTables_wrapper .dataTables_length select,
        .lieo-panel .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--lieo-border);
            border-radius: .375rem;
            padding: .25rem .5rem;
            font-size: .8125rem;
        }
        .lieo-panel .dataTables_wrapper .row:first-child,
        .lieo-panel .dataTables_wrapper .row:last-child {
            margin-left: 0;
            margin-right: 0;
        }
        .lieo-history-table-wrap .dataTables_wrapper .row:first-child {
            padding: .75rem 1.25rem 0;
            margin: 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .lieo-history-table-wrap .dataTables_wrapper .row:last-child {
            margin: 0;
            border-top: 1px solid #f1f5f9;
        }
        .lieo-panel .dataTables_wrapper table.dataTable { border-collapse: collapse !important; }
        .lieo-history-table-wrap {
            max-height: calc(100vh - 260px);
            overflow: auto;
        }
        .lieo-history-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            box-shadow: 0 1px 0 rgba(0,0,0,.08);
        }
        .lieo-history-table tbody td { vertical-align: middle; }
        .lieo-history-card .dataTables_wrapper .row { margin-left: 0; margin-right: 0; }
        #lieoTrailModal,
        #lieoEmpBrowseModal,
        #lieoGateCloseModal,
        #lieoTestMailModal,
        #lieoDialogModal {
            z-index: 2000;
        }
        .modal-backdrop { z-index: 1990; }
        .lieo-account-menu { flex: 0 0 auto; }
        .lieo-account-trigger {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .35rem .5rem .35rem .35rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #f8fafc;
            box-shadow: none;
            max-width: 280px;
        }
        .lieo-account-trigger:hover,
        .lieo-account-trigger:focus {
            background: #fff;
            border-color: #cbd5e1;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }
        .lieo-account-trigger::after { margin-left: .15rem; }
        .lieo-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(145deg, var(--lieo-green), var(--lieo-green-dark));
            color: #fff;
            font-size: .8125rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .lieo-account-text {
            text-align: left;
            min-width: 0;
            line-height: 1.2;
        }
        .lieo-account-name {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .lieo-account-sub {
            display: block;
            font-size: .75rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .lieo-account-dropdown {
            min-width: 260px;
            padding: 0;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .12);
            margin-top: .35rem;
        }
        .lieo-account-dropdown-head {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .lieo-account-dropdown-head .lieo-user-email-full {
            font-size: .8125rem;
            color: #475569;
            word-break: break-word;
        }
        .lieo-account-dropdown .dropdown-item {
            font-size: .875rem;
            padding: .55rem 1rem;
        }
        .lieo-account-dropdown .dropdown-item i {
            margin-right: .5rem;
            opacity: .75;
        }
        .lieo-flash {
            border: none;
            border-radius: 0;
            font-size: .875rem;
            padding: .65rem 1rem;
        }
        .lieo-flash-success {
            background: #ecfdf3;
            color: #166534;
            border-bottom: 1px solid #bbf7d0;
        }
        .lieo-flash-danger {
            background: #fef2f2;
            color: #991b1b;
            border-bottom: 1px solid #fecaca;
        }
        .lieo-flash-warning {
            background: #fffbeb;
            color: #92400e;
            border-bottom: 1px solid #fde68a;
        }
        .lieo-flash-info {
            background: #eff6ff;
            color: #1e40af;
            border-bottom: 1px solid #bfdbfe;
        }
        .min-width-0 { min-width: 0; }
        @media (max-width: 575.98px) {
            .lieo-account-trigger { max-width: none; padding-right: .65rem; }
            .lieo-brand-page { max-width: 50vw; }
        }
    </style>
</head>
<body class="lieo-app">
<div class="lieo-topbar">
    <div class="container-fluid lieo-topbar-inner">
        <div class="d-flex align-items-center lieo-brand-block">
            <img src="<?= $lieoAssets ?>images/nuvoco-ori.png" width="56" height="auto" alt="Nuvoco" class="mr-3 flex-shrink-0">
            <div class="min-width-0">
                <div class="lieo-brand-short" title="<?= htmlspecialchars(LIEO_APP_NAME) ?>"><?= htmlspecialchars(LIEO_APP_NAME) ." - ". htmlspecialchars(LIEO_APP_SHORT) ?></div>
                <div class="lieo-brand-page"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
        </div>

        <div class="dropdown lieo-account-menu">
            <button type="button" class="btn lieo-account-trigger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="lieo-avatar" aria-hidden="true"><?= htmlspecialchars($userInitials) ?></span>
                <span class="lieo-account-text d-none d-sm-block">
                    <span class="lieo-account-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="lieo-account-sub"><?= htmlspecialchars(lieo_role_label($role)) ?><?= $userScope !== '' ? ' · ' . htmlspecialchars($userScope) : '' ?></span>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-right lieo-account-dropdown">
                <div class="lieo-account-dropdown-head px-3 py-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="lieo-avatar mr-2"><?= htmlspecialchars($userInitials) ?></span>
                        <div class="min-width-0">
                            <div class="font-weight-bold text-truncate"><?= htmlspecialchars($userName) ?></div>
                            <span class="badge lieo-role-badge"><?= htmlspecialchars(lieo_role_label($role)) ?></span>
                        </div>
                    </div>
                    <div class="lieo-user-email-full"><?= htmlspecialchars($userEmail) ?></div>
                    <?php if ($userScope !== ''): ?>
                    <div class="small text-muted mt-1"><?= htmlspecialchars($userScope) ?></div>
                    <?php endif; ?>
                </div>
                <a class="dropdown-item<?= $isChangePasswordPage ? ' active' : '' ?>" href="<?= htmlspecialchars($lieoChangePasswordUrl) ?>">
                    <i class="typcn typcn-key-outline"></i> Change password
                </a>
                <div class="dropdown-divider my-0"></div>
                <a class="dropdown-item text-danger" href="<?= htmlspecialchars($lieoLogoutUrl) ?>">
                    <i class="typcn typcn-power-outline"></i> Sign out
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Prefer popup alert when set — do not also show the top flash banner.
if (!empty($_SESSION['lieo_alert']) && is_array($_SESSION['lieo_alert'])) {
    unset($_SESSION['lieo_mess'], $_SESSION['lieo_mess_type']);
} elseif (!empty($_SESSION['lieo_mess'])) {
    $messType = $_SESSION['lieo_mess_type'] ?? 'success';
    if (!in_array($messType, ['success', 'danger', 'warning', 'info'], true)) {
        $messType = 'success';
    }
    ?>
<div class="alert alert-dismissible fade show m-0 lieo-flash lieo-flash-<?= htmlspecialchars($messType) ?>" role="alert">
    <?= htmlspecialchars($_SESSION['lieo_mess']) ?>
    <button type="button" class="close py-1" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
    <?php
    unset($_SESSION['lieo_mess'], $_SESSION['lieo_mess_type']);
}
?>

<div class="container-fluid px-0">
    <div class="row no-gutters lieo-layout-row">
        <nav class="lieo-sidebar">
            <ul class="nav flex-column">
                <?php foreach ($navItems as $key => $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                        <i class="typcn <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <main class="lieo-main">
            <div class="lieo-main-inner">
