<?php
include('db.php');
date_default_timezone_set('Asia/Kolkata');
// Check database connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $visitorId = isset($_GET['visitorId']) ? $conn->real_escape_string($_GET['visitorId']) : null;
    $loginType = $_GET['logintype'];
    if (!$visitorId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid visitorId']);
        exit;
    }

    $serverTime = date('Y-m-d H:i:s');
  if($loginType == 'E')
  {$sql = "
    SELECT m.*, u.userName as visitor_name, u.userEmail as visitor_email, u.userMobile
    FROM meetings m
    JOIN tbl_user u ON u.id = m.visitor_id
    WHERE m.meeting_start_time > '$serverTime'
      AND m.meetingAprroved = 'Approved'
      AND (m.visitor_id = '$visitorId' OR m.meetperson_id = '$visitorId')
    ORDER BY m.meeting_start_time ASC
";


  }elseif($loginType == 'V')
  {
    $sql = "
    SELECT m.*, u.userName as visitor_name, u.userEmail as visitor_email, u.userMobile
    FROM meetings m
    JOIN tbl_user u ON u.id = m.visitor_id
    WHERE m.meeting_start_time > '$serverTime'
      AND m.meetingAprroved = 'Approved'
      AND m.visitor_id = '$visitorId'
    ORDER BY m.meeting_start_time ASC
";
  }

// Add the ORDER BY clause


    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $meetings = [];
        while ($row = $result->fetch_assoc()) {
            $meetings[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'server_time' => $serverTime,
            'data' => $meetings,
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'server_time' => $serverTime,
            'message' => 'No upcoming meetings found',
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

$conn->close();
?>
