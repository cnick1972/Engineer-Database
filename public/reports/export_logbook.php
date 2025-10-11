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
require_once APP_PATH . '/Reports/Logbook/Layout.php';
require_once APP_PATH . '/Reports/Logbook/DrawingHelpers.php';
require_once APP_PATH . '/Reports/Logbook/LogbookExport.php';

use App\Reports\Logbook\LogbookExport;

$report = new LogbookExport($pdo);
$report->generate();
