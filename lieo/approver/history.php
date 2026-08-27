<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['n1', 'hod']);

$pageTitle = 'My History';
$activeNav = 'history';
$userId = (int) ($_SESSION['lieo_user_id'] ?? 0);
$historyRows = lieo_list_approver_history($userId);
$historyMode = 'approver';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/history_view.php';
require_once __DIR__ . '/../includes/footer.php';
