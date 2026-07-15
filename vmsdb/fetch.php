<?php
include("db.php");
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method.');
    }

    if (!isset($_GET['userId'])) {
        throw new Exception('User ID is required.');
    }

    $userId = intval($_GET['userId']);

    // Query to fetch user details
    $query = "SELECT 
                  `userName` AS vName, 
                  `userEmail` AS vEmail, 
                  `userMobile` AS vMobile, 
                  `userCompany` AS vCompany,
                  `userAddress` AS vLocation,
                  `userDesignation` AS vDesignation,
                  `uDob` AS vBirthday
              FROM `tbl_user` 
              WHERE `id` = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparing query: ' . $conn->error);
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User not found.');
    }

    $user = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $user]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
