<?php
$pageTitle = 'Book a Car';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';

// ── Replace with your real key ──────────────────────────────
define('GMAPS_KEY', 'AIzaSyDAQKvkqDCX62vTR_oyGclCDQnMsnYOkg8');
// ────────────────────────────────────────────────────────────

$car_id = (int)($_GET['car_id'] ?? 0);
$error   = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time']   ?? '';
    $total_cost = (float)($_POST['total_cost'] ?? 0);

    if (empty($start_time) || empty($end_time)) {
        $error = 'Please select both start and end times.';
    } else {
        $start = new DateTime($start_time);
        $end   = new DateTime($end_time);
        $now   = new DateTime();

        if ($start <= $now) {
            $error = 'Start time must be in the future.';
        } elseif ($end <= $start) {
            $error = 'End time must be after start time.';
        } else {
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            if ($hours < 1) {
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
                    $error = 'This car is already booked for the selected time slot. Please choose different times.';
                } else {
                    $cost = round($hours * $car['price_per_hr'], 2);
                    $mid  = $_SESSION['member_id'];

                    $ins = $conn->prepare("
                        INSERT INTO bookings (member_id, car_id, start_time, end_time, total_cost, status)
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins->bind_param("iissd", $mid, $car_id, $start_time, $end_time, $cost);
                    if ($ins->execute()) {
                        $new_booking_id = $ins->insert_id;
                        $ins->close();
                        header("Location: " . BASE . "/payment.php?booking_id=" . $new_booking_id);
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

$categoryIcons = ['Economy'=>'🚗','Comfort'=>'🚙','SUV'=>'🚐','Premium'=>'🏎️'];

// Pickup coordinates (may be null if not set yet)
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
    <?php if (!empty($success)): ?>
        <div class="alert-success mb-4">
            <i class="bi bi-check-circle me-2"></i><?php echo h($success); ?>
            <a href="<?php echo BASE; ?>/my-bookings.php">View My Bookings →</a>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-error mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── Booking Form ───────────────────────────────── -->
        <div class="col-lg-7">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;">
                <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.6rem;margin-bottom:1.5rem;">Select Date &amp; Time</h4>
                <form method="POST" action="<?php echo BASE; ?>/book.php?car_id=<?php echo (int)$car_id; ?>"
                      onsubmit="return validateBookingForm();" novalidate>
                    <input type="hidden" id="total_cost" name="total_cost" value="0">
                    <input type="hidden" id="price_per_hr" value="<?php echo (float)$car['price_per_hr']; ?>">

                    <div class="mb-4">
                        <label class="form-label" for="start_time" style="color:var(--text-muted);font-size:.85rem;">Pickup Date &amp; Time</label>
                        <input type="datetime-local" class="form-control" id="start_time" name="start_time"
                            style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);padding:.7rem 1rem;"
                            min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="end_time" style="color:var(--text-muted);font-size:.85rem;">Return Date &amp; Time</label>
                        <input type="datetime-local" class="form-control" id="end_time" name="end_time"
                            style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);padding:.7rem 1rem;"
                            min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>

                    <div style="background:var(--bg-raised);border-radius:var(--radius-sm);padding:1.2rem;margin-bottom:1.5rem;">
                        <div class="price-breakdown d-flex justify-content-between mb-2">
                            <span>Duration</span><span id="hours_output">–</span>
                        </div>
                        <div class="price-breakdown d-flex justify-content-between mb-2">
                            <span>Rate</span><span>S$ <?php echo number_format($car['price_per_hr'], 2); ?>/hr</span>
                        </div>
                        <hr style="border-color:var(--border);margin:.8rem 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-weight:600;">Estimated Total</span>
                            <div class="price-total" id="price_output">S$ 0.00</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent w-100 py-2" style="font-size:1rem;">
                        <i class="bi bi-calendar-check me-2"></i>Confirm Booking
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Car Summary + Pickup Map ──────────────────── -->
        <div class="col-lg-5">
            <!-- Car summary card -->
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

            <!-- ── Pickup Location Map ─────────────────── -->
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

            <!-- Google Maps JS -->
            <script>
            const PICKUP_LAT = <?php echo $pickupLat; ?>;
            const PICKUP_LNG = <?php echo $pickupLng; ?>;
            const PICKUP_LABEL = <?php echo json_encode($car['location']); ?>;

            function initPickupMap() {
                const pos = { lat: PICKUP_LAT, lng: PICKUP_LNG };

                const map = new google.maps.Map(document.getElementById('pickupMap'), {
                    center           : pos,
                    zoom             : 15,
                    mapTypeControl   : false,
                    fullscreenControl: false,
                    streetViewControl: true,
                    zoomControl      : true,
                    styles: [
                        { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }
                    ]
                });

                // Pickup marker
                const marker = new google.maps.Marker({
                    position : pos,
                    map      : map,
                    title    : PICKUP_LABEL,
                    animation: google.maps.Animation.DROP,
                    icon     : {
                        path        : google.maps.SymbolPath.CIRCLE,
                        scale       : 12,
                        fillColor   : '#e63946',
                        fillOpacity : 1,
                        strokeColor : '#ffffff',
                        strokeWeight: 3,
                    }
                });

                // Info popup on marker click
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="font-family:sans-serif;padding:4px 0;">
                            <div style="font-weight:600;font-size:13px;margin-bottom:3px;">
                                <?php echo h($car['make'].' '.$car['model']); ?>
                            </div>
                            <div style="font-size:12px;color:#555;">
                                <i class="bi bi-geo-alt-fill"></i> ${PICKUP_LABEL}
                            </div>
                        </div>
                    `
                });

                marker.addListener('click', () => infoWindow.open(map, marker));
                // Open by default so user immediately sees the label
                infoWindow.open(map, marker);
            }
            </script>
            <script async defer
                src="https://maps.googleapis.com/maps/api/js?key=<?php echo GMAPS_KEY; ?>&callback=initPickupMap">
            </script>

            <?php else: ?>
            <!-- Fallback if no coords stored yet -->
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

<?php require_once 'includes/footer.php'; ?>