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
    FROM reviews r
    JOIN members m ON r.member_id = m.member_id
    JOIN cars    c ON r.car_id    = c.car_id
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin-bottom:1.5rem;">
    Reviews (<?php echo count($reviews); ?>)
</h2>

<?php if ($msgType === 'success'): ?>
<div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
<div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Reviews list">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Member</th>
                    <th scope="col">Car</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Comment</th>
                    <th scope="col">Date</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:3rem;">
                    No reviews yet.
                </td></tr>
                <?php else: foreach ($reviews as $r): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$r['review_id']; ?></td>
                    <td><?php echo h($r['full_name']); ?></td>
                    <td><?php echo h($r['make'].' '.$r['model']); ?></td>
                    <td>
                        <span aria-label="<?php echo (int)$r['rating']; ?> out of 5 stars"
                              style="color:#fbbc05;">
                            <?php echo str_repeat('★', (int)$r['rating']); ?>
                        </span>
                    </td>
                    <td style="max-width:300px;white-space:normal;"><?php echo h($r['comment']); ?></td>
                    <td style="font-size:.82rem;color:var(--text-muted);">
                        <?php echo date('d M Y', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <a href="?delete=<?php echo (int)$r['review_id']; ?>"
                           onclick="return confirm('Delete this review?')"
                           class="btn btn-outline-danger btn-sm"
                           aria-label="Delete review by <?php echo h($r['full_name']); ?>">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>
