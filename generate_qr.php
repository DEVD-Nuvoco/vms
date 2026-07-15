<?php
require_once 'phpqrcode/qrlib.php'; // Ensure this path points to your QR library

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['qrData']) || empty($_POST['qrData'])) {
        http_response_code(400);
        echo 'Invalid QR data.';
        exit;
    }

    $qrData = $_POST['qrData'];
    $fileName = 'qrcodes/' . uniqid() . '.png';

    // Ensure the directory exists
    if (!file_exists('qrcodes')) {
        mkdir('qrcodes', 0777, true);
    }

    // Generate the QR code
    QRcode::png($qrData, $fileName, QR_ECLEVEL_L, 5);

    // Return the QR code file path
    echo $fileName;
    exit;
} else {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}
?>
