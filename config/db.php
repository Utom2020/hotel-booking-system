<?php
// Database connection settings
$host     = 'localhost';
$dbname   = 'hotel_booking_db';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);
//$conn = new mysqli('localhost', 'hotel_booking_db', 'root', 'r');

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?> 