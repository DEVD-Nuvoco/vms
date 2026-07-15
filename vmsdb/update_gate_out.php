<?php
include('db.php');
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read and decode the JSON payload
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }
    
    // Retrieve values from the decoded JSON
    $meetingId  = isset($data['meeting_id']) ? trim($conn->real_escape_string($data['meeting_id'])) : null;
    $gearStatus = isset($data['gear_status']) ? $data['gear_status'] : [];
    $extraItems = isset($data['extra_items']) ? trim($conn->real_escape_string($data['extra_items'])) : '';
    
    if (!$meetingId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing meeting ID']);
        exit;
    }
    
    // Retrieve meeting details: meetingDays, meeting_end_time, and gate_out
    $checkMeetingSql = "SELECT meetingDays, meeting_end_time, gate_out FROM meetings WHERE meeting_id = '$meetingId'";
    $result = $conn->query($checkMeetingSql);
    if ($result && $row = $result->fetch_assoc()) {
        $meetingDays    = $row['meetingDays'];
        $meetingEndTime = $row['meeting_end_time'];
        $gateOut        = $row['gate_out'];
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Meeting not found or query failed']);
        exit;
    }
    
    $currentTime = date('Y-m-d H:i:s');
    
    // Apply conditional logic based on meetingDays
    if ($meetingDays === 'M') {
        // For recurring meetings, allow update only if current time hasn't passed meeting_end_time
        if ($currentTime > $meetingEndTime) {
            echo json_encode(['status' => 'error', 'message' => 'Meeting is already over. Gate-out update not allowed.']);
            exit;
        }
        // Otherwise, allow update (even if gate_out has been set before, it can be updated/truncated)
    } elseif ($meetingDays === 'S') {
        // For single meetings, allow update only if gate_out is not already recorded
        if ($gateOut !== '0000-00-00 00:00:00') {
            echo json_encode(['status' => 'error', 'message' => 'Gate-out is already recorded. Please create a new meeting.']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid meetingDays value.']);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update meetings table with current gate_out time and extra_items (ensure your table has an extra_Item column)
    $updateMeetingSql = "UPDATE meetings 
                         SET gate_out = '$currentTime', extra_Item = '$extraItems' 
                         WHERE meeting_id = '$meetingId'";
    if (!$conn->query($updateMeetingSql)) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to record gate-out: ' . $conn->error]);
        exit;
    }
    
    // Update each gear item in the gear_issued table with collected_at time and received status
    foreach ($gearStatus as $key => $status) {
        // Expect key format: gear_{gear_id}
        if (preg_match('/^gear_(\d+)$/', $key, $matches)) {
            $gearId = $matches[1];
            $statusEscaped = $conn->real_escape_string($status);
            $updateGearSql = "UPDATE gear_issued 
                              SET collected_at = '$currentTime', received = '$statusEscaped' 
                              WHERE meeting_id = '$meetingId' AND gear_id = '$gearId'";
            if (!$conn->query($updateGearSql)) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Failed to update gear status: ' . $conn->error]);
                exit;
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Gate-out recorded and gear status updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

$conn->close();
?>
