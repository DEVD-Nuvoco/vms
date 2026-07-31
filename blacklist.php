<?php
include 'header.php'; // Database connection script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check user access
if ($_SESSION['loginType'] !== 'S') {
    die("Access denied");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If the action is to unban
    if (isset($_POST['action']) && $_POST['action'] === 'unban') {
        $blacklistId = $_POST['blacklist_id'] ?? null;
        if ($blacklistId) {
            // Remove from blacklist
            $deleteQuery = "DELETE FROM tbl_blacklist_person WHERE id = ?";
            $deleteStmt = $mysqli->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $blacklistId);
            if ($deleteStmt->execute()) {
                echo "<div class='notification success'>Visitor unbanned successfully.</div>";
            } else {
                echo "<div class='notification error'>Failed to unban visitor.</div>";
            }
            $deleteStmt->close();
        }
    } else {
        // Otherwise, handle adding to blacklist
        $userId = $_POST['user_id'] ?? null;
        $customName = $_POST['custom_name'] ?? null;
        $photo = null;
        $addedBy = $_SESSION['userDetails']['id'];

        // Handle custom photo upload if provided
        if (!empty($_FILES['custom_photo']['name'])) {
            $targetDir = "uploads/blacklist/";
            // Make sure the directory exists, or create it
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $targetFile = $targetDir . uniqid() . "_" . basename($_FILES['custom_photo']['name']);
            $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            // Validate file type
            if (!in_array($fileType, ['jpg', 'jpeg', 'png'])) {
                die("<div class='notification error'>Only JPG, JPEG, and PNG files are allowed.</div>");
            }

            if (move_uploaded_file($_FILES['custom_photo']['tmp_name'], $targetFile)) {
                $photo = $targetFile;
            } else {
                die("<div class='notification error'>Failed to upload photo.</div>");
            }
        }

        // Insert into the database
        if ($userId) {
            // Add existing user to blacklist
            $query = "INSERT INTO tbl_blacklist_person (user_id, added_by) VALUES (?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $userId, $addedBy);
        } else {
            // Add custom entry to blacklist
            $query = "INSERT INTO tbl_blacklist_person (name, photo, added_by) VALUES (?, ?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ssi", $customName, $photo, $addedBy);
        }

        if ($stmt->execute()) {
            echo "<div class='notification success'>Visitor added to blacklist.</div>";
        } else {
            echo "<div class='notification error'>Failed to add visitor.</div>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blacklist Management</title>
    <style>
       
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 20px;
        }

        form {
            margin-bottom: 40px;
        }

        /* Form fields */
        label {
            display: block;
            margin: 10px 0 5px;
        }

        select, input[type="text"], input[type="file"] {
            width: 80%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        /* Buttons */
        button[type="submit"] {
            padding: 10px 20px;
            background-color: #0284a8;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button[type="submit"]:hover {
            background-color: #02718d;
        }

        .unban-btn {
            background-color: #dc3545;
            margin-top: 5px;
        }

        .unban-btn:hover {
            background-color: #c82333;
        }

        /* Notification styling */
        .notification {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
        }

        .success {
            background-color: #c8e6c9;
            color: #256029;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Blacklist table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background-color: #f0f0f0;
        }

        table th, table td {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
        }

        .photo-thumbnail {
            max-width: 80px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Blacklist Management</h2>

    <!-- Form to add someone to blacklist -->
    <form action="" method="POST" enctype="multipart/form-data">
        <h3>Select an Existing Visitor</h3>
        <label for="user_id">Select Visitor:</label>
        <select name="user_id" id="user_id">
            <option value="">None</option>
            <?php
            // Fetch all users
            $result = $mysqli->query("SELECT id, userName FROM tbl_user");
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['id']}'>{$row['id']}: {$row['userName']}</option>";
            }
            ?>
        </select>

        <h3>Or Add a Custom Visitor</h3>
        <label for="custom_name">Name:</label>
        <input type="text" name="custom_name" id="custom_name" placeholder="Enter name">

        <label for="custom_photo">Photo:</label>
        <input type="file" name="custom_photo" id="custom_photo" accept="image/*">

        <button type="submit">Add to Blacklist</button>
    </form>

    <!-- Display the current blacklist and provide an unban button -->
    <h3>Currently Blacklisted Visitors</h3>
    <?php
    // Join with tbl_user to get user info if available
    // The COALESCE(name, userName) will prefer the custom name if available, or fallback to the userName
    // If `name` is null, it means we used an existing user; else custom entry
    $blQuery = "
        SELECT 
            bp.id AS blacklist_id,
            bp.user_id,
            bp.name AS custom_name,
            bp.photo AS custom_photo,
            u.userName AS user_name,
            COALESCE(bp.name, u.userName) AS display_name,
            COALESCE(bp.photo, '') AS display_photo
        FROM tbl_blacklist_person bp
        LEFT JOIN tbl_user u ON bp.user_id = u.id
        ORDER BY bp.id DESC
    ";
    $blResult = $mysqli->query($blQuery);
    if ($blResult->num_rows > 0) {
        echo "<table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Photo</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>";
        while ($blRow = $blResult->fetch_assoc()) {
            $blacklistId = $blRow['blacklist_id'];
            $displayName = $blRow['display_name'];
            $displayPhoto = 'https://vms.nuvoco.in/vmsdb/serve_image.php?image='.$blRow['user_id'].'_profile.webp';
            echo "<tr>";
            echo "<td>{$blacklistId}</td>";
            echo "<td>{$displayName}</td>";
            echo "<td>";
            if (!empty($displayPhoto) && file_exists($displayPhoto)) {
                echo "<img src='{$displayPhoto}' alt='Photo' class='photo-thumbnail'>";
            } else {
                echo "No photo";
            }
            echo "</td>";
            echo "<td>
                    <form method='POST' style='display:inline;'>
                        <input type='hidden' name='action' value='unban'>
                        <input type='hidden' name='blacklist_id' value='{$blacklistId}'>
                        <button type='submit' class='unban-btn'>Unban</button>
                    </form>
                  </td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No visitors are currently blacklisted.</p>";
    }
    ?>
</div>
</body>
</html>
