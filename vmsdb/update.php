<?php
include("db.php");
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $userId = intval($input['userId'] ?? 0);
    $vName = trim($input['vName'] ?? '');
    $vEmail = trim($input['vEmail'] ?? '');
    $vMobile = trim($input['vMobile'] ?? '');
    $vCompany = trim($input['vCompany'] ?? '');
    $vLocation = trim($input['vLocation'] ?? '');
    $vDesignation = trim($input['vDesignation'] ?? '');
    $vBirthday = trim($input['vBirthday'] ?? '');

    if ($userId <= 0 || empty($vName) || empty($vEmail) || empty($vMobile) || empty($vCompany) || empty($vLocation) || empty($vDesignation) || empty($vBirthday)) {
        throw new Exception('All fields are required.');
    }

    // Query to update user details
    $query = "UPDATE `tbl_user` 
              SET `userName` = ?, 
                  `userEmail` = ?, 
                  `userMobile` = ?, 
                  `userCompany` = ?, 
                  `userAddress` = ?, 
                  `userDesignation` = ?, 
                  `uDob` = ?
              WHERE `id` = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparing update query: ' . $conn->error);
    }
    $stmt->bind_param('sssssssi', $vName, $vEmail, $vMobile, $vCompany, $vLocation, $vDesignation, $vBirthday, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update user details: ' . $stmt->error);
    }

    echo json_encode(['success' => true, 'message' => 'User profile updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
