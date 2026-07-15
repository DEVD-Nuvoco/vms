<?php
include('db.php');

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Sanitize inputs
    $username = $conn->real_escape_string($input['username']);
    $password = $conn->real_escape_string($input['password']);
    $type = $conn->real_escape_string($input['type']);


    // Validate credentials in tbl_logindetail

    $sql = "SELECT * FROM tbl_logindetail WHERE userName = '$username' AND userPassword = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Fetch user details from tbl_logindetail
        $logindetail = $result->fetch_assoc();
        $email = $logindetail['userName']; // Assuming `userName` is the email
        

        // Fetch the user's name and ID from tbl_users using email
        if($type=='V')
        {
            $userQuery = "SELECT id,userName FROM tbl_user WHERE userEmail = '$email'";
            $userResult = $conn->query($userQuery);

        } else {
            $userQuery = "SELECT empCode as id ,empName as userName FROM tbl_nuvo_employee WHERE empBusiEmail = '$email'";
            $userResult = $conn->query($userQuery);

        }
       

        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $userName = $user['userName'];
            $userId = $user['id'];

            // Return a successful response with the user's name and ID
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'userName' => $userName,
                'userId' => $userId,
                'type' => $type
            ]);
        } else {
            // Email not found in tbl_users
            echo json_encode(['success' => false, 'message' => 'User email not found in tbl_users']);
        }
    } else {
        // Invalid credentials
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

// Close the database connection
$conn->close();
?>
