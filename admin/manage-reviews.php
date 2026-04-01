<?php
$pageTitle = 'Manage Reviews';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';
?>
<main id="main-content" aria-label="Manage reviews">
<?php
$message = '';

// Toggle visibility (POST)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='toggle_visible') {
    $rid = (int)($_POST['review_id']??0);
    // Add is_visible column if it doesn't exist yet
    $conn->query("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) NOT NULL DEFAULT 1");
    $stmt = $conn->prepare("UPDATE reviews SET is_visible = NOT is_visible WHERE review_id=?");
    $stmt->bind_param("i",$rid);
    $message = $stmt->execute() ? 'success:Visibility updated.' : 'error:Update failed.';
    $stmt->close();
}

// Delete (GET)
if (isset($_GET['delete'])) {
    $id=$conn->prepare("DELETE FROM reviews WHERE review_id=?");
    $id->bind_param("i",(int)$_GET['delete']);
    $message = $id->execute() ? 'success:Review deleted.' : 'error:Delete failed.';
    $id->close();
}

// Filters
$filterRating = isset($_GET['rating'])&&is_numeric($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filterCar    = trim($_GET['car']??'');
$search       = trim($_GET['search']??'');
$where='WHERE 1=1'; $params=[]; $types='';
if ($filterRating>=1&&$filterRating<=5){ $where.=" AND r.rating=?"; $params[]=$filterRating; $types.='i'; }
if ($filterCar!==''){$like='%'.$filterCar.'%';$where.=" AND (c.make LIKE ? OR c.model LIKE ?)";$params[]=$like;$params[]=$like;$types.='ss';}
if ($search!==''){$like='%'.$search.'%';$where.=" AND (m.full_name LIKE ? OR r.comment LIKE ?)";$params[]=$like;$params[]=$like;$types.='ss';}

// Check is_visible column
$hasVis = (bool)$conn->query("SHOW COLUMNS FROM reviews LIKE 'is_visible'")->num_rows;
$visSel = $hasVis ? ", r.is_visible" : ", 1 AS is_visible";

$stmt=$conn->prepare("
    SELECT r.review_id,r.rating,r.comment,r.created_at,
           m.member_id,m.full_name,m.email,
           c.car_id,c.make,c.model,c.category $visSel
    FROM reviews r
    JOIN members m ON r.member_id=m.member_id
    JOIN cars c ON r.car_id=c.car_id
    $where ORDER BY r.created_at DESC");
if (!empty($params)) $stmt->bind_param($types,...$params);
$stmt->execute();
$reviews=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$dist=$conn->query("SELECT rating,COUNT(*) AS cnt FROM reviews GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
$totalRev=array_sum(array_column($dist,'cnt'));
$distMap=[]; foreach($dist as $d) $distMap[(int)$d['rating']]=(int)$d['cnt'];
$avgRating=$totalRev>0 ? array_sum(array_map(fn($d)=>$d['rating']*$d['cnt'],$dist))/$totalRev : 0;

$carSummary=$conn->query("
    SELECT c.make,c.model,c.car_id,COUNT(r.review_id) AS total,ROUND(AVG(r.rating),1) AS avg_rating
    FROM reviews r JOIN cars c ON r.car_id=c.car_id
    GROUP BY c.car_id ORDER BY avg_rating DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

[$msgType,$msgText]=!empty($message)?explode(':',$message,2):['',''];

function stars(int $n,int $max=5):string{
    $s='';
    for($i=1;$i<=$max;$i++) $s.=$i<=$n?'<i class="bi bi-star-fill star-on" aria-hidden="true"></i>':'<i class="bi bi-star star-off" aria-hidden="true"></i>';
    return $s;
}
?>

<div class="page-header-row">
    <h2>Reviews</h2>
    <span style="font-size:.82rem;color:var(--text-muted);"><?php echo count($reviews); ?> result<?php echo count($reviews)!==1?'s':''; ?></span>
</div>

<?php if($msgType==='success'): ?><div class="alert-success mb-3" role="alert"><?php echo h($msgText); ?></div>
<?php elseif($msgType==='error'): ?><div class="alert-error mb-3" role="alert"><?php echo h($msgText); ?></div><?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="mini-stat"><div class="mini-stat-val text-accent"><?php echo $totalRev; ?></div><div class="mini-stat-label">Total Reviews</div></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><div class="mini-stat-val" style="color:#fbbc05;"><?php echo number_format($avgRating,1); ?></div><div class="mini-stat-label">Average Rating</div></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><div class="mini-stat-val"><?php echo ($distMap[5]??0)+($distMap[4]??0); ?></div><div class="mini-stat-label">4–5 Star Reviews</div></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><div class="mini-stat-val" style="color:#f94144;"><?php echo ($distMap[1]??0)+($distMap[2]??0); ?></div><div class="mini-stat-label">1–2 Star Reviews</div></div></div>
</div>

<!-- Insights -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="dn-card">
            <div class="dn-card-header"><h3 class="dn-card-title"><i class="bi bi-bar-chart-fill text-accent" aria-hidden="true"></i> Rating Breakdown</h3></div>
            <div style="padding:1rem 1.25rem;">
                <?php for($star=5;$star>=1;$star--): $cnt=$distMap[$star]??0; $pct=$totalRev?round($cnt/$totalRev*100):0; ?>
                <div class="rating-bar-wrap">
                    <span style="width:3.5rem;text-align:right;font-size:.78rem;color:var(--text-muted);"><?php echo $star; ?> <i class="bi bi-star-fill star-on" style="font-size:.65rem;" aria-hidden="true"></i></span>
                    <div class="rating-bar-track" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo $star; ?> star: <?php echo $pct; ?>%">
                        <div class="rating-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <span style="width:2.5rem;font-size:.78rem;color:var(--text-muted);"><?php echo $cnt; ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dn-card">
            <div class="dn-card-header"><h3 class="dn-card-title"><i class="bi bi-trophy text-accent" aria-hidden="true"></i> Top-Rated Cars</h3></div>
            <div style="padding:.25rem 0;">
                <?php if(empty($carSummary)): ?>
                <div class="empty-state-box"><p>No car review data yet.</p></div>
                <?php else: foreach($carSummary as $i=>$cs): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 1.25rem;<?php echo $i<count($carSummary)-1?'border-bottom:1px solid var(--border);':''; ?>">
                    <div>
                        <div style="font-weight:600;font-size:.9rem;"><?php echo h($cs['make'].' '.$cs['model']); ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);"><?php echo (int)$cs['total']; ?> review<?php echo (int)$cs['total']!==1?'s':''; ?></div>
                    </div>
                    <div aria-label="<?php echo h($cs['avg_rating']); ?> out of 5"><?php echo stars((int)round((float)$cs['avg_rating'])); ?> <small style="color:var(--text-muted);">(<?php echo $cs['avg_rating']; ?>)</small></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;" role="search" aria-label="Filter reviews">
    <label for="srch" class="visually-hidden">Search member or comment</label>
    <input type="search" id="srch" name="search" class="dn-search" placeholder="Member or comment…" value="<?php echo h($search); ?>">
    <label for="fcar" class="visually-hidden">Car</label>
    <input type="text" id="fcar" name="car" class="dn-search" placeholder="Car make/model…" value="<?php echo h($filterCar); ?>">
    <label for="frat" class="visually-hidden">Rating</label>
    <select id="frat" name="rating" class="dn-select" aria-label="Filter by rating">
        <option value="">All ratings</option>
        <?php for($s=5;$s>=1;$s--): ?>
        <option value="<?php echo $s; ?>" <?php echo $filterRating===$s?'selected':''; ?>><?php echo $s; ?> Star<?php echo $s!==1?'s':''; ?></option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-accent btn-sm">Apply</button>
    <?php if($filterRating||$filterCar||$search): ?><a href="<?php echo BASE; ?>/admin/manage-reviews.php" class="btn btn-outline-light btn-sm">Clear</a><?php endif; ?>
</form>

<!-- Table -->
<div class="dn-card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table" aria-label="Reviews list">
            <thead>
                <tr>
                    <th scope="col">#</th><th scope="col">Member</th><th scope="col">Car</th>
                    <th scope="col">Rating</th><th scope="col">Comment</th>
                    <th scope="col">Date</th><th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($reviews)): ?>
            <tr><td colspan="7"><div class="empty-state-box"><i class="bi bi-star-slash" aria-hidden="true"></i><p>No reviews found.</p></div></td></tr>
            <?php else: foreach($reviews as $r): $vis=(bool)$r['is_visible']; ?>
            <tr style="<?php echo !$vis?'opacity:.5;':''; ?>">
                <td style="color:var(--text-muted);font-size:.8rem;"><?php echo (int)$r['review_id']; ?></td>
                <td>
                    <div style="font-weight:600;font-size:.88rem;"><?php echo h($r['full_name']); ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted);"><?php echo h($r['email']); ?></div>
                </td>
                <td>
                    <div style="font-size:.88rem;"><?php echo h($r['make'].' '.$r['model']); ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted);"><?php echo h($r['category']); ?></div>
                </td>
                <td><span aria-label="<?php echo (int)$r['rating']; ?> out of 5"><?php echo stars((int)$r['rating']); ?></span></td>
                <td style="max-width:260px;white-space:normal;font-size:.85rem;"><?php echo h($r['comment']??'–'); ?></td>
                <td style="white-space:nowrap;font-size:.8rem;"><?php echo date('d M Y',strtotime($r['created_at'])); ?></td>
                <td style="white-space:nowrap;">
                    <?php if($hasVis): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_visible">
                        <input type="hidden" name="review_id" value="<?php echo (int)$r['review_id']; ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="<?php echo $vis?'Hide':'Show'; ?>" aria-label="<?php echo $vis?'Hide':'Show'; ?> review">
                            <i class="bi bi-<?php echo $vis?'eye-slash':'eye'; ?>" aria-hidden="true"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="?delete=<?php echo (int)$r['review_id']; ?>"
                       onclick="return confirmDelete('Permanently delete this review?')"
                       class="btn btn-outline-danger btn-sm"
                       aria-label="Delete review by <?php echo h($r['full_name']); ?>">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once 'admin-footer.php'; ?>