<?php
$pageTitle = 'Leave a Review';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';

$car_id    = (int)($_GET['car_id'] ?? 0);
$member_id = $_SESSION['member_id'];
$error     = '';
$success   = '';

// Fetch car
$stmt = $conn->prepare("SELECT * FROM cars WHERE car_id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$car) { header("Location: " . BASE . "/cars.php"); exit(); }

// Check user has completed a booking for this car
$chk = $conn->prepare("SELECT booking_id FROM bookings WHERE member_id = ? AND car_id = ? AND status = 'completed' LIMIT 1");
$chk->bind_param("ii", $member_id, $car_id);
$chk->execute();
$chk->store_result();
$hasBooking = $chk->num_rows > 0;
$chk->close();

// Check already reviewed
$dup = $conn->prepare("SELECT review_id FROM reviews WHERE member_id = ? AND car_id = ?");
$dup->bind_param("ii", $member_id, $car_id);
$dup->execute();
$dup->store_result();
$alreadyReviewed = $dup->num_rows > 0;
$dup->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasBooking && !$alreadyReviewed) {
    $rating  = (int)($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment']  ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5.';
    } elseif (strlen($comment) < 10) {
        $error = 'Comment must be at least 10 characters.';
    } else {
        $ins = $conn->prepare("INSERT INTO reviews (member_id, car_id, rating, comment) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiis", $member_id, $car_id, $rating, $comment);
        if ($ins->execute()) {
            $success = 'Thank you for your review!';
        } else {
            $error = 'Failed to submit review. Please try again.';
        }
        $ins->close();
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <div class="section-eyebrow">Your Experience</div>
        <h1 class="section-title">Leave a Review</h1>
    </div>
</section>

<div class="container pb-5">
    <div class="dn-form-card" style="max-width:550px;">
        <div class="d-flex align-items-center gap-3 mb-4" style="border-bottom:1px solid var(--border);padding-bottom:1.2rem;">
            <div style="font-size:3rem;"><?php echo ['Economy'=>'🚗','Comfort'=>'🚙','SUV'=>'🚐','Premium'=>'🏎️'][$car['category']] ?? '🚗'; ?></div>
            <div>
                <div class="car-name"><?php echo h($car['make'].' '.$car['model']); ?></div>
                <div class="car-plate"><?php echo h($car['plate_no']); ?></div>
            </div>
        </div>

        <?php if (!$hasBooking): ?>
            <div class="alert-error"><i class="bi bi-exclamation-triangle me-2"></i>You can only review cars you've rented and returned.</div>
        <?php elseif ($alreadyReviewed): ?>
            <div class="alert-success"><i class="bi bi-check-circle me-2"></i>You've already reviewed this car. Thank you!</div>
        <?php else: ?>
            <?php if (!empty($success)): ?>
                <div class="alert-success mb-4"><i class="bi bi-check-circle me-2"></i><?php echo h($success); ?> <a href="<?php echo BASE; ?>/my-bookings.php">Back to Bookings →</a></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label d-block" style="color:var(--text-muted);font-size:.85rem;">Your Rating *</label>
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                                <?php echo (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : ''; ?>>
                            <label for="star<?php echo $i; ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="comment" style="color:var(--text-muted);font-size:.85rem;">Your Review *</label>
                    <textarea class="form-control" id="comment" name="comment" rows="5"
                        placeholder="Share your experience with this car..."
                        style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);resize:vertical;"
                        required><?php echo h($_POST['comment'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-accent w-100 py-2">
                    <i class="bi bi-star-fill me-2"></i>Submit Review
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
