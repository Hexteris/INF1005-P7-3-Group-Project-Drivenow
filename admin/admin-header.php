<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/config.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . BASE . "/admin/login.php");
    exit();
}
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' | DriveNow Admin' : 'DriveNow Admin'; ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo BASE; ?>/css/style.css" rel="stylesheet">
    <style>
        .admin-nav-section{padding:0 .75rem;margin:.75rem 0 .3rem;font-size:.65rem;color:var(--text-dim);letter-spacing:.1em;text-transform:uppercase;}
        .admin-topbar{display:flex;align-items:center;justify-content:space-between;padding:.6rem 1.5rem;border-bottom:1px solid var(--border);font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem;}
        .admin-topbar a{color:var(--text-muted);}
        .admin-topbar a:hover{color:var(--accent);}
        .page-header-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem;}
        .page-header-row h2{font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;line-height:1;}
        .dn-search{background:var(--bg-card);border:1px solid var(--border);color:var(--text);border-radius:var(--radius);padding:.4rem .75rem;font-size:.82rem;width:210px;transition:border-color .15s;}
        .dn-search:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(1,105,111,.18);}
        .dn-select{background:var(--bg-card);border:1px solid var(--border);color:var(--text);border-radius:var(--radius);padding:.4rem .75rem;font-size:.82rem;transition:border-color .15s;}
        .dn-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(1,105,111,.18);}
        .dn-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);}
        .dn-card-header{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.25rem;border-bottom:1px solid var(--border);gap:.5rem;flex-wrap:wrap;}
        .dn-card-title{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:.03em;margin:0;display:flex;align-items:center;gap:.4rem;}
        .mini-stat{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.2rem;}
        .mini-stat-val{font-family:'Bebas Neue',sans-serif;font-size:1.9rem;line-height:1;margin-bottom:.2rem;font-variant-numeric:tabular-nums;}
        .mini-stat-label{font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;font-weight:600;}
        .star-on{color:#fbbc05;} .star-off{color:var(--text-dim,#555);}
        .tier-gold{background:rgba(251,188,5,.15);color:#c9890d;}
        .tier-silver{background:rgba(170,170,170,.15);color:#888;}
        .tier-bronze{background:rgba(176,100,40,.15);color:#9a5e25;}
        .pts-pos{color:#34a853;font-weight:700;} .pts-neg{color:#f94144;font-weight:700;}
        .rating-bar-wrap{display:flex;align-items:center;gap:.6rem;font-size:.78rem;margin-bottom:.35rem;}
        .rating-bar-track{flex:1;height:6px;background:var(--border);border-radius:999px;overflow:hidden;}
        .rating-bar-fill{height:100%;background:#fbbc05;border-radius:999px;}
        .code-pill{font-family:'Courier New',monospace;font-size:.75rem;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:.1rem .45rem;letter-spacing:.04em;}
        .empty-state-box{text-align:center;padding:3rem 1.5rem;color:var(--text-muted);}
        .empty-state-box i{font-size:2.2rem;opacity:.35;display:block;margin-bottom:.75rem;}
        .empty-state-box p{margin:0;font-size:.9rem;}
        .skip-to-main{position:absolute;top:-4rem;left:1rem;z-index:9999;background:var(--accent,#01696f);color:#fff;padding:.5rem 1rem;border-radius:0 0 .4rem .4rem;font-size:.85rem;font-weight:600;text-decoration:none;transition:top .15s;}
        .skip-to-main:focus{top:0;}
        .modal-content{background:var(--bg-card);color:var(--text);border:1px solid var(--border);}
        .modal-header,.modal-footer{border-color:var(--border);}
        .sidebar-toggle-btn{display:none;background:none;border:1px solid var(--border);color:var(--text);border-radius:var(--radius);padding:.35rem .65rem;font-size:1.1rem;cursor:pointer;align-items:center;}
        @media(max-width:767.98px){
            .admin-sidebar{transform:translateX(-100%);transition:transform .25s;}
            .admin-sidebar.open{transform:translateX(0);}
            .admin-content{margin-left:0!important;}
            .sidebar-toggle-btn{display:flex!important;}
        }
    </style>
</head>
<body>

<a class="skip-to-main" href="#main-content">Skip to main content</a>

<nav class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin navigation">
    <div class="admin-logo">
        <i class="bi bi-car-front-fill" style="color:var(--accent);" aria-hidden="true"></i> DriveNow
    </div>

    <div class="admin-nav-section">Main</div>
    <a href="<?php echo BASE; ?>/admin/index.php" class="admin-nav-link <?php echo $currentPage==='index.php'?'active':''; ?>" <?php echo $currentPage==='index.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-cars.php" class="admin-nav-link <?php echo $currentPage==='manage-cars.php'?'active':''; ?>" <?php echo $currentPage==='manage-cars.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-car-front" aria-hidden="true"></i> Fleet / Cars
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-bookings.php" class="admin-nav-link <?php echo $currentPage==='manage-bookings.php'?'active':''; ?>" <?php echo $currentPage==='manage-bookings.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-calendar-check" aria-hidden="true"></i> Bookings
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-payments.php" class="admin-nav-link <?php echo $currentPage==='manage-payments.php'?'active':''; ?>" <?php echo $currentPage==='manage-payments.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-credit-card" aria-hidden="true"></i> Payments
    </a>

    <div class="admin-nav-section">Members &amp; Engagement</div>
    <a href="<?php echo BASE; ?>/admin/manage-users.php" class="admin-nav-link <?php echo $currentPage==='manage-users.php'?'active':''; ?>" <?php echo $currentPage==='manage-users.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-people" aria-hidden="true"></i> Members
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-reviews.php" class="admin-nav-link <?php echo $currentPage==='manage-reviews.php'?'active':''; ?>" <?php echo $currentPage==='manage-reviews.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-star" aria-hidden="true"></i> Reviews
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-loyalty.php" class="admin-nav-link <?php echo $currentPage==='manage-loyalty.php'?'active':''; ?>" <?php echo $currentPage==='manage-loyalty.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-gift" aria-hidden="true"></i> Loyalty Points
    </a>
    <a href="<?php echo BASE; ?>/admin/manage-referrals.php" class="admin-nav-link <?php echo $currentPage==='manage-referrals.php'?'active':''; ?>" <?php echo $currentPage==='manage-referrals.php'?'aria-current="page"':''; ?>>
        <i class="bi bi-share" aria-hidden="true"></i> Referrals
    </a>

    <hr style="border-color:var(--border);margin:1rem 1rem;">
    <a href="<?php echo BASE; ?>/index.php" class="admin-nav-link" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-globe" aria-hidden="true"></i> View Site
    </a>
    <a href="<?php echo BASE; ?>/admin/logout.php" class="admin-nav-link" style="color:#f94144;">
        <i class="bi bi-box-arrow-left" aria-hidden="true"></i> Sign Out
    </a>
</nav>

<div class="admin-content">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <button class="sidebar-toggle-btn" aria-label="Toggle navigation" aria-expanded="false"
                    aria-controls="adminSidebar" onclick="toggleSidebar(this)">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <nav aria-label="Breadcrumb">
                <a href="<?php echo BASE; ?>/admin/index.php">Admin</a>
                <span aria-hidden="true"> / </span>
                <span style="color:var(--text);"><?php echo h($pageTitle ?? 'Page'); ?></span>
            </nav>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span><?php echo h($_SESSION['admin_username'] ?? 'Admin'); ?></span>
        </div>
    </div>

<script>
function toggleSidebar(btn){
    const s=document.getElementById('adminSidebar'),open=s.classList.toggle('open');
    btn.setAttribute('aria-expanded',open);
}
document.addEventListener('click',function(e){
    const s=document.getElementById('adminSidebar'),b=document.querySelector('.sidebar-toggle-btn');
    if(s&&s.classList.contains('open')&&b&&!s.contains(e.target)&&e.target!==b){
        s.classList.remove('open'); b.setAttribute('aria-expanded','false');
    }
});
function confirmDelete(msg){return confirm(msg||'Are you sure you want to delete this item?');}
</script>