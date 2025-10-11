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

if (!function_exists('requireSecret')) {
    function requireSecret(string $key): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Missing required secret: {$key}");
        }
        return $value;
    }
}

$host       = getenv('DB_HOST') ?: '127.0.0.1';
$port       = getenv('DB_PORT') ?: '3306';
$db         = requireSecret('DB_NAME');
$user       = requireSecret('DB_USER');
$pass       = requireSecret('DB_PASS');


$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    throw new RuntimeException('Unable to connect to the database.', 0, $e);
}
