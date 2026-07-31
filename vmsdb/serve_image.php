<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$imageName = basename($_GET['image'] ?? '');

if ($imageName === '') {
    http_response_code(400);
    echo json_encode(["error" => "No image specified"]);
    exit;
}

$imagePath = __DIR__ . '/faces/' . $imageName;
$fallbackPath = dirname(__DIR__) . '/img/faces/default.png';

if (!file_exists($imagePath)) {
    $imagePath = $fallbackPath;
}

if (!file_exists($imagePath)) {
    http_response_code(404);
    echo json_encode(["error" => "Image not found"]);
    exit;
}

$mimeType = mime_content_type($imagePath) ?: 'image/png';
header("Content-Type: $mimeType");
readfile($imagePath);
exit;
