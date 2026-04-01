<?php
$pageTitle = 'Forgot Password';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/header.php';
require_once 'includes/mailer.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT member_id, full_name FROM members WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            // Generate reset token
            $token = generateResetToken();
            
            // Store token in database
            if (storeResetToken($conn, $email, $token, 60)) {
                // Send reset email
                $reset_link = BASE . "/reset-password.php?token=" . urlencode($token);
                
                $cfg = loadMailConfig();

                $subject = 'DriveNow Password Reset Request';
                $body = "Hi " . $row['full_name'] . ",\r\n\r\n"
                    . "We received a request to reset your password. Please click the link below to set a new password:\r\n\r\n"
                    . $reset_link . "\r\n\r\n"
                    . "This link expires in 60 minutes.\r\n\r\n"
                    . "If you did not request this, please ignore this email.\r\n\r\n"
                    . "DriveNow Team";

                // Use your existing SMTP function
                $sent = sendViaSmtp(
                    $cfg['smtp_host'], 
                    (int)$cfg['smtp_port'], 
                    $cfg['smtp_user'], 
                    $cfg['smtp_pass'], 
                    $cfg['smtp_user'], 
                    $email, 
                    $row['full_name'], 
                    $subject, 
                    $body
                );

                $success = true; // Set to true regardless of $sent for security
                
            }       
        } else {
            // Email not found - don't reveal for security
            $success = true;
        }
    }
}

?>

<main id="main-content">
<section class="page-header" aria-label="Password recovery">
    <div class="container text-center">
        <div class="section-eyebrow">Account Recovery</div>
        <h1 class="section-title">Reset Your Password</h1>
    </div>
</section>

<div class="container pb-5" style="max-width: 520px;">
    <div class="dn-form-card">
        <?php if ($success): ?>
            <div class="alert alert-success mb-4" style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 16px; border-radius: 4px;">
                <i class="bi bi-check-circle me-2"></i>
                Check your email for password reset instructions. The link expires in 60 minutes.
            </div>
            
            <div class="text-center mt-4">
                <p class="text-muted-dn mb-3">
                    Remember your password?
                </p>
                <a href="<?php echo BASE; ?>/login.php" class="btn btn-accent">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                </a>
            </div>

        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
            <?php endif; ?>

            <p class="text-muted-dn mb-4">
                Enter your email address and we'll send you a link to reset your password.
            </p>

            <form method="POST" action="<?php echo BASE; ?>/forgot-password.php" novalidate>
                <div class="mb-4">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email"
                        value="<?php echo h($_POST['email'] ?? ''); ?>"
                        placeholder="your@email.com" 
                        required>
                </div>

                <button type="submit" class="btn btn-accent w-100 py-2">
                    <i class="bi bi-envelope me-2"></i>Send Reset Link
                </button>
            </form>

            <hr class="divider my-4">

            <p class="text-center text-muted-dn" style="font-size: 0.9rem;">
                Remember your password? <a href="<?php echo BASE; ?>/login.php">Sign In →</a>
            </p>

        <?php endif; ?>
    </div>
</div>

</main>
<?php require_once 'includes/footer.php'; ?>
