<?php
$pageTitle = 'My Bookings';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';

$member_id = $_SESSION['member_id'];
$message   = '';

// Fetch member points for banner
$pStmt = $conn->prepare("SELECT points FROM members WHERE member_id = ?");
$pStmt->bind_param("i", $member_id);
$pStmt->execute();
$pRow = $pStmt->get_result()->fetch_assoc();
$pStmt->close();
$memberPoints = (int)($pRow['points'] ?? 0);

// Cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND member_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $cancel_id, $member_id);
    $stmt->execute();
    $message = $stmt->affected_rows > 0 ? 'success:Booking cancelled successfully.' : 'error:Could not cancel booking. It may have already been processed.';
    $stmt->close();
}

// Update bookings to 'completed' when end_time has passed
date_default_timezone_set('Asia/Singapore');
$now = date('Y-m-d H:i:s');
$autoComplete = $conn->prepare("
    UPDATE bookings b
    INNER JOIN payments p ON b.booking_id = p.booking_id
    SET b.status = 'completed' 
    WHERE b.member_id = ? 
      AND b.status = 'confirmed'
      AND b.end_time < ? 
      AND p.status = 'paid'
");
$autoComplete->bind_param("is", $member_id, $now);
$autoComplete->execute();
$autoComplete->close();

// Fetch bookings
$stmt = $conn->prepare("
    SELECT b.*, c.make, c.model, c.plate_no, c.category, c.location, c.price_per_hr,
           p.payment_id, p.card_type, p.card_last4, p.status AS payment_status, p.paid_at
    FROM bookings b
    JOIN cars c ON b.car_id = c.car_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
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

    <!-- Loyalty points banner -->
    <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:var(--radius);padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-star-fill" style="color:#f5d77e;font-size:1.4rem;"></i>
            <div>
                <div style="font-size:.75rem;color:#aaa;text-transform:uppercase;letter-spacing:.08em;">Loyalty Points</div>
                <div style="font-size:1.1rem;font-weight:600;color:#f5d77e;">
                    <?php echo number_format($memberPoints); ?> pts
                    <span style="font-size:.78rem;color:#aaa;font-weight:400;margin-left:.4rem;">
                        <?php
                            if ($memberPoints >= 1500)    echo '🥇 Gold Member';
                            elseif ($memberPoints >= 500) echo '🥈 Silver Member';
                            else                          echo '🥉 Bronze Member';
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2" style="font-size:.82rem;color:#aaa;">
            <?php if ($memberPoints >= 100): ?>
                <span style="color:#34a853;"><i class="bi bi-check-circle-fill me-1"></i>Enough to redeem on next booking</span>
            <?php else: ?>
                <span><?php echo 100 - $memberPoints; ?> pts until first redemption</span>
            <?php endif; ?>
            <a href="<?php echo BASE; ?>/my-points.php" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:#f5d77e;border:1px solid rgba(245,215,126,0.3);font-size:.78rem;">
                View History →
            </a>
        </div>
    </div>
    <?php if ($msgType === 'success'): ?>
        <div class="alert-success mb-4"><i class="bi bi-check-circle me-2"></i><?php echo h($msgText); ?></div>
    <?php elseif ($msgType === 'error'): ?>
        <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($msgText); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted-dn mb-0"><?php echo count($bookings); ?> booking<?php echo count($bookings) !== 1 ? 's' : ''; ?> total</p>
        <a href="<?php echo BASE; ?>/cars.php" class="btn btn-accent btn-sm"><i class="bi bi-plus me-1"></i>New Booking</a>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem;">📋</div>
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.8rem;margin-top:1rem;">No Bookings Yet</h4>
            <p class="text-muted-dn">Browse our fleet and make your first booking!</p>
            <a href="<?php echo BASE; ?>/cars.php" class="btn btn-accent mt-2">Browse Cars</a>
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
                            <th>Payment</th>
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
                                <?php if (!empty($b['payment_id'])): ?>
                                    <div style="font-size:.82rem;">
                                        <span style="color:#34a853;"><i class="bi bi-check-circle-fill me-1"></i>Paid</span><br>
                                        <span style="color:var(--text-muted);"><?php echo h($b['card_type']); ?> ••••<?php echo h($b['card_last4']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#fbbc05;font-size:.82rem;"><i class="bi bi-clock me-1"></i>Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($b['status'] === 'pending' && empty($b['payment_id'])): ?>
                                        <a href="<?php echo BASE; ?>/payment.php?booking_id=<?php echo (int)$b['booking_id']; ?>"
                                            class="btn btn-accent btn-sm">
                                            <i class="bi bi-credit-card me-1"></i>Pay Now
                                        </a>
                                        <form method="POST" onsubmit="return confirmDelete('Cancel this booking?')">
                                            <input type="hidden" name="cancel_id" value="<?php echo (int)$b['booking_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php elseif ($b['status'] === 'pending' && !empty($b['payment_id'])): ?>
                                        <span class="status-pill status-confirmed"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    <?php elseif ($b['status'] === 'confirmed'): ?>
                                        <span class="status-pill status-confirmed"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'completed' && !in_array($b['car_id'], $reviewedCars)): ?>
                                        <a href="<?php echo BASE; ?>/review.php?car_id=<?php echo (int)$b['car_id']; ?>" class="btn btn-sm"
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