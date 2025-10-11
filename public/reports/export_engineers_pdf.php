<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

use TCPDF;

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
$data = $stmt->fetchAll();

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Engineers Export (Filtered)', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$html = '<table border="1" cellpadding="4">
<tr bgcolor="#eaeaea"><th width="100">Name</th><th width="80">Licence Number</th></tr>';
foreach ($data as $r) {
    $html .= '<tr><td>' . htmlspecialchars($r['name']) . '</td>' .
           '<td>' . htmlspecialchars($r['licence_number']) . '</td></tr>';
}
$html .= '</table>';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Output('engineers_export.pdf', 'I');
