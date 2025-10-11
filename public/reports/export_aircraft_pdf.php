<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

use TCPDF;

$q      = $_GET['q'] ?? '';
$type   = $_GET['type'] ?? '';
$engine = $_GET['engine'] ?? '';
$sort   = $_GET['sort'] ?? 'tail';
$dir    = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$sortMap = ['tail' => 'tail_number','type' => 'aircraft_type','engine' => 'engine_type'];
$orderBy = $sortMap[$sort] ?? $sortMap['tail'];

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = 'tail_number LIKE :q';
    $params[':q'] = "%$q%";
}
if ($type !== '') {
    $where[] = 'aircraft_type = :type';
    $params[':type'] = $type;
}
if ($engine !== '') {
    $where[] = 'engine_type = :engine';
    $params[':engine'] = $engine;
}

$sql = "SELECT tail_number, aircraft_type, engine_type
        FROM aircraft
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy $dir, aircraft_id $dir";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Aircraft Export (Filtered)', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$html = '<table border="1" cellpadding="4">
<tr bgcolor="#eaeaea"><th width="60">Tail</th><th width="120">Aircraft Type</th><th width="120">Engine Type</th></tr>';
foreach ($data as $r) {
    $html .= '<tr><td>' . htmlspecialchars($r['tail_number']) . '</td>' .
           '<td>' . htmlspecialchars($r['aircraft_type']) . '</td>' .
           '<td>' . htmlspecialchars($r['engine_type']) . '</td></tr>';
}
$html .= '</table>';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Output('aircraft_export.pdf', 'I');
