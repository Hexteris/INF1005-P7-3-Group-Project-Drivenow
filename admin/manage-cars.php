<?php
$pageTitle = 'Manage Cars';
require_once '../includes/db-connect.php';
require_once 'admin-header.php';

$message = '';

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM cars WHERE car_id = ?");
    $stmt->bind_param("i", $id);
    $message = $stmt->execute() ? 'success:Car deleted successfully.' : 'error:Failed to delete car.';
    $stmt->close();
}

// TOGGLE AVAILABILITY
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE cars SET is_available = NOT is_available WHERE car_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: /admin/manage-cars.php");
    exit();
}

// ADD / EDIT
$editCar = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $s = $conn->prepare("SELECT * FROM cars WHERE car_id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $editCar = $s->get_result()->fetch_assoc();
    $s->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $make         = trim($_POST['make']         ?? '');
    $model        = trim($_POST['model']        ?? '');
    $plate_no     = trim($_POST['plate_no']     ?? '');
    $category     = $_POST['category']          ?? 'Economy';
    $seats        = (int)($_POST['seats']       ?? 5);
    $price_per_hr = (float)($_POST['price_per_hr'] ?? 0);
    $location     = trim($_POST['location']     ?? '');
    $image_url    = trim($_POST['image_url']    ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $car_id_edit  = (int)($_POST['car_id']      ?? 0);

    if (empty($make) || empty($model) || empty($plate_no) || $price_per_hr <= 0) {
        $message = 'error:Please fill in all required fields.';
    } elseif ($car_id_edit > 0) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE cars SET make=?,model=?,plate_no=?,category=?,seats=?,price_per_hr=?,location=?,image_url=?,is_available=? WHERE car_id=?");
        $stmt->bind_param("ssssidssii", $make,$model,$plate_no,$category,$seats,$price_per_hr,$location,$image_url,$is_available,$car_id_edit);
        $message = $stmt->execute() ? 'success:Car updated successfully.' : 'error:Update failed: '.$conn->error;
        $stmt->close();
        $editCar = null;
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO cars (make,model,plate_no,category,seats,price_per_hr,location,image_url,is_available) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssidss i", $make,$model,$plate_no,$category,$seats,$price_per_hr,$location,$image_url,$is_available);
        $message = $stmt->execute() ? 'success:Car added successfully.' : 'error:Insert failed: '.$conn->error;
        $stmt->close();
    }
}

// Fetch all cars
$cars = $conn->query("SELECT * FROM cars ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

[$msgType, $msgText] = !empty($message) ? explode(':', $message, 2) : ['',''];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;margin:0;">Manage Cars</h2>
    <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#carModal"
        onclick="resetForm()"><i class="bi bi-plus me-1"></i>Add Car</button>
</div>

<?php if ($msgType === 'success'): ?>
    <div class="alert-success mb-4"><?php echo h($msgText); ?></div>
<?php elseif ($msgType === 'error'): ?>
    <div class="alert-error mb-4"><?php echo h($msgText); ?></div>
<?php endif; ?>

<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="dn-table">
            <thead>
                <tr><th>ID</th><th>Car</th><th>Plate</th><th>Category</th><th>$/hr</th><th>Location</th><th>Available</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cars as $c): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?php echo (int)$c['car_id']; ?></td>
                    <td><strong><?php echo h($c['make'].' '.$c['model']); ?></strong></td>
                    <td><?php echo h($c['plate_no']); ?></td>
                    <td><span class="car-badge badge-<?php echo strtolower(h($c['category'])); ?>"><?php echo h($c['category']); ?></span></td>
                    <td>S$ <?php echo number_format($c['price_per_hr'],2); ?></td>
                    <td><?php echo h($c['location']); ?></td>
                    <td>
                        <a href="?toggle=<?php echo (int)$c['car_id']; ?>">
                            <span class="status-pill <?php echo $c['is_available'] ? 'status-confirmed' : 'status-cancelled'; ?>">
                                <?php echo $c['is_available'] ? 'Yes' : 'No'; ?>
                            </span>
                        </a>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm" style="background:var(--bg-raised);color:var(--text);border:1px solid var(--border);"
                                onclick='editCar(<?php echo json_encode($c); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?delete=<?php echo (int)$c['car_id']; ?>"
                                onclick="return confirmDelete('Delete this car permanently?')"
                                class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="carModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border);">
            <div class="modal-header" style="border-color:var(--border);">
                <h5 class="modal-title" id="modalTitle" style="font-family:'Bebas Neue',sans-serif;font-size:1.5rem;">Add Car</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="car_id" id="modal_car_id" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Make *</label>
                            <input type="text" class="form-control" name="make" id="m_make"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="Toyota" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Model *</label>
                            <input type="text" class="form-control" name="model" id="m_model"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="Corolla" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Plate No. *</label>
                            <input type="text" class="form-control" name="plate_no" id="m_plate"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="SBA1234A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Category *</label>
                            <select class="form-select" name="category" id="m_category"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);">
                                <option>Economy</option><option>Comfort</option><option>SUV</option><option>Premium</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Seats</label>
                            <input type="number" class="form-control" name="seats" id="m_seats" value="5" min="1" max="12"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Price/hr (S$) *</label>
                            <input type="number" class="form-control" name="price_per_hr" id="m_price" step="0.50" min="1"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="8.50" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Available</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="is_available" id="m_avail" checked>
                                <label class="form-check-label" for="m_avail" style="color:var(--text-muted);">Yes</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Location</label>
                            <input type="text" class="form-control" name="location" id="m_location"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="Tampines Hub">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="color:var(--text-muted);font-size:.85rem;">Image URL</label>
                            <input type="url" class="form-control" name="image_url" id="m_img"
                                style="background:var(--bg-raised);border:1px solid var(--border);color:var(--text);"
                                placeholder="https://...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color:var(--border);">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Save Car</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Add Car';
    document.getElementById('modal_car_id').value = '0';
    ['m_make','m_model','m_plate','m_location','m_img'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('m_seats').value = 5;
    document.getElementById('m_price').value = '';
    document.getElementById('m_category').value = 'Economy';
    document.getElementById('m_avail').checked = true;
}
function editCar(c) {
    document.getElementById('modalTitle').textContent = 'Edit Car';
    document.getElementById('modal_car_id').value = c.car_id;
    document.getElementById('m_make').value     = c.make;
    document.getElementById('m_model').value    = c.model;
    document.getElementById('m_plate').value    = c.plate_no;
    document.getElementById('m_category').value = c.category;
    document.getElementById('m_seats').value    = c.seats;
    document.getElementById('m_price').value    = c.price_per_hr;
    document.getElementById('m_location').value = c.location || '';
    document.getElementById('m_img').value      = c.image_url || '';
    document.getElementById('m_avail').checked  = c.is_available == 1;
    new bootstrap.Modal(document.getElementById('carModal')).show();
}
</script>

<?php require_once 'admin-footer.php'; ?>
