<?php
$pageTitle = 'Reset Password';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';

$error = '';
$success = false;
$token = trim($_GET['token'] ?? '');
$token_valid = false;
$reset_email = '';
$password_error = '';
$confirm_error = '';

// Verify token if provided
if (!empty($token)) {
    $token_data = verifyResetToken($conn, $token);
    if ($token_data) {
        $token_valid = true;
        $reset_email = $token_data['email'];
    } else {
        $error = 'This password reset link is invalid or has expired.';
    }
} else {
    $error = 'No reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$token_valid) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validate passwords match
        // Validate password strength
        $strength_errors = validatePasswordStrength($password);
        if (!empty($strength_errors)) {
            $password_error = $strength_errors[0];
        } elseif ($password !== $password_confirm) {
            $confirm_error = 'Passwords do not match.';
        } else {
            // Update password in database
            if (updatePasswordWithToken($conn, $token, $password)) {
                $success = true;
                $token_valid = false; // Prevent resubmission
            } else {
                $password_error = 'Failed to reset password. Please try again.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<main id="main-content">
<section class="page-header" aria-label="Reset password">
    <div class="container text-center">
        <div class="section-eyebrow">Account Security</div>
        <h1 class="section-title">Set New Password</h1>
    </div>
</section>

<div class="container pb-5" style="max-width: 520px;">
    <div class="dn-form-card">
        <?php if ($success): ?>
            <div class="alert alert-success mb-4" style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 16px; border-radius: 4px;">
                <i class="bi bi-check-circle me-2"></i>
                Your password has been reset successfully!
            </div>
            
            <p class="text-muted-dn mb-4">You can now log in with your new password.</p>
            
            <a href="<?php echo BASE; ?>/login.php" class="btn btn-accent w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In Now
            </a>

        <?php elseif (!empty($error)): ?>
            <div class="alert-error mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?>
            </div>
            
            <div class="mt-4">
                <p class="text-muted-dn mb-3">
                    <a href="<?php echo BASE; ?>/forgot-password.php" class="btn btn-link" style="padding: 0;">
                        Request another reset link
                    </a>
                </p>
                <p class="text-muted-dn mb-3">
                    <a href="<?php echo BASE; ?>/login.php" class="btn btn-link" style="padding: 0;">
                        Back to login
                    </a>
                </p>
            </div>

        <?php else: ?>
            <p class="text-muted-dn mb-4">
                Create a strong password to secure your account.
            </p>

            <form method="POST" action="<?php echo BASE; ?>/reset-password.php?token=<?php echo urlencode($token); ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="password">New Password</label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password"
                            placeholder="Minimum 8 characters"
                            required>
                        <button type="button" class="btn btn-link" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer;" onclick="togglePasswordVisibility('password')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <?php if (!empty($password_error)): ?>
                        <div class="text-danger mt-1" style="font-size: 0.85rem;">
                        <?php echo h($password_error); ?>
                    <?php endif; ?>
                    <small class="text-muted-dn d-block mt-2" style="font-size: 0.8rem;">
                        <ul style="margin: 8px 0; padding-left: 20px;">
                            <li>At least 8 characters</li>
                            <li>Uppercase and lowercase letters</li>
                            <li>At least one number</li>
                            <li>At least one special character (!@#$%^&*)</li>
                        </ul>
                    </small>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password_confirm">Confirm Password</label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_confirm" 
                            name="password_confirm"
                            placeholder="Repeat your password"
                            required>
                        <button type="button" class="btn btn-link" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer;" onclick="togglePasswordVisibility('password_confirm')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <?php if (!empty($confirm_error)): ?>
                        <div class="text-danger mt-1" style="font-size: 0.85rem; padding-bottom:5px;">
                        <?php echo h($confirm_error); ?>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-accent w-100 py-2">
                    <i class="bi bi-lock-fill me-2"></i>Reset Password
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

<script>
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.target.closest('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
