<?php
$pageTitle = 'Register';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

// Redirect if already logged in
if (isLoggedIn()) { header("Location: " . BASE . "/my-bookings.php"); exit(); }

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $licence_no = trim($_POST['licence_no'] ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (strlen($full_name) < 2)
        $errors['full_name'] = 'Full name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Please enter a valid email address.';
    if (strlen($password) < 8)
        $errors['password'] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $errors['confirm_password'] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT member_id FROM members WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors['email'] = 'This email is already registered.';
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO members (full_name, email, password, phone, licence_no) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssss", $full_name, $email, $hashed, $phone, $licence_no);

            if ($stmt->execute()) {
                // Send simple welcome email
                sendWelcomeEmail($email, $full_name);
                $success = 'Account created successfully! You can now <a href="' . BASE . '/login.php">log in</a>.';
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}

require_once 'includes/config.php';
require_once 'includes/header.php';
?>

<section class="page-header">
    <div class="container text-center">
        <div class="section-eyebrow">Join DriveNow</div>
        <h1 class="section-title">Create Your Account</h1>
        <p class="text-muted-dn">Fill in your details to start booking cars instantly.</p>
    </div>
</section>

<div class="container pb-5">
    <div class="dn-form-card">
        <?php if (!empty($success)): ?>
            <div class="alert-success mb-4">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($errors['general']); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE; ?>/register.php" onsubmit="return validateRegisterForm();" novalidate>
            <div class="mb-3">
                <label class="form-label" for="full_name">Full Name *</label>
                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                    id="full_name" name="full_name" value="<?php echo h($_POST['full_name'] ?? ''); ?>"
                    placeholder="John Tan" required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="form-error"><?php echo h($errors['full_name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email Address *</label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                    id="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>"
                    placeholder="john@example.com" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error"><?php echo h($errors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" class="form-control"
                    id="phone" name="phone" value="<?php echo h($_POST['phone'] ?? ''); ?>"
                    placeholder="+65 9123 4567">
            </div>

            <div class="mb-3">
                <label class="form-label" for="licence_no">Driving Licence No.</label>
                <input type="text" class="form-control"
                    id="licence_no" name="licence_no" value="<?php echo h($_POST['licence_no'] ?? ''); ?>"
                    placeholder="S1234567A">
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password *</label>
                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                    id="password" name="password" placeholder="Min. 8 characters" required>
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error"><?php echo h($errors['password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm Password *</label>
                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                    id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="form-error"><?php echo h($errors['confirm_password']); ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-accent w-100 py-2">
                <i class="bi bi-person-plus me-2"></i>Create Account
            </button>
        </form>

        <hr class="divider my-4">
        <p class="text-center text-muted-dn" style="font-size:0.9rem;">
            Already have an account? <a href="<?php echo BASE; ?>/login.php">Sign in →</a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
