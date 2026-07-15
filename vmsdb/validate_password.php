<?php
include('db.php');

// // Check database connection
// if ($conn->connect_error) {
//     die(json_encode(['success' => false, 'message' => 'Database connection failed']));
// }

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Sanitize inputs
    $userId = $conn->real_escape_string($input['userId']);
    $oldPassword = $conn->real_escape_string($input['oldPassword']);

    if (empty($userId) || empty($oldPassword)) {
        echo json_encode(['success' => false, 'message' => 'User ID and password are required.']);
        exit;
    }
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



    // Query to check if the old password matches
    $sql2 = "SELECT * FROM tbl_logindetail WHERE userName = '$email' AND userPassword = '$oldPassword'";
    $result = $conn->query($sql2);

    if ($result->num_rows > 0) {
        // Password is valid
        echo json_encode(['success' => true, 'message' => 'Password validated successfully.']);
    } else {
        // Invalid password
        echo json_encode(['success' => false, 'message' => 'Invalid current password.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Close the database connection
$conn->close();
?>
