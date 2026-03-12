<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: /admin/login.php"); exit(); }
$currentPage = basename($_SERVER['PHP_SELF']);
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' | DriveNow Admin' : 'DriveNow Admin'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="admin-sidebar">
    <div class="admin-logo"><i class="bi bi-car-front-fill" style="color:var(--accent);"></i> DriveNow</div>
    <div style="padding:0 0.75rem;margin-bottom:0.5rem;font-size:.7rem;color:var(--text-dim);letter-spacing:.1em;text-transform:uppercase;">Main</div>
    <a href="/admin/index.php"          class="admin-nav-link <?php echo $currentPage==='index.php'?'active':''; ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="/admin/manage-cars.php"    class="admin-nav-link <?php echo $currentPage==='manage-cars.php'?'active':''; ?>"><i class="bi bi-car-front"></i> Cars</a>
    <a href="/admin/manage-bookings.php" class="admin-nav-link <?php echo $currentPage==='manage-bookings.php'?'active':''; ?>"><i class="bi bi-calendar-check"></i> Bookings</a>
    <a href="/admin/manage-users.php"   class="admin-nav-link <?php echo $currentPage==='manage-users.php'?'active':''; ?>"><i class="bi bi-people"></i> Members</a>
    <a href="/admin/manage-reviews.php" class="admin-nav-link <?php echo $currentPage==='manage-reviews.php'?'active':''; ?>"><i class="bi bi-star"></i> Reviews</a>
    <hr style="border-color:var(--border);margin:1rem 1rem;">
    <a href="/index.php"         class="admin-nav-link"><i class="bi bi-globe"></i> View Site</a>
    <a href="/admin/logout.php"  class="admin-nav-link" style="color:#f94144;"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
</nav>

<div class="admin-content">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <div style="color:var(--text-muted);font-size:.85rem;"><i class="bi bi-person-circle me-1"></i><?php echo h($_SESSION['admin_username']); ?></div>
</div>
