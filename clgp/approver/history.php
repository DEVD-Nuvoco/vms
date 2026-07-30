<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['supervisor', 'n1', 'hod']);

$pageTitle = 'My History';
$activeNav = 'history';
$userId = (int) ($_SESSION['clgp_user_id'] ?? 0);
$historyRows = clgp_list_approver_history($userId);
$historyMode = 'approver';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/history_view.php';
require_once __DIR__ . '/../includes/footer.php';
