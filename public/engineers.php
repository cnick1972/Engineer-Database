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
   Filters (persist)
------------------------------*/
if (!isset($_SESSION['engineer_filters'])) {
    $_SESSION['engineer_filters'] = [];
}

$defaults = array_merge([
  'q'   => '',    // name or licence search
  'sort' => 'name',
  'dir' => 'asc',
], $_SESSION['engineer_filters']);

$use = $defaults;
if (!empty($_GET)) {
    foreach ($use as $k => $v) {
        if (isset($_GET[$k])) {
            $use[$k] = trim($_GET[$k]);
        }
    }
    $_SESSION['engineer_filters'] = $use;
}

[$q,$sort,$dir] = [$use['q'], $use['sort'], strtolower($use['dir']) === 'asc' ? 'asc' : 'desc'];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

/* -----------------------------
   Sorting whitelist
------------------------------*/
$sortMap = [
  'name'    => 'name',
  'licence' => 'licence_number'
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];

/* -----------------------------
   WHERE
------------------------------*/
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q OR licence_number LIKE :q)';
    $params[':q'] = "%$q%";
}
$whereSql = implode(' AND ', $where);

/* -----------------------------
   Count + rows
------------------------------*/
$cstmt = $pdo->prepare("SELECT COUNT(*) FROM engineers WHERE $whereSql");
$cstmt->execute($params);
$total = (int)$cstmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT engineer_id, name, licence_number
        FROM engineers
        WHERE $whereSql
        ORDER BY $orderBy $dir, engineer_id $dir
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
function qkeep_e(array $extra = [])
{
    $keep = $_GET ?: [];
    $arr = array_merge($keep, $extra);
    return http_build_query($arr);
}
function sort_link_e($key, $label, $cur, $dir)
{
    $next = ($cur === $key && strtolower($dir) === 'asc') ? 'desc' : 'asc';
    $icon = ($cur === $key) ? (strtolower($dir) === 'asc' ? '▲' : '▼') : '';
    return '<a class="text-decoration-none" href="engineers.php?' . qkeep_e(['sort' => $key,'dir' => $next,'page' => 1]) . '">' . htmlspecialchars($label) . ' ' . $icon . '</a>';
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
    <h2 class="mb-0">Engineers</h2>
    <div class="d-flex gap-2">
      <a href="/reports/export_engineers_csv.php?<?= qkeep_e() ?>" class="btn btn-outline-secondary">Export CSV</a>
      <a href="/reports/export_engineers_pdf.php?<?= qkeep_e() ?>" class="btn btn-outline-success">Export PDF</a>
      <a href="/manage/insert_engineer.php" class="btn btn-primary">Add Engineer</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Search (name or licence)</label>
          <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. Smith or EASA-XXXX">
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-secondary w-100" type="submit">Filter</button>
        </div>
        <div class="col-md-2 align-self-end">
          <a class="btn btn-outline-secondary w-100" href="engineers.php">Clear</a>
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
              <th><?= sort_link_e('name', 'Name', $sort, $dir) ?></th>
              <th><?= sort_link_e('licence', 'Licence Number', $sort, $dir) ?></th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows) : ?>
              <tr><td colspan="3" class="text-center">No engineers found</td></tr>
            <?php else :
                foreach ($rows as $r) : ?>
              <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['licence_number']) ?></td>
                <td class="text-end">
                  <form id="delete-engineer-<?= (int)$r['engineer_id'] ?>" method="post" action="/manage/delete_engineer.php" class="d-inline">
                      <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['engineer_id'] ?>">
                  </form>
                  <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#engModal"
                            data-eng='<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>View</button>
                    <a class="btn btn-sm btn-primary" href="/manage/edit_engineer.php?id=<?= (int)$r['engineer_id'] ?>">Edit</a>
                    <button type="submit"
                            form="delete-engineer-<?= (int)$r['engineer_id'] ?>"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete this engineer?');">Delete</button>
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
              $base = 'engineers.php?' . qkeep_e(['page' => null]);
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
<div class="modal fade" id="engModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Engineer Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Name</dt><dd class="col-sm-8" id="e_name"></dd>
          <dt class="col-sm-4">Licence</dt><dd class="col-sm-8" id="e_lic"></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<script>
const engModal = document.getElementById('engModal');
engModal.addEventListener('show.bs.modal', e => {
  const data = JSON.parse(e.relatedTarget.getAttribute('data-eng'));
  document.getElementById('e_name').textContent = data.name || '';
  document.getElementById('e_lic').textContent  = data.licence_number || '';
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
