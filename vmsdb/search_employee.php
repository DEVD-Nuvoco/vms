<?php
include("db.php");
$searchIndex = $conn->real_escape_string($_GET['searchIndex']);

$sql = "SELECT * FROM `tbl_nuvo_employee` WHERE `searchIndex` LIKE '%$searchIndex%' and empStatus = 'Active'";
$result = $conn->query($sql);

$response = [];
if ($result->num_rows > 0) {
    while ($resultarray = $result->fetch_assoc()) {
        $response[] = [
            "empCode" => $resultarray['empCode'],
            "empName" => $resultarray['empName'],
            "empDepartment" => $resultarray['Department'],
            "empLocation" => $resultarray['empWorkLocation']
        ];
    }
}

echo json_encode($response);

$conn->close();
