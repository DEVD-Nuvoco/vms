<?php
include('db.php');

header('Content-Type: application/json');

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Sanitize inputs
    $email = $conn->real_escape_string($input['email']);
    $otp = $conn->real_escape_string($input['otp']);
    $newPassword = $conn->real_escape_string($input['newpassword']);

    // Validate inputs
    if (empty($email) || empty($otp) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Retrieve the user and check the OTP
    $sql = "SELECT userPassword FROM tbl_logindetail WHERE userName = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare query']);
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $storedOtp = $user['userPassword'];

        // Verify the OTP
        if ($storedOtp === $otp) {
            // Update the password
            $updateSql = "UPDATE tbl_logindetail SET userPassword = ? WHERE userName = ?";
            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                echo json_encode(['success' => false, 'message' => 'Failed to prepare update query']);
                exit;
            }

            $updateStmt->bind_param("ss", $newPassword, $email);
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update password']);
            }

            $updateStmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close the database connection
$conn->close();
?>
