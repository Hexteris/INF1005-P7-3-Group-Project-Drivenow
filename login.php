<?php
$pageTitle = 'Login';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header("Location: /my-bookings.php"); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $_SESSION['member_id']  = $member_id;
            $_SESSION['full_name']  = $full_name;
            $_SESSION['email']      = $email;
            session_regenerate_id(true); // Prevent session fixation
            header("Location: /my-bookings.php");
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
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

        <form method="POST" action="/login.php" onsubmit="return validateLoginForm();" novalidate>
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
            </div>

            <button type="submit" class="btn btn-accent w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <hr class="divider my-4">
        <p class="text-center text-muted-dn" style="font-size:0.9rem;">
            Don't have an account? <a href="/register.php">Register →</a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
