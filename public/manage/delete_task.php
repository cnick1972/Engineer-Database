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

$taskId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
csrf_verify($_POST['_token'] ?? null);

if ($taskId <= 0) {
    header('Location: /tasks.php?err=' . urlencode('Invalid task id'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT task_id FROM maintenance_tasks WHERE task_id = :id');
    $stmt->execute([':id' => $taskId]);
    if (!$stmt->fetch()) {
        header('Location: /tasks.php?err=' . urlencode('Task not found'));
        exit;
    }

    $del = $pdo->prepare('DELETE FROM maintenance_tasks WHERE task_id = :id');
    $del->execute([':id' => $taskId]);

    header('Location: /tasks.php?msg=' . urlencode('Task deleted successfully'));
    exit;
} catch (PDOException $e) {
    header('Location: /tasks.php?err=' . urlencode('Unable to delete task'));
    exit;
}
