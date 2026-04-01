<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';

if (isset($_SESSION['member_id'])) {
    if (isSessionTimedOut()) {
        session_destroy();
        $_SESSION = [];
        header("Location: " . BASE . "/login.php?timeout=1");
        exit();
    }
}

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
    <?php if (isset($pageTitle) && ($pageTitle === 'Register' || $pageTitle === 'Login')): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <?php if (isset($pageTitle) && $pageTitle === 'Register'): ?>
    <style>
    .req-hint {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .78rem;
        color: var(--text-muted);
        transition: color .2s;
    }
    .req-hint .req-icon { transition: all .2s; }
    .req-hint.req-pass { color: #34a853; }
    .req-hint.req-pass .req-icon::before {
        content: "\f26a";
        font-family: "bootstrap-icons";
        color: #34a853;
    }
    .req-hint.req-fail { color: #f94144; }
    .req-hint.req-fail .req-icon::before {
        content: "\f623";
        font-family: "bootstrap-icons";
        color: #f94144;
    }
    </style>
    <?php endif; ?>
</head>
<body>

<header>
<a href="#main-content" class="visually-hidden-focusable" style="position:absolute;top:0;left:0;z-index:9999;background:#e63946;color:#fff;padding:8px 16px;font-size:.85rem;border-radius:0 0 8px 0;">Skip to main content</a>
<nav class="navbar navbar-expand-lg dn-navbar" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand dn-logo" href="<?php echo BASE; ?>/index.php">
            <span class="logo-icon"><i class="bi bi-car-front-fill"></i></span>
            Drive<span class="logo-accent">Now</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/cars.php">Browse Cars</a></li>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/my-bookings.php">My Bookings</a></li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1" href="<?php echo BASE; ?>/my-points.php">
                        <i class="bi bi-star-fill" style="color:#f5d77e;font-size:.8rem;"></i> My Points
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1"
                       href="<?php echo BASE; ?>/login.php?redirect=my-points"
                       title="Log in to view your points">
                        <i class="bi bi-star" style="color:#888;font-size:.8rem;"></i> My Points
                    </a>
                </li>
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

<?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
<div class="alert alert-warning m-0 text-center" style="border-radius: 0; margin-bottom: 0;">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Your session has expired due to inactivity. Please log in again.
</div>
<?php endif; ?>

</header>