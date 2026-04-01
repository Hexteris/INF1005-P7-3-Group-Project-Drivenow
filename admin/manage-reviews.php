<?php
$pageTitle = 'Manage Reviews';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage reviews">
<?php

$message = '';
if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Review deleted.' : 'error:Delete failed.';
    $stmt->close();
}

$reviews = $conn->query("
    SELECT r.*, m.full_name, c.make, c.model
    FROM reviews r JOIN members m ON r.member_id=m.member_id JOIN cars c ON r.car_id=c.car_id
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

[$msgType,$msgText] = !empty($message) ? explode(':',$message,2) : ['',''];
?>

<h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin-bottom:1.5rem;">Reviews (<?php echo count($reviews); ?>)</h2>

<?php if ($msgType==='success'): ?><div class="alert-success mb-3"><?php echo h($msgText); ?></div>
<?php elseif ($msgType==='error'): ?><div class="alert-error mb-3"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th>#</th><th>Member</th><th>Car</th><th>Rating</th><th>Comment</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $r): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$r['review_id']; ?></td>
                    <td><?php echo h($r['full_name']); ?></td>
                    <td><?php echo h($r['make'].' '.$r['model']); ?></td>
                    <td style="color:#fbbc05;"><?php echo str_repeat('★',(int)$r['rating']); ?></td>
                    <td style="max-width:300px;white-space:normal;"><?php echo h($r['comment']); ?></td>
                    <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                    <td>
                        <a href="?delete=<?php echo (int)$r['review_id']; ?>"
                            onclick="return confirmDelete('Delete this review?')"
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