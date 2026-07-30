<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
$plant = trim($_GET['plant'] ?? '');
$department = trim($_GET['department'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

echo json_encode(clgp_search_employees(
    $q,
    20,
    $plant !== '' ? $plant : null,
    $department !== '' ? $department : null
));
