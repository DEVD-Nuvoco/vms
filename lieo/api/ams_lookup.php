<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin', 'timeoffice']);

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'plants';

if ($type === 'plants') {
    $q = trim($_GET['q'] ?? '');
    echo json_encode(lieo_list_ams_plants($q !== '' ? $q : null));
    exit;
}

if ($type === 'departments') {
    $plant = trim($_GET['plant'] ?? '');
    echo json_encode(lieo_list_departments_for_plant($plant));
    exit;
}

if ($type === 'ams_departments') {
    $plant = trim($_GET['plant'] ?? '');
    echo json_encode(lieo_list_ams_departments($plant));
    exit;
}

echo json_encode([]);
