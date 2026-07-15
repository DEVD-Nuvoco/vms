<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = 'faces/';

    // Create the directory if it does not exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle Base64 image (Webcam capture)
    if (isset($_POST['image']) && isset($_POST['userId'])) {
        $userId = $_POST['userId'];
        $fileName = $userId . '_profile.jpg';  // Save original as JPG
        $uploadPath = $uploadDir . $fileName;
        $webpPath = $uploadDir . $userId . '_profile.webp'; // Final WebP file

        // Decode Base64
        $imageData = str_replace('data:image/jpeg;base64,', '', $_POST['image']);
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedData = base64_decode($imageData);

        if (file_put_contents($uploadPath, $decodedData)) {
            // Convert to WebP
            $image = imagecreatefromjpeg($uploadPath);
            if ($image && imagewebp($image, $webpPath, 80)) {
                imagedestroy($image);
                unlink($uploadPath); // Remove original JPG after conversion
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Captured image saved successfully!',
                    'filePath' => $webpPath
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to convert captured image to WebP']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save captured image']);
        }
        exit;
    }

    // Handle file uploads (User uploads a file)
    if (isset($_FILES['profile_picture']) && isset($_POST['userId'])) {
        $userId = $_POST['userId'];
        $fileName = $userId . '_profile.webp'; // Save as WebP
        $uploadPath = $uploadDir . $fileName;

        $imageType = exif_imagetype($_FILES['profile_picture']['tmp_name']);
        if ($imageType === IMAGETYPE_JPEG || $imageType === IMAGETYPE_PNG) {
            $image = null;
            if ($imageType === IMAGETYPE_JPEG) {
                $image = imagecreatefromjpeg($_FILES['profile_picture']['tmp_name']);
            } elseif ($imageType === IMAGETYPE_PNG) {
                $image = imagecreatefrompng($_FILES['profile_picture']['tmp_name']);
            }

            if ($image) {
                if (imagewebp($image, $uploadPath, 80)) {
                    imagedestroy($image);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'File uploaded successfully!',
                        'filePath' => $uploadPath
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to convert uploaded image to WebP']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Unsupported image type']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Only JPEG and PNG files are supported']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
