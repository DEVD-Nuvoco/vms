<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['security']);

$pageTitle = 'My History';
$activeNav = 'history';
$userId = (int) ($_SESSION['clgp_user_id'] ?? 0);
$historyRows = clgp_list_security_history($userId);
$historyMode = 'security';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/history_view.php';
require_once __DIR__ . '/../includes/footer.php';
