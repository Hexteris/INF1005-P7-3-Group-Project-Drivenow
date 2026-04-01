<?php
$pageTitle = 'My Points';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db-connect.php';

$member_id = $_SESSION['member_id'];

// Fetch member points
$stmt = $conn->prepare("SELECT points, full_name, referral_code FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();
$points = (int)$member['points'];

// Fetch points history
$hist = $conn->prepare("
    SELECT pl.*, b.start_time, c.make, c.model
    FROM points_log pl
    LEFT JOIN bookings b ON pl.booking_id = b.booking_id
    LEFT JOIN cars c ON b.car_id = c.car_id
    WHERE pl.member_id = ?
    ORDER BY pl.created_at DESC
    LIMIT 50
");
$hist->bind_param("i", $member_id);
$hist->execute();
$history = $hist->get_result()->fetch_all(MYSQLI_ASSOC);
$hist->close();

// Total earned/redeemed
$totals = $conn->prepare("
    SELECT
        SUM(CASE WHEN type='earned'   THEN points ELSE 0 END) AS total_earned,
        SUM(CASE WHEN type='redeemed' THEN ABS(points) ELSE 0 END) AS total_redeemed
    FROM points_log WHERE member_id = ?
");
$totals->bind_param("i", $member_id);
$totals->execute();
$totRow = $totals->get_result()->fetch_assoc();
$totals->close();
$totalEarned   = (int)($totRow['total_earned']   ?? 0);
$totalRedeemed = (int)($totRow['total_redeemed'] ?? 0);

// Tier logic
function getTier($pts) {
    if ($pts >= 1500) return ['name'=>'Gold',   'icon'=>'🥇', 'color'=>'#d4af37', 'next'=>null,  'nextPts'=>0];
    if ($pts >= 500)  return ['name'=>'Silver', 'icon'=>'🥈', 'color'=>'#b0b0b0', 'next'=>'Gold',   'nextPts'=>1500];
    return                   ['name'=>'Bronze', 'icon'=>'🥉', 'color'=>'#cd7f32', 'next'=>'Silver', 'nextPts'=>500];
}
$tier = getTier($points);

require_once 'includes/header.php';
?>

<section class="page-header" aria-label="Page header">
    <div class="container">
        <div class="section-eyebrow">Rewards</div>
        <h1 class="section-title">My Loyalty Points</h1>
        <p class="text-muted-dn">Earn points every time you book. Redeem for discounts.</p>
    </div>
</section>

<main id="main-content">
<div class="container pb-5">
    <div class="row g-4">

        <!-- ── Left: Balance + Tier ──────────────────────── -->
        <div class="col-lg-4">

            <!-- Points balance card -->
            <div role="region" aria-label="Points balance" style="background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:var(--radius);padding:2rem;margin-bottom:1.5rem;text-align:center;">
                <div style="font-size:.75rem;color:#aaa;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem;">Current Balance</div>
                <div style="font-family:'Bebas Neue',sans-serif;font-size:3.5rem;color:#f5d77e;line-height:1;">
                    <?php echo number_format($points); ?>
                </div>
                <div style="font-size:.85rem;color:#aaa;margin-bottom:1.5rem;">points</div>

                <!-- Tier badge -->
                <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,0.08);border-radius:20px;padding:.4rem 1.2rem;">
                    <span style="font-size:1.2rem;"><?php echo $tier['icon']; ?></span>
                    <span style="font-size:.88rem;font-weight:600;color:<?php echo $tier['color']; ?>;"><?php echo $tier['name']; ?> Member</span>
                </div>

                <!-- Progress to next tier -->
                <?php if ($tier['next']): ?>
                <div style="margin-top:1.2rem;">
                    <div class="d-flex justify-content-between" style="font-size:.75rem;color:#aaa;margin-bottom:.4rem;">
                        <span><?php echo $tier['name']; ?></span>
                        <span><?php echo $tier['next']; ?> (<?php echo number_format($tier['nextPts']); ?> pts)</span>
                    </div>
                    <div style="background:rgba(255,255,255,0.1);border-radius:20px;height:6px;">
                        <?php
                            $prevPts   = $tier['name'] === 'Silver' ? 500 : 0;
                            $progress  = min(100, (($points - $prevPts) / ($tier['nextPts'] - $prevPts)) * 100);
                        ?>
                        <div style="background:<?php echo $tier['color']; ?>;width:<?php echo $progress; ?>%;height:6px;border-radius:20px;transition:width .5s;"></div>
                    </div>
                    <div style="font-size:.75rem;color:#aaa;margin-top:.4rem;">
                        <?php echo number_format($tier['nextPts'] - $points); ?> pts to <?php echo $tier['next']; ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="margin-top:.8rem;font-size:.78rem;color:#f5d77e;">
                    <i class="bi bi-trophy-fill me-1"></i>You've reached the highest tier!
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div role="region" aria-label="Points statistics" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;margin-bottom:1.5rem;">
                <div style="font-size:.8rem;color:#9a9a9a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;">All Time</div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:.88rem;">Total earned</span>
                    <span style="font-size:.88rem;color:#34a853;font-weight:600;">+<?php echo number_format($totalEarned); ?> pts</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:.88rem;">Total redeemed</span>
                    <span style="font-size:.88rem;color:#e63946;font-weight:600;">−<?php echo number_format($totalRedeemed); ?> pts</span>
                </div>
                <hr style="border-color:var(--border);margin:.8rem 0;">
                <div class="d-flex justify-content-between">
                    <span style="font-size:.88rem;font-weight:600;">Current balance</span>
                    <span style="font-size:.88rem;color:#f5d77e;font-weight:600;"><?php echo number_format($points); ?> pts</span>
                </div>
            </div>

            <!-- Referral Code Card -->
            <?php if (!empty($member['referral_code'])): ?>
            <div role="region" aria-label="Points statistics" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;margin-bottom:1.5rem;">
                <div style="font-size:.8rem;color:#9a9a9a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.8rem;">Your Referral Code</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="flex:1;background:var(--bg-raised);border:1px solid var(--border);border-radius:var(--radius-sm);padding:.5rem 1rem;font-family:'Courier New',monospace;font-size:1.1rem;font-weight:700;letter-spacing:.12em;color:#f5d77e;" id="referralCodeDisplay">
                        <?php echo h($member['referral_code']); ?>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" id="copyBtn"
                            onclick="copyReferralCode()"
                            aria-label="Copy referral code"
                            style="padding:.45rem .7rem;border-color:var(--border);">
                        <i class="bi bi-clipboard" id="copyIcon" aria-hidden="true"></i>
                    </button>
                </div>
                <div style="font-size:.76rem;color:#9a9a9a;margin-top:.6rem;">
                    Share this code — your friend gets 15% off their first booking!
                </div>
            </div>
            <?php endif; ?>

            <!-- How it works -->
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;">
                <div style="font-size:.8rem;color:#9a9a9a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem;">How It Works</div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="width:28px;height:28px;background:#34a85322;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-plus-circle-fill" style="color:#34a853;font-size:13px;"></i>
                        </div>
                        <div style="font-size:.82rem;color:#9a9a9a;">
                            Earn <strong style="color:var(--text);">1 point</strong> for every S$1 spent on bookings
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="width:28px;height:28px;background:#e6394622;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-tag-fill" style="color:#e63946;font-size:13px;"></i>
                        </div>
                        <div style="font-size:.82rem;color:#9a9a9a;">
                            Redeem <strong style="color:var(--text);">100 points</strong> for <strong style="color:var(--text);">S$5 off</strong> your next booking
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <div style="width:28px;height:28px;background:#f5d77e22;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trophy-fill" style="color:#d4af37;font-size:13px;"></i>
                        </div>
                        <div style="font-size:.82rem;color:#9a9a9a;">
                            <strong style="color:var(--text);">Bronze → Silver → Gold</strong> tiers unlock as you accumulate points
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right: History ─────────────────────────────── -->
        <div class="col-lg-8">
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
                <div style="padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);">
                    <h5 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;margin:0;">Points History</h5>
                </div>

                <?php if (empty($history)): ?>
                <div style="text-align:center;padding:3rem;">
                    <div style="font-size:3rem;margin-bottom:1rem;">⭐</div>
                    <p style="color:#9a9a9a;">No points activity yet. Book a car to start earning!</p>
                    <a href="<?php echo BASE; ?>/cars.php" class="btn btn-accent btn-sm mt-2">Browse Cars</a>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;" tabindex="0">
                    <table class="dn-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Car</th>
                                <th style="text-align:right;">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                            <tr>
                                <td style="color:#9a9a9a;font-size:.82rem;white-space:nowrap;">
                                    <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td style="font-size:.85rem;"><?php echo h($row['description']); ?></td>
                                <td style="font-size:.82rem;color:#9a9a9a;">
                                    <?php echo !empty($row['make']) ? h($row['make'].' '.$row['model']) : '–'; ?>
                                </td>
                                <td style="text-align:right;font-weight:600;white-space:nowrap;">
                                    <?php if ($row['type'] === 'earned'): ?>
                                        <span style="color:#34a853;">+<?php echo number_format($row['points']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#e63946;"><?php echo number_format($row['points']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- CTA if enough to redeem -->
            <?php if ($points >= 100): ?>
            <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:var(--radius);padding:1.2rem 1.5rem;margin-top:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                <div>
                    <div style="font-size:.88rem;font-weight:600;color:#f5d77e;margin-bottom:.2rem;">
                        <i class="bi bi-star-fill me-1"></i>Ready to redeem!
                    </div>
                    <div style="font-size:.8rem;color:#aaa;">
                        You have enough points for a discount on your next booking.
                    </div>
                </div>
                <a href="<?php echo BASE; ?>/cars.php" class="btn btn-accent btn-sm" style="white-space:nowrap;">
                    Book Now →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyReferralCode() {
    const code = document.getElementById('referralCodeDisplay').textContent.trim();
    navigator.clipboard.writeText(code).then(() => {
        const icon = document.getElementById('copyIcon');
        const btn  = document.getElementById('copyBtn');
        icon.className = 'bi bi-check-lg';
        btn.style.borderColor  = '#34a853';
        btn.style.color        = '#34a853';
        setTimeout(() => {
            icon.className     = 'bi bi-clipboard';
            btn.style.borderColor = '';
            btn.style.color       = '';
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const el = document.createElement('textarea');
        el.value = code;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    });
}
</script>

</main>
<?php require_once 'includes/footer.php'; ?>