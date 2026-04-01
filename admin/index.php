<?php
$pageTitle = 'Dashboard';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Dashboard">
<?php

$totalCars     = $conn->query("SELECT COUNT(*) AS n FROM cars")->fetch_assoc()['n'];
$availableCars = $conn->query("SELECT COUNT(*) AS n FROM cars WHERE is_available=1")->fetch_assoc()['n'];
$totalBookings = $conn->query("SELECT COUNT(*) AS n FROM bookings")->fetch_assoc()['n'];
$totalMembers  = $conn->query("SELECT COUNT(*) AS n FROM members")->fetch_assoc()['n'];
$revenue       = $conn->query("SELECT COALESCE(SUM(total_cost),0) AS rev FROM bookings WHERE status='completed'")->fetch_assoc()['rev'];
$pendingCount  = $conn->query("SELECT COUNT(*) AS n FROM bookings WHERE status='pending'")->fetch_assoc()['n'];

$popular_cars = $conn->query("
    SELECT c.make, c.model, c.category,
           COUNT(b.booking_id) AS total_bookings,
           COALESCE(SUM(b.total_cost), 0) AS total_revenue
    FROM cars c
    LEFT JOIN bookings b ON c.car_id = b.car_id
    GROUP BY c.car_id
    ORDER BY total_bookings DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$top_customers = $conn->query("
    SELECT m.full_name, m.email,
           COUNT(b.booking_id) AS total_bookings,
           COALESCE(SUM(b.total_cost), 0) AS total_spent
    FROM members m
    LEFT JOIN bookings b ON m.member_id = b.member_id
    GROUP BY m.member_id
    ORDER BY total_bookings DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$recent = $conn->query("
    SELECT b.booking_id, b.start_time, b.end_time, b.total_cost, b.status,
           m.full_name, c.make, c.model
    FROM bookings b
    JOIN members m ON b.member_id = m.member_id
    JOIN cars c ON b.car_id = c.car_id
    ORDER BY b.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);
?>

<h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin-bottom:1.5rem;">Dashboard</h2>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val text-accent"><?php echo $totalCars; ?></div>
                <div class="stat-card-label">Total Cars</div>
            </div>
            <!-- FIX 5: aria-hidden on decorative icons -->
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-car-front"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val"><?php echo $totalBookings; ?></div>
                <div class="stat-card-label">Total Bookings</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-calendar-check"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val"><?php echo $totalMembers; ?></div>
                <div class="stat-card-label">Members</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-people"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val" style="font-size:1.8rem;">S$<?php echo number_format($revenue, 0); ?></div>
                <div class="stat-card-label">Total Revenue</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<!-- Quick Info -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span style="color:#34a853;font-weight:600;"><?php echo $availableCars; ?></span> available /
                    <span style="color:#f94144;"><?php echo $totalCars - $availableCars; ?></span> unavailable cars
                </div>
                <!-- FIX 1+2: color lifted to #f4646e (passes 4.5:1 on #181818); aria-label added -->
                <a href="<?php echo BASE; ?>/admin/manage-cars.php"
                   style="font-size:.85rem;color:#f4646e;"
                   aria-label="Manage cars">Manage →</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span style="color:#fbbc05;font-weight:600;"><?php echo $pendingCount; ?></span> pending bookings awaiting confirmation
                </div>
                <!-- FIX 1+3: same colour fix; descriptive aria-label -->
                <a href="<?php echo BASE; ?>/admin/manage-bookings.php"
                   style="font-size:.85rem;color:#f4646e;"
                   aria-label="Manage bookings">Manage →</a>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Row -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
            <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);">
                <!-- FIX 4: h5 → h3 (no heading levels skipped after h2) -->
                <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;margin:0;">
                    <i class="bi bi-car-front text-accent me-2" aria-hidden="true"></i>Most Popular Cars
                </h3>
            </div>
            <div style="padding:.5rem 0;">
                <?php foreach ($popular_cars as $i => $car): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.5rem;<?php echo $i < count($popular_cars)-1 ? 'border-bottom:1px solid var(--border);' : ''; ?>">
                    <div>
                        <div style="font-weight:600;"><?php echo h($car['make'].' '.$car['model']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted);"><?php echo h($car['category']); ?> · S$<?php echo number_format($car['total_revenue'], 0); ?> earned</div>
                    </div>
                    <span style="color:var(--accent);font-weight:700;"><?php echo $car['total_bookings']; ?> <small style="color:var(--text-muted);font-weight:400;">bookings</small></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
            <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);">
                <!-- FIX 4: h5 → h3 -->
                <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;margin:0;">
                    <i class="bi bi-people text-accent me-2" aria-hidden="true"></i>Top Customers
                </h3>
            </div>
            <div style="padding:.5rem 0;">
                <?php foreach ($top_customers as $i => $cust): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.5rem;<?php echo $i < count($top_customers)-1 ? 'border-bottom:1px solid var(--border);' : ''; ?>">
                    <div>
                        <div style="font-weight:600;"><?php echo h($cust['full_name']); ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted);"><?php echo h($cust['email']); ?></div>
                    </div>
                    <span style="color:var(--accent);font-weight:700;"><?php echo $cust['total_bookings']; ?> <small style="color:var(--text-muted);font-weight:400;">· S$<?php echo number_format($cust['total_spent'], 0); ?></small></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
    <div style="padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <!-- FIX 4: h5 → h3 -->
        <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;margin:0;">Recent Bookings</h3>
        <!-- FIX 1+3: colour fix + descriptive aria-label -->
        <a href="<?php echo BASE; ?>/admin/manage-bookings.php"
           style="font-size:.85rem;color:#f4646e;"
           aria-label="View all bookings">View All →</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th scope="col">#</th><th scope="col">Member</th><th scope="col">Car</th><th scope="col">Pickup</th><th scope="col">Cost</th><th scope="col">Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $b): ?>
                <tr>
                    <td style="color:var(--text-muted);">#<?php echo (int)$b['booking_id']; ?></td>
                    <td><?php echo h($b['full_name']); ?></td>
                    <td><?php echo h($b['make'].' '.$b['model']); ?></td>
                    <td><?php echo date('d M, H:i', strtotime($b['start_time'])); ?></td>
                    <td>S$ <?php echo number_format($b['total_cost'], 2); ?></td>
                    <td><span class="status-pill status-<?php echo h($b['status']); ?>"><?php echo h($b['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>