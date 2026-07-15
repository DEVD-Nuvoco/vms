
<?php
include('header.php');
require_once 'phpqrcode/qrlib.php'; // Ensure you have the QR code library
 // Include your database connection

 ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!isset($_GET['meeting_id']) || empty($_GET['meeting_id'])) {
    echo '<div class="alert alert-danger">Invalid Meeting ID.</div>';
    exit;
}

$meeting_id = intval($_GET['meeting_id']); // Sanitize the input

// Fetch meeting data and visitor details
$query = "SELECT m.meeting_id, m.gate_in, m.visit_purpose,m.visit_type, m.meeting_start_time, m.meeting_end_time, 
                 m.meeting_person, m.meeting_location, u.userName AS visitor_name, 
                 u.userEmail AS visitor_email, u.userMobile AS userMobile
          FROM meetings m
          JOIN tbl_user u ON m.visitor_id = u.id
          WHERE m.meeting_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger">No meeting data found for the given Meeting ID.</div>';
    exit;
}

$meetingData = $result->fetch_assoc();

// Prepare QR data
$qrData = "Meeting ID: {$meetingData['meeting_id']}\n" .
          "Gate In: {$meetingData['gate_in']}\n" .
          "Visitor: {$meetingData['visitor_name']}\n" .
          "Email: {$meetingData['visitor_email']}\n" .
          "Phone: {$meetingData['userMobile']}\n" .
          "Visit Purpose: {$meetingData['visit_purpose']}\n" .
          "Start Time: {$meetingData['meeting_start_time']}\n" .
          "Expected End Time: {$meetingData['meeting_end_time']}\n" .
          "To Meet: " . ($meetingData['meeting_person'] ?? 'N/A') . "\n" .
          "Department: " . ($meetingData['meeting_location'] ?? 'N/A');

// Generate QR code image
$qrDir = 'qrcodes/';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true); // Create directory if it doesn't exist
}

$qrFileName = $qrDir . $meetingData['meeting_id'] . '.png';
QRcode::png($qrData, $qrFileName, QR_ECLEVEL_L, 5);

?>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .qr-and-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .qr-code {
            flex: 1;
            max-width: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .details {
            flex: 2;
            display: flex;
            flex-direction: column;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .details-row > div {
            flex: 1;
            margin: 0 10px;
        }

        h2 {
            text-align: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        h3 {
            text-align: center;
            font-size: 1.5rem;
        }

        h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        p {
            margin: 5px 0;
            font-size: 0.9rem;
        }

        hr {
            margin: 15px 0;
        }

        @media (max-width: 768px) {
            .qr-and-details {
                flex-direction: column;
                align-items: center;
            }

            .details-row {
                flex-direction: column;
            }

            .details-row > div {
                margin-bottom: 15px;
            }

            h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1.3rem;
            }

            p {
                font-size: 0.85rem;
                text-align: left;
            }

            hr {
                border: 0;
                height: 1px;
                background: #ddd;
                margin: 10px 0;
                width: 100%;
            }
        }

        @media (min-width: 769px) {
            .details-row {
                display: flex;
                justify-content: space-between;
            }
        }
    </style>

    <div class="container">

        <div class="qr-and-details">
            <div class="qr-code">
                <img src="<?php echo $qrFileName; ?>" alt="QR Code"  class="qr" width ="200"/>
            </div>
            <div class="details">
                <h3>Nuvoco Vistas<br>CCP - MAIN GATE</h3>
                <hr>

                <div class="details-row">
                    <div>
                        <h4>Visitor Pass Details</h4>
                        <p><strong>Visitor Pass:</strong> <?php echo $meetingData['meeting_id']; ?></p>
                        <p><strong>Name:</strong> <?php echo $meetingData['visitor_name']; ?></p>
                    </div>
                    <div>
                        <h4>Visitor Information</h4>
                        <p><strong>Email:</strong> <?php echo $meetingData['visitor_email']; ?></p>
                        <p><strong>Mobile:</strong> <?php echo $meetingData['userMobile']; ?></p>
                    </div>
                    <div>
                        <h4>Meeting Details</h4>
                        <p><strong>To Meet:</strong> <?php echo $meetingData['meeting_person']; ?></p>
                        <p><strong>Location:</strong> <?php echo $meetingData['meeting_location']; ?></p>
                    </div>
                </div>

                <div class="details-row">
                    <div>
                        <h4>Visit Details</h4>
                        <p><strong>Visit Purpose:</strong> <?php echo $meetingData['visit_purpose']; ?></p>
                        <p><strong>Visit Type:</strong> <?php echo $meetingData['visit_type']; ?></p>
                    </div>
                    <div>
                        <h4>Time Details</h4>
                        <p><strong>In Time:</strong> <?php echo $meetingData['meeting_start_time']; ?></p>
                        <p><strong>Expected Out Time:</strong> <?php echo $meetingData['meeting_end_time']; ?></p>
                    </div>
                    <div>
                        <h4>Emergency Contact Details</h4>
                        <p>Security Point: 7727xxxxxx</p>
                        <p>Fire: 9214xxxxxx</p>
                        <p>Medical: 01462xxxxx</p>
                        <p>Control Room: 7727xxxxxx</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php

?>
