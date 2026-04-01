<?php
$pageTitle = 'Manage Members';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage members">
<?php
$message = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Member deleted.' : 'error:Delete failed.';
    $stmt->close();
}

$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("
        SELECT m.*, COUNT(b.booking_id) AS booking_count,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(r.review_id) AS review_count
        FROM members m
        LEFT JOIN bookings b ON m.member_id = b.member_id
        LEFT JOIN reviews r  ON m.member_id = r.member_id
        WHERE m.full_name LIKE ? OR m.email LIKE ?
        GROUP BY m.member_id ORDER BY m.created_at DESC");
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare("
        SELECT m.*, COUNT(b.booking_id) AS booking_count,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(r.review_id) AS review_count
        FROM members m
        LEFT JOIN bookings b ON m.member_id = b.member_id
        LEFT JOIN reviews r  ON m.member_id = r.member_id
        GROUP BY m.member_id ORDER BY m.created_at DESC");
}
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];

function tierBadgeU(int $p): string {
    if ($p >= 1500) return '<span class="status-pill tier-gold">Gold</span>';
    if ($p >= 500)  return '<span class="status-pill tier-silver">Silver</span>';
    return '<span class="status-pill tier-bronze">Bronze</span>';
}
?>

<div class="page-header-row">
    <h2>Members (<?php echo count($members); ?>)</h2>
    <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;" role="search" aria-label="Search members">
        <label for="memberSearch" class="visually-hidden">Search name or email</label>
        <input type="search" id="memberSearch" name="search" class="dn-search"
               placeholder="Name or email…" value="<?php echo h($search); ?>">
        <button type="submit" class="btn btn-accent btn-sm">Search</button>
        <?php if (!empty($search)): ?><a href="<?php echo BASE; ?>/admin/manage-users.php" class="btn btn-outline-light btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($msgType === 'success'): ?><div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?><div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div><?php endif; ?>

<div class="dn-card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Members list">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Licence</th>
                    <th scope="col">Points</th>
                    <th scope="col">Tier</th>
                    <th scope="col">Referral Code</th>
                    <th scope="col">Reviews</th>
                    <th scope="col">Bookings</th>
                    <th scope="col">Joined</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($members)): ?>
            <tr><td colspan="12">
                <div class="empty-state-box"><i class="bi bi-people" aria-hidden="true"></i><p>No members found.</p></div>
            </td></tr>
            <?php else: foreach ($members as $m):
                $pts = (int)($m['points'] ?? 0); ?>
            <tr>
                <td style="color:var(--text-muted);font-size:.8rem;"><?php echo (int)$m['member_id']; ?></td>
                <td style="font-weight:600;font-size:.88rem;"><?php echo h($m['full_name']); ?></td>
                <td style="color:var(--text-muted);font-size:.82rem;"><?php echo h($m['email']); ?></td>
                <td><?php echo h($m['phone'] ?: '–'); ?></td>
                <td><?php echo h($m['licence_no'] ?: '–'); ?></td>
                <td>
                    <a href="<?php echo BASE; ?>/admin/manage-loyalty.php?search=<?php echo urlencode($m['email']); ?>"
                       style="font-variant-numeric:tabular-nums;<?php echo $pts>0?'color:#34a853;font-weight:700;':''; ?>"
                       title="Manage loyalty points">
                        <?php echo number_format($pts); ?>
                    </a>
                </td>
                <td><?php echo tierBadgeU($pts); ?></td>
                <td>
                    <?php if (!empty($m['referral_code'])): ?>
                    <span class="code-pill"><?php echo h($m['referral_code']); ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-dim);">–</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo BASE; ?>/admin/manage-reviews.php?search=<?php echo urlencode($m['email']); ?>"
                       style="font-size:.82rem;" title="View reviews by this member">
                        <?php echo (int)$m['review_count']; ?>
                        <?php if ((float)$m['avg_rating'] > 0): ?>
                        <span style="color:#fbbc05;font-size:.75rem;">(<?php echo number_format((float)$m['avg_rating'],1); ?>★)</span>
                        <?php endif; ?>
                    </a>
                </td>
                <td><span class="status-pill status-confirmed"><?php echo (int)$m['booking_count']; ?></span></td>
                <td style="font-size:.8rem;white-space:nowrap;"><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                <td style="white-space:nowrap;">
                    <a href="<?php echo BASE; ?>/admin/manage-loyalty.php?search=<?php echo urlencode($m['email']); ?>"
                       class="btn btn-outline-secondary btn-sm" title="Loyalty" aria-label="Manage loyalty for <?php echo h($m['full_name']); ?>">
                        <i class="bi bi-gift" aria-hidden="true"></i>
                    </a>
                    <a href="<?php echo BASE; ?>/admin/manage-reviews.php?search=<?php echo urlencode($m['email']); ?>"
                       class="btn btn-outline-secondary btn-sm" title="Reviews" aria-label="View reviews by <?php echo h($m['full_name']); ?>">
                        <i class="bi bi-star" aria-hidden="true"></i>
                    </a>
                    <a href="?delete=<?php echo (int)$m['member_id']; ?>"
                       onclick="return confirmDelete('Delete <?php echo h($m['full_name']); ?> and all their data?')"
                       class="btn btn-outline-danger btn-sm" aria-label="Delete <?php echo h($m['full_name']); ?>">
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