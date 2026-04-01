<?php
$pageTitle = 'Manage Referrals';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage referrals">
<?php
$referrals = $conn->query("
    SELECT rr.*,
           ref1.full_name  AS referrer_name,
           ref1.email      AS referrer_email,
           ref1.referral_code,
           ref2.full_name  AS referred_name,
           ref2.email      AS referred_email,
           b.total_cost    AS booking_amount
    FROM referral_records rr
    JOIN members ref1 ON rr.referrer_user_id = ref1.member_id
    JOIN members ref2 ON rr.referred_user_id = ref2.member_id
    JOIN bookings b   ON rr.booking_id       = b.booking_id
    ORDER BY rr.used_at DESC
")->fetch_all(MYSQLI_ASSOC);

$stats = $conn->query("
    SELECT
        COUNT(*)                                         AS total_referrals,
        COUNT(CASE WHEN discount_used = 1 THEN 1 END)   AS redeemed,
        COUNT(CASE WHEN discount_used = 0 THEN 1 END)   AS pending
    FROM referral_records
")->fetch_assoc();

// Members with their referral codes
$members_with_codes = $conn->query("
    SELECT member_id, full_name, email, referral_code,
           (SELECT COUNT(*) FROM referral_records WHERE referrer_user_id = members.member_id) AS times_used
    FROM members
    WHERE referral_code IS NOT NULL AND referral_code != ''
    ORDER BY times_used DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin-bottom:1.5rem;">Referrals</h2>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val text-accent"><?php echo (int)($stats['total_referrals'] ?? 0); ?></div>
                <div class="stat-card-label">Total Referrals Used</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-share"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val" style="color:#34a853;"><?php echo (int)($stats['redeemed'] ?? 0); ?></div>
                <div class="stat-card-label">Discount Applied</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card d-flex justify-content-between align-items-start">
            <div>
                <div class="stat-card-val" style="color:#fbbc05;"><?php echo count($members_with_codes); ?></div>
                <div class="stat-card-label">Members with Codes</div>
            </div>
            <div class="stat-card-icon" aria-hidden="true"><i class="bi bi-person-badge"></i></div>
        </div>
    </div>
</div>

<!-- Members & Their Referral Codes -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:1.5rem;">
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.1rem;margin:0;">Member Referral Codes</h3>
    </div>
    <div style="overflow-x:auto;" tabindex="0" role="region" aria-label="Referrals table">
        <table class="dn-table" aria-label="Member referral codes">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Email</th>
                    <th scope="col">Referral Code</th>
                    <th scope="col">Times Used</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members_with_codes)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">
                    No referral codes assigned yet.
                </td></tr>
                <?php else: foreach ($members_with_codes as $mc): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo h($mc['full_name']); ?></td>
                    <td style="color:var(--text-muted);"><?php echo h($mc['email']); ?></td>
                    <td>
                        <code style="background:var(--bg-raised);color:var(--code-text);padding:.2rem .6rem;border-radius:var(--radius-sm);font-size:.82rem;letter-spacing:.05em;">
                            <?php echo h($mc['referral_code']); ?>
                        </code>
                    </td>
                    <td>
                        <span style="font-weight:600;color:var(--accent);"><?php echo (int)$mc['times_used']; ?></span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Referral Usage Log -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.1rem;margin:0;">Referral Usage Log</h3>
    </div>
    <div style="overflow-x:auto;" tabindex="0" role="region" aria-label="Referral usage log table">
        <table class="dn-table" aria-label="Referral usage log">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Referrer</th>
                    <th scope="col">Code Used</th>
                    <th scope="col">Referred User</th>
                    <th scope="col">Booking #</th>
                    <th scope="col">Booking Value</th>
                    <th scope="col">Discount Applied</th>
                    <th scope="col">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($referrals)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:3rem;">
                    No referrals recorded yet.
                </td></tr>
                <?php else: foreach ($referrals as $r): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$r['referral_id']; ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($r['referrer_name']); ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted);"><?php echo h($r['referrer_email']); ?></div>
                    </td>
                    <td>
                        <code style="background:var(--bg-raised);color:var(--code-text);padding:.2rem .5rem;border-radius:var(--radius-sm);font-size:.82rem;">
                            <?php echo h($r['referral_code']); ?>
                        </code>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo h($r['referred_name']); ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted);"><?php echo h($r['referred_email']); ?></div>
                    </td>
                    <td style="color:var(--text-muted);">#<?php echo (int)$r['booking_id']; ?></td>
                    <td>S$<?php echo number_format((float)($r['booking_amount'] ?? 0), 2); ?></td>
                    <td>
                        <?php if ($r['discount_used']): ?>
                            <span class="status-pill status-confirmed">Yes</span>
                        <?php else: ?>
                            <span class="status-pill status-cancelled">No</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--text-muted);">
                        <?php echo date('d M Y H:i', strtotime($r['used_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>
