<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

$engineerId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
csrf_verify($_POST['_token'] ?? null);
if ($engineerId <= 0) {
    header('Location: /engineers.php?err=' . urlencode('Invalid engineer id'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT engineer_id FROM engineers WHERE engineer_id = :id');
    $stmt->execute([':id' => $engineerId]);
    if (!$stmt->fetch()) {
        header('Location: /engineers.php?err=' . urlencode('Engineer not found'));
        exit;
    }

    $del = $pdo->prepare('DELETE FROM engineers WHERE engineer_id = :id');
    $del->execute([':id' => $engineerId]);

    header('Location: /engineers.php?msg=' . urlencode('Engineer deleted successfully'));
    exit;
} catch (PDOException $e) {
    $message = 'Unable to delete engineer';
    if ((int)$e->getCode() === 23000) {
        $message = 'Cannot delete engineer while tasks reference them';
    }
    header('Location: /engineers.php?err=' . urlencode($message));
    exit;
}
