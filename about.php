<?php
$pageTitle = 'About Us';
require_once 'includes/db-connect.php'; 
require_once 'includes/header.php';
?>

<main>

<!--HERO-->
<section class="about-hero">
    <div class="container">
        <p class="about-hero-eyebrow">Our Story</p>
        <h1 class="about-hero-title">
            Built for Singapore.<br>
            Built for <span class="logo-accent">You.</span>
        </h1>
        <p class="about-hero-sub">
            DriveNow was born from a simple idea — getting a car shouldn't be complicated.
            No queues, no paperwork, no surprises. Just drive.
        </p>
    </div>
</section>

<!-- WHO WE ARE -->
<section class="about-section">
    <div class="container">

        <?php
        // Pull live stats from DB
        $totalMembers  = $conn->query("SELECT COUNT(*) AS n FROM members")->fetch_assoc()['n'];
        $totalCars     = $conn->query("SELECT COUNT(*) AS n FROM cars")->fetch_assoc()['n'];
        $avgRating     = $conn->query("SELECT ROUND(AVG(rating), 1) AS avg FROM reviews")->fetch_assoc()['avg'];
        $avgRating     = $avgRating ?? 'New'; // fallback if no reviews yet
        ?>

        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <p class="section-label">Who We Are</p>
                <h2 class="section-heading">Singapore's on-demand car rental, reimagined.</h2>
                <p class="section-body">
                    DriveNow is a Singapore-based hourly car rental platform that connects drivers
                    to a premium fleet — from fuel-efficient city cars to weekend SUVs. Whether you
                    need a vehicle for two hours or two days, we make it seamless from booking to keys.
                </p>
                <p class="section-body">
                    We believe car access should be flexible, transparent, and fair. That means no
                    hidden fees, clear pricing per hour, and a fleet that's always maintained to
                    the highest standard.
                </p>
            </div>
            <div class="col-lg-5 offset-lg-1">
                <div class="about-stat-grid">
                    <div class="about-stat-card">
                        <div class="about-stat-num text-accent"><?php echo $totalMembers; ?>+</div>
                        <div class="about-stat-label">Happy Members</div>
                    </div>
                    <div class="about-stat-card">
                        <div class="about-stat-num text-accent"><?php echo $totalCars; ?>+</div>
                        <div class="about-stat-label">Cars in Fleet</div>
                    </div>
                    <div class="about-stat-card">
                        <div class="about-stat-num text-accent">24/7</div>
                        <div class="about-stat-label">Booking Access</div>
                    </div>
                    <div class="about-stat-card">
                        <div class="about-stat-num text-accent">
                            <?php echo is_numeric($avgRating) ? $avgRating . ' ★' : $avgRating; ?>
                        </div>
                        <div class="about-stat-label">Average Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- VALUES -->
<section class="about-section about-section--alt">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">What We Stand For</p>
            <h2 class="section-heading">Our Values</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="value-card">
                    <div class="value-icon"><i class="bi bi-shield-check"></i></div>
                    <h3 class="value-title">Trust &amp; Safety</h3>
                    <p class="value-body">Every vehicle undergoes regular maintenance checks. Your safety on Singapore roads is non-negotiable.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="value-card">
                    <div class="value-icon"><i class="bi bi-eye"></i></div>
                    <h3 class="value-title">Full Transparency</h3>
                    <p class="value-body">What you see is what you pay. Hourly rates, fuel policy, and terms are always clearly displayed — no surprises at checkout.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="value-card">
                    <div class="value-icon"><i class="bi bi-lightning-charge"></i></div>
                    <h3 class="value-title">Effortless Booking</h3>
                    <p class="value-body">Browse, book, and pay in under three minutes. Our platform was designed so you spend less time clicking and more time driving.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="value-card">
                    <div class="value-icon"><i class="bi bi-geo-alt"></i></div>
                    <h3 class="value-title">Local Roots</h3>
                    <p class="value-body">We're proudly Singapore-built and operated. Our fleet, our team, and our support are all right here, ready when you need us.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="about-section">
    <div class="container">
        <div class="text-center mb-5">
            <p class="section-label">Getting Started</p>
            <h2 class="section-heading">How DriveNow Works</h2>
        </div>
        <div class="row g-0 how-steps">
            <div class="col-md-3 how-step">
                <div class="how-step-num">01</div>
                <h3 class="how-step-title">Create an Account</h3>
                <p class="how-step-body">Register in minutes with your name, email, and contact details. No lengthy forms.</p>
            </div>
            <div class="col-md-3 how-step">
                <div class="how-step-num">02</div>
                <h3 class="how-step-title">Browse the Fleet</h3>
                <p class="how-step-body">Filter by car type, seats, or transmission. Every listing shows live availability and hourly pricing.</p>
            </div>
            <div class="col-md-3 how-step">
                <div class="how-step-num">03</div>
                <h3 class="how-step-title">Book &amp; Pay</h3>
                <p class="how-step-body">Pick your pickup time, confirm your duration, and pay securely. Your booking is confirmed instantly.</p>
            </div>
            <div class="col-md-3 how-step">
                <div class="how-step-num">04</div>
                <h3 class="how-step-title">Drive &amp; Review</h3>
                <p class="how-step-body">Collect your car, enjoy the drive, and leave a review when you're done. It's that simple.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="about-cta">
    <div class="container text-center">
        <h2 class="about-cta-title">Ready to hit the road?</h2>
        <p class="about-cta-sub">
            Join our growing community of Singaporeans who drive on their own terms.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?php echo BASE; ?>cars.php" class="btn btn-accent btn-lg">Browse Cars</a>
            <a href="<?php echo BASE; ?>register.php" class="btn btn-outline-light btn-lg">Create Account</a>
        </div>
    </div>
</section>

</main>

<?php require_once 'includes/footer.php'; ?>