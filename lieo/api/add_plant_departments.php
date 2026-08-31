<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin', 'timeoffice']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$plant = lieo_ams_canonical_plant((string) ($payload['plant'] ?? ''));
$names = $payload['departments'] ?? $payload['department_names'] ?? [];

if (is_string($names)) {
    $names = preg_split('/\r\n|\r|\n/', $names) ?: [];
}
if (!is_array($names)) {
    $names = [];
}

if ($plant === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Plant is required.']);
    exit;
}

if ($_SESSION['lieo_role'] === 'timeoffice') {
    $sessionPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    if ($sessionPlant === '' || $plant !== $sessionPlant) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'You can only add departments for your plant.']);
        exit;
    }
}

$result = lieo_add_plant_departments_bulk($plant, $names);
if (empty($result['ok'])) {
    http_response_code(400);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
