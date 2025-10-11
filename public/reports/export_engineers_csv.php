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

$q    = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$dir  = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$sortMap = ['name' => 'name','licence' => 'licence_number'];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q OR licence_number LIKE :q)';
    $params[':q'] = "%$q%";
}

$sql = "SELECT name, licence_number
        FROM engineers
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy $dir, engineer_id $dir";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="engineers_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Name','Licence Number']);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [$r['name'],$r['licence_number']]);
}
fclose($out);
