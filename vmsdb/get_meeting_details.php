<?php
include ('db.php');

// Get meeting ID from request
if (isset($_GET['meeting_id']) && !empty($_GET['meeting_id'])) {
    $meeting_id = $conn->real_escape_string($_GET['meeting_id']);

    // Fetch meeting details from the database
    $query = "SELECT meeting_id,meeting_person, visit_type , meeting_start_time,meeting_end_time, where_meeting, meetingDays, gate_in , gate_out, meeting_location, forwarded_to  FROM meetings WHERE meeting_id = '$meeting_id'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $meeting = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'meeting' => $meeting]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Meeting not found.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$conn->close();
?>
