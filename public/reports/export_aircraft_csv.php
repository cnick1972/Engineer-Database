<?php

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="aircraft_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Tail','Aircraft Type','Engine Type']);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [$r['tail_number'],$r['aircraft_type'],$r['engine_type']]);
}
fclose($out);
