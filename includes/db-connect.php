<?php
// -------------------------------------------------------
// Environment-aware DB config loader
// Works on: GCP production VM, XAMPP, Laravel Herd
// -------------------------------------------------------
$possiblePaths = [
    '/var/www/private/db-config.ini',
    'C:/xampp/drivenow-private/db-config.ini',
    getenv('USERPROFILE') . '/Herd/drivenow-private/db-config.ini',
    dirname(__DIR__) . '/../../drivenow-private/db-config.ini',
];

$config = false;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $config = parse_ini_file($path);
        break;
    }
}

if ($config === false) {
    die("ERROR: db-config.ini not found. Please follow README-team-setup.md");
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
