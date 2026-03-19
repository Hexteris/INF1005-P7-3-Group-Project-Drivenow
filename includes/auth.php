<?php
if (!defined('BASE')) require_once __DIR__ . '/config.php';

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['member_id'])) {
        header("Location: " . BASE . "/login.php");
        exit();
    }
}

function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_id'])) {
        header("Location: " . BASE . "/admin/login.php");
        exit();
    }
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['member_id']);
}

function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_id']);
}

// XSS sanitization helper
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
