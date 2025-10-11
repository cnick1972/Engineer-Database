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

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify($_POST['_token'] ?? null);
    $name = $_POST['name'];
    $licence_number = $_POST['licence_number'];

    $stmt = $pdo->prepare("INSERT INTO engineers (name, licence_number) VALUES (:name, :licence_number)");
    if ($stmt->execute(['name' => $name, 'licence_number' => $licence_number])) {
        $message = '<div class="alert alert-success">Engineer added successfully.</div>';
    } else {
        $message = '<div class="alert alert-danger">Error adding engineer.</div>';
    }
}
?>

<h2>Add Certifying Engineer</h2>
<?= $message ?>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" class="form-control" name="name" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Licence Number</label>
        <input type="text" class="form-control" name="licence_number" required>
    </div>
    <button type="submit" class="btn btn-primary">Add Engineer</button>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>
