<?php
file_put_contents('debug.log', "Request received: " . file_get_contents('php://input') . PHP_EOL, FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    file_put_contents('debug.log', "Parsed input: " . json_encode($input) . PHP_EOL, FILE_APPEND);

    if (empty($input['userId'])) {
        file_put_contents('debug.log', "Missing userId" . PHP_EOL, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
}

header('Content-Type: application/json');
error_reporting(0);
include('db.php');

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Sanitize inputs
    $userId = $conn->real_escape_string($input['userId']);
    $newPin = $conn->real_escape_string($input['newPin']);
    $sql = "SELECT userEmail FROM tbl_user WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId); // "i" denotes an integer type
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $email = $row['userEmail'];
    } else {
        $email = null; // Handle the case where no row is returned
    }
    $stmt->close();
    


  
    if ($result->num_rows > 0) {
        // User exists and old password is correct
        $updateSql = "UPDATE tbl_logindetail SET userPassword = '$newPin' WHERE userName = '$email'";

        if ($conn->query($updateSql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update the password']);
        }
    } else {
        // Old password is incorrect
        echo json_encode(['success' => false, 'message' => ' password is incorrect']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close the database connection
$conn->close();
?>
