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
// include __DIR__ . '/../partials/header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify($_POST['_token'] ?? null);
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Fetch current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $_SESSION['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        $message = 'Current password is incorrect';
    } elseif ($new !== $confirm) {
        $message = 'New passwords do not match';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=:hash WHERE username=:username");
        $stmt->execute(['hash' => $hash,'username' => $_SESSION['username']]);
        $message = 'Password updated successfully';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - Maintenance Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
    <div class="card p-4 shadow" style="width: 400px;">
        <h3 class="text-center mb-3">Change Password</h3>

        <?php if ($message) : ?>
            <div class="alert <?= strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>

        <div class="text-center mt-3">
            <a href="/dashboard.php" class="btn btn-link">Back to Dashboard</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
