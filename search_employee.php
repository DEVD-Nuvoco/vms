<?php include("db.php");
$searchIndex = $mysqli->real_escape_string($_GET['searchIndex']);


$sql = "SELECT * FROM `tbl_nuvo_employee` WHERE `searchIndex` like '%$searchIndex%'";
$result = mysqli_query($mysqli,$sql);

if ($result->num_rows > 0) {
    while($resultarray = mysqli_fetch_array($result)) {
        $empCode =  $resultarray['empCode'];
        $empName = $resultarray['empName'];
        $empDepartment = $resultarray['empDepartment'];
        echo "<li data-name='$empName' data-id = '$empCode'>" . $empName . " (" . $empDepartment . ")" . "</li>";
    }
} else {
    echo " No results found.";
}

$mysqli->close();
?>
