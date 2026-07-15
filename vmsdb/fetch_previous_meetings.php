<?php
include('db.php');

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Ensure the request is a GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Sanitize input
    $visitorId = $conn->real_escape_string($_GET['visitorId']);
    $loginType = $_GET['logintype'];

    // Build SQL based on loginType
    if ($loginType === 'E') {
        // If loginType is 'E', combine both cases using OR
        $sql = "
            SELECT 
                m.*, 
                u.userName as visitor_name, 
                u.userEmail as visitor_email, 
                u.userMobile
            FROM meetings m
            JOIN tbl_user u ON u.id = m.visitor_id
            WHERE 
                (m.visitor_id = '$visitorId' OR m.meetperson_id = '$visitorId')
                AND m.gate_out != '0000:00:00 00:00:00'
                AND m.meetingAprroved = 'Approved'
            ORDER BY m.meeting_start_time ASC
        ";
    } else {
        // Otherwise, default case uses only visitor_id
        $sql = "
            SELECT 
                m.*, 
                u.userName as visitor_name, 
                u.userEmail as visitor_email, 
                u.userMobile
            FROM meetings m
            JOIN tbl_user u ON u.id = m.visitor_id
            WHERE 
                m.visitor_id = '$visitorId'
                AND m.gate_out != '0000:00:00 00:00:00'
                AND m.meetingAprroved = 'Approved'
            ORDER BY m.meeting_start_time ASC
        ";
    }

    // Execute the query
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $meetings = [];
        while ($row = $result->fetch_assoc()) {
            $meetings[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $meetings]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No meetings found']);
    }
}

// Close the database connection
$conn->close();
?>
