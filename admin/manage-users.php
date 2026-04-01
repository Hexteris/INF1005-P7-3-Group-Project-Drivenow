<?php
$pageTitle = 'Manage Members';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage users">
<?php

$message = '';

// Delete member
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Member deleted.' : 'error:Delete failed.';
    $stmt->close();
}

$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $like = '%'.$search.'%';
    $stmt = $conn->prepare("SELECT m.*, COUNT(b.booking_id) AS booking_count FROM members m LEFT JOIN bookings b ON m.member_id=b.member_id WHERE m.full_name LIKE ? OR m.email LIKE ? GROUP BY m.member_id ORDER BY m.created_at DESC");
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare("SELECT m.*, COUNT(b.booking_id) AS booking_count FROM members m LEFT JOIN bookings b ON m.member_id=b.member_id GROUP BY m.member_id ORDER BY m.created_at DESC");
}
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

[$msgType,$msgText] = !empty($message) ? explode(':',$message,2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">Members (<?php echo count($members); ?>)</h2>
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm"
            style="background:var(--bg-card);border:1px solid var(--border);color:var(--text);width:220px;"
            placeholder="Search name or email..." value="<?php echo h($search); ?>">
        <button type="submit" class="btn btn-outline-light btn-sm">Search</button>
        <?php if (!empty($search)): ?><a href="<?php echo BASE; ?>/admin/manage-users.php" class="btn btn-outline-light btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($msgType==='success'): ?><div class="alert-success mb-3"><?php echo h($msgText); ?></div>
<?php elseif ($msgType==='error'): ?><div class="alert-error mb-3"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Licence</th><th>Bookings</th><th>Joined</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$m['member_id']; ?></td>
                    <td><?php echo h($m['full_name']); ?></td>
                    <td style="color:var(--text-muted);"><?php echo h($m['email']); ?></td>
                    <td><?php echo h($m['phone'] ?: '–'); ?></td>
                    <td><?php echo h($m['licence_no'] ?: '–'); ?></td>
                    <td><span class="status-pill status-confirmed"><?php echo (int)$m['booking_count']; ?></span></td>
                    <td><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                    <td>
                        <a href="?delete=<?php echo (int)$m['member_id']; ?>"
                            onclick="return confirmDelete('Delete member <?php echo h($m['full_name']); ?> and all their data?')"
                            class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php ?>
</main>
<?php
require_once 'admin-footer.php'; ?>