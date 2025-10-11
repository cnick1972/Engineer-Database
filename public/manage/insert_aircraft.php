<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';
include __DIR__ . '/../partials/header.php';

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify($_POST['_token'] ?? null);
    $tail_number = $_POST['tail_number'];
    $aircraft_type = $_POST['aircraft_type'];
    $engine_type = $_POST['engine_type'];

    $stmt = $pdo->prepare("INSERT INTO aircraft (tail_number, aircraft_type, engine_type) VALUES (:tail_number, :aircraft_type, :engine_type)");
    if ($stmt->execute(['tail_number' => $tail_number, 'aircraft_type' => $aircraft_type, 'engine_type' => $engine_type])) {
        $message = '<div class="alert alert-success">Aircraft added successfully.</div>';
    } else {
        $message = '<div class="alert alert-danger">Error adding aircraft.</div>';
    }
}
?>

<h2>Add Aircraft</h2>
<?= $message ?>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Tail Number</label>
        <input type="text" class="form-control" name="tail_number" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Aircraft Type</label>
        <input type="text" class="form-control" name="aircraft_type" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Engine Type</label>
        <input type="text" class="form-control" name="engine_type" required>
    </div>
    <button type="submit" class="btn btn-primary">Add Aircraft</button>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>