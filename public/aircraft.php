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

require_once __DIR__ . '/../config/bootstrap.php';
require_once APP_PATH . '/Auth/guard.php';
include __DIR__ . '/partials/header.php';

/* -----------------------------
   Filters (persist in session)
------------------------------*/
if (!isset($_SESSION['aircraft_filters'])) {
    $_SESSION['aircraft_filters'] = [];
}

$defaults = array_merge([
  'q'        => '',      // tail search
  'type'     => '',
  'engine'   => '',
  'sort'     => 'tail',
  'dir'      => 'asc',
], $_SESSION['aircraft_filters']);

$use = $defaults;
if (!empty($_GET)) {
    foreach ($use as $k => $v) {
        if (isset($_GET[$k])) {
            $use[$k] = trim($_GET[$k]);
        }
    }
    $_SESSION['aircraft_filters'] = $use;
}

[$q, $type, $engine, $sort, $dir] = [$use['q'], $use['type'], $use['engine'], $use['sort'], strtolower($use['dir']) === 'asc' ? 'asc' : 'desc'];

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

/* -----------------------------
   Sorting whitelist
------------------------------*/
$sortMap = [
  'tail'   => 'tail_number',
  'type'   => 'aircraft_type',
  'engine' => 'engine_type',
];
$orderBy = $sortMap[$sort] ?? $sortMap['tail'];

/* -----------------------------
   WHERE
------------------------------*/
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

$whereSql = implode(' AND ', $where);

/* -----------------------------
   Dropdown data
------------------------------*/
$types  = $pdo->query("SELECT DISTINCT aircraft_type FROM aircraft ORDER BY aircraft_type")->fetchAll(PDO::FETCH_COLUMN);
$engines = $pdo->query("SELECT DISTINCT engine_type   FROM aircraft ORDER BY engine_type")->fetchAll(PDO::FETCH_COLUMN);

/* -----------------------------
   Count + page rows
------------------------------*/
$cstmt = $pdo->prepare("SELECT COUNT(*) FROM aircraft WHERE $whereSql");
$cstmt->execute($params);
$total = (int)$cstmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT aircraft_id, tail_number, aircraft_type, engine_type
        FROM aircraft
        WHERE $whereSql
        ORDER BY $orderBy $dir, aircraft_id $dir
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$flashMsg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$flashErr = isset($_GET['err']) ? trim((string)$_GET['err']) : '';

/* Helpers */
function qkeep(array $extra = [])
{
    $keep = $_GET ?: [];
    $arr = array_merge($keep, $extra);
    return http_build_query($arr);
}
function sort_link($key, $label, $cur, $dir)
{
    $next = ($cur === $key && strtolower($dir) === 'asc') ? 'desc' : 'asc';
    $icon = ($cur === $key) ? (strtolower($dir) === 'asc' ? '▲' : '▼') : '';
    return '<a class="text-decoration-none" href="aircraft.php?' . qkeep(['sort' => $key,'dir' => $next,'page' => 1]) . '">' . htmlspecialchars($label) . ' ' . $icon . '</a>';
}
?>

<div class="container mt-4">
  <?php if ($flashMsg) : ?>
    <div class="alert alert-success"><?= htmlspecialchars($flashMsg) ?></div>
  <?php endif; ?>
  <?php if ($flashErr) : ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flashErr) ?></div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Aircraft</h2>
    <div class="d-flex gap-2">
      <a href="/reports/export_aircraft_csv.php?<?= qkeep() ?>" class="btn btn-outline-secondary">Export CSV</a>
      <a href="/reports/export_aircraft_pdf.php?<?= qkeep() ?>" class="btn btn-outline-success">Export PDF</a>
      <a href="/manage/insert_aircraft.php" class="btn btn-primary">Add Aircraft</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tail contains</label>
          <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. EI-DCL">
        </div>
        <div class="col-md-3">
          <label class="form-label">Aircraft Type</label>
          <select class="form-select" name="type">
            <option value="">All</option>
            <?php foreach ($types as $t) : ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Engine Type</label>
          <select class="form-select" name="engine">
            <option value="">All</option>
            <?php foreach ($engines as $e) : ?>
              <option value="<?= htmlspecialchars($e) ?>" <?= $engine === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-secondary w-100" type="submit">Filter</button>
        </div>
        <div class="col-md-1 align-self-end">
          <a class="btn btn-outline-secondary w-100" href="aircraft.php">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th><?= sort_link('tail', 'Tail', $sort, $dir) ?></th>
              <th><?= sort_link('type', 'Aircraft Type', $sort, $dir) ?></th>
              <th><?= sort_link('engine', 'Engine Type', $sort, $dir) ?></th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows) : ?>
              <tr><td colspan="4" class="text-center">No aircraft found</td></tr>
            <?php else :
                foreach ($rows as $r) : ?>
              <tr>
                <td><?= htmlspecialchars($r['tail_number']) ?></td>
                <td><?= htmlspecialchars($r['aircraft_type']) ?></td>
                <td><?= htmlspecialchars($r['engine_type']) ?></td>
                <td class="text-end">
                <form id="delete-aircraft-<?= (int)$r['aircraft_id'] ?>" method="post" action="/manage/delete_aircraft.php" class="d-inline">
                    <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['aircraft_id'] ?>">
                </form>
                <div class="btn-group">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#airModal"
                          data-air='<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>View</button>
                  <a class="btn btn-sm btn-primary" href="/manage/edit_aircraft.php?id=<?= (int)$r['aircraft_id'] ?>">Edit</a>
                  <button type="submit"
                          form="delete-aircraft-<?= (int)$r['aircraft_id'] ?>"
                          class="btn btn-sm btn-danger"
                          onclick="return confirm('Delete this aircraft?');">Delete</button>
                </div>
                </td>
              </tr>
                <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1) : ?>
        <nav>
          <ul class="pagination justify-content-end">
            <?php
              $base = 'aircraft.php?' . qkeep(['page' => null]);
              $prev = max(1, $page - 1);
              $next = min($totalPages, $page + 1);
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $base . '&page=' . $prev ?>">Previous</a>
            </li>
            <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $base . '&page=' . $p ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $base . '&page=' . $next ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="airModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Aircraft Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Tail</dt><dd class="col-sm-8" id="m_tail"></dd>
          <dt class="col-sm-4">Type</dt><dd class="col-sm-8" id="m_type"></dd>
          <dt class="col-sm-4">Engine</dt><dd class="col-sm-8" id="m_engine"></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<script>
const airModal = document.getElementById('airModal');
airModal.addEventListener('show.bs.modal', e => {
  const data = JSON.parse(e.relatedTarget.getAttribute('data-air'));
  document.getElementById('m_tail').textContent   = data.tail_number || '';
  document.getElementById('m_type').textContent   = data.aircraft_type || '';
  document.getElementById('m_engine').textContent = data.engine_type || '';
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
