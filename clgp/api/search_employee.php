<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$plant = trim($_GET['plant'] ?? '');
$department = trim($_GET['department'] ?? '');
$all = isset($_GET['all']) && $_GET['all'] === '1';

// Dropdown mode: load all for plant/dept (q optional filter)
if ($all) {
    if ($plant === '') {
        echo json_encode([]);
        exit;
    }
    echo json_encode(clgp_search_employees(
        $q,
        2000,
        $plant,
        $department !== '' ? $department : null
    ));
    exit;
}

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

echo json_encode(clgp_search_employees(
    $q,
    50,
    $plant !== '' ? $plant : null,
    $department !== '' ? $department : null
));
