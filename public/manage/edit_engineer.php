<?php

/**
 * Maintenance Log Application
 *
 * Copyright (c) 2024 The Maintenance Log Developers.
 * All rights reserved.
 *
 * This source code is proprietary and confidential. Unauthorized copying,
 * modification, distribution, or disclosure is strictly prohibited without
 * prior written consent.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';
include __DIR__ . '/../partials/header.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">No engineer ID specified.</div>';
    include __DIR__ . '/../partials/footer.php';
    exit;
}
$engineer_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM engineers WHERE engineer_id=:id");
$stmt->execute(['id' => $engineer_id]);
$eng = $stmt->fetch();

if (!$eng) {
    echo '<div class="alert alert-danger">Engineer not found.</div>';
    include __DIR__ . '/../partials/footer.php';
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify($_POST['_token'] ?? null);
    $name = $_POST['name'];
    $licence_number = $_POST['licence_number'];
    $stmt = $pdo->prepare("UPDATE engineers SET name=:name, licence_number=:licence_number WHERE engineer_id=:id");
    $stmt->execute(['name' => $name,'licence_number' => $licence_number,'id' => $engineer_id]);
    echo '<div class="alert alert-success">Engineer updated successfully.</div>';
}
?>

<h2>Edit Engineer</h2>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($eng['name']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Licence Number</label>
        <input type="text" class="form-control" name="licence_number" value="<?= htmlspecialchars($eng['licence_number']) ?>" required>
    </div>
    <button type="submit" class="btn btn-success">Update Engineer</button>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>
