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

/* ---------- Calendar data: ISO year/week ---------- */
$taskData = $pdo->query("
    SELECT 
      FLOOR(YEARWEEK(date_performed, 3)/100) AS iso_year,
      MOD(YEARWEEK(date_performed, 3), 100)   AS iso_week,
      COUNT(*) AS c
    FROM maintenance_tasks
    GROUP BY iso_year, iso_week
    ORDER BY iso_year, iso_week
")->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
$minYear = PHP_INT_MAX;
$maxYear = 0;
foreach ($taskData as $row) {
    $y = (int)$row['iso_year'];
    $w = (int)$row['iso_week'];
    $counts[$y][$w] = (int)$row['c'];
    if ($y < $minYear) {
        $minYear = $y;
    }
    if ($y > $maxYear) {
        $maxYear = $y;
    }
}
if ($minYear === PHP_INT_MAX) { // no data fallback
    $minYear = $maxYear = (int)date('o'); // ISO year
}

/* ---------- ATA chart data ---------- */
$ataData = $pdo->query("
    SELECT ata.ata_number, ata.description, COUNT(*) AS count
    FROM maintenance_tasks mt
    JOIN ata ON mt.ata_id = ata.ata_id
    GROUP BY ata.ata_number, ata.description
    ORDER BY CAST(ata.ata_number AS UNSIGNED), ata.ata_number
")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Task type summary ---------- */
$summary = $pdo->query("
    SELECT 
      COUNT(*) AS total_tasks,
      SUM(CASE WHEN cdccl_task=1 THEN 1 ELSE 0 END) AS cdccl,
      SUM(CASE WHEN ewis_task=1   THEN 1 ELSE 0 END) AS ewis,
      SUM(CASE WHEN ezap_task=1   THEN 1 ELSE 0 END) AS ezap,
      SUM(CASE WHEN awl_task=1   THEN 1 ELSE 0 END) AS awl
    FROM maintenance_tasks
")->fetch(PDO::FETCH_ASSOC);

$totalTasks = (int)($summary['total_tasks'] ?? 0);
$totCDCCL   = (int)($summary['cdccl'] ?? 0);
$totEWIS    = (int)($summary['ewis'] ?? 0);
$totEZAP    = (int)($summary['ezap'] ?? 0);
$totAWL     = (int)($summary['awl'] ?? 0);
$pct = function ($n, $d) {
    return $d > 0 ? round(($n / $d) * 100) : 0;
};
?>
<style>
  .calendar-box{
    width:24px;height:24px;min-width:24px;min-height:24px;
    padding:0;margin:0;font-size:10px;line-height:24px;
    text-align:center;vertical-align:middle;color:#fff;user-select:none;
  }
  .calendar-legend span{display:inline-block;padding:2px 6px;margin-right:6px;border-radius:4px;font-size:12px}
</style>

<div class="container mt-4">
  <h2 class="mb-3">Dashboard</h2>

  <!-- Quick actions -->
  <div class="d-flex gap-2 flex-wrap mb-3">
  <!--  <a class="btn btn-primary btn-sm" href="/reports/export_calendar.php">Export Calendar PDF</a> -->
    <a class="btn btn-info btn-sm" href="/reports/export_logbook.php">Export Logbook PDF</a>
  </div>

  <!-- Calendar -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">Calendar View (ISO weeks)</div>
    <div class="card-body p-3">
      <div class="calendar-legend mb-2">
        <span style="background:red;color:#fff;">0</span>
        <span style="background:gold;color:#000;">1</span>
        <span style="background:green;color:#fff;">2+</span>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered text-center align-middle small" style="table-layout:fixed;width:100%;">
          <colgroup>
            <col style="width:70px;">
            <?php for ($w = 1; $w <= 53; $w++) :
                ?><col style="width:24px;"><?php
            endfor; ?>
          </colgroup>
          <thead class="table-light">
            <tr>
              <th>Year</th>
              <?php for ($w = 1; $w <= 53; $w++) :
                    ?><th><?= $w ?></th><?php
              endfor; ?>
            </tr>
          </thead>
          <tbody>
          <?php for ($year = $minYear; $year <= $maxYear; $year++) :
                $weeksInYear = (int)date('W', strtotime("$year-12-28")); // 52 or 53
                ?>
            <tr>
              <th><?= $year ?></th>
                <?php for ($w = 1; $w <= 53; $w++) : ?>
                    <?php if ($w > $weeksInYear) : ?>
                  <td class="calendar-box" style="background-color:#f8f9fa;color:#6c757d;" title="No ISO week <?= $w ?> in <?= $year ?>">—</td>
                    <?php else :
                        $c = $counts[$year][$w] ?? 0;
                        $color = ($c === 0) ? 'red' : (($c === 1) ? 'gold' : 'green');
                        $textColor = ($color === 'gold') ? '#000' : '#fff';
                        ?>
                  <td class="calendar-box" style="background-color:<?= $color ?>;color:<?= $textColor ?>;" title="Year <?= $year ?>, Week <?= $w ?>: <?= $c ?> tasks">
                        <?= $c ?>
                  </td>
                    <?php endif; ?>
                <?php endfor; ?>
            </tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Row: ATA chart + summary -->
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header bg-success text-white">Tasks by ATA Chapter</div>
        <div class="card-body">
          <canvas id="ataChart" height="120"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-dark text-white">Task Type Summary</div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold">Total Tasks</span>
            <span class="badge bg-secondary"><?= number_format($totalTasks) ?></span>
          </div>
          <hr>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>CDCCL</span>
              <span class="badge bg-info text-dark"><?= number_format($totCDCCL) ?> (<?= $pct($totCDCCL, $totalTasks) ?>%)</span>
            </div>
            <div class="progress mt-1" style="height:8px;">
              <div class="progress-bar bg-info" style="width:<?= $pct($totCDCCL, $totalTasks) ?>%"></div>
            </div>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>EWIS</span>
              <span class="badge bg-warning text-dark"><?= number_format($totEWIS) ?> (<?= $pct($totEWIS, $totalTasks) ?>%)</span>
            </div>
            <div class="progress mt-1" style="height:8px;">
              <div class="progress-bar bg-warning" style="width:<?= $pct($totEWIS, $totalTasks) ?>%"></div>
            </div>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>EZAP</span>
              <span class="badge bg-success"><?= number_format($totEZAP) ?> (<?= $pct($totEZAP, $totalTasks) ?>%)</span>
            </div>
            <div class="progress mt-1" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= $pct($totEZAP, $totalTasks) ?>%"></div>
            </div>
          </div>
          <div>
            <div class="d-flex justify-content-between">
              <span>AWL</span>
              <span class="badge bg-success"><?= number_format($totAWL) ?> (<?= $pct($totAWL, $totalTasks) ?>%)</span>
            </div>
            <div class="progress mt-1" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= $pct($totAWL, $totalTasks) ?>%"></div>
            </div>
          </div>
          <hr>
          <div class="text-muted small">Counts are totals across the whole database.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('ataChart').getContext('2d');
const ataLabels = <?= json_encode(array_map(fn($a) => $a['ata_number'] . " - " . $a['description'], $ataData)) ?>;
const ataCounts = <?= json_encode(array_values(array_map(fn($a) => (int)$a['count'], $ataData))) ?>;

new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ataLabels,
    datasets: [{
      label: 'Number of Tasks',
      data: ataCounts,
      backgroundColor: 'rgba(25, 135, 84, 0.7)',
      borderColor: 'rgba(25, 135, 84, 1)',
      borderWidth: 1
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: c => `${c.parsed.x} tasks` } }
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: {
          stepSize: 1,
          callback: v => Number.isInteger(v) ? v : ''
        }
      },
      y: { ticks: { autoSkip: false } }
    }
  }
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
