<?php
$pageTitle = 'Browse Cars';
require_once 'includes/header.php';
require_once 'includes/db-connect.php';
require_once 'includes/auth.php';

// Filter inputs
$category  = $_GET['category']  ?? '';
$location  = $_GET['location']  ?? '';
$max_price = $_GET['max_price'] ?? '';
$search    = $_GET['search']    ?? '';

// Build query safely
$where = ["is_available = 1"];
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

// Get distinct locations for filter
$locResult = $conn->query("SELECT DISTINCT location FROM cars WHERE location IS NOT NULL ORDER BY location");
$locations = $locResult ? $locResult->fetch_all(MYSQLI_ASSOC) : [];

$categoryIcons = ['Economy'=>'🚗','Comfort'=>'🚙','SUV'=>'🚐','Premium'=>'🏎️'];
?>

<section class="page-header">
    <div class="container">
        <div class="section-eyebrow">Available Now</div>
        <h1 class="section-title">Browse Our Fleet</h1>
        <p class="text-muted-dn">Find the perfect car for your journey.</p>
    </div>
</section>

<div class="container pb-5">
    <!-- Filter Bar -->
    <form method="GET" action="/cars.php" class="filter-bar">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted-dn" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Search</label>
                <input type="text" class="form-control" name="search" placeholder="Make, model, plate..."
                    value="<?php echo h($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted-dn" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach (['Economy','Comfort','SUV','Premium'] as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted-dn" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Location</label>
                <select class="form-select" name="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo h($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                            <?php echo h($loc['location']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted-dn" style="font-size:.82rem;letter-spacing:.05em;text-transform:uppercase;">Max $/hr</label>
                <input type="number" class="form-control" name="max_price" placeholder="e.g. 20"
                    value="<?php echo h($max_price); ?>" min="1" step="0.5">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-accent w-100">Filter</button>
                <a href="/cars.php" class="btn btn-outline-light">Reset</a>
            </div>
        </div>
    </form>

    <!-- Results -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted-dn mb-0"><?php echo count($cars); ?> car<?php echo count($cars) !== 1 ? 's' : ''; ?> found</p>
    </div>

    <?php if (empty($cars)): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem;">🔍</div>
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1.8rem;margin-top:1rem;">No Cars Found</h4>
            <p class="text-muted-dn">Try adjusting your filters.</p>
            <a href="/cars.php" class="btn btn-accent mt-2">Clear Filters</a>
        </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($cars as $car): ?>
        <div class="col-md-6 col-lg-4">
            <div class="car-card">
                <div class="car-card-img">
                    <?php if (!empty($car['image_url'])): ?>
                        <img src="<?php echo h($car['image_url']); ?>" alt="<?php echo h($car['make'].' '.$car['model']); ?>">
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
                        <a href="/book.php?car_id=<?php echo (int)$car['car_id']; ?>" class="btn btn-accent btn-sm">Book Now</a>
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

<?php require_once 'includes/footer.php'; ?>
