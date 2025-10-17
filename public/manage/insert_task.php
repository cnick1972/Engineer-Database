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

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';
include __DIR__ . '/../partials/header.php';

// Dropdown data
$aircraft  = $pdo->query("SELECT aircraft_id, tail_number, aircraft_type, engine_type FROM aircraft ORDER BY tail_number")->fetchAll(PDO::FETCH_ASSOC);
$engineers = $pdo->query("SELECT engineer_id, name FROM engineers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$ata       = $pdo->query("SELECT ata_id, ata_number, description FROM ata ORDER BY CAST(ata_number AS UNSIGNED), ata_number")->fetchAll(PDO::FETCH_ASSOC);
// Load available task types
$types = $pdo->query('SELECT ID, tasks FROM task_types ORDER BY tasks')->fetchAll(PDO::FETCH_ASSOC);


$errors = [];
$old = [
  'date_performed'       => $_POST['date_performed']       ?? '',
  'aircraft_id'          => $_POST['aircraft_id']          ?? '',
  'engineer_id'          => $_POST['engineer_id']          ?? '',
  'ata_id'               => $_POST['ata_id']               ?? '',
  'task_description'     => $_POST['task_description']     ?? '',
  'WO_number'            => $_POST['WO_number']            ?? '', // <-- use existing column name
  'task_card_seq'        => $_POST['task_card_seq']        ?? '',
  'check_pack_reference' => $_POST['check_pack_reference'] ?? '',
  'reference'            => $_POST['reference']            ?? '',
  'calibrated_tools'     => $_POST['calibrated_tools']     ?? '',
  'cdccl_task'           => isset($_POST['cdccl_task']) ? '1' : '0',
  'ezap_task'            => isset($_POST['ezap_task'])  ? '1' : '0',
  'ewis_task'            => isset($_POST['ewis_task'])  ? '1' : '0',
  'awl_task'             => isset($_POST['awl_task'])   ? '1' : '0',
  'task_type'          => $_POST['task_type']          ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_token'] ?? null);
    if ($old['date_performed'] === '') {
        $errors[] = "Date is required.";
    }
    if ($old['aircraft_id'] === '') {
        $errors[] = "Aircraft is required.";
    }
    if ($old['engineer_id'] === '') {
        $errors[] = "Engineer is required.";
    }
    if ($old['ata_id'] === '') {
        $errors[] = "ATA chapter is required.";
    }
    if (trim($old['task_description']) === '') {
        $errors[] = "Task description is required.";
    }

  // Adjust length if your schema defines a different size for WO_number
    if (mb_strlen($old['WO_number']) > 20) {
        $errors[] = "W/O Number must be ≤ 20 characters.";
    }
    if (mb_strlen($old['task_card_seq']) > 5) {
        $errors[] = "Task Card Seq. # must be ≤ 5 characters.";
    }
    if (mb_strlen($old['check_pack_reference']) > 24) {
        $errors[] = "Check Pack Reference must be ≤ 24 characters.";
    }

    $task_type = (int)($_POST['task_type'] ?? 0);
        // Must choose a valid type (you said this will become NOT NULL)
    if ($task_type <= 0) {
        $errors[] = 'Please choose a task type.';
    } else {
        $chk = $pdo->prepare('SELECT 1 FROM task_types WHERE ID = :id');
        $chk->execute([':id' => $task_type]);
        if (!$chk->fetchColumn()) {
            $errors[] = "Selected task type is not valid.";
        }
    }



    if (!$errors) {
        $sql = "INSERT INTO maintenance_tasks
              (user_id, date_performed, aircraft_id, engineer_id, ata_id, task_description,
               WO_number, task_card_seq, check_pack_reference,
               cdccl_task, ezap_task, ewis_task, awl_task, reference, calibrated_tools,
               task_type)
            VALUES
              (:user_id, :date_performed, :aircraft_id, :engineer_id, :ata_id, :task_description,
               :WO_number, :task_card_seq, :check_pack_reference,
               :cdccl_task, :ezap_task, :ewis_task, :awl_task, :reference, :calibrated_tools,
               :task_type)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
        ':user_id'              => $_SESSION['user_id'],  
        ':date_performed'       => $old['date_performed'],
        ':aircraft_id'          => (int)$old['aircraft_id'],
        ':engineer_id'          => (int)$old['engineer_id'],
        ':ata_id'               => (int)$old['ata_id'],
        ':task_description'     => $old['task_description'],
        ':WO_number'            => $old['WO_number']            !== '' ? $old['WO_number']            : null,
        ':task_card_seq'        => $old['task_card_seq']        !== '' ? $old['task_card_seq']        : null,
        ':check_pack_reference' => $old['check_pack_reference'] !== '' ? $old['check_pack_reference'] : null,
        ':cdccl_task'           => (int)$old['cdccl_task'],
        ':ezap_task'            => (int)$old['ezap_task'],
        ':ewis_task'            => (int)$old['ewis_task'],
        ':awl_task'             => (int)$old['awl_task'],
        ':reference'            => $old['reference'],
        ':calibrated_tools'     => $old['calibrated_tools'],
        ':task_type'            => $task_type,
        ]);
        header("Location: /tasks.php?msg=" . urlencode("Task added successfully"));
        exit;
    }
}
?>
<div class="container my-4">
  <h2 class="mb-3">Add Maintenance Task</h2>

  <?php if ($errors) : ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
                                                     } ?></ul></div>
  <?php endif; ?>

  <form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" name="date_performed" class="form-control" value="<?= htmlspecialchars($old['date_performed']) ?>" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Aircraft</label>
      <select name="aircraft_id" class="form-select" required>
        <option value="">Select…</option>
        <?php foreach ($aircraft as $ac) : ?>
          <option value="<?= $ac['aircraft_id'] ?>" <?= $old['aircraft_id'] == $ac['aircraft_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ac['tail_number'] . ' (' . $ac['aircraft_type'] . ' / ' . $ac['engine_type'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Engineer</label>
      <select name="engineer_id" class="form-select" required>
        <option value="">Select…</option>
        <?php foreach ($engineers as $eng) : ?>
          <option value="<?= $eng['engineer_id'] ?>" <?= $old['engineer_id'] == $eng['engineer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($eng['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">ATA Chapter</label>
      <select name="ata_id" class="form-select" required>
        <option value="">Select…</option>
        <?php foreach ($ata as $ch) : ?>
          <option value="<?= $ch['ata_id'] ?>" <?= $old['ata_id'] == $ch['ata_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ch['ata_number'] . ' - ' . $ch['description']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label">Task Description</label>
      <textarea name="task_description" class="form-control" rows="4" required><?= htmlspecialchars($old['task_description']) ?></textarea>
    </div>

    <div class="col-md-3">
      <label class="form-label">W/O Number</label>
      <input type="text" name="WO_number" class="form-control" maxlength="20" value="<?= htmlspecialchars($old['WO_number']) ?>" placeholder="e.g. WO-12345">
    </div>

    <div class="col-md-3">
      <label class="form-label">Task Card Seq. #</label>
      <input type="text" name="task_card_seq" class="form-control" maxlength="5" value="<?= htmlspecialchars($old['task_card_seq']) ?>" placeholder="e.g. A1234">
      <div class="form-text">Max 5 characters.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Check Pack Reference</label>
      <input type="text" name="check_pack_reference" class="form-control" maxlength="24" value="<?= htmlspecialchars($old['check_pack_reference']) ?>" placeholder="Up to 24 chars">
      <div class="form-text">Max 24 characters.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label d-block">Task Types</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="cdccl_task" id="cdccl_task" value="1" <?= $old['cdccl_task'] == '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="cdccl_task">CDCCL</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="ezap_task" id="ezap_task" value="1" <?= $old['ezap_task'] == '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="ezap_task">EZAP</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="ewis_task" id="ewis_task" value="1" <?= $old['ewis_task'] == '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="ewis_task">EWIS</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="awl_task" id="awl_task" value="1" <?= $old['awl_task'] == '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="awl_task">AWL</label>
      </div>
    </div>

    <div class="col-mb-3">
      <label class="form-label">Task Type</label>
      <select name="task_type" class="form-select" required>
        <option value="">— Select task type —</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int)$t['ID'] ?>"
            <?= ((string)$old['task_type'] === (string)$t['ID']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['tasks']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Reference</label>
      <input type="text" name="reference" class="form-control" value="<?= htmlspecialchars($old['reference']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Calibrated Tools Used</label>
      <input type="text" name="calibrated_tools" class="form-control" value="<?= htmlspecialchars($old['calibrated_tools']) ?>" placeholder="Comma-separated list">
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">Save Task</button>
      <a href="/tasks.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
