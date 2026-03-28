<?php
$pageTitle = 'Book a Car';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';
require_once 'includes/config.php';

define('GMAPS_KEY', $_ENV['GMAPS_KEY'] ?? '');

$car_id = (int)($_GET['car_id'] ?? 0);
$error  = '';

// Fetch car
$stmt = $conn->prepare("SELECT * FROM cars WHERE car_id = ? AND is_available = 1");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$car) {
    header("Location: " . BASE . "/cars.php");
    exit();
}

// ── Server-side POST validation ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time']  ?? '';
    $total_cost = (float)($_POST['total_cost'] ?? 0);
    $referral_code = trim($_POST['referral_code'] ?? '');

    $mid  = $_SESSION['member_id'];
    $discount_applied = false;
    $referrer_id = null;

    if (empty($start_time) || empty($end_time)) {
        $error = 'Please select both start and end times.';
    } else {
        $start = new DateTime($start_time);
        $end   = new DateTime($end_time);
        $now   = new DateTime();
        // Minimum allowed start = now + 15 mins, snapped to next 15-min boundary
        $minStart = new DateTime();
        $minStart->modify('+15 minutes');
        $minMins = (int)$minStart->format('i');
        $snapMins = (int)(ceil($minMins / 15) * 15);
        if ($snapMins >= 60) {
            $minStart->modify('+1 hour');
            $minStart->setTime((int)$minStart->format('H'), 0, 0);
        } else {
            $minStart->setTime((int)$minStart->format('H'), $snapMins, 0);
        }

        // Snap submitted times to nearest 15 mins
        $sm = (int)$start->format('i');
        $snappedSm = (int)(round($sm / 15) * 15);
        if ($snappedSm >= 60) { $start->modify('+1 hour'); $start->setTime((int)$start->format('H'), 0, 0); }
        else { $start->setTime((int)$start->format('H'), $snappedSm, 0); }

        $em = (int)$end->format('i');
        $snappedEm = (int)(round($em / 15) * 15);
        if ($snappedEm >= 60) { $end->modify('+1 hour'); $end->setTime((int)$end->format('H'), 0, 0); }
        else { $end->setTime((int)$end->format('H'), $snappedEm, 0); }

        $start_time = $start->format('Y-m-d H:i:s');
        $end_time   = $end->format('Y-m-d H:i:s');

        if ($start < $minStart) {
            $error = 'Pickup time must be at least 15 minutes from now (next available: ' . $minStart->format('H:i') . ').';
        } elseif ($end <= $start) {
            $error = 'Return time must be after pickup time.';
        } else {
            $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
            if ($minutes < 60) {
                $error = 'Minimum rental duration is 1 hour.';
            } else {
                $chk = $conn->prepare("
                    SELECT booking_id FROM bookings
                    WHERE car_id = ? AND status != 'cancelled'
                    AND NOT (end_time <= ? OR start_time >= ?)
                ");
                $chk->bind_param("iss", $car_id, $start_time, $end_time);
                $chk->execute();
                $chk->store_result();

                if ($chk->num_rows > 0) {
                    $error = 'This car is already booked for that slot. Please choose different times.';
                } else {
                    $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                    $cost  = round($hours * $car['price_per_hr'], 2);
                    $mid   = $_SESSION['member_id'];

                    // Validate referral code if provided
                    if (!empty($referral_code)) {
                        // First, check if user has already used ANY discount code before
                        $usedChk = $conn->prepare("SELECT discount_used FROM referral_records WHERE referred_user_id = ? AND discount_used = TRUE");
                        $usedChk->bind_param("i", $mid);
                        $usedChk->execute();
                        $usedChk->store_result();
                        $hasUsedDiscount = $usedChk->num_rows > 0;
                        $usedChk->close();

                        if ($hasUsedDiscount) {
                            $error = 'You have already used a referral code for a previous booking.';
                        } else {
                            // Check if the code is valid
                            $refChk = $conn->prepare("SELECT member_id FROM members WHERE referral_code = ?");
                            $refChk->bind_param("s", $referral_code);
                            $refChk->execute();
                            $result = $refChk->get_result()->fetch_assoc();
                            $refChk->close();

                            if ($result && $result['member_id'] != $mid) {
                                $referrer_id = $result['member_id'];
                                $discountPercent = 15;
                                $discountMultiplier = 1 - ($discountPercent / 100);
                                $cost = round($cost * $discountMultiplier, 2);
                                $discount_applied = true;
                            } elseif ($result && $result['member_id'] == $mid) {
                                $error = 'You cannot use your own referral code.';
                            } else {
                                $error = 'Invalid referral code.';
                            }
                        }
                    }
                    
                    if (!$error)
                    $ins = $conn->prepare("
                        INSERT INTO bookings (member_id, car_id, start_time, end_time, total_cost, status)
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins->bind_param("iissd", $mid, $car_id, $start_time, $end_time, $cost);
                    if ($ins->execute()) {
                        $bid = $ins->insert_id;
                        $ins->close();

                        // Insert referral usage if discount applied
                            if ($discount_applied && $referrer_id) {
                                $stmt2 = $conn->prepare("
                                    INSERT INTO referral_records (referrer_user_id, referred_user_id, booking_id, discount_used)
                                    VALUES (?, ?, ?, FALSE)
                                ");
                                $stmt2->bind_param("iii", $referrer_id, $mid, $bid);
                                $stmt2->execute();
                                $stmt2->close();
                            }
                        
                        // Redirect to payment page
                        header("Location: " . BASE . "/payment.php?booking_id=" . $bid);
                        exit();
                    } else {
                        $error = 'Booking failed. Please try again.';
                    }
                    $ins->close();
                }
                $chk->close();
            }
        }
    }
}

// ── Fetch existing bookings for calendar ─────────────────────
$bkStmt = $conn->prepare("
    SELECT start_time, end_time FROM bookings
    WHERE car_id = ? AND status != 'cancelled' AND end_time >= NOW()
    ORDER BY start_time ASC
");
$bkStmt->bind_param("i", $car_id);
$bkStmt->execute();
$existingBookings = $bkStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bkStmt->close();

$bookingsJson = json_encode(array_map(fn($b) => [
    'start' => $b['start_time'],
    'end'   => $b['end_time'],
], $existingBookings));

$categoryIcons = ['Economy'=>'🚗','Comfort'=>'🚙','SUV'=>'🚐','Premium'=>'🏎️'];
$hasCoords = !empty($car['lat']) && !empty($car['lng']);
$pickupLat = $hasCoords ? (float)$car['lat'] : null;
$pickupLng = $hasCoords ? (float)$car['lng'] : null;

require_once 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <div class="section-eyebrow">Booking</div>
        <h1 class="section-title">Reserve Your Car</h1>
    </div>
</section>

<div class="container pb-5">
    <?php if (!empty($error)): ?>
        <div class="alert-error mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── LEFT: Calendar + Booking Form ─────────────── -->
        <div class="col-lg-7">

            <!-- Availability Calendar -->
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;margin:0;">
                        <i class="bi bi-calendar3 me-2" style="color:#e63946;"></i>Availability Calendar
                    </h5>
                    <button type="button" class="btn btn-sm btn-accent" onclick="jumpToNextSlot()" style="font-size:.8rem;">
                        <i class="bi bi-skip-forward-fill me-1"></i>Next Available Slot
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="prevMonth()">&#8592;</button>
                    <span id="calMonthLabel" style="font-weight:600;font-size:.95rem;"></span>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="nextMonth()">&#8594;</button>
                </div>
                <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;"></div>
                <div class="d-flex gap-3 mt-3 flex-wrap" style="font-size:.78rem;color:var(--text-muted);">
                    <span><span style="display:inline-block;width:10px;height:10px;background:#3a1515;border:1px solid #e63946;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Fully booked</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#2a1f00;border:1px solid #f0a030;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Partially booked</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#34a85322;border:1px solid #34a853;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Available</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#1a6ef5;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Your selection</span>
                </div>
            </div>

            <!-- Booking Form -->
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;">
                <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.6rem;margin-bottom:.4rem;">Select Date &amp; Time</h4>
                <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Times are in 15-minute slots &middot; Must book at least 15 mins ahead &middot; Minimum rental: 30 mins
                </p>

                <div id="conflictAlert" class="alert-error mb-3" style="display:none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This slot overlaps an existing booking. Use <strong>Next Available Slot</strong> to find a free time.
                </div>

                <form method="POST" action="<?php echo BASE; ?>/book.php?car_id=<?php echo (int)$car_id; ?>"
                      onsubmit="return validateBookingForm();" novalidate>
                    <input type="hidden" id="total_cost" name="total_cost" value="0">
                    <input type="hidden" id="price_per_hr" value="<?php echo (float)$car['price_per_hr']; ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="start_time" style="color:var(--text-muted);font-size:.85rem;">Pickup Date &amp; Time</label>
                            <!-- min set by JS to enforce 15-min-ahead rule -->
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time"
                                step="900"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);padding:.7rem 1rem;"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_time" style="color:var(--text-muted);font-size:.85rem;">Return Date &amp; Time</label>
                            <input type="datetime-local" class="form-control" id="end_time" name="end_time"
                                step="900"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);padding:.7rem 1rem;"
                                required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="referral_code" style="color:var(--text-muted);font-size:.85rem;">
                            Referral/Discount Code (Optional)
                        </label>
                        <input type="text" class="form-control" id="referral_code" name="referral_code"
                            style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);padding:.7rem 1rem;"
                            placeholder="Enter code for discount">
                        <small id="discount_feedback" style="font-size:.78rem;margin-top:.3rem;display:block;"></small>
                    </div>

                    <div style="background:var(--bg-raised);border-radius:var(--radius-sm);padding:1.2rem;margin-bottom:1.5rem;">
                        <div class="price-breakdown d-flex justify-content-between mb-2">
                            <span>Duration</span><span id="hours_output">–</span>
                        </div>
                        <div class="price-breakdown d-flex justify-content-between mb-2">
                            <span>Rate</span><span>S$ <?php echo number_format($car['price_per_hr'], 2); ?>/hr</span>
                        </div>
                        <div class="price-breakdown d-flex justify-content-between mb-2" id="discount_row" style="display:none !important;">
                            <span style="color:var(--accent);">Discount</span>
                            <span style="color:var(--accent);" id="discount_amount">- S$ 0.00</span>
                        </div>
                        <hr style="border-color:var(--border);margin:.8rem 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-weight:600;">Estimated Total</span>
                            <div class="price-total" id="price_output">S$ 0.00</div>
                        </div>
                    </div>

                    <button type="submit" id="confirmBtn" class="btn btn-accent w-100 py-2" style="font-size:1rem;">
                        <i class="bi bi-calendar-check me-2"></i>Confirm Booking
                    </button>
                </form>
            </div>
        </div>

        <!-- ── RIGHT: Car Summary + Google Map ───────────── -->
        <div class="col-lg-5">
            <!-- Car summary — normal flow, no sticky -->
            <div class="booking-summary-card mb-4">
                <div class="car-card-img mb-3" style="border-radius:var(--radius-sm);height:170px;">
                    <?php if (!empty($car['image_url'])): ?>
                        <img src="<?php echo h($car['image_url']); ?>" alt="Car"
                            style="width:100%;height:170px;object-fit:cover;border-radius:var(--radius-sm);">
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:170px;font-size:5rem;background:var(--bg-raised);border-radius:var(--radius-sm);">
                            <?php echo $categoryIcons[$car['category']] ?? '🚗'; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="car-badge badge-<?php echo strtolower(h($car['category'])); ?> mb-2"><?php echo h($car['category']); ?></div>
                <div class="car-name"><?php echo h($car['make'].' '.$car['model']); ?></div>
                <div class="car-plate mb-3"><?php echo h($car['plate_no']); ?></div>
                <div class="car-meta mb-3">
                    <span><i class="bi bi-people-fill"></i> <?php echo (int)$car['seats']; ?> seats</span>
                    <span><i class="bi bi-geo-alt-fill"></i> <?php echo h($car['location']); ?></span>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:1rem;">
                    <div class="price-breakdown d-flex justify-content-between">
                        <span>Hourly Rate</span>
                        <span style="color:var(--text);font-weight:600;">S$ <?php echo number_format($car['price_per_hr'], 2); ?>/hr</span>
                    </div>
                </div>
            </div>

            <!-- Pickup Map — sits naturally below car card, no overlap -->
            <?php if ($hasCoords): ?>
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
                <div style="padding:.8rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
                        <i class="bi bi-pin-map-fill me-2" style="color:#e63946;"></i>Pickup Location
                    </span>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $pickupLat; ?>,<?php echo $pickupLng; ?>"
                       target="_blank" rel="noopener"
                       style="font-size:.78rem;color:#e63946;text-decoration:none;">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Get Directions
                    </a>
                </div>
                <!-- gestureHandling:'cooperative' stops map hijacking page scroll -->
                <div id="pickupMap" style="width:100%;height:260px;"></div>
                <div style="padding:.75rem 1.2rem;border-top:1px solid var(--border);display:flex;align-items:center;gap:.6rem;">
                    <i class="bi bi-geo-alt-fill" style="color:#e63946;font-size:1rem;"></i>
                    <span style="font-size:.88rem;font-weight:500;"><?php echo h($car['location']); ?></span>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($car['location'] . ', Singapore'); ?>"
                       target="_blank" rel="noopener"
                       class="btn btn-sm ms-auto"
                       style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);font-size:.78rem;">
                        Open in Maps
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;text-align:center;">
                <i class="bi bi-geo-alt" style="font-size:2rem;color:var(--text-muted);"></i>
                <p class="text-muted-dn mt-2 mb-1" style="font-size:.88rem;">
                    Pickup: <strong><?php echo h($car['location']); ?></strong>
                </p>
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($car['location'] . ', Singapore'); ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-light mt-1" style="font-size:.8rem;">
                    Search on Google Maps
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Calculate price based on duration
function calculatePrice() {
    const start = document.getElementById('start_time').value;
    const end = document.getElementById('end_time').value;
    const pricePerHr = parseFloat(document.getElementById('price_per_hr').value);

    if (start && end) {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const hours = (endDate - startDate) / (1000 * 60 * 60);

        if (hours > 0) {
            document.getElementById('hours_output').textContent = hours.toFixed(1) + ' hrs';
            document.getElementById('hours_output').dataset.hours = hours;
            
            // Calculate discount if a code has been entered
            const code = document.getElementById('referral_code').value.trim();
            if (code) {
                applyReferralDiscount();
            } else {
                // Show base price without discount
                const baseCost = hours * pricePerHr;
                document.getElementById('price_output').textContent = `S$ ${baseCost.toFixed(2)}`;
                document.getElementById('total_cost').value = baseCost.toFixed(2);
            }
        } else {
            document.getElementById('hours_output').textContent = '–';
            document.getElementById('price_output').textContent = 'S$ 0.00';
            document.getElementById('total_cost').value = '0';
        }
    }
}

// Validate and apply referral discount
function applyReferralDiscount() {
    const code = document.getElementById('referral_code').value.trim();
    const hoursEl = document.getElementById('hours_output');
    // Try to get hours from dataset, otherwise parse from text content
    let hours = parseFloat(hoursEl.dataset.hours || 0);
    if (!hours && hoursEl.textContent) {
        const match = hoursEl.textContent.match(/([\d.]+)\s*(hr|min)/);
        if (match) {
            hours = match[2] === 'min' ? parseFloat(match[1]) / 60 : parseFloat(match[1]);
        }
    }
    const pricePerHr = parseFloat(document.getElementById('price_per_hr').value);
    const feedbackEl = document.getElementById('discount_feedback');
    const discountRow = document.getElementById('discount_row');

    if (!hours || !pricePerHr) {
        // Can't calculate yet, just show base price
        return;
    }

    const baseCost = hours * pricePerHr;

    if (!code) {
        // No code entered, show base price
        document.getElementById('price_output').textContent = `S$ ${baseCost.toFixed(2)}`;
        document.getElementById('total_cost').value = baseCost.toFixed(2);
        feedbackEl.textContent = '';
        feedbackEl.style.color = '';
        discountRow.style.display = 'none';
        return;
    }

    // Validate code via AJAX
    fetch('<?php echo BASE; ?>/validate_discount.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'validate_discount',
            referral_code: code,
            hours: hours,
            price_per_hr: pricePerHr
        })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('price_output').textContent = `S$ ${data.discountedCost.toFixed(2)}`;
        document.getElementById('total_cost').value = data.discountedCost.toFixed(2);
        
        if (data.valid) {
            feedbackEl.textContent = '✓ ' + data.message;
            feedbackEl.style.color = '#34a853';
            discountRow.style.display = 'flex';
            const discountAmount = baseCost - data.discountedCost;
            document.getElementById('discount_amount').textContent = `- S$ ${discountAmount.toFixed(2)}`;
        } else {
            feedbackEl.textContent = data.message;
            feedbackEl.style.color = '#f94144';
            discountRow.style.display = 'none';
        }
    })
    .catch(err => {
        console.error('Discount validation error:', err);
    });
}

// Event listeners
document.getElementById('start_time').addEventListener('change', calculatePrice);
document.getElementById('end_time').addEventListener('change', calculatePrice);
document.getElementById('referral_code').addEventListener('blur', applyReferralDiscount);
document.getElementById('referral_code').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        applyReferralDiscount();
    }
});
</script>

<!-- ── JS ─────────────────────────────────────────────────── -->
<script>
const BOOKED_RANGES = <?php echo $bookingsJson; ?>.map(b => ({
    start: new Date(b.start.replace(' ','T')),
    end  : new Date(b.end.replace(' ','T'))
}));
const PRICE_PER_HR = <?php echo (float)$car['price_per_hr']; ?>;
const MIN_MINS     = 60;   // minimum booking duration
const STEP_MINS    = 15;   // slot size
const BUFFER_MINS  = 15;   // must book at least this far ahead

// ── Compute the earliest bookable start time ─────────────────
// Logic: now + 15 min buffer, then round UP to next 15-min slot
// e.g. now=12:37 → 12:37+15=12:52 → round up to 13:00
function getMinStart() {
    const now = new Date();
    const withBuffer = new Date(now.getTime() + BUFFER_MINS * 60000);
    const ms = STEP_MINS * 60000;
    return new Date(Math.ceil(withBuffer.getTime() / ms) * ms);
}

// ── Format a Date to datetime-local input value ──────────────
function toInputVal(dt) {
    const p = n => String(n).padStart(2, '0');
    return `${dt.getFullYear()}-${p(dt.getMonth()+1)}-${p(dt.getDate())}T${p(dt.getHours())}:${p(dt.getMinutes())}`;
}

// ── Snap a datetime-local string to nearest 15-min boundary ──
function snapTo15(val) {
    if (!val) return val;
    const dt = new Date(val);
    const ms = STEP_MINS * 60000;
    // round to nearest 15, not ceil — user may pick 12:45 exactly
    const snapped = new Date(Math.round(dt.getTime() / ms) * ms);
    return toInputVal(snapped);
}

// ── Set min attribute on start input dynamically ─────────────
function refreshMinStart() {
    const minStart = getMinStart();
    document.getElementById('start_time').min = toInputVal(minStart);
}

// ── Overlaps check ───────────────────────────────────────────
function overlaps(s, e) {
    return BOOKED_RANGES.some(r => s < r.end && e > r.start);
}

// ── Calendar ─────────────────────────────────────────────────
const now = new Date();
let calYear = now.getFullYear(), calMonth = now.getMonth();

function dayStatus(y, m, d) {
    const s = new Date(y,m,d,0,0), e = new Date(y,m,d,23,59);
    const hits = BOOKED_RANGES.filter(r => r.start < e && r.end > s);
    if (!hits.length) return 'free';
    const booked = hits.reduce((acc, r) =>
        acc + (Math.min(r.end, e) - Math.max(r.start, s)), 0);
    return booked >= 23 * 3600 * 1000 ? 'full' : 'partial';
}

function renderCalendar() {
    const MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    document.getElementById('calMonthLabel').textContent = MONTHS[calMonth] + ' ' + calYear;
    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';

    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
        const el = document.createElement('div');
        el.textContent = d;
        el.style.cssText = 'font-size:10px;color:var(--text-muted);text-align:center;padding:4px 0;text-transform:uppercase;letter-spacing:.04em;';
        grid.appendChild(el);
    });

    const firstDow  = new Date(calYear, calMonth, 1).getDay();
    const daysInMon = new Date(calYear, calMonth+1, 0).getDate();
    const todayFloor = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const minStart   = getMinStart();

    const sv = document.getElementById('start_time').value;
    const ev = document.getElementById('end_time').value;
    const selS = sv ? new Date(sv) : null;
    const selE = ev ? new Date(ev) : null;

    for (let i = 0; i < firstDow; i++) grid.appendChild(document.createElement('div'));

    for (let d = 1; d <= daysInMon; d++) {
        const el     = document.createElement('div');
        const dt     = new Date(calYear, calMonth, d);
        const isPast = dt < todayFloor;
        const isToday = dt.toDateString() === todayFloor.toDateString();
        const status = dayStatus(calYear, calMonth, d);

        el.textContent = d;
        el.style.cssText = 'aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:12px;border-radius:6px;position:relative;transition:background .1s;';

        if (isToday) el.style.outline = '1px solid var(--text-muted)';

        const inSel = selS && selE
            && dt >= new Date(selS.getFullYear(), selS.getMonth(), selS.getDate())
            && dt <= new Date(selE.getFullYear(), selE.getMonth(), selE.getDate());

        if (isPast) {
            el.style.color = 'var(--text-muted)'; el.style.opacity = '.35';
        } else if (inSel) {
            el.style.background = '#1a6ef5'; el.style.color = '#fff'; el.style.cursor = 'pointer';
        } else if (status === 'full') {
            el.style.background = '#3a1515'; el.style.color = '#e63946';
            el.style.cursor = 'not-allowed'; el.title = 'Fully booked';
        } else if (status === 'partial') {
            el.style.background = '#2a1f00'; el.style.color = '#f0a030';
            el.style.cursor = 'pointer'; el.title = 'Some slots available — click to pick';
        } else {
            el.style.color = 'var(--text)'; el.style.cursor = 'pointer';
            el.onmouseenter = () => { if (!inSel) el.style.background = 'var(--bg-raised)'; };
            el.onmouseleave = () => { if (!inSel) el.style.background = ''; };
        }

        if (!isPast && status !== 'full') {
            el.onclick = () => {
                const p = n => String(n).padStart(2,'0');
                const base = `${calYear}-${p(calMonth+1)}-${p(d)}`;

                // If clicking today, use minStart time; otherwise default to 09:00
                let startHH = '09', startMM = '00';
                if (isToday) {
                    startHH = p(minStart.getHours());
                    startMM = p(minStart.getMinutes());
                }
                const startVal = `${base}T${startHH}:${startMM}`;
                const startDt  = new Date(startVal);
                const endDt    = new Date(startDt.getTime() + MIN_MINS * 60000);

                document.getElementById('start_time').value = startVal;
                document.getElementById('end_time').value   = toInputVal(endDt);
                onTimeChange();
                renderCalendar();
            };
        }
        grid.appendChild(el);
    }
}

function prevMonth() {
    if (calMonth === 0) { calMonth = 11; calYear--; } else calMonth--;
    renderCalendar();
}
function nextMonth() {
    if (calMonth === 11) { calMonth = 0; calYear++; } else calMonth++;
    renderCalendar();
}

// ── Next Available Slot ──────────────────────────────────────
function jumpToNextSlot() {
    let candidate = getMinStart(); // already rounded to next 15-min slot
    const limit   = new Date(Date.now() + 30 * 24 * 3600 * 1000);
    while (candidate < limit) {
        const end = new Date(candidate.getTime() + MIN_MINS * 60000);
        if (!overlaps(candidate, end)) {
            document.getElementById('start_time').value = toInputVal(candidate);
            document.getElementById('end_time').value   = toInputVal(end);
            calYear  = candidate.getFullYear();
            calMonth = candidate.getMonth();
            onTimeChange();
            renderCalendar();
            document.getElementById('start_time').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        candidate = new Date(candidate.getTime() + STEP_MINS * 60000);
    }
    alert('No available slots found in the next 30 days.');
}

// ── Handle input change — snap + validate ────────────────────
function onTimeChange() {
    // Snap both inputs to 15-min boundaries
    const startEl = document.getElementById('start_time');
    const endEl   = document.getElementById('end_time');
    if (startEl.value) startEl.value = snapTo15(startEl.value);
    if (endEl.value)   endEl.value   = snapTo15(endEl.value);

    const sv = startEl.value;
    const ev = endEl.value;
    const alertEl = document.getElementById('conflictAlert');
    const btn     = document.getElementById('confirmBtn');
    const hOut    = document.getElementById('hours_output');
    const pOut    = document.getElementById('price_output');

    const resetBtn = () => {
        btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Confirm Booking';
    };

    if (!sv || !ev) {
        hOut.textContent = '–'; pOut.textContent = 'S$ 0.00';
        document.getElementById('total_cost').value = 0;
        alertEl.style.display = 'none';
        btn.disabled = false; resetBtn();
        return;
    }

    const s = new Date(sv), e = new Date(ev);
    const minStart = getMinStart();
    const diffMins = (e - s) / 60000;

    // Check 15-min ahead rule
    if (s < minStart) {
        hOut.textContent = 'Too soon';
        pOut.textContent = 'S$ 0.00';
        document.getElementById('total_cost').value = 0;
        alertEl.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = `<i class="bi bi-clock me-2"></i>Earliest pickup: ${toInputVal(minStart).split('T')[1]}`;
        return;
    }

    if (e <= s) {
        hOut.textContent = 'Invalid'; pOut.textContent = 'S$ 0.00';
        alertEl.style.display = 'none'; btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Return must be after pickup';
        return;
    }

    if (diffMins < MIN_MINS) {
        hOut.textContent = diffMins + ' mins (too short)'; pOut.textContent = 'S$ 0.00';
        document.getElementById('total_cost').value = 0;
        alertEl.style.display = 'none'; btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Minimum 30 minutes required';
        return;
    }

    if (overlaps(s, e)) {
        alertEl.style.display = 'block';
        hOut.textContent = '–'; pOut.textContent = 'S$ 0.00';
        document.getElementById('total_cost').value = 0;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Time Conflict — Choose Different Times';
        return;
    }

    // All good
    alertEl.style.display = 'none';
    const hrs  = diffMins / 60;
    const cost = Math.round(hrs * PRICE_PER_HR * 100) / 100;
    const hStr = hrs >= 1
        ? (Number.isInteger(hrs) ? hrs + ' hr' + (hrs > 1 ? 's' : '') : hrs.toFixed(1) + ' hrs')
        : diffMins + ' mins';

    hOut.textContent = hStr;
    pOut.textContent = 'S$ ' + cost.toFixed(2);
    document.getElementById('total_cost').value = cost;
    btn.disabled = false; resetBtn();
    renderCalendar();
}

// ── Form validation before submit ────────────────────────────
function validateBookingForm() {
    const sv = document.getElementById('start_time').value;
    const ev = document.getElementById('end_time').value;
    if (!sv || !ev) { alert('Please select both times.'); return false; }
    const s = new Date(sv), e = new Date(ev);
    if (s < getMinStart()) { alert('Pickup must be at least 15 minutes from now.'); return false; }
    if ((e - s) / 60000 < MIN_MINS) { alert('Minimum rental is 30 minutes.'); return false; }
    if (overlaps(s, e)) { alert('This slot is already booked. Please choose different times.'); return false; }
    return true;
}

// ── Wire up inputs ────────────────────────────────────────────
document.getElementById('start_time').addEventListener('change', onTimeChange);
document.getElementById('end_time').addEventListener('change', onTimeChange);
document.getElementById('start_time').addEventListener('blur', onTimeChange);
document.getElementById('end_time').addEventListener('blur', onTimeChange);

// Refresh min every minute so it stays current
refreshMinStart();
setInterval(refreshMinStart, 60000);

// Init calendar
renderCalendar();
</script>

<?php if ($hasCoords): ?>
<script>
const PICKUP_LAT   = <?php echo $pickupLat; ?>;
const PICKUP_LNG   = <?php echo $pickupLng; ?>;
const PICKUP_LABEL = <?php echo json_encode($car['location']); ?>;

function initPickupMap() {
    const pos = { lat: PICKUP_LAT, lng: PICKUP_LNG };
    const map = new google.maps.Map(document.getElementById('pickupMap'), {
        center: pos, zoom: 15,
        mapTypeControl: false,
        fullscreenControl: false,
        streetViewControl: true,
        gestureHandling: 'cooperative',
        styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }]
    });
    const marker = new google.maps.Marker({
        position: pos, map,
        title: PICKUP_LABEL,
        animation: google.maps.Animation.DROP,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 12, fillColor: '#e63946', fillOpacity: 1,
            strokeColor: '#fff', strokeWeight: 3
        }
    });
    const iw = new google.maps.InfoWindow({
        content: `<div style="font-family:sans-serif;padding:4px 0;">
            <div style="font-weight:600;font-size:13px;margin-bottom:3px;"><?php echo h($car['make'].' '.$car['model']); ?></div>
            <div style="font-size:12px;color:#555;">${PICKUP_LABEL}</div>
        </div>`
    });
    marker.addListener('click', () => iw.open(map, marker));
    iw.open(map, marker);
}
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GMAPS_KEY; ?>&callback=initPickupMap">
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>