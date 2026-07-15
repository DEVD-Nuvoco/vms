<?php
// Allow requests from any origin

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 1);
// Database credentials
$host = "localhost";
$user = "iris";
$password = "uh(*6l7AQJ@qM.@7";
$database = "vms";

// Create connection
$conn = new mysqli($host, $user, $password, $database);


?>
