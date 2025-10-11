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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify($_POST['_token'] ?? null);

    session_destroy();
    header("Location: /auth/login.php");
    exit;
}
