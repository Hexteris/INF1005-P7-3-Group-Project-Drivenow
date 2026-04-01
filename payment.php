<?php
$pageTitle = 'Payment';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';
require_once 'includes/mailer.php';

// ── Loyalty points config ────────────────────────────────────
define('POINTS_PER_DOLLAR',   1);
define('POINTS_REDEEM_UNIT',  100);
define('POINTS_REDEEM_VALUE', 5.00);
define('POINTS_MIN_REDEEM',   100);

$booking_id = (int)($_GET['booking_id'] ?? 0);
$member_id  = $_SESSION['member_id'];
$error      = '';
$success    = false;

// Fetch booking — must belong to this member and be pending
$stmt = $conn->prepare("
    SELECT b.*, c.make, c.model, c.plate_no, c.category, c.price_per_hr
    FROM bookings b
    JOIN cars c ON b.car_id = c.car_id
    WHERE b.booking_id = ? AND b.member_id = ? AND b.status = 'pending'
");
$stmt->bind_param("ii", $booking_id, $member_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: " . BASE . "/my-bookings.php");
    exit();
}

// Check if already paid
$chk = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ?");
$chk->bind_param("i", $booking_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    header("Location: " . BASE . "/my-bookings.php");
    exit();
}
$chk->close();

// Fetch member's current points
$mStmt = $conn->prepare("SELECT points FROM members WHERE member_id = ?");
$mStmt->bind_param("i", $member_id);
$mStmt->execute();
$mRow = $mStmt->get_result()->fetch_assoc();
$mStmt->close();
$currentPoints      = (int)($mRow['points'] ?? 0);
$maxRedeemableUnits = floor($currentPoints / POINTS_REDEEM_UNIT);
$maxPointsDiscount  = min($maxRedeemableUnits * POINTS_REDEEM_VALUE, $booking['total_cost']);
$maxRedeemableUnits = (int)ceil($maxPointsDiscount / POINTS_REDEEM_VALUE);

// Points trackers
$pointsEarned    = 0;
$pointsRedeemed  = 0;
$discountApplied = 0.00;
$finalAmount     = 0.00;

// Detect card type from number
function detectCardType($number) {
    $number = preg_replace('/\D/', '', $number);
    if (preg_match('/^4/', $number))           return 'Visa';
    if (preg_match('/^5[1-5]/', $number))      return 'Mastercard';
    if (preg_match('/^3[47]/', $number))       return 'Amex';
    if (preg_match('/^6(?:011|5)/', $number))  return 'Discover';
    return 'Card';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_name   = trim($_POST['card_name']   ?? '');
    $card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = trim($_POST['card_cvv']    ?? '');
    $use_points_units = isset($_POST['use_points']) ? (int)($_POST['use_points_units'] ?? 0) : 0;
    $use_points_units = max(0, min($use_points_units, $maxRedeemableUnits));
    $discountApplied  = round($use_points_units * POINTS_REDEEM_VALUE, 2);
    $pointsRedeemed   = $use_points_units * POINTS_REDEEM_UNIT;
    $finalAmount      = max(0, round($booking['total_cost'] - $discountApplied, 2));

    // Validate
    if (strlen($card_name) < 2) {
        $error = 'Please enter the cardholder name.';
    } elseif (strlen($card_number) < 13 || strlen($card_number) > 19) {
        $error = 'Please enter a valid card number.';
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $card_expiry)) {
        $error = 'Please enter a valid expiry date (MM/YY).';
    } elseif (strlen($card_cvv) < 3 || strlen($card_cvv) > 4) {
        $error = 'Please enter a valid CVV.';
    } else {
        // Check expiry not in past
        [$expMonth, $expYear] = explode('/', $card_expiry);
        $expiry = \DateTime::createFromFormat('m/y', $card_expiry);
        $now    = new \DateTime();
        if ($expiry < $now) {
            $error = 'Your card has expired.';
        }
    }

    if (empty($error)) {
        $card_last4 = substr($card_number, -4);
        $card_type  = detectCardType($card_number);
        $amount     = $finalAmount;

        // Insert payment record
        $ins = $conn->prepare("
            INSERT INTO payments (booking_id, member_id, amount, card_name, card_last4, card_type, status)
            VALUES (?, ?, ?, ?, ?, ?, 'paid')
        ");
        $ins->bind_param("iidsss", $booking_id, $member_id, $amount, $card_name, $card_last4, $card_type);

        if ($ins->execute()) {
            // Update booking status to confirmed
            $upd = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
            $upd->bind_param("i", $booking_id);
            $upd->execute();
            $upd->close();
            
            // Mark referral as used if this booking had a discount
            $markUsed = $conn->prepare("UPDATE referral_records SET discount_used = TRUE WHERE booking_id = ? AND referred_user_id = ?");
            $markUsed->bind_param("ii", $booking_id, $member_id);
            $markUsed->execute();
            $markUsed->close();

            // ── Loyalty: deduct redeemed points ─────────────
            if ($pointsRedeemed > 0) {
                $deduct = $conn->prepare("UPDATE members SET points = points - ? WHERE member_id = ?");
                $deduct->bind_param("ii", $pointsRedeemed, $member_id);
                $deduct->execute(); $deduct->close();
                $logR = $conn->prepare("INSERT INTO points_log (member_id, booking_id, points, type, description) VALUES (?, ?, ?, 'redeemed', ?)");
                $neg  = -$pointsRedeemed;
                $desc = "Redeemed for S\${$discountApplied} off booking #{$booking_id}";
                $logR->bind_param("iiis", $member_id, $booking_id, $neg, $desc);
                $logR->execute(); $logR->close();
            }

            // ── Loyalty: award earned points ─────────────────
            $pointsEarned = (int)floor($finalAmount * POINTS_PER_DOLLAR);
            if ($pointsEarned > 0) {
                $award = $conn->prepare("UPDATE members SET points = points + ? WHERE member_id = ?");
                $award->bind_param("ii", $pointsEarned, $member_id);
                $award->execute(); $award->close();
                $logE = $conn->prepare("INSERT INTO points_log (member_id, booking_id, points, type, description) VALUES (?, ?, ?, 'earned', ?)");
                $desc2 = "Earned for booking #{$booking_id} (S\${$finalAmount} paid)";
                $logE->bind_param("iiis", $member_id, $booking_id, $pointsEarned, $desc2);
                $logE->execute(); $logE->close();
            }

            $success = true;
            $mem = $conn->prepare("SELECT full_name, email FROM members WHERE member_id = ?");
            $mem->bind_param("i", $member_id);
            $mem->execute();
            $member = $mem->get_result()->fetch_assoc();
            $mem->close();
            sendPaymentConfirmationEmail($member['email'], $member['full_name'], $booking, $card_type, $card_last4);
        } else {
            $error = 'Payment processing failed. Please try again.';
        }
        $ins->close();
    }
}

require_once 'includes/header.php';
?>

<main id="main-content">
<section class="page-header" aria-label="Payment header">
    <div class="container">
        <div class="section-eyebrow">Secure Checkout</div>
        <h1 class="section-title">Complete Your Payment</h1>
    </div>
</section>

<div class="container pb-5">
    <?php if ($success): ?>
    <!-- Success State -->
    <div style="max-width:520px;margin:0 auto;text-align:center;padding:3rem 2rem;">
        <div style="width:80px;height:80px;background:rgba(52,168,83,0.1);border:2px solid #34a853;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;color:#34a853;">
            ✓
        </div>
        <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2.5rem;margin-bottom:0.5rem;">Payment Successful!</h2>
        <p class="text-muted-dn mb-4">Your booking has been confirmed. You're all set to drive!</p>

        <?php if ($pointsEarned > 0 || $pointsRedeemed > 0): ?>
        <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:var(--radius);padding:1.2rem 1.5rem;margin-bottom:1.5rem;text-align:left;">
            <div style="font-size:.75rem;color:#aaa;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.6rem;">
                <i class="bi bi-star-fill me-1" style="color:#f5d77e;"></i>Loyalty Points Update
            </div>
            <?php if ($pointsRedeemed > 0): ?>
            <div class="d-flex justify-content-between mb-1">
                <span style="font-size:.88rem;color:#ccc;">Points redeemed</span>
                <span style="font-size:.88rem;color:#f94144;">−<?php echo $pointsRedeemed; ?> pts</span>
            </div>
            <?php endif; ?>
            <?php if ($pointsEarned > 0): ?>
            <div class="d-flex justify-content-between mb-1">
                <span style="font-size:.88rem;color:#ccc;">Points earned</span>
                <span style="font-size:.88rem;color:#34a853;">+<?php echo $pointsEarned; ?> pts</span>
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mt-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.1);">
                <span style="font-size:.88rem;color:#fff;font-weight:600;">New balance</span>
                <span style="font-size:.88rem;color:#f5d77e;font-weight:600;">
                    <?php echo $currentPoints - $pointsRedeemed + $pointsEarned; ?> pts
                </span>
            </div>
        </div>
        <?php endif; ?>

        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:2rem;text-align:left;">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted-dn">Car</span>
                <span><?php echo h($booking['make'].' '.$booking['model']); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted-dn">Booking ID</span>
                <span>#<?php echo (int)$booking_id; ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted-dn">Pickup</span>
                <span><?php echo date('d M Y, H:i', strtotime($booking['start_time'])); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted-dn">Return</span>
                <span><?php echo date('d M Y, H:i', strtotime($booking['end_time'])); ?></span>
            </div>
            <?php if ($discountApplied > 0): ?>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted-dn">Points discount</span>
                <span style="color:#34a853;">−S$ <?php echo number_format($discountApplied, 2); ?></span>
            </div>
            <?php endif; ?>
            <hr style="border-color:var(--border);">
            <div class="d-flex justify-content-between">
                <span style="font-weight:600;">Amount Paid</span>
                <span style="font-family:'Bebas Neue',sans-serif;font-size:1.5rem;color:var(--accent);">S$ <?php echo number_format($finalAmount > 0 ? $finalAmount : $booking['total_cost'], 2); ?></span>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo BASE; ?>/my-bookings.php" class="btn btn-accent w-100 py-2">
                <i class="bi bi-calendar-check me-2"></i>View My Bookings
            </a>
            <a href="<?php echo BASE; ?>/my-points.php" class="btn btn-outline-light py-2" style="white-space:nowrap;">
                <i class="bi bi-star me-1"></i>My Points
            </a>
        </div>
    </div>

    <?php else: ?>
    <div class="row g-4" style="max-width:900px;margin:0 auto;">

        <!-- Payment Form -->
        <div class="col-lg-7">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;">

                <?php if (!empty($error)): ?>
                    <div class="alert-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
                <?php endif; ?>

                <!-- Points redemption panel -->
                <?php if ($currentPoints >= POINTS_MIN_REDEEM): ?>
                <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:var(--radius-sm);padding:1.2rem;margin-bottom:1.5rem;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span style="font-size:.88rem;font-weight:600;color:#f5d77e;">
                            <i class="bi bi-star-fill me-1"></i>Use Loyalty Points
                        </span>
                        <span style="font-size:.8rem;color:#aaa;">Balance: <?php echo number_format($currentPoints); ?> pts</span>
                    </div>
                    <p style="font-size:.78rem;color:#aaa;margin-bottom:.8rem;">Every 100 pts = S$5 off. Max discount: S$<?php echo number_format($maxPointsDiscount, 2); ?></p>
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" id="usePointsCheck" onchange="togglePoints()" style="width:16px;height:16px;accent-color:#e63946;">
                        <span style="font-size:.85rem;color:#ccc;">Apply points discount</span>
                    </label>
                    <div id="pointsSliderWrap" style="display:none;margin-top:.8rem;">
                        <div class="d-flex justify-content-between" style="font-size:.78rem;color:#aaa;margin-bottom:4px;">
                            <span>Units (1 unit = 100 pts = S$5)</span>
                            <span id="unitsLabel">0</span>
                        </div>
                        <input type="range" id="pointsSlider" min="0" max="<?php echo $maxRedeemableUnits; ?>" value="0" step="1" style="width:100%;" oninput="updatePointsDiscount()">
                        <div class="d-flex justify-content-between mt-1" style="font-size:.8rem;">
                            <span style="color:#aaa;">Discount: <span id="discountDisplay" style="color:#34a853;">S$0.00</span></span>
                            <span style="color:#aaa;">You pay: <span id="finalDisplay" style="color:#f5d77e;font-weight:600;">S$<?php echo number_format($booking['total_cost'], 2); ?></span></span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="background:var(--bg-raised);border-radius:var(--radius-sm);padding:.8rem 1rem;margin-bottom:1.5rem;font-size:.82rem;color:var(--text-muted);">
                    <i class="bi bi-star me-1" style="color:#f5d77e;"></i>
                    You have <strong><?php echo $currentPoints; ?> pts</strong> — earn <?php echo POINTS_PER_DOLLAR; ?> pt per S$1 spent.
                    Need <?php echo POINTS_MIN_REDEEM; ?> pts to redeem.
                    <a href="<?php echo BASE; ?>/my-points.php" style="color:#e63946;">View points →</a>
                </div>
                <?php endif; ?>

                <!-- Accepted Cards -->
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span class="text-muted-dn" style="font-size:.82rem;">Accepted:</span>
                    <span class="card-chip" data-type="visa">VISA</span>
                    <span class="card-chip" data-type="mastercard">MC</span>
                    <span class="card-chip" data-type="amex">AMEX</span>
                </div>

                <form method="POST" id="paymentForm" novalidate>
                    <input type="hidden" name="use_points" id="usePointsHidden" value="">
                    <input type="hidden" name="use_points_units" id="usePointsUnits" value="0">

                    <!-- Card Preview -->
                    <div class="credit-card-preview mb-4" id="cardPreview">
                        <div class="card-preview-top">
                            <div class="card-chip-icon"></div>
                            <div id="previewCardType" class="card-type-label">CARD</div>
                        </div>
                        <div id="previewNumber" class="card-number-preview">•••• •••• •••• ••••</div>
                        <div class="card-preview-bottom">
                            <div>
                                <div style="font-size:.6rem;opacity:.7;letter-spacing:.1em;">CARD HOLDER</div>
                                <div id="previewName" style="font-size:.85rem;letter-spacing:.05em;">FULL NAME</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:.6rem;opacity:.7;letter-spacing:.1em;">EXPIRES</div>
                                <div id="previewExpiry" style="font-size:.85rem;">MM/YY</div>
                            </div>
                        </div>
                    </div>

                    <!-- Cardholder Name -->
                    <div class="mb-3">
                        <label class="form-label" for="card_name" style="color:var(--text-muted);font-size:.85rem;letter-spacing:.04em;">Cardholder Name</label>
                        <input type="text" class="form-control pay-input" id="card_name" name="card_name"
                            placeholder="As shown on card"
                            value="<?php echo h($_POST['card_name'] ?? ''); ?>"
                            autocomplete="cc-name" required>
                    </div>

                    <!-- Card Number -->
                    <div class="mb-3">
                        <label class="form-label" for="card_number" style="color:var(--text-muted);font-size:.85rem;letter-spacing:.04em;">Card Number</label>
                        <div style="position:relative;">
                            <input type="text" class="form-control pay-input" id="card_number" name="card_number"
                                placeholder="1234 5678 9012 3456"
                                maxlength="19" autocomplete="cc-number" required
                                value="<?php echo h($_POST['card_number'] ?? ''); ?>">
                            <span id="cardTypeIcon" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:.75rem;font-weight:700;color:var(--text-muted);letter-spacing:.05em;"></span>
                        </div>
                    </div>

                    <!-- Expiry + CVV -->
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label" for="card_expiry" style="color:var(--text-muted);font-size:.85rem;letter-spacing:.04em;">Expiry Date</label>
                            <input type="text" class="form-control pay-input" id="card_expiry" name="card_expiry"
                                placeholder="MM/YY" maxlength="5" autocomplete="cc-exp" required
                                value="<?php echo h($_POST['card_expiry'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;letter-spacing:.04em;">CVV</label>
                            <div style="position:relative;">
                                <input type="password" class="form-control pay-input" id="card_cvv" name="card_cvv"
                                    placeholder="•••" maxlength="4" autocomplete="cc-csc" required
                                    value="<?php echo h($_POST['card_cvv'] ?? ''); ?>">
                                <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.9rem;">
                                    <i class="bi bi-question-circle" title="3 digits on back of card"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent w-100 py-2" id="payBtn" style="font-size:1rem;">
                        <i class="bi bi-lock-fill me-2"></i>Pay S$ <span id="payBtnAmount"><?php echo number_format($booking['total_cost'], 2); ?></span>
                    </button>

                    <p class="text-center text-muted-dn mt-3" style="font-size:.78rem;">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        Your payment details are encrypted and secure. This is a simulated payment for demonstration purposes.
                    </p>
                </form>
            </div>
        </div>

        <!-- Booking Summary -->
        <div class="col-lg-5">
            <div class="booking-summary-card">
                <h2 style="font-family:'Bebas Neue',sans-serif;font-size:1.4rem;margin-bottom:1.2rem;">Order Summary</h2>

                <div style="background:var(--bg-raised);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1rem;">
                    <div style="font-weight:600;margin-bottom:.3rem;"><?php echo h($booking['make'].' '.$booking['model']); ?></div>
                    <div class="car-plate"><?php echo h($booking['plate_no']); ?></div>
                </div>

                <div class="price-breakdown">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Pickup</span>
                        <span><?php echo date('d M, H:i', strtotime($booking['start_time'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Return</span>
                        <span><?php echo date('d M, H:i', strtotime($booking['end_time'])); ?></span>
                    </div>
                    <?php
                        $hours = (strtotime($booking['end_time']) - strtotime($booking['start_time'])) / 3600;
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Duration</span>
                        <span><?php echo number_format($hours, 1); ?> hrs</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Rate</span>
                        <span>S$ <?php echo number_format($booking['price_per_hr'], 2); ?>/hr</span>
                    </div>
                </div>

                <hr style="border-color:var(--border);margin:1rem 0;">

                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-weight:600;">Total Due</span>
                    <div class="price-total">S$ <?php echo number_format($booking['total_cost'], 2); ?></div>
                </div>

                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);">
                    <div class="d-flex align-items-center gap-2 text-muted-dn" style="font-size:.78rem;">
                        <i class="bi bi-shield-check" style="color:#34a853;"></i>
                        Booking ID #<?php echo (int)$booking_id; ?>
                    </div>
                </div>
            </div>

            <a href="<?php echo BASE; ?>/my-bookings.php" class="btn btn-outline-light w-100 mt-3">
                Cancel &amp; Go Back
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.pay-input {
    background: var(--bg-raised);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: var(--radius-sm);
    padding: .7rem 1rem;
    font-size: .95rem;
    transition: border-color .25s, box-shadow .25s;
}
.pay-input:focus {
    border-color: var(--border-acc);
    box-shadow: 0 0 0 3px rgba(230,57,70,0.12);
    background: var(--bg-raised);
    color: var(--text);
    outline: none;
}
.pay-input.invalid { border-color: #f94144; }
.pay-input.valid   { border-color: #34a853; }

.card-chip {
    display: inline-block;
    padding: .2rem .6rem;
    border-radius: 4px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .06em;
    background: var(--bg-raised);
    border: 1px solid var(--border);
    color: var(--text-muted);
}

/* Credit card preview */
.credit-card-preview {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 16px;
    padding: 1.5rem;
    color: #fff;
    font-family: 'Courier New', monospace;
    position: relative;
    overflow: hidden;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.credit-card-preview::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
}
.credit-card-preview::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -20px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
}
.card-preview-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-chip-icon {
    width: 36px; height: 28px;
    background: linear-gradient(135deg, #d4af37, #f5d77e);
    border-radius: 5px;
    position: relative;
}
.card-chip-icon::after {
    content: '';
    position: absolute;
    top: 50%; left: 0; right: 0;
    height: 1px;
    background: rgba(0,0,0,0.2);
    transform: translateY(-50%);
}
.card-type-label {
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .1em;
    opacity: .9;
}
.card-number-preview {
    font-size: 1.2rem;
    letter-spacing: .15em;
    margin: .8rem 0;
    opacity: .9;
}
.card-preview-bottom {
    display: flex;
    justify-content: space-between;
    font-size: .8rem;
    opacity: .85;
}
</style>

<script>
const BOOKING_TOTAL    = <?php echo (float)$booking['total_cost']; ?>;
const POINTS_PER_UNIT  = <?php echo POINTS_REDEEM_UNIT; ?>;
const DOLLARS_PER_UNIT = <?php echo POINTS_REDEEM_VALUE; ?>;
const PTS_PER_DOLLAR   = <?php echo POINTS_PER_DOLLAR; ?>;

function togglePoints() {
    const checked = document.getElementById('usePointsCheck').checked;
    document.getElementById('pointsSliderWrap').style.display = checked ? 'block' : 'none';
    document.getElementById('usePointsHidden').value = checked ? '1' : '';
    if (!checked) { document.getElementById('pointsSlider').value = 0; updatePointsDiscount(); }
}

function updatePointsDiscount() {
    const units    = parseInt(document.getElementById('pointsSlider').value);
    const discount = Math.min(units * DOLLARS_PER_UNIT, BOOKING_TOTAL);
    const final_   = Math.max(0, BOOKING_TOTAL - discount);
    document.getElementById('unitsLabel').textContent    = units + ' unit' + (units!==1?'s':'') + ' (' + (units*POINTS_PER_UNIT) + ' pts)';
    document.getElementById('discountDisplay').textContent = 'S$' + discount.toFixed(2);
    document.getElementById('finalDisplay').textContent  = 'S$' + final_.toFixed(2);
    document.getElementById('payBtnAmount').textContent  = final_.toFixed(2);
    document.getElementById('usePointsUnits').value      = units;
}

// Format card number with spaces
document.getElementById('card_number').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '').substring(0, 16);
    let formatted = v.replace(/(.{4})/g, '$1 ').trim();
    e.target.value = formatted;

    // Update preview
    let padded = v.padEnd(16, '•');
    let preview = padded.replace(/(.{4})/g, '$1 ').trim();
    document.getElementById('previewNumber').textContent = preview;

    // Detect card type
    let type = 'CARD';
    if (/^4/.test(v))           type = 'VISA';
    else if (/^5[1-5]/.test(v)) type = 'MASTERCARD';
    else if (/^3[47]/.test(v))  type = 'AMEX';
    else if (/^6/.test(v))      type = 'DISCOVER';

    document.getElementById('previewCardType').textContent = type;
    document.getElementById('cardTypeIcon').textContent = type !== 'CARD' ? type : '';

    // Validation style
    e.target.classList.toggle('valid',   v.length >= 13);
    e.target.classList.toggle('invalid', v.length > 0 && v.length < 13);
});

// Format expiry MM/YY
document.getElementById('card_expiry').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2);
    e.target.value = v;
    document.getElementById('previewExpiry').textContent = v || 'MM/YY';

    let valid = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(v);
    e.target.classList.toggle('valid',   valid);
    e.target.classList.toggle('invalid', v.length > 0 && !valid);
});

// CVV — numbers only
document.getElementById('card_cvv').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    let v = e.target.value;
    e.target.classList.toggle('valid',   v.length >= 3);
    e.target.classList.toggle('invalid', v.length > 0 && v.length < 3);
});

// Name — update preview
document.getElementById('card_name').addEventListener('input', function(e) {
    let v = e.target.value.toUpperCase() || 'FULL NAME';
    document.getElementById('previewName').textContent = v;
    e.target.classList.toggle('valid', e.target.value.trim().length >= 2);
});

// Simulate processing on submit
document.getElementById('paymentForm').addEventListener('submit', function() {
    const btn = document.getElementById('payBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    btn.disabled = true;
});
</script>

</main>
<?php require_once 'includes/footer.php'; ?>