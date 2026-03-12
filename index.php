<?php
$pageTitle = 'Home';
require_once 'includes/header.php';
require_once 'includes/db-connect.php';

// Fetch 3 featured cars
$result = $conn->query("SELECT * FROM cars WHERE is_available = 1 ORDER BY created_at DESC LIMIT 3");
$featuredCars = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch 3 latest reviews with member name and car info
$reviewResult = $conn->query("
    SELECT r.*, m.full_name, c.make, c.model
    FROM reviews r
    JOIN members m ON r.member_id = m.member_id
    JOIN cars c ON r.car_id = c.car_id
    ORDER BY r.created_at DESC LIMIT 3
");
$reviews = $reviewResult ? $reviewResult->fetch_all(MYSQLI_ASSOC) : [];

$categoryIcons = [
    'Economy' => '🚗', 'Comfort' => '🚙', 'SUV' => '🚐', 'Premium' => '🏎️'
];
?>

<!-- Hero -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-grid-overlay"></div>
    <div class="container hero-content">
        <div class="row">
            <div class="col-lg-6">
                <div class="hero-eyebrow">
                    <i class="bi bi-lightning-charge-fill"></i> Singapore's #1 Hourly Rental
                </div>
                <h1 class="hero-title">
                    Drive<br>
                    <span class="line-accent">Your Way.</span>
                </h1>
                <p class="hero-subtitle">
                    Book a car by the hour, wherever you are. Economy to Premium — pick up and go in minutes.
                </p>
                <div class="hero-cta">
                    <a href="/cars.php" class="btn-hero-primary">
                        Browse Cars <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="/register.php" class="btn-hero-secondary">
                        <i class="bi bi-person-plus"></i> Create Account
                    </a>
                </div>
                <div class="hero-stats">
                    <div>
                        <div class="stat-val">500<span>+</span></div>
                        <div class="stat-label">Cars Available</div>
                    </div>
                    <div>
                        <div class="stat-val">50<span>+</span></div>
                        <div class="stat-label">Locations</div>
                    </div>
                    <div>
                        <div class="stat-val">S$8<span>.50/hr</span></div>
                        <div class="stat-label">Starting From</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-6" style="padding: 5rem 0;">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Simple Process</div>
            <h2 class="section-title">How DriveNow Works</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="how-card">
                    <div class="how-num">01</div>
                    <div class="how-icon"><i class="bi bi-person-badge"></i></div>
                    <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.04em;">Register & Verify</h5>
                    <p class="text-muted-dn">Sign up with your driving licence number. Takes less than 2 minutes.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="how-card">
                    <div class="how-num">02</div>
                    <div class="how-icon"><i class="bi bi-calendar2-check"></i></div>
                    <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.04em;">Pick & Book</h5>
                    <p class="text-muted-dn">Choose from our fleet, select your pickup time and duration, confirm instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="how-card">
                    <div class="how-num">03</div>
                    <div class="how-icon"><i class="bi bi-car-front"></i></div>
                    <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.04em;">Unlock & Drive</h5>
                    <p class="text-muted-dn">Head to the car park, unlock via the app, and you're off. Return it when done.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Cars -->
<section style="padding: 0 0 5rem;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <div class="section-eyebrow">Our Fleet</div>
                <h2 class="section-title mb-0">Featured Cars</h2>
            </div>
            <a href="/cars.php" class="btn btn-outline-light btn-sm">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (empty($featuredCars)): ?>
            <div class="text-center py-5" style="color:var(--text-muted);">No cars available yet. Check back soon!</div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featuredCars as $car): ?>
            <div class="col-md-4">
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
                            <span><i class="bi bi-people-fill"></i> <?php echo h($car['seats']); ?> seats</span>
                            <span><i class="bi bi-geo-alt-fill"></i> <?php echo h($car['location']); ?></span>
                        </div>
                        <div class="car-price">S$<?php echo number_format($car['price_per_hr'], 2); ?> <span>/ hour</span></div>
                    </div>
                    <div class="car-card-footer">
                        <span class="car-location"><i class="bi bi-pin-map-fill"></i> <?php echo h($car['location']); ?></span>
                        <a href="/book.php?car_id=<?php echo (int)$car['car_id']; ?>" class="btn btn-accent btn-sm">Book Now</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Reviews -->
<?php if (!empty($reviews)): ?>
<section style="padding: 0 0 5rem;">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Real Experiences</div>
            <h2 class="section-title">What Our Members Say</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($reviews as $rev): ?>
            <div class="col-md-4">
                <div class="review-card">
                    <div class="review-stars">
                        <?php echo str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']); ?>
                    </div>
                    <div class="review-text">"<?php echo h($rev['comment']); ?>"</div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="review-author"><i class="bi bi-person-circle me-1"></i><?php echo h($rev['full_name']); ?></div>
                        <div style="color:var(--text-dim);font-size:0.8rem;"><?php echo h($rev['make'].' '.$rev['model']); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Banner -->
<section style="padding: 0 0 5rem;">
    <div class="container">
        <div style="background:linear-gradient(135deg,rgba(230,57,70,0.15),rgba(230,57,70,0.05));border:1px solid var(--border-acc);border-radius:var(--radius);padding:3.5rem;text-align:center;">
            <h2 class="section-title">Ready to Hit the Road?</h2>
            <p class="text-muted-dn mb-4">Join thousands of drivers who trust DriveNow for their daily commute and weekend getaways.</p>
            <a href="/register.php" class="btn-hero-primary">Get Started Today <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
