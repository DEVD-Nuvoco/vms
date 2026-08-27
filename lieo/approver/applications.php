<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['n1', 'hod']);

$pageTitle = 'Application Tracking';
$activeNav = 'list';

$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? '',
    'workman' => $_GET['workman'] ?? '',
    'contractor_id' => $_GET['contractor_id'] ?? '',
];
$list = lieo_list_applications(lieo_apply_session_plant_scope(array_filter($filters)));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/applications_track.php';
require_once __DIR__ . '/../includes/footer.php';
