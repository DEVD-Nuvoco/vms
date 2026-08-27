<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['section_incharge']);

$pageTitle = 'Section Incharge Dashboard';
$activeNav = 'dashboard';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stats = lieo_role_dashboard_stats($date, ['created_by' => (int) $_SESSION['lieo_user_id']]);
$pending = [];
$roleTitle = 'Section Incharge';
$showCreate = true;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/role_dashboard.php';
require_once __DIR__ . '/../includes/footer.php';
