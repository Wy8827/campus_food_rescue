<?php 
$host = "localhost";
$user = "root";
$password = "";
$database = "food_rescue";

$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>