<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: " . BASE . "/admin/login.php"); exit(); }
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
    <link href="<?php echo BASE; ?>/css/style.css" rel="stylesheet">
    <style>
        /* Fix: nav section label contrast — #444 on #111 fails 4.5:1; use #a8a8a8 (≈6.7:1) */
        .admin-nav-section { color: #a8a8a8 !important; }
    </style>
</head>
<body>

<a href="#main-content" class="visually-hidden-focusable"
   style="position:absolute;top:0;left:0;z-index:9999;background:#c1121f;color:#fff;padding:8px 16px;font-size:.85rem;">
    Skip to main content
</a>

<!-- Fix: remove redundant role="navigation" from <nav> -->
<nav class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
    <div class="admin-logo"><i class="bi bi-car-front-fill" style="color:var(--accent);" aria-hidden="true"></i> DriveNow</div>

    <!-- Fix: section label color now passes contrast via CSS above -->
    <div class="admin-nav-section" aria-hidden="true">Main</div>
    <a href="<?php echo BASE; ?>/admin/index.php"
       class="admin-nav-link <?php echo $currentPage==='index.php'?'active':''; ?>"
       <?php echo $currentPage==='index.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-cars.php"
       class="admin-nav-link <?php echo $currentPage==='manage-cars.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-cars.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-car-front" aria-hidden="true"></i> Cars
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-bookings.php"
       class="admin-nav-link <?php echo $currentPage==='manage-bookings.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-bookings.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-calendar-check" aria-hidden="true"></i> Bookings
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-users.php"
       class="admin-nav-link <?php echo $currentPage==='manage-users.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-users.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-people" aria-hidden="true"></i> Members
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-reviews.php"
       class="admin-nav-link <?php echo $currentPage==='manage-reviews.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-reviews.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-star" aria-hidden="true"></i> Reviews
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-payments.php"
       class="admin-nav-link <?php echo $currentPage==='manage-payments.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-payments.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-credit-card" aria-hidden="true"></i> Payments
    </a>

    <div class="admin-nav-section" aria-hidden="true">Loyalty &amp; Referrals</div>
    <a href="<?php echo BASE; ?>/admin/manage-loyalty.php"
       class="admin-nav-link <?php echo $currentPage==='manage-loyalty.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-loyalty.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-gift" aria-hidden="true"></i> Loyalty Points
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-referrals.php"
       class="admin-nav-link <?php echo $currentPage==='manage-referrals.php'?'active':''; ?>"
       <?php echo $currentPage==='manage-referrals.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-share" aria-hidden="true"></i> Referrals
    </a>

    <hr style="border-color:var(--border);margin:1rem 1rem;" aria-hidden="true">
    <a href="<?php echo BASE; ?>/index.php" class="admin-nav-link" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-globe" aria-hidden="true"></i> View Site
    </a>
    <a href="<?php echo BASE; ?>/admin/logout.php" class="admin-nav-link" style="color:#f94144;">
        <i class="bi bi-box-arrow-left" aria-hidden="true"></i> Sign Out
    </a>
</nav>

<div class="admin-content">
    <header aria-label="Admin toolbar" style="display:contents;">
        <h1 class="visually-hidden"><?php echo isset($pageTitle) ? h($pageTitle) . ' | DriveNow Admin' : 'DriveNow Admin'; ?></h1>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div>
            <div style="color:var(--text-muted);font-size:.85rem;">
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i><?php echo h($_SESSION['admin_username']); ?>
            </div>
        </div>
    </header>
