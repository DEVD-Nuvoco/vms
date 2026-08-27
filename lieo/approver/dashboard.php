<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['n1', 'hod']);

$pageTitle = lieo_role_label($_SESSION['lieo_role']) . ' Dashboard';
$activeNav = 'dashboard';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stats = lieo_role_dashboard_stats($date);
$pending = lieo_list_applications(lieo_apply_session_plant_scope(['pending_for_role' => $_SESSION['lieo_role'], 'date' => $date]));
$roleTitle = lieo_role_label($_SESSION['lieo_role']);
$showCreate = false;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/role_dashboard.php';
require_once __DIR__ . '/../includes/footer.php';
