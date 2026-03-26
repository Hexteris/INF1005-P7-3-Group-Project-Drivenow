<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$isLoggedIn = isLoggedIn();
$isAdmin    = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' | DriveNow' : 'DriveNow – Car Rental Singapore'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE; ?>/css/style.css" rel="stylesheet">
    <?php if (isset($pageTitle) && $pageTitle === 'Register' || $pageTitle === 'Login'): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg dn-navbar">
    <div class="container">
        <a class="navbar-brand dn-logo" href="<?php echo BASE; ?>/index.php">
            <span class="logo-icon"><i class="bi bi-car-front-fill"></i></span>
            Drive<span class="logo-accent">Now</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/cars.php">Browse Cars</a></li>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/my-bookings.php">My Bookings</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <span class="nav-user"><i class="bi bi-person-circle me-1"></i><?php echo h($_SESSION['full_name']); ?></span>
                    <a href="<?php echo BASE; ?>/logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
                <?php elseif ($isAdmin): ?>
                    <a href="<?php echo BASE; ?>/admin/index.php" class="btn btn-accent btn-sm">Admin Panel</a>
                    <a href="<?php echo BASE; ?>/admin/logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
                <?php else: ?>
                    <a href="<?php echo BASE; ?>/login.php" class="btn btn-outline-light btn-sm">Login</a>
                    <a href="<?php echo BASE; ?>/register.php" class="btn btn-accent btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
