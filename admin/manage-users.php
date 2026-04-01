<?php
$pageTitle = 'Manage Members';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage members">
<?php
$message = '';
if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Member deleted.' : 'error:Delete failed.';
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id       = (int)$_POST['member_id'];
    $fullName = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $stmt     = $conn->prepare("UPDATE members SET full_name=?,email=?,phone=? WHERE member_id=?");
    $stmt->bind_param("sssi", $fullName, $email, $phone, $id);
    $message  = $stmt->execute() ? 'success:Member updated.' : 'error:Update failed.';
    $stmt->close();
}

$members = $conn->query("
    SELECT m.*,
           COUNT(DISTINCT b.booking_id)   AS total_bookings,
           COALESCE(SUM(b.total_cost), 0) AS total_spent
    FROM members m
    LEFT JOIN bookings b ON m.member_id = b.member_id
    GROUP BY m.member_id
    ORDER BY m.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">
        Members (<?php echo count($members); ?>)
    </h2>
</div>

<?php if ($msgType === 'success'): ?>
<div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
<div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Members list">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Full Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Bookings</th>
                    <th scope="col">Total Spent</th>
                    <th scope="col">Joined</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$m['member_id']; ?></td>
                    <td style="font-weight:600;"><?php echo h($m['full_name']); ?></td>
                    <td><?php echo h($m['email']); ?></td>
                    <td style="color:var(--text-muted);"><?php echo h($m['phone'] ?? '—'); ?></td>
                    <td><?php echo (int)$m['total_bookings']; ?></td>
                    <td>S$<?php echo number_format($m['total_spent'], 2); ?></td>
                    <td style="font-size:.82rem;color:var(--text-muted);">
                        <?php echo date('d M Y', strtotime($m['created_at'])); ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <!-- Fix: aria-label on icon-only edit button -->
                            <button class="btn btn-sm"
                                    style="background:var(--bg-raised);color:var(--text);border:1px solid var(--border);"
                                    onclick='editMember(<?php echo json_encode($m); ?>)'
                                    aria-label="Edit member <?php echo h($m['full_name']); ?>"
                                    aria-haspopup="dialog">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <a href="?delete=<?php echo (int)$m['member_id']; ?>"
                               onclick="return confirmDelete('Delete this member? Their bookings will also be removed.')"
                               class="btn btn-outline-danger btn-sm"
                               aria-label="Delete member <?php echo h($m['full_name']); ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="userModal" tabindex="-1"
     aria-labelledby="userModalTitle" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border);">
            <div class="modal-header" style="border-color:var(--border);">
                <h3 class="modal-title" id="userModalTitle"
                    style="font-family:'Bebas Neue',sans-serif;font-size:1.5rem;margin:0;">Edit Member</h3>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="member_id" id="u_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="u_name" style="color:var(--text-muted);font-size:.85rem;">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="u_name"
                               style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="u_email" style="color:var(--text-muted);font-size:.85rem;">Email</label>
                        <input type="email" class="form-control" name="email" id="u_email"
                               style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="u_phone" style="color:var(--text-muted);font-size:.85rem;">Phone</label>
                        <input type="text" class="form-control" name="phone" id="u_phone"
                               style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);">
                    </div>
                </div>
                <div class="modal-footer" style="border-color:var(--border);">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMember(m) {
    document.getElementById('u_id').value    = m.member_id;
    document.getElementById('u_name').value  = m.full_name;
    document.getElementById('u_email').value = m.email;
    document.getElementById('u_phone').value = m.phone || '';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
function confirmDelete(msg) { return confirm(msg); }
</script>

</main>
<?php require_once 'admin-footer.php'; ?>
