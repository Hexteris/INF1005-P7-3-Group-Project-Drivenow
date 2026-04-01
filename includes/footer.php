<?php if (!defined('BASE')) require_once __DIR__ . '/config.php'; ?>
<footer class="dn-footer mt-auto">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-4">
                <div class="dn-logo mb-2"><i class="bi bi-car-front-fill"></i> Drive<span class="logo-accent">Now</span></div>
                <p class="footer-tagline">Singapore's premium hourly car rental. Drive when you want, where you want.</p>
            </div>
            <div class="col-md-2">
                <h3 class="footer-heading">Company</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE; ?>/index.php">Home</a></li>
                    <li><a href="<?php echo BASE; ?>/about.php">About Us</a></li>
                    <li><a href="<?php echo BASE; ?>/cars.php">Fleet</a></li> 
                </ul>
            </div>
            <div class="col-md-2">
                <h3 class="footer-heading">Account</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE; ?>/register.php">Register</a></li>
                    <li><a href="<?php echo BASE; ?>/login.php">Login</a></li>
                    <li><a href="<?php echo BASE; ?>/my-bookings.php">My Bookings</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h3 class="footer-heading">Contact</h3>
                <p class="footer-tagline"><i class="bi bi-geo-alt-fill me-1"></i> 1 Tech Park Ave, Singapore 099234</p>
                <p class="footer-tagline"><i class="bi bi-envelope-fill me-1"></i> angweilong12345@gmail.com</p>
                <p class="footer-tagline"><i class="bi bi-telephone-fill me-1"></i> +65 6123 4567</p>
            </div>
        </div>
        <hr class="footer-divider">
        <p class="footer-copy">&copy; <?php echo date('Y'); ?> DriveNow Singapore. Built for INF1005 @ SIT.</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE; ?>/js/main.js"></script>
</body>
</html>
