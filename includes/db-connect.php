<?php
// Parse the secure config file from OUTSIDE the web root
$config = parse_ini_file('/var/www/private/db-config.ini');

if ($config === false) {
    die("ERROR: Could not load database configuration.");
}

$conn = new mysqli(
    $config['servername'],
    $config['username'],
    $config['password'],
    $config['dbname']
);

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    die("Service temporarily unavailable. Please try again later.");
}

$conn->set_charset("utf8mb4");
?>
