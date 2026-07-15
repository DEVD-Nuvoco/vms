<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'PHPmailer/PHPmailer.php';
require 'PHPmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
include('db.php');

header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata'); // Set timezone

try {
    // Fetch data directly from $_POST and $_FILES
    $visitorName        = trim($_POST['visitorName'] ?? '');
    $visitorEmail       = trim($_POST['visitorEmail'] ?? '');
    $visitorMobile      = trim($_POST['visitorMobile'] ?? '');
    $visitorDesignation = trim($_POST['visitorDesignation'] ?? '');
    $gender             = trim($_POST['gender'] ?? '');
    $visitorAge         = intval($_POST['visitorAge'] ?? 0);
    $visitPurpose       = trim($_POST['visitPurpose'] ?? '');
    $startTime          = trim($_POST['startTime'] ?? '');
    $endTime            = trim($_POST['endTime'] ?? '');
    $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
    $endTimeFormatted   = date('Y-m-d H:i:s', strtotime($endTime));
    $empCode            = intval($_POST['empCode'] ?? 0);
    $empLocation        = trim($_POST['emplocation'] ?? '');
    $meetingDays        = trim($_POST['meetingDays'] ?? '');
    
    // Validate datetime conversion
    if (!$startTimeFormatted || !$endTimeFormatted) {
        throw new Exception('Invalid datetime format. Please provide a valid datetime.');
    }
    
    $visitType       = trim($_POST['visitType'] ?? '');
    $searchMeetTo    = trim($_POST['searchMeetTo'] ?? '');
    $vehiclePermit   = trim($_POST['vehiclePermit'] ?? 'No');
    $visitorCompany  = trim($_POST['visitorCompany'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $state           = trim($_POST['state'] ?? '');
    $baggageDetails  = trim($_POST['baggageDetails'] ?? '');
    // groupMembers is sent as JSON so we'll decode it later
    $gearDetails     = $_POST['gear'] ?? []; // Already an array if sent correctly

    if ($visitorAge < 18) {
        throw new Exception('Visitor must be at least 18 years old.');
    }
    if (!in_array($meetingDays, ['S', 'M'])) {
        throw new Exception('Invalid meetingDays value.');
    }
    
    $conn->begin_transaction();
    $isNewVisitor = false;
    
    // 1. Check if the visitor already exists in tbl_user
    $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE userEmail = ?");
    $stmt->bind_param('s', $visitorEmail);
    $stmt->execute();
    $stmt->bind_result($visitorId);
    $stmt->fetch();
    $stmt->close();
    
    // Use a separate variable for the visitor's password
    $visitorPassword = '';
    if (!$visitorId) {
        // Insert new visitor if not exists
        $stmt = $conn->prepare("INSERT INTO tbl_user (userGender, userName, userEmail, userMobile, userDesignation, userAge, userCompany, userCity, userState, userCreatedOn) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssssisss', $gender, $visitorName, $visitorEmail, $visitorMobile, $visitorDesignation, $visitorAge, $visitorCompany, $city, $state);
        $stmt->execute();
        $visitorId = $stmt->insert_id;
        $stmt->close();
        
        $isNewVisitor    = true;
        $visitorPassword = mt_rand(100000, 999999); // Generate visitor's password
        
        // Create login details for the new visitor
        $stmt = $conn->prepare("INSERT INTO tbl_logindetail (logType, activationStatus, userName, userPassword)
                                VALUES ('V', 't', ?, ?)");
        $stmt->bind_param("ss", $visitorEmail, $visitorPassword);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create login details for visitor: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // Convert meeting start time to meeting date (Y-m-d)
    $meetingDate = date('Y-m-d', strtotime($startTimeFormatted));
// default it to "No" if somehow missing
$safetyInductionDone = $_POST['safetyInduction'] ?? 'No';

    
    // 2. Check if the visitor already has a meeting at this location on the same day
    // $stmt = $conn->prepare("SELECT COUNT(*) FROM meetings WHERE visitor_id = ? AND meeting_location = ? AND DATE(meeting_start_time) = ?");
    // $stmt->bind_param("sis", $visitorId, $empLocation, $meetingDate);
    // $stmt->execute();
    // $stmt->bind_result($existingMeetingCount);
    // $stmt->fetch();
    // $stmt->close();
    
    // if ($existingMeetingCount > 0) {
    //     throw new Exception('The visitor already has a meeting scheduled at this location on the same day.');
    // }
    
    // // 3. Check for conflicting bookings for the meeting person (employee)
    // $stmt = $conn->prepare(
    //     "SELECT COUNT(*) FROM meetings 
    //      WHERE meeting_person = ? o
    //      AND (
    //         (meeting_start_time <= ? AND meeting_end_time >= ?) OR
    //         (meeting_start_time >= ? AND meeting_start_time < ?) OR
    //         (meeting_end_time > ? AND meeting_end_time <= ?)
    //      )"
    // );
    // $stmt->bind_param("sssssss", $searchMeetTo, $endTimeFormatted, $startTimeFormatted, $startTimeFormatted, $endTimeFormatted, $startTimeFormatted, $endTimeFormatted);
    // $stmt->execute();
    // $stmt->bind_result($employeeConflictCount);
    // $stmt->fetch();
    // $stmt->close();
    
    // if ($employeeConflictCount > 0) {
    //     throw new Exception('The meeting person is already booked during this time.');
    // }
    
    // 4. Insert meeting details
    error_log("Safety Induction: " . $safetyInductionDone);

    $stmt = $conn->prepare(
        "INSERT INTO meetings (meetingDays, meeting_location, visitor_id, meetperson_id, meeting_person, visit_type, visit_purpose, meeting_start_time, meeting_end_time, vehicle_permit, baggage_details,safety_induction_done, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?, NOW())"
    );
    $stmt->bind_param('ssiissssssss', $meetingDays, $empLocation, $visitorId, $empCode, $searchMeetTo, $visitType, $visitPurpose, $startTimeFormatted, $endTimeFormatted, $vehiclePermit, $baggageDetails,  $safetyInductionDone);
    $stmt->execute();
    $meetingId = $stmt->insert_id;
    $stmt->close();
    
    // 5. Insert gear details if provided
    $gearDetails = json_decode($_POST['gear'], true);
    $returnable  = $_POST['returnable'];  // This is an array from the form
    
    if (!empty($gearDetails)) {
        $gearStmt = $conn->prepare("INSERT INTO gear_issued (meeting_id, gear_name, gear_quantity, returnable) VALUES (?, ?, ?, ?)");
        foreach ($gearDetails as $index => $item) {
            $gearName     = $item['gearName'] ?? null;
            $gearQuantity = $item['gearQuantity'] ?? 0;
            // Get corresponding returnable value, defaulting to 'Yes' if not set
            $isReturnable = isset($returnable[$index]) ? $returnable[$index] : 'Yes';
        
            if ($gearName && $gearQuantity > 0) {
                $gearStmt->bind_param("isis", $meetingId, $gearName, $gearQuantity, $isReturnable);
                $gearStmt->execute();
            }
        }
    }
    
    // 6. Insert group members if visitType is 'Group'
    if ($visitType === 'Group') {
        // Decode the JSON string to get the group members array
        $groupMembers = json_decode($_POST['groupMembers'] ?? '[]', true);
        
        if (is_array($groupMembers) && count($groupMembers) > 0) {
            foreach ($groupMembers as $member) {
                $name   = $member['groupMemberName'];
                $email  = $member['groupMemberEmail'];
                $mobile = $member['groupMemberMobile'];
                // Use a separate variable for each group member's password
                $groupMemberPassword = mt_rand(100000, 999999);
                
                // Check if the group member already exists
                $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE userEmail = ? OR userMobile = ?");
                $stmt->bind_param("ss", $email, $mobile);
                $stmt->execute();
                $stmt->bind_result($registered_user_id);
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->fetch(); // Get the existing user ID
                } else {
                    // Insert new group member into tbl_user
                    $stmt = $conn->prepare("INSERT INTO tbl_user (userName, userEmail, userMobile, userCity, userState, userCreatedOn)
                                            VALUES (?, ?, ?, '', '', NOW())");
                    $stmt->bind_param("sss", $name, $email, $mobile);
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting group member into tbl_user: " . $stmt->error);
                    }
                    $registered_user_id = $stmt->insert_id;
                }
                $stmt->close();
                
                // Insert login details for the group member
                $insertLoginQuery = "INSERT INTO tbl_logindetail (logType, activationStatus, userName, userPassword)
                                     VALUES ('V', 't', ?, ?)";
                $stmt2 = $conn->prepare($insertLoginQuery);
                if (!$stmt2) {
                    throw new Exception('Error preparing login insert query: ' . $conn->error);
                }
                $stmt2->bind_param('ss', $email, $groupMemberPassword);
                if (!$stmt2->execute()) {
                    throw new Exception('Failed to create login details: ' . $stmt2->error);
                }
                $stmt2->close();
                
                // Insert group member into group_members table
                $stmt = $conn->prepare("INSERT INTO group_members (meeting_id, group_member_name, group_member_email, group_member_mobile, registered_user_id, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssi", $meetingId, $name, $email, $mobile, $registered_user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting group member into group_members: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            throw new Exception('Group members data is not valid or empty.');
        }
    }
    
    // 7. Handle image upload - Convert images to WebP and store in 'faces/' folder
    $uploadDir = 'faces/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true); // Create directory if it doesn't exist
    }
    
    if (isset($_FILES['visitorImage']) && $_FILES['visitorImage']['error'] === UPLOAD_ERR_OK) {
        // Process the uploaded file
        $originalTempPath = $_FILES['visitorImage']['tmp_name'];
        $imageInfo = getimagesize($originalTempPath);
        $mimeType = $imageInfo['mime'] ?? '';
        
        // Determine supported MIME types and use webp as output format
        $fileExtension = match ($mimeType) {
            'image/png', 'image/jpeg', 'image/jpg' => 'webp',
            default => throw new Exception('Unsupported image type.'),
        };
        
        // Create an image resource based on MIME type
        $imageResource = match ($mimeType) {
            'image/png'  => imagecreatefrompng($originalTempPath),
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($originalTempPath),
            default => throw new Exception('Unsupported image type.'),
        };
        
        if (!$imageResource) {
            throw new Exception('Failed to create image resource from uploaded file.');
        }
        
        $fileName = $meetingId . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        // Convert and save the image as WebP
        if (!imagewebp($imageResource, $uploadPath)) {
            imagedestroy($imageResource);
            throw new Exception('Failed to convert and save uploaded image as WebP.');
        }
        imagedestroy($imageResource);
        
    } elseif (!empty($_POST['capturedImageData'])) {
        // Process captured image data from base64
        $base64Image = $_POST['capturedImageData'];
        if (strpos($base64Image, ';base64,') !== false) {
            $imageParts = explode(';base64,', $base64Image);
            $imageType = explode(':', $imageParts[0])[1] ?? null;
            $imageData = base64_decode($imageParts[1] ?? '');
            
            if (!$imageType || !$imageData) {
                throw new Exception('Invalid base64 image data.');
            }
            
            // Determine supported MIME types and force output to webp
            $fileExtension = match ($imageType) {
                'image/png', 'image/jpeg', 'image/jpg' => 'webp',
                default => throw new Exception('Unsupported image type.'),
            };
            
            $fileName = $visitorId . '_profile.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            // Create an image resource from the decoded data
            $imageResource = imagecreatefromstring($imageData);
            if (!$imageResource) {
                throw new Exception('Failed to create image resource from captured data.');
            }
            
            // Convert and save the image as WebP
            if (!imagewebp($imageResource, $uploadPath)) {
                imagedestroy($imageResource);
                throw new Exception('Failed to convert and save captured image as WebP.');
            }
            imagedestroy($imageResource);
            
        } else {
            throw new Exception('Captured image is not in the correct format.');
        }
    }
    
    // 8. Prepare the approval link and send emails
    $approvalLink = "https://vms.nuvoco.in/vmsdb/approve_meeting.php?meeting_id=" . $meetingId;
    $mail = new PHPMailer(true);
    
    // Get the meeting person's email address
    $sql = "SELECT e.empBusiEmail 
            FROM meetings m
            JOIN tbl_nuvo_employee e ON m.meetperson_id = e.empCode
            WHERE m.meeting_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meetingId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $empBusiEmail = $row['empBusiEmail'];
    } else {
        throw new Exception("No email found for the given meeting ID.");
    }
    $stmt->close();
    
    try {
        // Email to meeting person for approval
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vmssupport@nuvoco.com';
        $mail->Password   = 'mpjqqbtmkgrbrvpb';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->setFrom('vmssupport@nuvoco.com', 'Meeting Approval');
        $mail->addAddress($empBusiEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Action Required: Approval Needed for Scheduled Meeting';
        $mail->Body    = "<p>Dear User,</p>
                          <p>A new meeting has been scheduled and requires your review. Kindly click the link below to approve or disapprove the meeting:</p>
                          <p><a href='$approvalLink'>Approve/Disapprove Meeting</a></p>
                          <p>Thank you for your prompt attention.</p>
                          <p>Best regards,</p>
                          <p>VMS TEAM</p>";
        $mail->send();
    } catch (PHPMailerException $e) {
        throw new Exception("Failed to send email to meeting person: " . $e->getMessage());
    }
    $mail->clearAddresses();
    
    // Email to visitor with account details (only if a new account was created)
    if ($isNewVisitor) {
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.office365.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'vmssupport@nuvoco.com';
            $mail->Password   = 'mpjqqbtmkgrbrvpb';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('vmssupport@nuvoco.com', 'Welcome to VMS');
            $mail->addAddress($visitorEmail);
            $mail->isHTML(true);
            $mail->Subject = 'A new Account has been generated for you.';
            $mail->Body    = "<p>Dear User,</p>
                              <p>A new ID has been created for you on VMS. Please use the following credentials to log in:</p>
                              <p><strong>Email:</strong> {$visitorEmail}<br>
                              <strong>Password:</strong> {$visitorPassword}</p>
                              <p>If you wish to change your password, please use the 'forgot password' option on our website.</p>
                              <p>You can also use the website to prebook meetings next time.<br>
                              <a href='https://vms.nuvoco.in/'>VMS WEBSITE LINK</a></p>
                              <p>Thank you for your prompt attention.</p>
                              <p>Best regards,</p>
                              <p>VMS TEAM</p>";
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new Exception("Failed to send email to visitor: " . $e->getMessage());
        }
    }
    
    // Commit the transaction
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Meeting scheduled successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
