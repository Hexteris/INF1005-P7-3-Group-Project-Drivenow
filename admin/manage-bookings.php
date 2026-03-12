<?php
$pageTitle = 'Manage Bookings';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';

$message = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    $bid    = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    $allowed = ['pending','confirmed','completed','cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE booking_id=?");
        $stmt->bind_param("si", $status, $bid);
        $message = $stmt->execute() ? 'success:Booking status updated.' : 'error:Update failed.';
        $stmt->close();
    }
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$where = '';
$params = []; $types = '';
if (!empty($filterStatus) && in_array($filterStatus, ['pending','confirmed','completed','cancelled'])) {
    $where    = "WHERE b.status = ?";
    $params[] = $filterStatus;
    $types    = 's';
}

$sql = "SELECT b.*, m.full_name, m.email, c.make, c.model, c.plate_no
        FROM bookings b JOIN members m ON b.member_id=m.member_id JOIN cars c ON b.car_id=c.car_id
        $where ORDER BY b.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">Manage Bookings</h2>
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm"
            style="background:var(--bg-card);border:1px solid var(--border);color:var(--text);width:160px;">
            <option value="">All Statuses</option>
            <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $filterStatus===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-light btn-sm">Filter</button>
    </form>
</div>

<?php if ($msgType==='success'): ?><div class="alert-success mb-3"><?php echo h($msgText); ?></div>
<?php elseif ($msgType==='error'): ?><div class="alert-error mb-3"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th>#</th><th>Member</th><th>Car</th><th>Pickup</th><th>Return</th><th>Cost</th><th>Status</th><th>Update</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td style="color:var(--text-muted);">#<?php echo (int)$b['booking_id']; ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($b['full_name']); ?></div>
                        <div style="color:var(--text-muted);font-size:.78rem;"><?php echo h($b['email']); ?></div>
                    </td>
                    <td><?php echo h($b['make'].' '.$b['model']); ?><br><span style="color:var(--text-muted);font-size:.78rem;"><?php echo h($b['plate_no']); ?></span></td>
                    <td><?php echo date('d M Y H:i', strtotime($b['start_time'])); ?></td>
                    <td><?php echo date('d M Y H:i', strtotime($b['end_time'])); ?></td>
                    <td>S$ <?php echo number_format($b['total_cost'],2); ?></td>
                    <td><span class="status-pill status-<?php echo h($b['status']); ?>"><?php echo h($b['status']); ?></span></td>
                    <td>
                        <form method="POST" class="d-flex gap-1">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                            <select name="status" class="form-select form-select-sm"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);font-size:.8rem;width:120px;">
                                <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $b['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm" style="background:var(--accent);color:#fff;border:none;white-space:nowrap;">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'admin-footer.php'; ?>
