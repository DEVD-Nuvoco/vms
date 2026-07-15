
<?php
include("db.php");

// Retrieve the search index from the request and sanitize it
$searchIndex = $conn->real_escape_string($_GET['searchIndex']);

// Query to search users based on the input
$sql = "SELECT * FROM `tbl_user` WHERE `userName` LIKE '%$searchIndex%' and userBlock = 'f'";
$result = $conn->query($sql);

$response = [];
if ($result->num_rows > 0) {
    while ($resultarray = $result->fetch_assoc()) {
        $response[] = [
            "userId" => $resultarray['id'], // Assuming 'id' is the primary key for tbl_user
            "userName" => $resultarray['userName'],
            "userEmail" => $resultarray['userEmail'],
            "userMobile" => $resultarray['userMobile'],
            "userCity" => $resultarray['userCity'],
            "userState" => $resultarray['userState'],
            "userDesignation" => $resultarray['userDesignation'],
            "userCompany" => $resultarray['userCompany'],
            "userAge" => $resultarray['userAge']
        ];
    }
}

// Return the response as JSON
echo json_encode($response);

$conn->close();
