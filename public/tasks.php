<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';
include __DIR__ . '/partials/header.php';

// Fetch dropdown filter data
$aircraft  = $pdo->query("SELECT aircraft_id, tail_number FROM aircraft ORDER BY tail_number")->fetchAll(PDO::FETCH_ASSOC);
$engineers = $pdo->query("SELECT engineer_id, name FROM engineers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$ata       = $pdo->query("SELECT ata_id, ata_number, description FROM ata ORDER BY CAST(ata_number AS UNSIGNED), ata_number")->fetchAll(PDO::FETCH_ASSOC);

$params = [];
$where = [];

// Apply filters
if (!empty($_GET['aircraft_id'])) {
    $where[] = "mt.aircraft_id = :aircraft_id";
    $params['aircraft_id'] = $_GET['aircraft_id'];
}
if (!empty($_GET['engineer_id'])) {
    $where[] = "mt.engineer_id = :engineer_id";
    $params['engineer_id'] = $_GET['engineer_id'];
}
if (!empty($_GET['ata_id'])) {
    $where[] = "mt.ata_id = :ata_id";
    $params['ata_id'] = $_GET['ata_id'];
}
if (!empty($_GET['date_from'])) {
    $where[] = "mt.date_performed >= :date_from";
    $params['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = "mt.date_performed <= :date_to";
    $params['date_to'] = $_GET['date_to'];
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT 
        mt.task_id,
        mt.date_performed,
        mt.WO_number,                -- NEW: include W/O Number
        mt.task_description,
        mt.task_card_seq,
        mt.check_pack_reference,
        a.tail_number,
        a.aircraft_type,
        e.name AS engineer_name,
        e.licence_number,
        ata.ata_number,
        ata.description AS ata_desc
    FROM maintenance_tasks mt
    JOIN aircraft a ON mt.aircraft_id = a.aircraft_id
    JOIN engineers e ON mt.engineer_id = e.engineer_id
    JOIN ata ON mt.ata_id = ata.ata_id
    $whereSQL
    ORDER BY mt.date_performed DESC, mt.task_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$flashMsg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$flashErr = isset($_GET['err']) ? trim((string)$_GET['err']) : '';
?>

<div class="container my-4">
  <?php if ($flashMsg) : ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashMsg) ?></div>
  <?php endif; ?>
  <?php if ($flashErr) : ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashErr) ?></div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Maintenance Tasks</h2>
    <a href="/manage/insert_task.php" class="btn btn-sm btn-primary">Add Task</a>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-light"><strong>Filter Tasks</strong></div>
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Aircraft</label>
          <select name="aircraft_id" class="form-select">
            <option value="">All</option>
            <?php foreach ($aircraft as $a) : ?>
              <option value="<?= $a['aircraft_id'] ?>" <?= ($_GET['aircraft_id'] ?? '') == $a['aircraft_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['tail_number']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Engineer</label>
          <select name="engineer_id" class="form-select">
            <option value="">All</option>
            <?php foreach ($engineers as $eng) : ?>
              <option value="<?= $eng['engineer_id'] ?>" <?= ($_GET['engineer_id'] ?? '') == $eng['engineer_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($eng['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">ATA Chapter</label>
          <select name="ata_id" class="form-select">
            <option value="">All</option>
            <?php foreach ($ata as $ch) : ?>
              <option value="<?= $ch['ata_id'] ?>" <?= ($_GET['ata_id'] ?? '') == $ch['ata_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ch['ata_number'] . ' - ' . $ch['description']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Date From</label>
          <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>

        <div class="col-md-2">
          <label class="form-label">Date To</label>
          <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>

        <div class="col-md-2 align-self-end">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-secondary text-white">Task List</div>
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>Date</th>
            <th>W/O Number</th> <!-- NEW -->
            <th>Aircraft</th>
            <th>ATA</th>
            <th>Task Description</th>
            <th>Engineer</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($tasks) : ?>
                <?php foreach ($tasks as $t) : ?>
              <tr>
                <td><?= htmlspecialchars(date('d M Y', strtotime($t['date_performed']))) ?></td>
                <td><?= htmlspecialchars($t['WO_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($t['tail_number'] . ' (' . $t['aircraft_type'] . ')') ?></td>
                <td><?= htmlspecialchars($t['ata_number']) ?> - <?= htmlspecialchars($t['ata_desc']) ?></td>
                <td><?= nl2br(htmlspecialchars($t['task_description'])) ?></td>
                <td><?= htmlspecialchars($t['engineer_name']) ?> (<?= htmlspecialchars($t['licence_number']) ?>)</td>
                <td>
                  <a href="/manage/edit_task.php?id=<?= $t['task_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <form method="post" action="/manage/delete_task.php" class="d-inline" onsubmit="return confirm('Delete this task?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$t['task_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
                <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="7" class="text-center">No tasks found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
