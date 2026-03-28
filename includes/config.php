<?php
// -------------------------------------------------------
// config.php - Environment Detection
// Include this once via header.php — all pages get BASE
// -------------------------------------------------------

// Detect if running locally (XAMPP or Herd) vs production
function detectBase() {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // XAMPP: document root ends in htdocs, site is in a subfolder
    if (stripos($docRoot, 'htdocs') !== false) {
        return '/car_rental';
    }

    // Laravel Herd: document root points directly to the site folder
    if (stripos($docRoot, 'Herd') !== false) {
        return '';
    }

    // GCP production: document root is /var/www/html
    return '';
}

if (!defined('BASE')) {
    define('BASE', detectBase());
}

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}
?>
