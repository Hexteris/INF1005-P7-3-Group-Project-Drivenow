<?php
$pageTitle = 'Verify Email';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';
require_once 'includes/config.php';

$status  = 'invalid'; // invalid | expired | already | success
$message = '';

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    // Look up the token (not yet expired, not yet verified)
    $stmt = $conn->prepare(
        "SELECT member_id, verification_expires, email_verified
         FROM members
         WHERE verification_token = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $status  = 'invalid';
        $message = 'This verification link is invalid or has already been used.';
    } elseif ($row['email_verified']) {
        $status  = 'already';
        $message = 'Your email is already verified. You can log in.';
    } elseif (strtotime($row['verification_expires']) < time()) {
        $status  = 'expired';
        $message = 'This verification link has expired. Please register again or contact support.';
    } else {
        // Mark verified and clear token
        $upd = $conn->prepare(
            "UPDATE members
             SET email_verified = 1,
                 verification_token   = NULL,
                 verification_expires = NULL
             WHERE member_id = ?"
        );
        $upd->bind_param('i', $row['member_id']);
        $upd->execute();
        $upd->close();

        $status  = 'success';
        $message = 'Your email has been verified! You can now log in to your account.';
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
    <div class="container text-center">
        <div class="section-eyebrow">Account Verification</div>
        <h1 class="section-title">Email Verification</h1>
    </div>
</section>

<div class="container pb-5" style="max-width:560px;">
    <div class="dn-form-card text-center">

        <?php if ($status === 'success'): ?>
            <div style="font-size:3rem;margin-bottom:16px;">✅</div>
            <h2 style="color:#1a1a2e;margin-bottom:12px;">All Set!</h2>
            <p class="text-muted-dn mb-4"><?php echo h($message); ?></p>
            <a href="<?php echo BASE; ?>/login.php" class="btn btn-accent px-4 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In Now
            </a>

        <?php elseif ($status === 'already'): ?>
            <div style="font-size:3rem;margin-bottom:16px;">ℹ️</div>
            <h2 style="color:#1a1a2e;margin-bottom:12px;">Already Verified</h2>
            <p class="text-muted-dn mb-4"><?php echo h($message); ?></p>
            <a href="<?php echo BASE; ?>/login.php" class="btn btn-accent px-4 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
            </a>

        <?php elseif ($status === 'expired'): ?>
            <div style="font-size:3rem;margin-bottom:16px;">⏰</div>
            <h2 style="color:#1a1a2e;margin-bottom:12px;">Link Expired</h2>
            <p class="text-muted-dn mb-4"><?php echo h($message); ?></p>
            <a href="<?php echo BASE; ?>/register.php" class="btn btn-accent px-4 py-2">
                <i class="bi bi-person-plus me-2"></i>Register Again
            </a>

        <?php else: ?>
            <div style="font-size:3rem;margin-bottom:16px;">❌</div>
            <h2 style="color:#1a1a2e;margin-bottom:12px;">Invalid Link</h2>
            <p class="text-muted-dn mb-4"><?php echo h($message ?: 'No verification token was provided.'); ?></p>
            <a href="<?php echo BASE; ?>/register.php" class="btn btn-accent px-4 py-2">
                <i class="bi bi-person-plus me-2"></i>Register
            </a>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
