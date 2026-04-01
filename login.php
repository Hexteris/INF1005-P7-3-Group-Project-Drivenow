<?php
$pageTitle = 'Login';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

if (isLoggedIn()) { header("Location: " . BASE . "/my-bookings.php"); exit(); }

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // ===== RATE LIMITING CHECK =====
    $rate_limit = checkLoginRateLimit($email, $max_attempts = 5, $lockout_minutes = 15);
    
    if ($rate_limit['limited']) {
        $minutes_remaining = ceil($rate_limit['remaining_seconds'] / 60);
        $error = "Too many login attempts. Please try again in $minutes_remaining minutes.";
    } else {
        $captcha_secret   = '6LdZc5gsAAAAANBuDosEPrR7f-o_rLt_IB-jGiZv';
        $captcha_response = $_POST['g-recaptcha-response'] ?? '';
        $captcha_passed = false;

        if (empty($captcha_response)) {
            $errors['captcha'] = 'Please complete the CAPTCHA verification.';
        } else {
            $verify = file_get_contents(
                "https://www.google.com/recaptcha/api/siteverify?secret=" .
                urlencode($captcha_secret) . "&response=" . urlencode($captcha_response)
            );
            $captcha_result = json_decode($verify, true);
            if ($captcha_result['success']) {
                $captcha_passed = true;
            } else {
                $errors['captcha'] = 'CAPTCHA verification failed. Please try again.';
            }
        }

        if ($captcha_passed) {
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Please enter your email and password.';
            } else {
                $stmt = $conn->prepare("SELECT member_id, full_name, password FROM members WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($member_id, $full_name, $hashed);

                if ($stmt->fetch() && password_verify($password, $hashed)) {
                    clearLoginAttempts($email);

                    $_SESSION['member_id']  = $member_id;
                    $_SESSION['full_name']  = $full_name;
                    $_SESSION['email']      = $email;

                    initializeSessionTimeout($timeout_minutes = 30);

                    session_regenerate_id(true); // Prevent session fixation
                    header("Location: " . BASE . "/my-bookings.php");
                    exit();
                } else {
                    recordFailedLoginAttempt($email, $max_attempts = 5, $lockout_minutes = 15);
                    $error = 'Invalid email or password.';
                }
                $stmt->close();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<main id="main-content">
<section class="page-header" aria-label="Sign in">
    <div class="container text-center">
        <div class="section-eyebrow">Welcome Back</div>
        <h1 class="section-title">Sign In to DriveNow</h1>
    </div>
</section>

<div class="container pb-5">
    <div class="dn-form-card">
        <?php if (!empty($error)): ?>
            <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE; ?>/login.php" onsubmit="return validateLoginFormFull();" novalidate>
            <div class="mb-3">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" class="form-control" id="email" name="email"
                    value="<?php echo h($_POST['email'] ?? ''); ?>"
                    placeholder="john@example.com" required>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                    placeholder="Your password" required>
                <small class="text-muted-dn d-block mt-2">
                    <a href="<?php echo BASE; ?>/forgot-password.php">Forgot your password?</a>
                </small>
            </div>

            <div class="mb-4">
                <div class="g-recaptcha" data-sitekey="6LdZc5gsAAAAAELSc101oVrd2ZB74yVahYxjIAwk" data-theme="dark"></div>
            </div>

            <button type="submit" class="btn btn-accent w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <hr class="divider my-4">
        <p class="text-center text-muted-dn" style="font-size:0.9rem;">
            Don't have an account? <a href="<?php echo BASE; ?>/register.php" style="text-decoration:underline;">Register →</a>
        </p>
    </div>
</div>

<script>
function validateLoginFormFull() {
    // Step 1: Validate fields first
    if (!validateLoginForm()) {
        return false;
    }

    // Step 2: Fields are valid — now check CAPTCHA
    document.querySelectorAll('.captcha-client-error').forEach(e => e.remove());
    if (!grecaptcha.getResponse()) {
        const captchaDiv = document.querySelector('.g-recaptcha');
        const err = document.createElement('div');
        err.className = 'form-error captcha-client-error';
        err.textContent = 'Please complete the CAPTCHA verification.';
        captchaDiv.parentNode.insertBefore(err, captchaDiv.nextSibling);
        return false;
    }

    return true;
}
</script>

</main>
<?php require_once 'includes/footer.php'; ?>