<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';   // omit on login/change_password pages
include __DIR__ . '/../partials/header.php';


if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">No aircraft ID specified.</div>';
    include __DIR__ . '/../partials/footer.php';
    exit;
}

$aircraft_id = $_GET['id'];

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM aircraft WHERE aircraft_id = :id");
$stmt->execute(['id' => $aircraft_id]);
$aircraft = $stmt->fetch();

if (!$aircraft) {
    echo '<div class="alert alert-danger">Aircraft not found.</div>';
    include __DIR__ . '/../partials/footer.php';
    exit;
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify($_POST['_token'] ?? null);
    $tail_number = $_POST['tail_number'];
    $aircraft_type = $_POST['aircraft_type'];
    $engine_type = $_POST['engine_type'];

    $stmt = $pdo->prepare("UPDATE aircraft SET tail_number=:tail_number, aircraft_type=:aircraft_type, engine_type=:engine_type WHERE aircraft_id=:id");
    $stmt->execute([
        'tail_number' => $tail_number,
        'aircraft_type' => $aircraft_type,
        'engine_type' => $engine_type,
        'id' => $aircraft_id
    ]);

    echo '<div class="alert alert-success">Aircraft updated successfully.</div>';
}
?>

<h2>Edit Aircraft</h2>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Tail Number</label>
        <input type="text" class="form-control" name="tail_number" value="<?= htmlspecialchars($aircraft['tail_number']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Aircraft Type</label>
        <input type="text" class="form-control" name="aircraft_type" value="<?= htmlspecialchars($aircraft['aircraft_type']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Engine Type</label>
        <input type="text" class="form-control" name="engine_type" value="<?= htmlspecialchars($aircraft['engine_type']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Aircraft</button>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>