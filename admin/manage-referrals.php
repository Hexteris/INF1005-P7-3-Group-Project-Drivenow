<?php
$pageTitle = 'Referrals';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage referrals">
<?php
/*
 * Real schema (from car_rental.sql):
 *   members:          member_id, full_name, email, points, referral_code, ...
 *   referral_records: id, referrer_user_id, referred_user_id, booking_id,
 *                     discount_used, used_at
 * There is NO referral_id column — primary key is `id`.
 * referred_by column does NOT exist on members; relationships are in referral_records.
 */

$message = '';

/* ── POST: generate missing referral codes ──────────────────────────────*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_codes') {
    $rows      = $conn->query("SELECT member_id FROM members WHERE referral_code IS NULL OR referral_code = ''")->fetch_all(MYSQLI_ASSOC);
    $generated = 0;
    foreach ($rows as $row) {
        do {
            $code   = 'DN' . strtoupper(substr(md5($row['member_id'] . microtime(true)), 0, 6));
            $check  = $conn->prepare("SELECT 1 FROM members WHERE referral_code = ?");
            $check->bind_param("s", $code);
            $check->execute();
            $exists = $check->get_result()->num_rows;
            $check->close();
        } while ($exists);
        $s = $conn->prepare("UPDATE members SET referral_code = ? WHERE member_id = ?");
        $s->bind_param("si", $code, $row['member_id']);
        $s->execute();
        $s->close();
        $generated++;
    }
    $message = "success:Generated $generated referral code" . ($generated !== 1 ? 's.' : '.');
}

/* ── Filters ────────────────────────────────────────────────────────────*/
$search = trim($_GET['search'] ?? '');
$where  = 'WHERE 1=1';
$params = [];
$types  = '';
if ($search !== '') {
    $like     = '%' . $search . '%';
    $where   .= ' AND (m.full_name LIKE ? OR m.email LIKE ? OR m.referral_code LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

/* ── Main member query ──────────────────────────────────────────────────*/
$stmt = $conn->prepare("
    SELECT m.member_id,
           m.full_name,
           m.email,
           m.referral_code,
           m.points,
           (SELECT COUNT(*)
            FROM referral_records rr
            WHERE rr.referrer_user_id = m.member_id)   AS referrals_made,
           (SELECT COUNT(*)
            FROM referral_records rr
            WHERE rr.referred_user_id = m.member_id)   AS was_referred,
           (SELECT ref.full_name
            FROM referral_records rr2
            JOIN members ref ON rr2.referrer_user_id = ref.member_id
            WHERE rr2.referred_user_id = m.member_id
            LIMIT 1)                                    AS referred_by_name
    FROM members m
    $where
    ORDER BY referrals_made DESC, m.full_name ASC");

if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Summary KPIs ───────────────────────────────────────────────────────*/
$stats = $conn->query("
    SELECT COUNT(*)                                                      AS total_members,
           COUNT(CASE WHEN referral_code IS NOT NULL
                       AND referral_code != '' THEN 1 END)               AS with_code
    FROM members")->fetch_assoc();

$totalReferrals = (int)$conn->query("SELECT COUNT(*) AS c FROM referral_records")->fetch_assoc()['c'];

$discountUsed = (int)$conn->query("SELECT COUNT(*) AS c FROM referral_records WHERE discount_used = 1")->fetch_assoc()['c'];

/* ── Top referrers ──────────────────────────────────────────────────────*/
$topReferrers = $conn->query("
    SELECT m.full_name,
           m.email,
           COUNT(rr.id)                               AS count,
           SUM(rr.discount_used)                      AS discounts_given
    FROM members m
    JOIN referral_records rr ON rr.referrer_user_id = m.member_id
    GROUP BY m.member_id
    ORDER BY count DESC
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

/* ── Recent referral activity ───────────────────────────────────────────*/
$recentLog = $conn->query("
    SELECT rr.id,
           rr.discount_used,
           rr.used_at,
           referrer.full_name AS referrer_name,
           referee.full_name  AS referee_name,
           b.total_cost
    FROM referral_records rr
    JOIN members referrer ON rr.referrer_user_id = referrer.member_id
    JOIN members referee  ON rr.referred_user_id  = referee.member_id
    LEFT JOIN bookings b  ON rr.booking_id         = b.booking_id
    ORDER BY rr.used_at DESC
    LIMIT 15")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['', ''];
?>

<div class="page-header-row">
    <h2>Referrals</h2>
    <form method="POST">
        <input type="hidden" name="action" value="generate_codes">
        <button type="submit" class="btn btn-accent btn-sm">
            <i class="bi bi-qr-code" aria-hidden="true"></i> Generate Missing Codes
        </button>
    </form>
</div>

<?php if ($msgType === 'success'): ?>
<div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
<div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php endif; ?>

<!-- ── KPI row ───────────────────────────────────────────────────────────-->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val text-accent"><?php echo (int)($stats['total_members'] ?? 0); ?></div>
            <div class="mini-stat-label">Total Members</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val"><?php echo (int)($stats['with_code'] ?? 0); ?></div>
            <div class="mini-stat-label">Have Referral Code</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#34a853;"><?php echo $totalReferrals; ?></div>
            <div class="mini-stat-label">Successful Referrals</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#fbbc05;"><?php echo $discountUsed; ?></div>
            <div class="mini-stat-label">Discounts Used</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ── Members table ────────────────────────────────────────────────-->
    <div class="col-lg-8">

        <form method="GET" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;"
              role="search" aria-label="Filter referral members">
            <label for="rf-search" class="visually-hidden">Search name, email or code</label>
            <input type="search" id="rf-search" name="search" class="dn-search"
                   placeholder="Name, email or code…" value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-accent btn-sm">Apply</button>
            <?php if ($search): ?>
            <a href="<?php echo BASE; ?>/admin/manage-referrals.php"
               class="btn btn-outline-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="dn-card" style="overflow:hidden;">
            <div class="dn-card-header">
                <h3 class="dn-card-title">
                    <i class="bi bi-share text-accent" aria-hidden="true"></i> Member Referral Codes
                </h3>
                <span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($members); ?> members</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="dn-table" aria-label="Member referral codes table">
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Referral Code</th>
                            <th scope="col">Referred By</th>
                            <th scope="col">Referrals Made</th>
                            <th scope="col">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($members)): ?>
                    <tr><td colspan="5">
                        <div class="empty-state-box">
                            <i class="bi bi-share" aria-hidden="true"></i>
                            <p>No members found.</p>
                        </div>
                    </td></tr>
                    <?php else: foreach ($members as $m):
                        $rc = $m['referral_code'] ?? '';
                        $rm = (int)$m['referrals_made']; ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:.88rem;"><?php echo h($m['full_name']); ?></div>
                            <div style="font-size:.74rem;color:var(--text-muted);"><?php echo h($m['email']); ?></div>
                        </td>
                        <td>
                            <?php if ($rc): ?>
                            <span class="code-pill"><?php echo h($rc); ?></span>
                            <?php else: ?>
                            <span style="font-size:.78rem;color:var(--text-dim);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.85rem;">
                            <?php echo $m['referred_by_name']
                                ? h($m['referred_by_name'])
                                : '<span style="color:var(--text-dim);">—</span>'; ?>
                        </td>
                        <td>
                            <span <?php echo $rm > 0 ? 'class="pts-pos"' : 'style="color:var(--text-muted);"'; ?>>
                                <?php echo $rm; ?>
                            </span>
                        </td>
                        <td style="font-variant-numeric:tabular-nums;"><?php echo number_format((int)$m['points']); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Sidebar ───────────────────────────────────────────────────────-->
    <div class="col-lg-4">

        <!-- Top referrers -->
        <div class="dn-card mb-3">
            <div class="dn-card-header">
                <h3 class="dn-card-title">
                    <i class="bi bi-trophy text-accent" aria-hidden="true"></i> Top Referrers
                </h3>
            </div>
            <?php if (empty($topReferrers)): ?>
            <div class="empty-state-box"><p>No referrals yet.</p></div>
            <?php else: foreach ($topReferrers as $i => $tr): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:.65rem 1rem;
                        <?php echo $i < count($topReferrers) - 1 ? 'border-bottom:1px solid var(--border);' : ''; ?>">
                <div>
                    <div style="font-size:.87rem;font-weight:600;"><?php echo h($tr['full_name']); ?></div>
                    <div style="font-size:.73rem;color:var(--text-muted);"><?php echo h($tr['email']); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="pts-pos" style="font-size:.88rem;"><?php echo (int)$tr['count']; ?> referral<?php echo (int)$tr['count'] !== 1 ? 's' : ''; ?></div>
                    <div style="font-size:.73rem;color:var(--text-muted);"><?php echo (int)$tr['discounts_given']; ?> discount<?php echo (int)$tr['discounts_given'] !== 1 ? 's' : ''; ?> given</div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Recent referral log -->
        <div class="dn-card">
            <div class="dn-card-header">
                <h3 class="dn-card-title">
                    <i class="bi bi-clock-history text-accent" aria-hidden="true"></i> Recent Referrals
                </h3>
            </div>
            <div style="max-height:340px;overflow-y:auto;" role="log" aria-label="Recent referral activity">
                <?php if (empty($recentLog)): ?>
                <div class="empty-state-box"><p>No referrals recorded yet.</p></div>
                <?php else: foreach ($recentLog as $i => $rl): ?>
                <div style="padding:.6rem 1rem;font-size:.8rem;
                            <?php echo $i < count($recentLog) - 1 ? 'border-bottom:1px solid var(--border);' : ''; ?>">
                    <div style="font-weight:600;margin-bottom:.2rem;">
                        <?php echo h($rl['referrer_name']); ?>
                        <span style="color:var(--text-muted);font-weight:400;"> referred </span>
                        <?php echo h($rl['referee_name']); ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="color:var(--text-dim);">
                            <?php echo date('d M Y', strtotime($rl['used_at'])); ?>
                        </span>
                        <?php if ($rl['discount_used']): ?>
                        <span class="status-pill" style="background:rgba(52,168,83,.15);color:#34a853;font-size:.68rem;">
                            Discount used
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-dim);font-size:.72rem;">No discount</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($rl['total_cost']): ?>
                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem;">
                        Booking: S$<?php echo number_format((float)$rl['total_cost'], 2); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>
