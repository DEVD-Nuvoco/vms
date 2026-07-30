<?php
require_once dirname(__DIR__) . '/config.php';
clgp_require_login();

$role = $_SESSION['clgp_role'];
$userName = $_SESSION['clgp_user_name'];
$userEmail = $_SESSION['clgp_user_email'] ?? '';
$userPlant = trim($_SESSION['clgp_plant'] ?? '');
$userDept = trim($_SESSION['clgp_department'] ?? '');
$clgpChangePasswordUrl = clgp_web_base() . '/change_password.php';
$clgpLogoutUrl = clgp_web_base() . '/logout.php';
$pageTitle = $pageTitle ?? CLGP_APP_SHORT;
$activeNav = $activeNav ?? '';
$clgpAssets = clgp_assets_prefix();
$clgpRoot = clgp_root_prefix();

$adminNav = [
    'dashboard'   => ['label' => 'Dashboard',       'url' => clgp_nav_url('admin', 'index.php'),            'icon' => 'typcn-chart-area-outline'],
    'matrix'      => ['label' => 'Approval Matrix', 'url' => clgp_nav_url('admin', 'approval_matrix.php'),  'icon' => 'typcn-flow-merge'],
    'contractors' => ['label' => 'Contractors',     'url' => clgp_nav_url('admin', 'contractors.php'),      'icon' => 'typcn-briefcase'],
    'workmen'     => ['label' => 'Workmen',         'url' => clgp_nav_url('admin', 'workmen.php'),          'icon' => 'typcn-user'],
    'users'       => ['label' => 'Roles',           'url' => clgp_nav_url('admin', 'users.php'),            'icon' => 'typcn-group-outline'],
];

$timeofficeNav = [
    'dashboard' => ['label' => 'Dashboard',           'url' => clgp_nav_url('timeoffice', 'index.php'),              'icon' => 'typcn-chart-area-outline'],
    'create'    => ['label' => 'Create Application',  'url' => clgp_nav_url('timeoffice', 'create_application.php'), 'icon' => 'typcn-document-add'],
    'list'      => ['label' => 'All Applications',    'url' => clgp_nav_url('timeoffice', 'applications.php'),       'icon' => 'typcn-th-list'],
    'history'   => ['label' => 'My History',          'url' => clgp_nav_url('timeoffice', 'history.php'),            'icon' => 'typcn-time'],
];

$approverNav = [
    'pending' => ['label' => 'Pending Approvals', 'url' => clgp_nav_url($role, 'pending.php'), 'icon' => 'typcn-tick-outline'],
    'history' => ['label' => 'My History',        'url' => clgp_nav_url($role, 'history.php'), 'icon' => 'typcn-time'],
];

$securityNav = [
    'attendance' => ['label' => 'Gate Attendance', 'url' => clgp_nav_url('security', 'attendance.php'), 'icon' => 'typcn-arrow-forward-outline'],
    'history'    => ['label' => 'My History',      'url' => clgp_nav_url('security', 'history.php'),    'icon' => 'typcn-time'],
];

$hrNav = [
    'reactivation' => ['label' => 'Reactivation Requests', 'url' => clgp_nav_url('hr', 'reactivation.php'), 'icon' => 'typcn-refresh'],
];

if ($role === 'admin') {
    $navItems = $adminNav;
} elseif ($role === 'timeoffice') {
    $navItems = $timeofficeNav;
} elseif ($role === 'security') {
    $navItems = $securityNav;
} elseif ($role === 'hr') {
    $navItems = $hrNav;
} else {
    $navItems = $approverNav;
}

$userInitials = clgp_user_initials($userName);
$userScope = trim($userPlant . ($userPlant && $userDept ? ' · ' : '') . $userDept);
$isChangePasswordPage = ($pageTitle === 'Change Password');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(CLGP_APP_SHORT) ?></title>
    <link href="<?= $clgpAssets ?>lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="<?= $clgpAssets ?>lib/typicons.font/typicons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $clgpAssets ?>css/azia.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        :root {
            --clgp-green: #42bb52;
            --clgp-green-dark: #38a644;
            --clgp-surface: #f1f5f9;
            --clgp-border: #e2e8f0;
            --clgp-text: #0f172a;
            --clgp-muted: #64748b;
        }
        body.clgp-app { background: var(--clgp-surface); }
        .clgp-sidebar {
            background: #1e293b;
            min-height: calc(100vh - 64px);
            padding: 1rem .75rem;
        }
        .clgp-sidebar .nav-link {
            color: #cbd5e1;
            padding: .65rem .85rem;
            border-left: 3px solid transparent;
            border-radius: .5rem;
            margin-bottom: .25rem;
            font-size: .875rem;
            font-weight: 500;
        }
        .clgp-sidebar .nav-link:hover, .clgp-sidebar .nav-link.active {
            color: #fff;
            background: rgba(66,187,82,.12);
            border-left-color: var(--clgp-green);
        }
        .clgp-sidebar .nav-link i { margin-right: 8px; width: 20px; }
        .clgp-topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        }
        .clgp-topbar-inner {
            min-height: 64px;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-top: .5rem;
            padding-bottom: .5rem;
        }
        .clgp-brand-block { min-width: 0; flex: 1 1 auto; }
        .clgp-brand-short {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--clgp-green);
            letter-spacing: .02em;
        }
        .clgp-brand-page {
            font-size: .8125rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width:  min(420px, 40vw);
        }
        .clgp-role-badge { background: var(--clgp-green); color: #fff; font-size: 11px; font-weight: 600; }
        .clgp-title { color: var(--clgp-green); font-weight: 700; }
        .btn-clgp {
            background: var(--clgp-green);
            color: #fff;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            font-size: .8125rem;
            padding: .4rem .85rem;
            box-shadow: 0 1px 2px rgba(66, 187, 82, .25);
        }
        .btn-clgp:hover { background: var(--clgp-green-dark); color: #fff; }
        .card-stat { border-left: 4px solid var(--clgp-green); }
        .clgp-main { padding: 1.25rem 1.5rem 2rem; }
        .clgp-main-inner { width: 100%; max-width: none; }
        .clgp-page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .clgp-page-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--clgp-text);
            margin: 0 0 .35rem;
            letter-spacing: -.02em;
        }
        .clgp-page-lead {
            margin: 0;
            font-size: .9375rem;
            color: var(--clgp-muted);
            max-width: 42rem;
            line-height: 1.5;
        }
        .clgp-panel {
            background: #fff;
            border: 1px solid var(--clgp-border);
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
            overflow: hidden;
        }
        .clgp-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--clgp-border);
            background: linear-gradient(180deg, #fff 0%, #fafbfc 100%);
        }
        .clgp-panel-title {
            font-size: .9375rem;
            font-weight: 700;
            color: var(--clgp-text);
            margin: 0;
        }
        .clgp-panel-subtitle {
            font-size: .8125rem;
            color: var(--clgp-muted);
            margin-top: .2rem;
        }
        .clgp-panel-count {
            font-size: .75rem;
            font-weight: 700;
            color: var(--clgp-green-dark);
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            padding: .25rem .55rem;
            border-radius: 999px;
            min-width: 1.75rem;
            text-align: center;
        }
        .clgp-panel-body { padding: 0; }
        .clgp-panel-body.padded { padding: 1.25rem; }
        .clgp-empty-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
        }
        .clgp-empty-icon {
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
        .clgp-empty-message {
            margin: 0;
            font-size: .9375rem;
            font-weight: 600;
            color: #475569;
        }
        .clgp-empty-hint {
            margin: .35rem 0 0;
            font-size: .8125rem;
            color: var(--clgp-muted);
        }
        .clgp-table-wrap { overflow-x: auto; }
        .clgp-panel .table,
        .clgp-table {
            margin-bottom: 0;
            font-size: .875rem;
        }
        .clgp-panel .table thead th,
        .clgp-table thead th,
        .clgp-history-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: .6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid var(--clgp-border);
            border-top: none;
            white-space: nowrap;
            padding: .65rem .75rem;
        }
        .clgp-panel .table tbody td,
        .clgp-table tbody td {
            padding: .65rem .75rem;
            vertical-align: middle;
            border-color: #f1f5f9;
            color: #334155;
        }
        .clgp-panel .table tbody tr:hover,
        .clgp-table tbody tr:hover {
            background: #fafbfc;
        }
        .clgp-panel .dataTables_wrapper .dataTables_length,
        .clgp-panel .dataTables_wrapper .dataTables_filter,
        .clgp-panel .dataTables_wrapper .dataTables_info,
        .clgp-panel .dataTables_wrapper .dataTables_paginate {
            font-size: .8125rem;
            color: var(--clgp-muted);
            padding: .75rem 1.25rem;
        }
        .clgp-panel .dataTables_wrapper .dataTables_length select,
        .clgp-panel .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--clgp-border);
            border-radius: .375rem;
            padding: .25rem .5rem;
            font-size: .8125rem;
        }
        .clgp-panel .dataTables_wrapper .row:first-child,
        .clgp-panel .dataTables_wrapper .row:last-child {
            margin-left: 0;
            margin-right: 0;
        }
        .clgp-history-table-wrap .dataTables_wrapper .row:first-child {
            padding: .75rem 1.25rem 0;
            margin: 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .clgp-history-table-wrap .dataTables_wrapper .row:last-child {
            margin: 0;
            border-top: 1px solid #f1f5f9;
        }
        .clgp-panel .dataTables_wrapper table.dataTable { border-collapse: collapse !important; }
        .clgp-history-table-wrap {
            max-height: calc(100vh - 260px);
            overflow: auto;
        }
        .clgp-history-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            box-shadow: 0 1px 0 rgba(0,0,0,.08);
        }
        .clgp-history-table tbody td { vertical-align: middle; }
        .clgp-history-card .dataTables_wrapper .row { margin-left: 0; margin-right: 0; }
        #clgpTrailModal { z-index: 1060; }
        .modal-backdrop { z-index: 1055; }
        .clgp-account-menu { flex: 0 0 auto; }
        .clgp-account-trigger {
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
        .clgp-account-trigger:hover,
        .clgp-account-trigger:focus {
            background: #fff;
            border-color: #cbd5e1;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }
        .clgp-account-trigger::after { margin-left: .15rem; }
        .clgp-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(145deg, var(--clgp-green), var(--clgp-green-dark));
            color: #fff;
            font-size: .8125rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .clgp-account-text {
            text-align: left;
            min-width: 0;
            line-height: 1.2;
        }
        .clgp-account-name {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .clgp-account-sub {
            display: block;
            font-size: .75rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .clgp-account-dropdown {
            min-width: 260px;
            padding: 0;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .12);
            margin-top: .35rem;
        }
        .clgp-account-dropdown-head {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .clgp-account-dropdown-head .clgp-user-email-full {
            font-size: .8125rem;
            color: #475569;
            word-break: break-word;
        }
        .clgp-account-dropdown .dropdown-item {
            font-size: .875rem;
            padding: .55rem 1rem;
        }
        .clgp-account-dropdown .dropdown-item i {
            margin-right: .5rem;
            opacity: .75;
        }
        .clgp-flash {
            border: none;
            border-radius: 0;
            font-size: .875rem;
            padding: .65rem 1rem;
        }
        .clgp-flash-success {
            background: #ecfdf3;
            color: #166534;
            border-bottom: 1px solid #bbf7d0;
        }
        .clgp-flash-danger {
            background: #fef2f2;
            color: #991b1b;
            border-bottom: 1px solid #fecaca;
        }
        .clgp-flash-warning {
            background: #fffbeb;
            color: #92400e;
            border-bottom: 1px solid #fde68a;
        }
        .clgp-flash-info {
            background: #eff6ff;
            color: #1e40af;
            border-bottom: 1px solid #bfdbfe;
        }
        .min-width-0 { min-width: 0; }
        @media (max-width: 575.98px) {
            .clgp-account-trigger { max-width: none; padding-right: .65rem; }
            .clgp-brand-page { max-width: 50vw; }
        }
    </style>
</head>
<body class="clgp-app">
<div class="clgp-topbar">
    <div class="container-fluid clgp-topbar-inner">
        <div class="d-flex align-items-center clgp-brand-block">
            <img src="<?= $clgpAssets ?>images/nuvoco-ori.png" width="56" height="auto" alt="Nuvoco" class="mr-3 flex-shrink-0">
            <div class="min-width-0">
                <div class="clgp-brand-short" title="<?= htmlspecialchars(CLGP_APP_NAME) ?>"><?= htmlspecialchars(CLGP_APP_SHORT) ?></div>
                <div class="clgp-brand-page"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
        </div>

        <div class="dropdown clgp-account-menu">
            <button type="button" class="btn clgp-account-trigger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="clgp-avatar" aria-hidden="true"><?= htmlspecialchars($userInitials) ?></span>
                <span class="clgp-account-text d-none d-sm-block">
                    <span class="clgp-account-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="clgp-account-sub"><?= htmlspecialchars(clgp_role_label($role)) ?><?= $userScope !== '' ? ' · ' . htmlspecialchars($userScope) : '' ?></span>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-right clgp-account-dropdown">
                <div class="clgp-account-dropdown-head px-3 py-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="clgp-avatar mr-2"><?= htmlspecialchars($userInitials) ?></span>
                        <div class="min-width-0">
                            <div class="font-weight-bold text-truncate"><?= htmlspecialchars($userName) ?></div>
                            <span class="badge clgp-role-badge"><?= htmlspecialchars(clgp_role_label($role)) ?></span>
                        </div>
                    </div>
                    <div class="clgp-user-email-full"><?= htmlspecialchars($userEmail) ?></div>
                    <?php if ($userScope !== ''): ?>
                    <div class="small text-muted mt-1"><?= htmlspecialchars($userScope) ?></div>
                    <?php endif; ?>
                </div>
                <a class="dropdown-item<?= $isChangePasswordPage ? ' active' : '' ?>" href="<?= htmlspecialchars($clgpChangePasswordUrl) ?>">
                    <i class="typcn typcn-key-outline"></i> Change password
                </a>
                <div class="dropdown-divider my-0"></div>
                <a class="dropdown-item text-danger" href="<?= htmlspecialchars($clgpLogoutUrl) ?>">
                    <i class="typcn typcn-power-outline"></i> Sign out
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['clgp_mess'])): ?>
<?php
    $messType = $_SESSION['clgp_mess_type'] ?? 'success';
    if (!in_array($messType, ['success', 'danger', 'warning', 'info'], true)) {
        $messType = 'success';
    }
?>
<div class="alert alert-dismissible fade show m-0 clgp-flash clgp-flash-<?= htmlspecialchars($messType) ?>" role="alert">
    <?= htmlspecialchars($_SESSION['clgp_mess']) ?>
    <button type="button" class="close py-1" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<?php
    unset($_SESSION['clgp_mess'], $_SESSION['clgp_mess_type']);
endif; ?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 clgp-sidebar py-3">
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
        <main class="col-md-10 clgp-main">
            <div class="clgp-main-inner">
