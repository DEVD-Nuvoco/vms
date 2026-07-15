<?php
include('db.php');
require 'PHPmailer/PHPmailer.php';
require 'PHPmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as e;

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Validate the meeting_id
if (!isset($_GET['meeting_id']) || empty($_GET['meeting_id'])) {
    echo "<h3>Invalid Meeting ID</h3>";
    exit;
}

$meeting_id = $_GET['meeting_id'];

// Fetch meeting details: meeting_location remains (for example, state)
// and where_meeting (for example, city/venue) is the value to update.
$stmt = $conn->prepare("
  SELECT 
    visitor_id,
    meeting_location,
    where_meeting,
    meeting_person,
    visit_type,
    visit_purpose,
    meeting_start_time,
    meeting_end_time,
    safety_induction_done,
    meetingAprroved
  FROM meetings
  WHERE meeting_id = ?
");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
// Now bind the results, including the new field:
$stmt->bind_result(
  $userId,
  $meeting_location,
  $where_meeting,
  $person,
  $visitType,
  $purpose,
  $startTime,
  $endTime,
  $safetyInductionDone,   // ← your new variable
  $status
);


if ($stmt->fetch()) {
    $stmt->close();
} else {
    echo "<h3>Meeting not found</h3>";
    exit;
}

// Fetch user details (company, name, designation)
$userSql = "
   SELECT
  /* unified output column names */
  COALESCE(u_user.userEmail,  u_emp.empBusiEmail) AS userEmail,
  COALESCE(u_user.userName,   u_emp.empName)      AS userName,
u_user.userCompany ,
    COALESCE(u_user.userDesignation,   u_emp.empDesignation)  as userDesignation
FROM meetings m

/* external visitor path: NOT 3000xxxx (exactly 8 digits) */
LEFT JOIN tbl_user AS u_user
  ON u_user.id = m.visitor_id
 AND CAST(m.visitor_id AS CHAR) NOT REGEXP '^3000[0-9]{4}$'

/* employee visitor path: IS 3000xxxx (exactly 8 digits) */
LEFT JOIN tbl_nuvo_employee AS u_emp
  ON u_emp.empCode = m.visitor_id
 AND CAST(m.visitor_id AS CHAR) REGEXP '^3000[0-9]{4}$'

WHERE m.meeting_id = ?;

";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $meeting_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

$userEmail = $userName = $userCompany = $userDesignation = "";
if ($userResult->num_rows > 0) {
    $userRow = $userResult->fetch_assoc();
    $userEmail = $userRow['userEmail'];
    $userName = $userRow['userName'];
    $userCompany = $userRow['userCompany']?? 'Nuvoco Vistas';
    $userDesignation = $userRow['userDesignation'];
}
$userStmt->close();

$showAnimation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the form input for meeting location. 
    // (Note: the input is named "meeting_location", but its value is intended for "where_meeting")
    $selectedLocation = isset($_POST['meeting_location']) ? $_POST['meeting_location'] : $where_meeting;
    if ($selectedLocation === 'Custom') {
        $customLocation = trim($_POST['custom_location']);
        if (!empty($customLocation)) {
            $selectedLocation = $customLocation;
        }
    }
    
    $action = $_POST['action'];
    $newStatus = $action === 'approve' ? 'Approved' : 'Disapproved';
    // Capture forwarded user (employee code) if any.
    $forwardUser = isset($_POST['forward_meeting']) ? $_POST['forward_meeting'] : '';

    // Update the meetingAprroved and where_meeting columns.
    $updateStmt = $conn->prepare("UPDATE meetings SET meetingAprroved = ?, where_meeting = ? WHERE meeting_id = ?");
    $updateStmt->bind_param("ssi", $newStatus, $selectedLocation, $meeting_id);
    if ($updateStmt->execute()) {
        $updateStmt->close();

        // Retrieve group member emails for notification.
        $query = "SELECT gm.group_member_email FROM group_members gm WHERE gm.meeting_id = ?";
        $gmStmt = $conn->prepare($query);
        $gmStmt->bind_param("i", $meeting_id);
        $gmStmt->execute();
        $gmResult = $gmStmt->get_result();
        
        $mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.office365.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'vmssupport@nuvoco.com';
    $mail->Password   = 'mpjqqbtmkgrbrvpb';    // your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('vmssupport@nuvoco.com', 'Meeting Approval');
    while ($row = $gmResult->fetch_assoc()) {
        $mail->addAddress($row['group_member_email']);
    }
    $mail->addAddress($userEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Meeting Status Update: Your Action is Recorded';

    // Build the HTML body
    $body  = "<p>Dear User,</p>";
    $body .= "<p>The meeting you reviewed has been <strong>{$newStatus}</strong>.</p>";
    $body .= "<p>If this was not your intended action or if you have further questions, please contact the VMS team for assistance.</p>";

    // Append the safety rules
    $body .= "<p>When visiting any of our facilities, please observe these safety rules:</p>"
           . "<ol>"
           . "<li>Use pedestrian walkways and zebra crossings when moving around.</li>"
           . "<li>Hold onto hand railings when using staircases.</li>"
           . "<li>Wear all four mandatory PPEs: Helmet, Safety shoes, Reflective jacket, and Safety goggles.</li>"
           . "<li>Avoid using your mobile phone while walking or crossing roads.</li>"
           . "</ol>";

    $body .= "<p>Thank you for your time and cooperation.</p>"
           . "<p>Best regards,<br>VMS TEAM</p>";

    $mail->Body = $body;
    $mail->send();
} catch (Exception $e) {
    throw new Exception("Failed to send email: " . $e->getMessage());
}
$gmStmt->close();

        // If a forwarded user is selected, update the meeting record and notify that employee.
        if (!empty($forwardUser)) {
            // Update the query below to use the correct email field name in your tbl_nuvo_employee table.
            $fwdStmt = $conn->prepare("SELECT empName, empBusiEmail FROM tbl_nuvo_employee WHERE empCode = ?");
            $fwdStmt->bind_param("s", $forwardUser);
            $fwdStmt->execute();
            $fwdResult = $fwdStmt->get_result();
            if ($fwdResult->num_rows > 0) {
                $fwdRow = $fwdResult->fetch_assoc();
                $forwardName = $fwdRow['empName'];
                $forwardEmail = $fwdRow['empBusiEmail']; // Adjust if your column name differs

                // Update the forwarded_to column in the meetings table with the forwarded employee's name.
                $updFwdStmt = $conn->prepare("UPDATE meetings SET forwarded_to = ? WHERE meeting_id = ?");
                $updFwdStmt->bind_param("si", $forwardName, $meeting_id);
                $updFwdStmt->execute();
                $updFwdStmt->close();

                // Send email notification to the forwarded employee.
                $mailFwd = new PHPMailer(true);
                try {
                    $mailFwd->isSMTP();
                    $mailFwd->Host = 'smtp.office365.com';
                    $mailFwd->SMTPAuth = true;
                    $mailFwd->Username = 'vmssupport@nuvoco.com';
                    $mailFwd->Password = 'mpjqqbtmkgrbrvpb';
                    $mailFwd->SMTPSecure = 'tls';
                    $mailFwd->Port = 587;
                    $mailFwd->setFrom('vmssupport@nuvoco.com', 'Meeting Forward');
                    $mailFwd->addAddress($forwardEmail);
                    $mailFwd->isHTML(true);
                    $mailFwd->Subject = 'Meeting Forwarded to You';
                    $mailFwd->Body = "
                        <p>Dear {$forwardName},</p>
                        <p>You have been forwarded a meeting by <strong>{$person}</strong>.</p>
                        <p>Please log in to the system to view the meeting details.</p>
                        <p>Regards,</p>
                        <p>VMS TEAM</p>
                    ";
                    $mailFwd->send();
                } catch (Exception $e) {
                    // Optionally log the error.
                }
            }
            $fwdStmt->close();
        }
        
        // Update local variables to reflect changes.
        $status = $newStatus;
        $where_meeting = $selectedLocation;
        $showAnimation = true;
    } else {
        echo "<h3>Failed to update meeting status. Please try again.</h3>";
    }
}

$meetingImageUrl = "https://vms.nuvoco.in/vmsdb/uploads/{$meeting_id}.jpg";
$profileImageUrl = "https://vms.nuvoco.in/vmsdb/faces/{$userId}_profile.webp";

function urlExists($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
}

if (urlExists($meetingImageUrl)) {
    $finalImageUrl = $meetingImageUrl ?? ' ' ;
} elseif (urlExists($profileImageUrl)) {
    $finalImageUrl = $profileImageUrl ?? ' ';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Approval</title>
    <!-- Include jQuery for AJAX -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; padding: 0; background-color: #f4f4f9; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        .profile-pic { display: block; margin: 0 auto 20px auto; max-width: 100%; height: auto; border-radius: 4px; }
        .details-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .details-column { flex: 1; min-width: 200px; }
        .details p { margin: 5px 0; line-height: 1.6; }
        button, select, input[type="text"] { display: inline-block; padding: 10px; margin: 10px 5px; font-size: 16px; border-radius: 4px; border: 1px solid #ccc; }
        .approve { background-color: #28a745; color: white; }
        .approve:hover { background-color: #218838; }
        .disapprove { background-color: #dc3545; color: white; }
        .disapprove:hover { background-color: #c82333; }
        .animation-container { text-align: center; margin-top: 20px; }
        .tick-mark { font-size: 50px; color: #28a745; animation: pop-in 0.5s ease-in-out; }
        @keyframes pop-in { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    </style>
    <script>
        function showCustomLocationInput() {
            const locationDropdown = document.getElementById('meeting_location');
            const customLocationInput = document.getElementById('custom_location');
            customLocationInput.style.display = locationDropdown.value === 'Custom' ? 'block' : 'none';
        }

        // Populate "Forward this meet" dropdown via AJAX.
        $(document).ready(function () {
            $.ajax({
                url: 'search_employee.php',
                type: 'GET',
                data: { searchIndex: '' },
                dataType: 'json',
                success: function (data) {
                    const forwardMeetingDropdown = $('#forward_meeting');
                    data.forEach(function (user) {
                        forwardMeetingDropdown.append(
                            `<option value="${user.empCode}">${user.empName} (${user.empDepartment})</option>`
                        );
                    });
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching users:', error);
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <table width="90%" align="left" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td width="5%"><img src="../images/nuvoco-ori.png" width="80"></td>
                <td width="95%" align="left"><h2>Meeting Approval</h2></td>
            </tr>
        </table>

        <img src="<?php echo $finalImageUrl ?? ' ';; ?>" alt="Meeting or Profile Image" class="profile-pic" width="200">

        <div class="details-container">
            <div class="details-column">
                <div class="details">
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($where_meeting); ?></p>
                    <p><strong>Meeting With:</strong> <?php echo htmlspecialchars($person); ?></p>
                    <p><strong>Visit Type:</strong> <?php echo htmlspecialchars($visitType); ?></p>
                    <p><strong>Purpose:</strong> <?php echo htmlspecialchars($purpose); ?></p>
                    <p><strong>Start Time:</strong> <?php echo htmlspecialchars($startTime); ?></p>
                    <p><strong>End Time:</strong> <?php echo htmlspecialchars($endTime); ?></p>
                    <p><strong>Safety Induction:</strong>
   <?php echo htmlspecialchars($safetyInductionDone ?? 'No'); ?></p>

                    <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>
                </div>
            </div>

            <div class="details-column">
                <div class="details">
                    <p><strong>User Name:</strong> <?php echo htmlspecialchars($userName); ?></p>
                    <p><strong>Company:</strong> <?php echo htmlspecialchars($userCompany); ?></p>
                    <p><strong>Designation:</strong> <?php echo htmlspecialchars($userDesignation); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?></p>
                </div>
            </div>
        </div>

        <!-- All form inputs are wrapped inside the form -->
        <?php if (!$showAnimation && $status !== 'Approved' && $status !== 'Disapproved'): ?>
            <form method="POST">
                <div>
                    <label for="meeting_location"><strong>Where would you meet:</strong></label>
                    <select id="meeting_location" name="meeting_location" onChange="showCustomLocationInput()">
                        <option value="Office">Office</option>
                        <option value="Plant">Plant</option>
                        <option value="Custom">Custom Location</option>
                    </select>
                    <input type="text" id="custom_location" name="custom_location" placeholder="Enter custom location" style="display: none; margin-top: 10px;">
                </div>
                <div>
                    <label for="forward_meeting"><strong>Forward this meet:</strong></label>
                    <select id="forward_meeting" name="forward_meeting">
                        <option value="">Select User</option>
                    </select>
                </div>
                <div align="center">
                    <button type="submit" name="action" value="approve" class="approve">Approve</button>
                    <button type="submit" name="action" value="disapprove" class="disapprove">Disapprove</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($showAnimation || $status === 'Approved' || $status === 'Disapproved'): ?>
            <div class="animation-container">
                <div class="tick-mark">✔</div>
                <h3 class="text-success">Meeting has been <?php echo htmlspecialchars($status); ?> successfully.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
