<?php

declare(strict_types=1);

define('BASE_PATH', realpath(__DIR__ . '/..'));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('APP_PATH', BASE_PATH . '/app');

require BASE_PATH . '/vendor/autoload.php';   // TCPDF via Composer

$CONFIG = require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('LOGBOOK_OWNER')) {
    define('LOGBOOK_OWNER', $CONFIG['logbook_owner'] ?? 'Maintenance Department');
}

require APP_PATH . '/Helpers/csrf.php';

require APP_PATH . '/Database/pdo.php';       // provides $pdo
