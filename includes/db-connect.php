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
$loadedPath = '';
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $config = parse_ini_file($path);
        $loadedPath = $path;
        break;
    }
}

if ($config === false) {
    error_log("DB config not found. Checked: " . implode(', ', array_filter($possiblePaths)));
    die("ERROR: db-config.ini not found. Please follow README-team-setup.md");
}

$requiredKeys = ['servername', 'username', 'password', 'dbname'];
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $config)) {
        error_log("DB config missing key '{$key}' in {$loadedPath}");
        die("Service temporarily unavailable. Please try again later.");
    }
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
error_log("DB connected using config file: {$loadedPath}");
