<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Get the image file name from the request
$imageName = $_GET['image'] ?? null;

if ($imageName) {
    $imagePath = __DIR__ . '/faces/' . $imageName;

    if (file_exists($imagePath)) {
        // Get the MIME type of the image
        $mimeType = mime_content_type($imagePath);
        header("Content-Type: $mimeType");

        // Output the image
        readfile($imagePath);
        exit;
    } else {
        // If the file doesn't exist, return a 404
        http_response_code(404);
        echo json_encode(["error" => "Image not found"]);
        exit;
    }
} else {
    // If no image name is provided, return a 400 Bad Request
    http_response_code(400);
    echo json_encode(["error" => "No image specified"]);
    exit;
}
?>
