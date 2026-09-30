<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['security']);

$pageTitle = 'Application Tracking';
$activeNav = 'list';

$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? '',
    'workman_id' => $_GET['workman_id'] ?? '',
    'contractor_id' => $_GET['contractor_id'] ?? '',
    'shift' => $_GET['shift'] ?? '',
];
$list = lieo_list_applications(lieo_apply_session_plant_scope(array_filter($filters)));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/applications_track.php';
require_once __DIR__ . '/../includes/footer.php';
