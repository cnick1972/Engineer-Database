<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

$aircraftId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
csrf_verify($_POST['_token'] ?? null);
if ($aircraftId <= 0) {
    header('Location: /aircraft.php?err=' . urlencode('Invalid aircraft id'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT aircraft_id FROM aircraft WHERE aircraft_id = :id');
    $stmt->execute([':id' => $aircraftId]);
    if (!$stmt->fetch()) {
        header('Location: /aircraft.php?err=' . urlencode('Aircraft not found'));
        exit;
    }

    $del = $pdo->prepare('DELETE FROM aircraft WHERE aircraft_id = :id');
    $del->execute([':id' => $aircraftId]);

    header('Location: /aircraft.php?msg=' . urlencode('Aircraft deleted successfully'));
    exit;
} catch (PDOException $e) {
    $message = 'Unable to delete aircraft';
    if ((int)$e->getCode() === 23000) {
        $message = 'Cannot delete aircraft while tasks reference it';
    }
    header('Location: /aircraft.php?err=' . urlencode($message));
    exit;
}
