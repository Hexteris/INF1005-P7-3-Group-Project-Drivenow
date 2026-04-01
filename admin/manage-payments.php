<?php
$pageTitle = 'Manage Payments';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage payments">
<?php

$message = '';

// Refund action
if (isset($_GET['refund'])) {
    $id   = (int)$_GET['refund'];
    $stmt = $conn->prepare("UPDATE payments SET status = 'refunded' WHERE payment_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Payment marked as refunded.' : 'error:Failed to update payment.';
    $stmt->close();
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$where  = '';
$params = []; $types = '';
if (!empty($filterStatus) && in_array($filterStatus, ['paid','refunded','failed'])) {
    $where    = "WHERE p.status = ?";
    $params[] = $filterStatus;
    $types    = 's';
}

$sql = "
    SELECT p.*, m.full_name, m.email,
           c.make, c.model, c.plate_no,
           b.start_time, b.end_time, b.status AS booking_status
    FROM payments p
    JOIN members m  ON p.member_id  = m.member_id
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN cars c     ON b.car_id     = c.car_id
    $where
    ORDER BY p.paid_at DESC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Revenue stats
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_payments,
        SUM(CASE WHEN status='paid'     THEN amount ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN status='refunded' THEN amount ELSE 0 END) AS total_refunded,
        COUNT(CASE WHEN status='paid'   THEN 1 END) AS paid_count,
        COUNT(CASE WHEN status='refunded' THEN 1 END) AS refunded_count
    FROM payments
")->fetch_assoc();

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">Payments</h2>
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm"
            style="background:var(--bg-card);border:1px solid var(--border);color:var(--text);width:150px;">
            <option value="">All Statuses</option>
            <?php foreach (['paid','refunded','failed'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $filterStatus===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-light btn-sm">Filter</button>
        <?php if (!empty($filterStatus)): ?><a href="/admin/manage-payments.php" class="btn btn-outline-light btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($msgType==='success'): ?><div class="alert-success mb-3"><?php echo h($msgText); ?></div>
<?php elseif ($msgType==='error'): ?><div class="alert-error mb-3"><?php echo h($msgText); ?></div>
<?php endif; ?>

<!-- Revenue Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val text-accent">S$<?php echo number_format($stats['total_revenue'], 0); ?></div>
                <div class="stat-card-label">Total Revenue</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val"><?php echo (int)$stats['paid_count']; ?></div>
                <div class="stat-card-label">Successful Payments</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-credit-card"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val" style="font-size:1.8rem;">S$<?php echo number_format($stats['total_refunded'], 0); ?></div>
                <div class="stat-card-label">Total Refunded</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-arrow-return-left"></i></div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Car</th>
                    <th>Card</th>
                    <th>Amount</th>
                    <th>Paid At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:3rem;">No payments found.</td></tr>
                <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td style="color:var(--text-muted);">#<?php echo (int)$p['payment_id']; ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($p['full_name']); ?></div>
                        <div style="color:var(--text-muted);font-size:.78rem;"><?php echo h($p['email']); ?></div>
                    </td>
                    <td>
                        <?php echo h($p['make'].' '.$p['model']); ?><br>
                        <span style="color:var(--text-muted);font-size:.78rem;"><?php echo h($p['plate_no']); ?></span>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($p['card_type']); ?> ••••<?php echo h($p['card_last4']); ?></div>
                        <div style="color:var(--text-muted);font-size:.78rem;"><?php echo h($p['card_name']); ?></div>
                    </td>
                    <td style="font-weight:600;color:var(--accent);">S$ <?php echo number_format($p['amount'], 2); ?></td>
                    <td><?php echo date('d M Y H:i', strtotime($p['paid_at'])); ?></td>
                    <td>
                        <?php
                        $statusClass = ['paid'=>'status-confirmed','refunded'=>'status-cancelled','failed'=>'status-cancelled'];
                        ?>
                        <span class="status-pill <?php echo $statusClass[$p['status']] ?? ''; ?>">
                            <?php echo ucfirst(h($p['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'paid'): ?>
                            <a href="?refund=<?php echo (int)$p['payment_id']; ?>"
                                onclick="return confirmDelete('Mark this payment as refunded?')"
                                class="btn btn-sm" style="background:var(--bg-raised);color:var(--text);border:1px solid var(--border);">
                                <i class="bi bi-arrow-return-left"></i> Refund
                            </a>
                        <?php else: ?>
                            <span style="color:var(--text-dim);font-size:.82rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php ?>
</main>
<?php
require_once 'admin-footer.php'; ?>