<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fyp_furniture_shop";

date_default_timezone_set('Asia/Kuala_Lumpur');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>
