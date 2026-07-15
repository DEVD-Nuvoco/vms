<?php
// Include database connection file
include("db.php"); // Replace with your actual database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch form data
    $meetingId = $_POST['meetingId'];
    $gateInTime = $_POST['gateInTime'];
    $gears = $_POST['gear'];
    $gearQuantities = $_POST['gear-quantity'];

    // Validate inputs
    if (empty($meetingId) || empty($gateInTime) || empty($gears) || empty($gearQuantities)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit();
    }

    // Begin a transaction to ensure data integrity
    $mysqli->begin_transaction();

    try {
        // Update the gate-in time for the meeting
        $stmt = $mysqli->prepare("UPDATE meetings SET gate_in = ? WHERE meeting_id = ?");
        $stmt->bind_param('si', $gateInTime, $meetingId);
        $stmt->execute();
        $stmt->close();

        // Insert each issued gear into the gear_issued table
        $stmt = $mysqli->prepare("INSERT INTO gear_issued (meeting_id, gear_name, gear_quantity, issued_at) VALUES (?, ?, ?, NOW())");
        foreach ($gears as $index => $gearName) {
            $gearQuantity = $gearQuantities[$index];
            $stmt->bind_param('isi', $meetingId, $gearName, $gearQuantity);
            $stmt->execute();
        }
        $stmt->close();

        // Commit the transaction
        $mysqli->commit();

        // Send a success response
        echo json_encode(['status' => 'success', 'message' => 'Gear issued and Gate-In time recorded successfully.']);
    } catch (Exception $e) {
        // Rollback the transaction in case of an error
        $mysqli->rollback();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
}
?>
