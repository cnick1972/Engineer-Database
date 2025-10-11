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
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h1 class="h4 mb-3">Calendar Export</h1>
          <p class="lead mb-0">This page is coming soon. Check back later for the calendar export feature.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
