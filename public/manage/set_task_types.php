<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';

$show  = ($_GET['show'] ?? 'missing') === 'all' ? 'all' : 'missing';   // default: show only NULL task_type
$per   = (int)($_GET['per'] ?? 100);                                   // items per page
$per   = max(10, min(500, $per));
$page  = max(1, (int)($_GET['page'] ?? 1));
$offs  = ($page - 1) * $per;

$errors = [];
$flash  = null;

// Load task types once (task_types.ID, task_types.tasks)
try {
    $types = $pdo->query('SELECT ID, tasks FROM task_types ORDER BY tasks')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $types = [];
    $errors[] = 'Could not load task types.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify($_POST['_token'] ?? null);

        $submitted = $_POST['task_type'] ?? [];
        if (!is_array($submitted)) $submitted = [];

        $pdo->beginTransaction();

        $chkType = $pdo->prepare('SELECT 1 FROM task_types WHERE ID = :id');                // validates selected type exists
        $upd     = $pdo->prepare('UPDATE maintenance_tasks
                                  SET task_type = :tid
                                  WHERE task_id = :id
                                    AND (task_type IS NULL OR task_type <> :tid)');

        $updated = 0;
        foreach ($submitted as $taskIdStr => $tidStr) {
            $taskId = (int)$taskIdStr;
            $tid    = (int)$tidStr;
            if ($taskId <= 0 || $tid <= 0) {
                continue; // ignore empties/invalids
            }

            $chkType->execute([':id' => $tid]);
            if (!$chkType->fetchColumn()) {
                continue; // ignore bad IDs (FK will protect anyway)
            }

            $upd->execute([':tid' => $tid, ':id' => $taskId]);
            $updated += $upd->rowCount();
        }

        $pdo->commit();
        $flash = $updated > 0 ? "Updated {$updated} task(s)." : "No changes detected.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'Save failed.';
    }
}

// Build list query
try {
    $where = ($show === 'all') ? '1=1' : 'mt.task_type IS NULL';
    $sql = "SELECT
                mt.task_id,
                mt.date_performed,
                mt.WO_number,
                mt.task_description,
                mt.task_type,
                a.tail_number,
                tt.tasks AS task_type_name
            FROM maintenance_tasks mt
            JOIN aircraft a ON a.aircraft_id = mt.aircraft_id
            LEFT JOIN task_types tt ON tt.ID = mt.task_type
            WHERE {$where}
            ORDER BY mt.date_performed DESC, mt.task_id DESC
            LIMIT :per OFFSET :offs";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':per',  $per,  PDO::PARAM_INT);
    $stmt->bindValue(':offs', $offs, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // crude total for pager
    $cntSql = "SELECT COUNT(*) FROM maintenance_tasks mt WHERE {$where}";
    $total = (int)$pdo->query($cntSql)->fetchColumn();
    $pages = (int)ceil($total / $per);
} catch (Throwable $e) {
    $rows = [];
    $total = 0;
    $pages = 1;
    $errors[] = 'Could not load tasks.';
}

include __DIR__ . '/../partials/header.php';
?>
<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">Bulk Set Task Types</h2>
    <div>
      <a class="btn btn-sm <?= $show==='missing'?'btn-primary':'btn-outline-primary' ?>" href="?show=missing&per=<?= $per ?>">Missing only</a>
      <a class="btn btn-sm <?= $show==='all'?'btn-primary':'btn-outline-primary' ?>" href="?show=all&per=<?= $per ?>">All tasks</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= htmlspecialchars($er) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="row g-2 align-items-end mb-3">
      <div class="col-auto">
        <label class="form-label">Apply type to all shown</label>
        <select id="bulkType" class="form-select">
          <option value="">— choose —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= (int)$t['ID'] ?>"><?= htmlspecialchars($t['tasks']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="button" id="applyAll" class="btn btn-outline-secondary">Apply to all</button>
      </div>
      <div class="col-auto ms-auto">
        <label class="form-label">Per page</label>
        <select class="form-select" onchange="location.search='?show=<?= $show ?>&per='+this.value">
          <?php foreach ([25,50,100,200,500] as $opt): ?>
            <option value="<?= $opt ?>" <?= $opt===$per?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>WO</th>
            <th>Tail</th>
            <th>Description (snippet)</th>
            <th>Current Type</th>
            <th>Set New Type</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted">No tasks to show.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['task_id'] ?></td>
              <td><?= htmlspecialchars($r['date_performed']) ?></td>
              <td><?= htmlspecialchars($r['WO_number']) ?></td>
              <td><?= htmlspecialchars($r['tail_number']) ?></td>
              <td style="max-width:480px">
                <?= htmlspecialchars(mb_strimwidth((string)$r['task_description'], 0, 140, '…')) ?>
              </td>
              <td>
                <?= $r['task_type_name'] ? htmlspecialchars($r['task_type_name']) : '<span class="text-danger">NULL</span>' ?>
              </td>
              <td>
                <select name="task_type[<?= (int)$r['task_id'] ?>]" class="form-select form-select-sm bulkable">
                  <option value="">— leave unchanged —</option>
                  <?php foreach ($types as $t): ?>
                    <option value="<?= (int)$t['ID'] ?>" <?= ((string)$r['task_type'] === (string)$t['ID']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($t['tasks']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <div>
        <button class="btn btn-primary">Save all changes</button>
        <a class="btn btn-outline-secondary" href="/tasks.php">Back</a>
      </div>
      <nav>
        <ul class="pagination mb-0">
          <?php
          $base = '?show=' . $show . '&per=' . $per . '&page=';
          for ($p = 1; $p <= max(1, $pages); $p++):
          ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
              <a class="page-link" href="<?= $base . $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </form>
</div>

<script>
document.getElementById('applyAll')?.addEventListener('click', function () {
  const v = document.getElementById('bulkType').value;
  if (!v) return;
  document.querySelectorAll('select.bulkable').forEach(sel => sel.value = v);
});
</script>
<?php include __DIR__ . '/../partials/footer.php';
