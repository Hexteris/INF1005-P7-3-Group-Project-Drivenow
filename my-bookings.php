<?php
$pageTitle = 'My Bookings';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';

$member_id = $_SESSION['member_id'];
$message   = '';

// Cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND member_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $cancel_id, $member_id);
    $stmt->execute();
    $message = $stmt->affected_rows > 0 ? 'success:Booking cancelled successfully.' : 'error:Could not cancel booking. It may have already been processed.';
    $stmt->close();
}

// Fetch bookings
$stmt = $conn->prepare("
    SELECT b.*, c.make, c.model, c.plate_no, c.category, c.location, c.price_per_hr
    FROM bookings b
    JOIN cars c ON b.car_id = c.car_id
    WHERE b.member_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check which completed bookings already have a review
$reviewedCars = [];
$r = $conn->prepare("SELECT car_id FROM reviews WHERE member_id = ?");
$r->bind_param("i", $member_id);
$r->execute();
$rr = $r->get_result();
while ($row = $rr->fetch_assoc()) { $reviewedCars[] = $row['car_id']; }
$r->close();

require_once 'includes/header.php';

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['', ''];
?>

<section class="page-header">
    <div class="container">
        <div class="section-eyebrow">Dashboard</div>
        <h1 class="section-title">My Bookings</h1>
        <p class="text-muted-dn">Hello, <?php echo h($_SESSION['full_name']); ?>. Here are all your rentals.</p>
    </div>
</section>

<div class="container pb-5">
    <?php if ($msgType === 'success'): ?>
        <div class="alert-success mb-4"><i class="bi bi-check-circle me-2"></i><?php echo h($msgText); ?></div>
    <?php elseif ($msgType === 'error'): ?>
        <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($msgText); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted-dn mb-0"><?php echo count($bookings); ?> booking<?php echo count($bookings) !== 1 ? 's' : ''; ?> total</p>
        <a href="/cars.php" class="btn btn-accent btn-sm"><i class="bi bi-plus me-1"></i>New Booking</a>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem;">📋</div>
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.8rem;margin-top:1rem;">No Bookings Yet</h4>
            <p class="text-muted-dn">Browse our fleet and make your first booking!</p>
            <a href="/cars.php" class="btn btn-accent mt-2">Browse Cars</a>
        </div>
    <?php else: ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
            <div style="overflow-x:auto;">
                <table class="dn-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Car</th>
                            <th>Pickup</th>
                            <th>Return</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td style="color:var(--text-muted);">#<?php echo (int)$b['booking_id']; ?></td>
                            <td>
                                <div style="font-weight:600;"><?php echo h($b['make'].' '.$b['model']); ?></div>
                                <div style="color:var(--text-muted);font-size:.8rem;"><?php echo h($b['plate_no']); ?> · <?php echo h($b['location']); ?></div>
                            </td>
                            <td><?php echo date('d M Y, H:i', strtotime($b['start_time'])); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($b['end_time'])); ?></td>
                            <td style="font-weight:600;">S$ <?php echo number_format($b['total_cost'], 2); ?></td>
                            <td><span class="status-pill status-<?php echo h($b['status']); ?>"><?php echo h($b['status']); ?></span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirmDelete('Cancel this booking?')">
                                            <input type="hidden" name="cancel_id" value="<?php echo (int)$b['booking_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'completed' && !in_array($b['car_id'], $reviewedCars)): ?>
                                        <a href="/review.php?car_id=<?php echo (int)$b['car_id']; ?>" class="btn btn-sm"
                                            style="background:var(--bg-raised);color:var(--text);border:1px solid var(--border);">
                                            <i class="bi bi-star"></i> Review
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
