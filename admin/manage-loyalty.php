<?php
$pageTitle = 'Loyalty Points';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage loyalty points">
<?php
/*
 * Real schema (car_rental.sql):
 *   points_log: log_id, member_id, booking_id (nullable FK), points,
 *               type enum('earned','redeemed'), description, created_at
 *   members:    member_id, full_name, email, points (running balance), ...
 *
 * Admin adjustments: positive delta → type='earned', negative → type='redeemed'
 */

$message = '';

/* ── POST: manual point adjustment ──────────────────────────────────────*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_points') {
    $mid   = (int)$_POST['member_id'];
    $delta = (int)$_POST['delta'];
    $note  = trim($_POST['note'] ?? '');
    $type  = $delta >= 0 ? 'earned' : 'redeemed';
    $abs   = abs($delta);

    if ($mid && $delta !== 0) {
        $conn->begin_transaction();
        try {
            // Update balance
            $s = $conn->prepare("UPDATE members SET points = GREATEST(0, points + ?) WHERE member_id = ?");
            $s->bind_param("ii", $delta, $mid);
            $s->execute();
            $s->close();

            // Log entry (booking_id = NULL for admin adjustments)
            $desc = $note ?: 'Admin adjustment (' . ($delta >= 0 ? '+' : '') . $delta . ' pts)';
            $s2   = $conn->prepare("INSERT INTO points_log (member_id, booking_id, points, type, description) VALUES (?, NULL, ?, ?, ?)");
            $s2->bind_param("iiss", $mid, $abs, $type, $desc);
            $s2->execute();
            $s2->close();

            $conn->commit();
            $message = "success:Points adjusted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "error:Adjustment failed: " . $e->getMessage();
        }
    } else {
        $message = "error:Invalid input — member or delta missing.";
    }
}

/* ── Filters ────────────────────────────────────────────────────────────*/
$search = trim($_GET['search'] ?? '');
$where  = '1=1';
$params = [];
$types  = '';
if ($search !== '') {
    $like     = '%' . $search . '%';
    $where   .= ' AND (m.full_name LIKE ? OR m.email LIKE ?)';
    $params[] = $like; $params[] = $like;
    $types   .= 'ss';
}

/* ── Members with point balances ─────────────────────────────────────────*/
$stmt = $conn->prepare("
    SELECT m.member_id,
           m.full_name,
           m.email,
           m.points,
           COUNT(DISTINCT b.booking_id)                 AS booking_count,
           COALESCE(SUM(CASE WHEN pl.type='earned'   THEN pl.points ELSE 0 END), 0) AS total_earned,
           COALESCE(SUM(CASE WHEN pl.type='redeemed' THEN pl.points ELSE 0 END), 0) AS total_redeemed
    FROM members m
    LEFT JOIN bookings b    ON m.member_id  = b.member_id
    LEFT JOIN points_log pl ON m.member_id  = pl.member_id
    WHERE $where
    GROUP BY m.member_id
    ORDER BY m.points DESC");

if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── KPI totals ──────────────────────────────────────────────────────────*/
$kpi = $conn->query("
    SELECT SUM(CASE WHEN type='earned'   THEN points ELSE 0 END) AS issued,
           SUM(CASE WHEN type='redeemed' THEN points ELSE 0 END) AS redeemed
    FROM points_log")->fetch_assoc();

$totalBalance = (int)$conn->query("SELECT COALESCE(SUM(points),0) AS s FROM members")->fetch_assoc()['s'];
$goldCount    = (int)$conn->query("SELECT COUNT(*) AS c FROM members WHERE points >= 1500")->fetch_assoc()['c'];
$silverCount  = (int)$conn->query("SELECT COUNT(*) AS c FROM members WHERE points >= 500 AND points < 1500")->fetch_assoc()['c'];
$bronzeCount  = (int)$conn->query("SELECT COUNT(*) AS c FROM members WHERE points < 500")->fetch_assoc()['c'];

/* ── Recent log entries ──────────────────────────────────────────────────*/
$recentLog = $conn->query("
    SELECT pl.log_id, pl.points, pl.type, pl.description, pl.created_at,
           m.full_name, m.email,
           b.booking_id
    FROM points_log pl
    JOIN members m ON pl.member_id = m.member_id
    LEFT JOIN bookings b ON pl.booking_id = b.booking_id
    ORDER BY pl.created_at DESC
    LIMIT 20")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['', ''];

function tierBadge(int $p): string {
    if ($p >= 1500) return '<span class="status-pill tier-gold">Gold</span>';
    if ($p >= 500)  return '<span class="status-pill tier-silver">Silver</span>';
    return '<span class="status-pill tier-bronze">Bronze</span>';
}
?>

<div class="page-header-row">
    <h2>Loyalty Points</h2>
    <button type="button" class="btn btn-accent btn-sm"
            data-bs-toggle="modal" data-bs-target="#adjustModal"
            aria-haspopup="dialog">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Adjust Points
    </button>
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
            <div class="mini-stat-val text-accent"><?php echo number_format($totalBalance); ?></div>
            <div class="mini-stat-label">Total Points Held</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val"><?php echo number_format((int)($kpi['issued'] ?? 0)); ?></div>
            <div class="mini-stat-label">Points Issued</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat">
            <div class="mini-stat-val"><?php echo number_format((int)($kpi['redeemed'] ?? 0)); ?></div>
            <div class="mini-stat-label">Points Redeemed</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="mini-stat" title="Gold ≥1500 | Silver ≥500 | Bronze <500">
            <div class="mini-stat-val" style="display:flex;gap:.3rem;align-items:center;justify-content:center;">
                <span class="status-pill tier-gold"><?php echo $goldCount; ?></span>
                <span class="status-pill tier-silver"><?php echo $silverCount; ?></span>
                <span class="status-pill tier-bronze"><?php echo $bronzeCount; ?></span>
            </div>
            <div class="mini-stat-label">Gold / Silver / Bronze</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ── Members table ────────────────────────────────────────────────-->
    <div class="col-lg-8">

        <form method="GET" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;"
              role="search" aria-label="Filter loyalty members">
            <label for="ly-search" class="visually-hidden">Search name or email</label>
            <input type="search" id="ly-search" name="search" class="dn-search"
                   placeholder="Search name or email…" value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-accent btn-sm">Apply</button>
            <?php if ($search): ?>
            <a href="<?php echo BASE; ?>/admin/manage-loyalty.php"
               class="btn btn-outline-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="dn-card" style="overflow:hidden;">
            <div class="dn-card-header">
                <h3 class="dn-card-title">
                    <i class="bi bi-gift text-accent" aria-hidden="true"></i> Member Point Balances
                </h3>
                <span style="font-size:.78rem;color:var(--text-muted);"><?php echo count($members); ?> members</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="dn-table" aria-label="Member loyalty points table">
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Balance</th>
                            <th scope="col">Tier</th>
                            <th scope="col">Earned</th>
                            <th scope="col">Redeemed</th>
                            <th scope="col">Bookings</th>
                            <th scope="col"><span class="visually-hidden">Adjust</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($members)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state-box">
                            <i class="bi bi-gift" aria-hidden="true"></i>
                            <p>No members found.</p>
                        </div>
                    </td></tr>
                    <?php else: foreach ($members as $m):
                        $pts = (int)$m['points']; ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:.88rem;"><?php echo h($m['full_name']); ?></div>
                            <div style="font-size:.74rem;color:var(--text-muted);"><?php echo h($m['email']); ?></div>
                        </td>
                        <td style="font-variant-numeric:tabular-nums;font-weight:700;">
                            <?php echo number_format($pts); ?>
                        </td>
                        <td><?php echo tierBadge($pts); ?></td>
                        <td class="pts-pos" style="font-variant-numeric:tabular-nums;font-size:.85rem;">
                            +<?php echo number_format((int)$m['total_earned']); ?>
                        </td>
                        <td class="pts-neg" style="font-variant-numeric:tabular-nums;font-size:.85rem;">
                            −<?php echo number_format((int)$m['total_redeemed']); ?>
                        </td>
                        <td>
                            <span class="status-pill status-confirmed"><?php echo (int)$m['booking_count']; ?></span>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    onclick="openAdjust(<?php echo (int)$m['member_id']; ?>, <?php echo h(json_encode($m['full_name'])); ?>)"
                                    aria-label="Adjust points for <?php echo h($m['full_name']); ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Recent log ────────────────────────────────────────────────────-->
    <div class="col-lg-4">
        <div class="dn-card" style="height:100%;">
            <div class="dn-card-header">
                <h3 class="dn-card-title">
                    <i class="bi bi-clock-history text-accent" aria-hidden="true"></i> Recent Activity
                </h3>
            </div>
            <div style="max-height:520px;overflow-y:auto;" role="log" aria-label="Recent points activity">
                <?php if (empty($recentLog)): ?>
                <div class="empty-state-box"><p>No activity yet.</p></div>
                <?php else: foreach ($recentLog as $i => $e):
                    $earned = $e['type'] === 'earned';
                    $bdr    = $i < count($recentLog) - 1 ? 'border-bottom:1px solid var(--border);' : ''; ?>
                <div style="padding:.6rem 1rem;<?php echo $bdr; ?>">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                        <div style="font-size:.82rem;font-weight:600;max-width:68%;">
                            <?php echo h($e['full_name']); ?>
                        </div>
                        <span class="<?php echo $earned ? 'pts-pos' : 'pts-neg'; ?>"
                              style="font-size:.82rem;font-variant-numeric:tabular-nums;">
                            <?php echo $earned ? '+' : '−'; ?><?php echo number_format((int)$e['points']); ?>
                        </span>
                    </div>
                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem;">
                        <?php echo h($e['description'] ?: ucfirst($e['type'])); ?>
                    </div>
                    <div style="font-size:.68rem;color:var(--text-dim);margin-top:.1rem;">
                        <?php echo date('d M Y, H:i', strtotime($e['created_at'])); ?>
                        <?php if ($e['booking_id']): ?>
                        · Booking #<?php echo (int)$e['booking_id']; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Adjust Points Modal ───────────────────────────────────────────────-->
<div class="modal fade" id="adjustModal" tabindex="-1"
     aria-labelledby="adjustModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="POST"
              style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
            <input type="hidden" name="action" value="adjust_points">
            <input type="hidden" name="member_id" id="adjustMemberId">
            <div class="modal-header" style="border-bottom:1px solid var(--border);">
                <h4 class="modal-title" id="adjustModalLabel" style="font-size:1rem;">
                    Adjust Points — <span id="adjustMemberName"></span>
                </h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body" style="display:flex;flex-direction:column;gap:.9rem;">
                <div>
                    <label for="adjustDelta" class="dn-label">
                        Points Change
                        <span style="font-size:.73rem;color:var(--text-muted);">(use − for deduction, e.g. −100)</span>
                    </label>
                    <input type="number" id="adjustDelta" name="delta" class="dn-input"
                           required placeholder="e.g. 200 or -50" step="1">
                </div>
                <div>
                    <label for="adjustNote" class="dn-label">Note <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="text" id="adjustNote" name="note" class="dn-input"
                           placeholder="Reason for adjustment" maxlength="200">
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);gap:.5rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-accent btn-sm">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjust(id, name) {
    document.getElementById('adjustMemberId').value = id;
    document.getElementById('adjustMemberName').textContent = name;
    document.getElementById('adjustDelta').value = '';
    document.getElementById('adjustNote').value  = '';
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
</script>

</main>
<?php require_once 'admin-footer.php'; ?>
