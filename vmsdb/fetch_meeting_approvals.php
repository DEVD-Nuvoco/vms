<?php
include('db.php');

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $meetpersonId = $conn->real_escape_string($_GET['meetpersonId']); // Match with user_id

    // Fetch meetings and join with tbl_user to get visitor name
    $sql = "SELECT 
              m.*,
                u.userName AS visitor_name,
                u.userCompany,
                u.userDesignation,
                u.userMobile
            FROM 
                meetings AS m
            JOIN 
                tbl_user AS u 
            ON 
                m.visitor_id = u.id
            WHERE 
                m.meetperson_id = '$meetpersonId' 
                AND m.meetingAprroved = 'On Hold'";

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

$conn->close();
?>
