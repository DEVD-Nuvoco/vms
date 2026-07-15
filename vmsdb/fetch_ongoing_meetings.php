<?php
session_start(); // Start session to access loginType
include('db.php');
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json'); // Set JSON response header

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// 1) Retrieve & sanitize input
$userId  = isset($_GET['userId'])  ? $conn->real_escape_string($_GET['userId'])  : null;
$logType = isset($_GET['logType']) ? $conn->real_escape_string($_GET['logType']) : null;

// 2) Check required parameters
if (!$userId || !$logType) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

// 3) Ensure loginType is set in the session
if (!isset($_SESSION['loginType'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$loginType = $_SESSION['loginType']; // Retrieve loginType from session

// 4) Build the ongoing logic query
$serverTime = date('Y-m-d H:i:s');
$query = "
SELECT m.*, u.userName as visting_person
FROM meetings m
JOIN tbl_user u ON m.visitor_id = u.id
WHERE m.visitor_id = '$userId'
  AND m.meeting_start_time <= '$serverTime'
  AND m.meeting_end_time >= '$serverTime'
  AND m.meetingAprroved = 'Approved'
ORDER BY m.meeting_start_time ASC

";
// 5) Run the query
$result = $conn->query($query);

// 6) Check results and output JSON
if ($result && $result->num_rows > 0) {
    $meetings = [];
    while ($row = $result->fetch_assoc()) {
        $meetings[] = $row;
    }
    echo json_encode([
        'status' => 'success',
        'data' => $meetings,
        'loginType' => $loginType // Include loginType in the response
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No ongoing meetings found',
        'loginType' => $loginType // Include loginType even if no meetings
    ]);
}

$conn->close();
?>
