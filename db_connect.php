<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "ev_recharge_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>