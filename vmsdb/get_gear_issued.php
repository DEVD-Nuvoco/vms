<?php
// get_gear_issued.php
include("db.php");
header('Content-Type: application/json');

// Check if meeting_id parameter is provided
if (!isset($_GET['meeting_id']) || empty($_GET['meeting_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Meeting ID is missing']);
    exit;
}

$meeting_id = $_GET['meeting_id'];


// Prepare SQL statement to fetch issued gear for the given meeting_id
$stmt = $conn->prepare("SELECT gear_id, gear_name FROM gear_issued WHERE meeting_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement']);
    exit;
}
$stmt->bind_param("s", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

$issuedGear = [];
while ($row = $result->fetch_assoc()) {
    $issuedGear[] = $row;
}

// Close statement and connection
$stmt->close();
$conn->close();

// Return the data as JSON
echo json_encode($issuedGear);
?>
