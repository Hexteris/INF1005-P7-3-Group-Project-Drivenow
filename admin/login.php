<?php
$pageTitle = 'Admin Login';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db-connect.php';

if (isset($_SESSION['admin_id'])) { header("Location: /admin/index.php"); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($admin_id, $uname, $hashed);

    if ($stmt->fetch() && password_verify($password, $hashed)) {
        $_SESSION['admin_id']       = $admin_id;
        $_SESSION['admin_username'] = $uname;
        session_regenerate_id(true);
        header("Location: /admin/index.php");
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login | DriveNow</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg-base);">
<div class="dn-form-card" style="width:100%;max-width:400px;">
    <div class="text-center mb-4">
        <div class="dn-logo justify-content-center mb-1"><i class="bi bi-car-front-fill logo-icon"></i> Drive<span class="logo-accent">Now</span></div>
        <div style="color:var(--text-muted);font-size:.85rem;letter-spacing:.08em;text-transform:uppercase;">Admin Portal</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" placeholder="admin" required
                value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-accent w-100 py-2">
            <i class="bi bi-shield-lock me-2"></i>Sign In to Admin
        </button>
    </form>
    <div class="text-center mt-3">
        <a href="/index.php" style="color:var(--text-muted);font-size:.85rem;">← Back to Site</a>
    </div>
</div>
</body>
</html>
