<?php
$pageTitle = 'Dashboard';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';

$totalCars     = $conn->query("SELECT COUNT(*) AS n FROM cars")->fetch_assoc()['n'];
$availableCars = $conn->query("SELECT COUNT(*) AS n FROM cars WHERE is_available=1")->fetch_assoc()['n'];
$totalBookings = $conn->query("SELECT COUNT(*) AS n FROM bookings")->fetch_assoc()['n'];
$totalMembers  = $conn->query("SELECT COUNT(*) AS n FROM members")->fetch_assoc()['n'];
$revenue       = $conn->query("SELECT COALESCE(SUM(total_cost),0) AS rev FROM bookings WHERE status='completed'")->fetch_assoc()['rev'];
$pendingCount  = $conn->query("SELECT COUNT(*) AS n FROM bookings WHERE status='pending'")->fetch_assoc()['n'];

// Recent bookings
$recent = $conn->query("
    SELECT b.booking_id, b.start_time, b.end_time, b.total_cost, b.status,
           m.full_name, c.make, c.model
    FROM bookings b JOIN members m ON b.member_id=m.member_id JOIN cars c ON b.car_id=c.car_id
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
            <div class="stat-card-icon"><i class="bi bi-car-front"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val"><?php echo $totalBookings; ?></div>
                <div class="stat-card-label">Total Bookings</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-calendar-check"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val"><?php echo $totalMembers; ?></div>
                <div class="stat-card-label">Members</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-people"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val" style="font-size:1.8rem;">S$<?php echo number_format($revenue, 0); ?></div>
                <div class="stat-card-label">Total Revenue</div>
            </div>
            <div class="stat-card-icon"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<!-- Quick Info -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div><span style="color:#34a853;font-weight:600;"><?php echo $availableCars; ?></span> available / <span style="color:#f94144;"><?php echo $totalCars - $availableCars; ?></span> unavailable cars</div>
                <a href="/admin/manage-cars.php" style="font-size:.85rem;">Manage →</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div><span style="color:#fbbc05;font-weight:600;"><?php echo $pendingCount; ?></span> pending bookings awaiting confirmation</div>
                <a href="/admin/manage-bookings.php" style="font-size:.85rem;">Manage →</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
    <div style="padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;margin:0;">Recent Bookings</h5>
        <a href="/admin/manage-bookings.php" style="font-size:.85rem;">View All →</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th>#</th><th>Member</th><th>Car</th><th>Pickup</th><th>Cost</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $b): ?>
                <tr>
                    <td style="color:var(--text-muted);">#<?php echo (int)$b['booking_id']; ?></td>
                    <td><?php echo h($b['full_name']); ?></td>
                    <td><?php echo h($b['make'].' '.$b['model']); ?></td>
                    <td><?php echo date('d M, H:i', strtotime($b['start_time'])); ?></td>
                    <td>S$ <?php echo number_format($b['total_cost'],2); ?></td>
                    <td><span class="status-pill status-<?php echo h($b['status']); ?>"><?php echo h($b['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'admin-footer.php'; ?>
