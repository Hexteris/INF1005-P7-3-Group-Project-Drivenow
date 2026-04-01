<?php
$pageTitle = 'Manage Bookings';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage bookings">
<?php
$message = '';

if (isset($_GET['update_status'])) {
    $id     = (int)$_GET['update_status'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['pending','confirmed','cancelled','completed'])) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $status, $id);
        $message = $stmt->execute() ? 'success:Booking status updated.' : 'error:Update failed.';
        $stmt->close();
    }
}

$filterStatus = $_GET['status'] ?? '';
$where  = ''; $params = []; $types = '';
if (!empty($filterStatus) && in_array($filterStatus, ['pending','confirmed','cancelled','completed'])) {
    $where    = "WHERE b.status = ?";
    $params[] = $filterStatus;
    $types    = 's';
}

$stmt = $conn->prepare("
    SELECT b.*, m.full_name, m.email, c.make, c.model, c.plate_no
    FROM bookings b
    JOIN members m ON b.member_id = m.member_id
    JOIN cars    c ON b.car_id    = c.car_id
    $where
    ORDER BY b.created_at DESC");
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap:wrap;gap:.75rem;">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">
        Bookings (<?php echo count($bookings); ?>)
    </h2>
    <!-- Fix: <select> must have an accessible name -->
    <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;" role="search" aria-label="Filter bookings">
        <label for="booking-status-filter" class="visually-hidden">Filter by status</label>
        <select id="booking-status-filter" name="status" class="form-select form-select-sm"
                style="background:var(--bg-card);border:1px solid var(--border);color:var(--text);width:160px;">
            <option value="">All Statuses</option>
            <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $filterStatus===$s?'selected':''; ?>>
                <?php echo ucfirst($s); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-light btn-sm">Filter</button>
        <?php if (!empty($filterStatus)): ?>
        <a href="<?php echo BASE; ?>/admin/manage-bookings.php"
           class="btn btn-outline-light btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($msgType === 'success'): ?>
<div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
<div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Bookings list">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Member</th>
                    <th scope="col">Car</th>
                    <th scope="col">Period</th>
                    <th scope="col">Cost</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Update status</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:3rem;">
                    No bookings found.
                </td></tr>
                <?php else: foreach ($bookings as $b): ?>
                <tr>
                    <td style="color:var(--text-muted);">#<?php echo (int)$b['booking_id']; ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($b['full_name']); ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted);"><?php echo h($b['email']); ?></div>
                    </td>
                    <td>
                        <?php echo h($b['make'].' '.$b['model']); ?><br>
                        <span style="font-size:.78rem;color:var(--text-muted);"><?php echo h($b['plate_no']); ?></span>
                    </td>
                    <td style="font-size:.83rem;">
                        <div><?php echo date('d M Y H:i', strtotime($b['start_time'])); ?></div>
                        <div style="color:var(--text-muted);">to <?php echo date('d M Y H:i', strtotime($b['end_time'])); ?></div>
                    </td>
                    <td style="font-weight:600;">S$<?php echo number_format($b['total_cost'], 2); ?></td>
                    <td>
                        <span class="status-pill status-<?php echo h($b['status']); ?>">
                            <?php echo h($b['status']); ?>
                        </span>
                    </td>
                    <td>
                        <!-- Fix: inline status update <select> missing accessible name -->
                        <form method="POST"
                              action="?update_status=<?php echo (int)$b['booking_id']; ?>"
                              style="display:flex;gap:.35rem;align-items:center;">
                            <label for="status-<?php echo (int)$b['booking_id']; ?>" class="visually-hidden">
                                Update status for booking #<?php echo (int)$b['booking_id']; ?>
                            </label>
                            <select id="status-<?php echo (int)$b['booking_id']; ?>"
                                    name="status" class="form-select form-select-sm"
                                    style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);width:130px;">
                                <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $b['status']===$s?'selected':''; ?>>
                                    <?php echo ucfirst($s); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-accent btn-sm"
                                    aria-label="Save status for booking #<?php echo (int)$b['booking_id']; ?>">
                                Save
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(msg) { return confirm(msg); }
</script>

</main>
<?php require_once 'admin-footer.php'; ?>
