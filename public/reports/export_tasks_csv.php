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

// Same filter building as tasks.php
$where  = ["1=1"];
$params = [];

$aircraft_id = $_GET['aircraft_id'] ?? '';
$engineer_id = $_GET['engineer_id'] ?? '';
$ata_id      = $_GET['ata_id'] ?? '';
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$q           = trim($_GET['q'] ?? '');
$sort        = $_GET['sort'] ?? 'date';
$dir         = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$sortMap = [
  'date' => 'mt.date_performed',
  'aircraft' => 'a.tail_number',
  'ata' => 'ata.ata_number',
  'engineer' => 'e.name'
];
$orderBy = $sortMap[$sort] ?? $sortMap['date'];

if ($aircraft_id !== '') {
    $where[] = "mt.aircraft_id = :aircraft_id";
    $params[':aircraft_id'] = $aircraft_id;
}
if ($engineer_id !== '') {
    $where[] = "mt.engineer_id = :engineer_id";
    $params[':engineer_id'] = $engineer_id;
}
if ($ata_id !== '') {
    $where[] = "mt.ata_id = :ata_id";
    $params[':ata_id'] = $ata_id;
}
if ($date_from !== '') {
    $where[] = "mt.date_performed >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = "mt.date_performed <= :date_to";
    $params[':date_to'] = $date_to;
}
if ($q !== '') {
    $where[] = "(mt.task_description LIKE :q OR mt.reference LIKE :q OR mt.calibrated_tools LIKE :q)";
    $params[':q'] = "%$q%";
}

$whereSql = implode(' AND ', $where);

$sql = "
  SELECT 
    mt.date_performed, a.tail_number, a.aircraft_type, a.engine_type,
    ata.ata_number, ata.description AS ata_desc,
    mt.task_description, mt.reference, mt.calibrated_tools,
    mt.cdccl_task, mt.ezap_task, mt.ewis_task,
    e.name AS engineer_name, e.licence_number
  FROM maintenance_tasks mt
  JOIN aircraft a  ON mt.aircraft_id = a.aircraft_id
  JOIN engineers e ON mt.engineer_id = e.engineer_id
  JOIN ata ON mt.ata_id = ata.ata_id
  WHERE $whereSql
  ORDER BY $orderBy $dir, mt.task_id $dir
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tasks_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Tail','Type','Engine','ATA','ATA Desc','Task','Reference','Tools','CDCCL','EZAP','EWIS','Engineer','Licence']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $row['date_performed'],
        $row['tail_number'],
        $row['aircraft_type'],
        $row['engine_type'],
        $row['ata_number'],
        $row['ata_desc'],
        $row['task_description'],
        $row['reference'],
        $row['calibrated_tools'],
        (int)$row['cdccl_task'],
        (int)$row['ezap_task'],
        (int)$row['ewis_task'],
        $row['engineer_name'],
        $row['licence_number']
    ]);
}
fclose($out);
