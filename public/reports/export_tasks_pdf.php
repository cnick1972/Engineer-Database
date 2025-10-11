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

use TCPDF;

// Build filters like tasks.php
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
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('MaintenanceDB');
$pdf->SetAuthor('MaintenanceDB');
$pdf->SetTitle('Tasks Export');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Header
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Tasks Export (Filtered)', 0, 1, 'C');
$pdf->Ln(2);

// Table
$pdf->SetFont('helvetica', 'B', 9);
$tbl = '<table border="1" cellpadding="3">
<tr bgcolor="#eaeaea">
  <th width="22">Date</th>
  <th width="30">Tail</th>
  <th width="38">Type</th>
  <th width="30">Engine</th>
  <th width="18">ATA</th>
  <th width="60">Task</th>
  <th width="30">Ref</th>
  <th width="35">Tools</th>
  <th width="18">CDCCL</th>
  <th width="18">EZAP</th>
  <th width="18">EWIS</th>
  <th width="40">Engineer</th>
</tr>';
$pdf->SetFont('helvetica', '', 9);

foreach ($data as $r) {
    $tbl .= '<tr>
    <td>' . htmlspecialchars($r['date_performed']) . '</td>
    <td>' . htmlspecialchars($r['tail_number']) . '</td>
    <td>' . htmlspecialchars($r['aircraft_type']) . '</td>
    <td>' . htmlspecialchars($r['engine_type']) . '</td>
    <td>' . htmlspecialchars($r['ata_number']) . '</td>
    <td>' . htmlspecialchars($r['task_description']) . '</td>
    <td>' . htmlspecialchars($r['reference']) . '</td>
    <td>' . htmlspecialchars($r['calibrated_tools']) . '</td>
    <td style="text-align:center;">' . ((int)$r['cdccl_task'] ? 'Yes' : '') . '</td>
    <td style="text-align:center;">' . ((int)$r['ezap_task']  ? 'Yes' : '') . '</td>
    <td style="text-align:center;">' . ((int)$r['ewis_task']  ? 'Yes' : '') . '</td>
    <td>' . htmlspecialchars($r['engineer_name']) . ' (' . htmlspecialchars($r['licence_number']) . ')</td>
  </tr>';
}
$tbl .= '</table>';

$pdf->writeHTML($tbl, true, false, false, false, '');
$pdf->Output('tasks_export.pdf', 'I');
