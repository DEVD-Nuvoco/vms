<?php
include('db.php');
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Sanitize and assign inputs
$meetingId      = isset($_POST['meeting_id']) ? trim($conn->real_escape_string($_POST['meeting_id'])) : null;
$vehiclePermit  = isset($_POST['vehicle_permit']) ? trim($conn->real_escape_string($_POST['vehicle_permit'])) : null;
$baggageDetails = isset($_POST['baggage_details']) ? trim($conn->real_escape_string($_POST['baggage_details'])) : null;
$tokenNumber    = isset($_POST['token_number']) ? trim($conn->real_escape_string($_POST['token_number'])) : null;

$gears       = isset($_POST['gear']) ? $_POST['gear'] : [];
$quantities  = isset($_POST['gear-quantity']) ? $_POST['gear-quantity'] : [];
$returnables = isset($_POST['returnable']) ? $_POST['returnable'] : [];  // New field for returnable values
$idCards     = isset($_POST['id_card']) ? $_POST['id_card'] : [];
$idNumbers   = isset($_POST['id-number']) ? $_POST['id-number'] : [];

if (!$meetingId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing meeting ID']);
    exit;
}

$currentTime = date('Y-m-d H:i:s');

// Combine ID card details (format: CardName:IDNumber,CardName:IDNumber,...)
$idDetails = [];
foreach ($idCards as $index => $idCard) {
    $idCardName = trim($conn->real_escape_string($idCard));
    $idNumber   = isset($idNumbers[$index]) ? trim($conn->real_escape_string($idNumbers[$index])) : '';
    if (!empty($idCardName) && !empty($idNumber)) {
        $idDetails[] = "$idCardName:$idNumber";
    }
}
$combinedIdDetails = implode(',', $idDetails);

// Check if gate_in already exists for this meeting
$checkGateInSql = "SELECT gate_in FROM meetings WHERE meeting_id = ?";
$checkStmt = $conn->prepare($checkGateInSql);
$checkStmt->bind_param("s", $meetingId);
$checkStmt->execute();
$result = $checkStmt->get_result();



// First case: Gate-in – Update meeting details (including token number) and insert gear records
$updateMeetingSql = "
    UPDATE meetings 
    SET vehicle_permit = ?, 
        baggage_details = ?, 
        gate_in = ?, 
        token_number = ?, 
        meeting_details = ? 
    WHERE meeting_id = ?
";
$stmtMeeting = $conn->prepare($updateMeetingSql);
if (!$stmtMeeting) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare meeting update: ' . $conn->error]);
    exit;
}
$stmtMeeting->bind_param("ssssss", $vehiclePermit, $baggageDetails, $currentTime, $tokenNumber, $combinedIdDetails, $meetingId);
if (!$stmtMeeting->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update meeting details: ' . $stmtMeeting->error]);
    exit;
}
$stmtMeeting->close();

// Insert gear details for gate-in (collected_at remains NULL)
// Now including the returnable value for each gear item
$insertGearSql = "INSERT INTO gear_issued (meeting_id, gear_name, gear_quantity, returnable, collected_at) VALUES (?, ?, ?, ?, NULL)";
$stmtInsertGear = $conn->prepare($insertGearSql);
if (!$stmtInsertGear) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare gear insertion: ' . $conn->error]);
    exit;
}
foreach ($gears as $index => $gear) {
    $gearName = trim($conn->real_escape_string($gear));
    $quantity = isset($quantities[$index]) ? intval($quantities[$index]) : 0;
    $returnableValue = isset($returnables[$index]) ? trim($conn->real_escape_string($returnables[$index])) : 'Yes';
    
    if (!empty($gearName)) {
        $stmtInsertGear->bind_param("ssis", $meetingId, $gearName, $quantity, $returnableValue);
        if (!$stmtInsertGear->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to insert gear details: ' . $stmtInsertGear->error]);
            exit;
        }
    }
}
$stmtInsertGear->close();

// Process image upload (either file upload or captured base64 image)
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    if (isset($_FILES['visitorImage']) && $_FILES['visitorImage']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = pathinfo($_FILES['visitorImage']['name'], PATHINFO_EXTENSION);
        $fileName = $meetingId . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        if (!move_uploaded_file($_FILES['visitorImage']['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to upload image file.');
        }
    } elseif (!empty($_POST['capturedImageData'])) {
        $base64Image = $_POST['capturedImageData'];
        if (strpos($base64Image, ';base64,') !== false) {
            $imageParts = explode(';base64,', $base64Image);
            $imageType = explode(':', $imageParts[0])[1] ?? null;
            $imageData = base64_decode($imageParts[1] ?? '');
            if (!$imageType || !$imageData) {
                throw new Exception('Invalid base64 image data.');
            }
            // Support common image types: PNG and JPEG (all saved as jpg)
            switch ($imageType) {
                case 'image/png':
                case 'image/jpeg':
                case 'image/jpg':
                    $fileExtension = 'jpg';
                    break;
                default:
                    throw new Exception('Unsupported image type.');
            }
            $fileName = $meetingId . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            if (!file_put_contents($uploadPath, $imageData)) {
                throw new Exception('Failed to save captured image.');
            }
        } else {
            throw new Exception('Captured image is not in the correct format.');
        }
    } else {
        throw new Exception('No image provided.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Gate-in and details recorded successfully']);
$conn->close();
?>
