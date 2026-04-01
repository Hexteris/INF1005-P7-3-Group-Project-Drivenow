<?php
$pageTitle = 'Manage Loyalty Points';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage loyalty points">
<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    $member_id   = (int)$_POST['member_id'];
    $points_delta = (int)$_POST['points_delta'];
    $reason       = trim($_POST['reason'] ?? 'Admin adjustment');
    $type         = $points_delta >= 0 ? 'earned' : 'redeemed';
    $abs_pts      = abs($points_delta);

    if ($member_id > 0 && $abs_pts > 0) {
        $stmt = $conn->prepare(
            "UPDATE members SET points = GREATEST(0, points + ?) WHERE member_id = ?"
        );
        $stmt->bind_param("ii", $points_delta, $member_id);
        if ($stmt->execute()) {
            $log = $conn->prepare(
                "INSERT INTO points_log (member_id, points, type, description) VALUES (?, ?, ?, ?)"
            );
            $log->bind_param("iiss", $member_id, $abs_pts, $type, $reason);
            $log->execute();
            $log->close();
            $message = 'success:Loyalty points updated.';
        } else {
            $message = 'error:Failed to update points: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $message = 'error:Please select a member and enter a non-zero value.';
    }
}

// Fetch members with their points balance
$members = $conn->query("
    SELECT m.member_id, m.full_name, m.email, m.points,
           COUNT(DISTINCT b.booking_id) AS total_bookings
    FROM members m
    LEFT JOIN bookings b ON m.member_id = b.member_id
    GROUP BY m.member_id
    ORDER BY m.points DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch recent log entries
$transactions = $conn->query("
    SELECT pl.*, m.full_name
    FROM points_log pl
    JOIN members m ON pl.member_id = m.member_id
    ORDER BY pl.created_at DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['', ''];

// Tier helper
function pointsTier(int $pts): string {
    if ($pts >= 1500) return 'Gold';
    if ($pts >= 500)  return 'Silver';
    return 'Bronze';
}
function tierColor(string $tier): string {
    return match($tier) {
        'Gold'   => '#f9a825',
        'Silver' => '#90a4ae',
        default  => '#cd7f32',
    };
}
?>

<h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin-bottom:1.5rem;">Loyalty Points</h2>

<?php if ($msgType === 'success'): ?>
<div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
<div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php endif; ?>

<!-- Quick Adjust Panel -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;margin-bottom:1.5rem;">
    <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;margin-bottom:1rem;">Adjust Member Points</h3>
    <form method="POST" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
        <input type="hidden" name="adjust_points" value="1">

        <div>
            <label for="adj_member" style="font-size:.82rem;color:var(--text-muted);display:block;margin-bottom:.25rem;">
                Member
            </label>
            <select id="adj_member" name="member_id" class="form-select form-select-sm"
                    style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);min-width:220px;"
                    required>
                <option value="">— Select member —</option>
                <?php foreach ($members as $mb): ?>
                <option value="<?php echo (int)$mb['member_id']; ?>">
                    <?php echo h($mb['full_name']); ?> (<?php echo number_format($mb['points']); ?> pts)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="adj_delta" style="font-size:.82rem;color:var(--text-muted);display:block;margin-bottom:.25rem;">
                Points <span style="color:var(--text-muted);font-size:.78rem;">(negative to deduct)</span>
            </label>
            <input type="number" id="adj_delta" name="points_delta"
                   class="form-control form-control-sm"
                   style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);width:150px;"
                   placeholder="e.g. 50 or -20" required>
        </div>

        <div>
            <label for="adj_reason" style="font-size:.82rem;color:var(--text-muted);display:block;margin-bottom:.25rem;">
                Reason
            </label>
            <input type="text" id="adj_reason" name="reason"
                   class="form-control form-control-sm"
                   style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);width:200px;"
                   placeholder="Admin adjustment">
        </div>

        <button type="submit" class="btn btn-accent btn-sm">Apply</button>
    </form>
    <p style="font-size:.78rem;color:var(--text-muted);margin-top:.75rem;margin-bottom:0;">
        <strong>Tiers:</strong> Bronze 0–499 pts &nbsp;·&nbsp; Silver 500–1,499 pts &nbsp;·&nbsp; Gold 1,500+ pts
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Redemption:</strong> 100 pts = S$5 discount
    </p>
</div>

<!-- Members Table -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:1.5rem;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Member loyalty points">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Email</th>
                    <th scope="col">Bookings</th>
                    <th scope="col">Points</th>
                    <th scope="col">Tier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">No members found.</td></tr>
                <?php else: foreach ($members as $mb):
                    $tier = pointsTier((int)$mb['points']);
                ?>
                <tr>
                    <td style="font-weight:600;"><?php echo h($mb['full_name']); ?></td>
                    <td style="color:var(--text-muted);"><?php echo h($mb['email']); ?></td>
                    <td><?php echo (int)$mb['total_bookings']; ?></td>
                    <td>
                        <span style="color:#f04550;font-weight:700;">
                            <?php echo number_format($mb['points']); ?>
                        </span> pts
                    </td>
                    <td>
                        <span style="font-weight:600;color:<?php echo tierColor($tier); ?>">
                            <?php echo $tier; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transaction Log -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);">
        <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.1rem;margin:0;">
            Recent Transactions <span style="color:var(--text-muted);font-size:.85rem;font-family:inherit;">(last 30)</span>
        </h3>
    </div>
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Recent loyalty transactions">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Member</th>
                    <th scope="col">Type</th>
                    <th scope="col">Points</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">
                    No transactions yet.
                </td></tr>
                <?php else: foreach ($transactions as $t): ?>
                <tr>
                    <td style="font-size:.82rem;color:var(--text-muted);">
                        <?php echo date('d M Y H:i', strtotime($t['created_at'])); ?>
                    </td>
                    <td><?php echo h($t['full_name']); ?></td>
                    <td>
                        <span class="status-pill <?php echo $t['type'] === 'earned' ? 'status-confirmed' : 'status-cancelled'; ?>">
                            <?php echo h($t['type']); ?>
                        </span>
                    </td>
                    <td style="font-weight:600;color:<?php echo $t['type'] === 'earned' ? '#34a853' : '#f94144'; ?>">
                        <?php echo $t['type'] === 'earned' ? '+' : '-'; ?><?php echo (int)$t['points']; ?>
                    </td>
                    <td style="color:var(--text-muted);"><?php echo h($t['description'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>
