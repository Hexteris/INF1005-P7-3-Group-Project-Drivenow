<?php
$pageTitle = 'Browse Cars';
require_once 'includes/header.php';
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';

define('GMAPS_KEY', $_ENV['GMAPS_KEY'] ?? '');

// Filter inputs
$category  = $_GET['category']  ?? '';
$location  = $_GET['location']  ?? '';
$max_price = $_GET['max_price'] ?? '';
$search    = $_GET['search']    ?? '';

// Build query safely
$where  = ["is_available = 1"];
$params = [];
$types  = '';

if (!empty($category) && in_array($category, ['Economy','Comfort','SUV','Premium'])) {
    $where[]  = "category = ?";
    $params[] = $category;
    $types   .= 's';
}
if (!empty($location)) {
    $where[]  = "location LIKE ?";
    $params[] = '%' . $location . '%';
    $types   .= 's';
}
if (!empty($max_price) && is_numeric($max_price)) {
    $where[]  = "price_per_hr <= ?";
    $params[] = (float)$max_price;
    $types   .= 'd';
}
if (!empty($search)) {
    $where[]  = "(make LIKE ? OR model LIKE ? OR plate_no LIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

$sql  = "SELECT * FROM cars WHERE " . implode(" AND ", $where) . " ORDER BY category, price_per_hr";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct locations for filter dropdown
$locResult = $conn->query("SELECT DISTINCT location FROM cars WHERE location IS NOT NULL ORDER BY location");
$locations = $locResult ? $locResult->fetch_all(MYSQLI_ASSOC) : [];

$categoryIcons = ['Economy'=>'🚗','Comfort'=>'🚙','SUV'=>'🚐','Premium'=>'🏎️'];

// Build JSON for the map (only cars that have lat/lng)
$mapCars = array_values(array_filter($cars, fn($c) => !empty($c['lat']) && !empty($c['lng'])));
$mapJson = json_encode(array_map(fn($c) => [
    'car_id'       => (int)$c['car_id'],
    'label'        => $c['make'] . ' ' . $c['model'],
    'plate'        => $c['plate_no'],
    'category'     => $c['category'],
    'location'     => $c['location'],
    'price'        => 'S$' . number_format($c['price_per_hr'], 2) . '/hr',
    'book_url'     => BASE . '/book.php?car_id=' . (int)$c['car_id'],
    'lat'          => (float)$c['lat'],
    'lng'          => (float)$c['lng'],
], $mapCars));
?>

<main id="main-content">
<section class="page-header" aria-label="Page header">
    <div class="container">
        <div class="section-eyebrow">Available Now</div>
        <h1 class="section-title">Browse Our Fleet</h1>
        <p class="text-muted-dn">Find the perfect car for your journey.</p>
    </div>
</section>

<div class="container-fluid pb-5 px-4">
    <!-- Filter Bar -->
    <form method="GET" action="<?php echo BASE; ?>/cars.php" class="filter-bar mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted-dn" for="filter-search" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Search</label>
                <input type="text" class="form-control" id="filter-search" name="search" placeholder="Make, model, plate..."
                    value="<?php echo h($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted-dn" for="filter-category" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Category</label>
                <select class="form-select" id="filter-category" name="category" aria-label="Filter by category">
                    <option value="">All Categories</option>
                    <?php foreach (['Economy','Comfort','SUV','Premium'] as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted-dn" for="filter-location" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Location</label>
                <select class="form-select" id="filter-location" name="location" aria-label="Filter by location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo h($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                            <?php echo h($loc['location']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted-dn" for="filter-maxprice" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Max $/hr</label>
                <input type="number" class="form-control" id="filter-maxprice" name="max_price" placeholder="e.g. 20"
                    value="<?php echo h($max_price); ?>" min="1" step="0.5">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-accent w-100" aria-label="Apply filters">Filter</button>
                <a href="<?php echo BASE; ?>/cars.php" class="btn btn-outline-light" aria-label="Reset all filters">Reset</a>
            </div>
        </div>
    </form>

    <!-- Split layout: cards left, map right -->
    <div class="row g-4">

        <!-- Left: Car Cards -->
        <div class="col-lg-6">
            <p class="text-muted-dn mb-3">
                <?php echo count($cars); ?> car<?php echo count($cars) !== 1 ? 's' : ''; ?> found
            </p>

            <?php if (empty($cars)): ?>
                <div class="text-center py-5">
                    <div style="font-size:4rem;">🔍</div>
                    <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.8rem;margin-top:1rem;">No Cars Found</h4>
                    <p class="text-muted-dn">Try adjusting your filters.</p>
                    <a href="<?php echo BASE; ?>/cars.php" class="btn btn-accent mt-2">Clear Filters</a>
                </div>
            <?php else: ?>
            <div class="row g-3" id="carCardGrid">
                <?php foreach ($cars as $car): ?>
                <div class="col-md-6"
                     id="card-<?php echo (int)$car['car_id']; ?>"
                     data-car-id="<?php echo (int)$car['car_id']; ?>">
                    <div class="car-card" onclick="focusMapPin(<?php echo (int)$car['car_id']; ?>)"
                         style="cursor:pointer;">
                        <div class="car-card-img">
                            <?php if (!empty($car['image_url'])): ?>
                                <img src="<?php echo BASE . '/uploads/cars/' . h($car['image_url']); ?>" alt="<?php echo h($car['make'].' '.$car['model']); ?>">
                            <?php else: ?>
                                <?php echo $categoryIcons[$car['category']] ?? '🚗'; ?>
                            <?php endif; ?>
                        </div>
                        <div class="car-card-body">
                            <div class="car-badge badge-<?php echo strtolower(h($car['category'])); ?>"><?php echo h($car['category']); ?></div>
                            <div class="car-name"><?php echo h($car['make'].' '.$car['model']); ?></div>
                            <div class="car-plate"><?php echo h($car['plate_no']); ?></div>
                            <div class="car-meta">
                                <span><i class="bi bi-people-fill"></i> <?php echo (int)$car['seats']; ?> seats</span>
                                <span><i class="bi bi-geo-alt-fill"></i> <?php echo h($car['location']); ?></span>
                            </div>
                            <div class="car-price">S$<?php echo number_format($car['price_per_hr'], 2); ?> <span>/ hour</span></div>
                        </div>
                        <div class="car-card-footer">
                            <?php if ($car['is_available']): ?>
                                <span style="color:#34a853;font-size:.82rem;"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Available</span>
                                <a href="<?php echo BASE; ?>/book.php?car_id=<?php echo (int)$car['car_id']; ?>"
                                   class="btn btn-accent btn-sm"
                                   onclick="event.stopPropagation();">Book Now</a>
                            <?php else: ?>
                                <span style="color:#fbbc05;font-size:.82rem;"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Unavailable</span>
                                <button class="btn btn-sm" style="background:var(--bg-raised);color:var(--text-muted);cursor:not-allowed;" disabled>Unavailable</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Google Map -->
        <div class="col-lg-6">
            <div style="position:sticky;top:90px;">
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
                    <div style="padding:.8rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:.85rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">
                            <i class="bi bi-geo-alt-fill me-2" style="color:#e63946;"></i>Pickup Locations
                        </span>
                        <span style="font-size:.78rem;color:var(--text-muted);">Click a pin or card to focus</span>
                    </div>
                    <div id="browseMap" style="width:100%;height:520px;"></div>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>

<!-- ── Google Maps JS ──────────────────────────────────────── -->
<script>
const CAR_DATA = <?php echo $mapJson; ?>;

let gMap, infoWindow, markers = {};

function initBrowseMap() {
    // Singapore bounds
    const sg = { lat: 1.3521, lng: 103.8198 };

    gMap = new google.maps.Map(document.getElementById('browseMap'), {
        center: sg,
        zoom: 11,
        mapTypeControl: false,
        fullscreenControl: true,
        streetViewControl: false,
        styles: [
            { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }
        ]
    });

    infoWindow = new google.maps.InfoWindow();

    CAR_DATA.forEach(car => {
        const marker = new google.maps.Marker({
            position : { lat: car.lat, lng: car.lng },
            map      : gMap,
            title    : car.label,
            icon     : {
                path        : google.maps.SymbolPath.CIRCLE,
                scale       : 10,
                fillColor   : '#e63946',
                fillOpacity : 1,
                strokeColor : '#ffffff',
                strokeWeight: 2,
            }
        });

        marker.addListener('click', () => openInfoWindow(marker, car));
        markers[car.car_id] = marker;
    });

    // Auto-fit map to all markers
    if (CAR_DATA.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        CAR_DATA.forEach(c => bounds.extend({ lat: c.lat, lng: c.lng }));
        gMap.fitBounds(bounds);
    }
}

function openInfoWindow(marker, car) {
    infoWindow.setContent(`
        <div style="font-family:sans-serif;min-width:200px;padding:4px 0;">
            <div style="font-weight:600;font-size:14px;margin-bottom:4px;">${car.label}</div>
            <div style="font-size:12px;color:#555;margin-bottom:2px;">
                <i class="bi bi-geo-alt-fill"></i> ${car.location}
            </div>
            <div style="font-size:12px;color:#555;margin-bottom:8px;">${car.plate} &middot; ${car.category}</div>
            <div style="font-size:15px;font-weight:700;color:#e63946;margin-bottom:10px;">${car.price}</div>
            <a href="${car.book_url}"
               style="display:inline-block;background:#e63946;color:#fff;padding:6px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">
               Book Now →
            </a>
        </div>
    `);
    infoWindow.open(gMap, marker);

    // Highlight matching card
    document.querySelectorAll('.car-card').forEach(c => c.style.outline = '');
    const card = document.getElementById('card-' + car.car_id);
    if (card) {
        card.style.outline = '2px solid #e63946';
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Called when user clicks a car card
function focusMapPin(carId) {
    const marker = markers[carId];
    const car    = CAR_DATA.find(c => c.car_id === carId);
    if (!marker || !car) return;
    gMap.panTo(marker.getPosition());
    gMap.setZoom(15);
    openInfoWindow(marker, car);
}
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo GMAPS_KEY; ?>&callback=initBrowseMap">
</script>
<!-- ────────────────────────────────────────────────────────── -->

</main>
<?php require_once 'includes/footer.php'; ?>