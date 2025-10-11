<?php

$host       = getenv('DB_HOST');
$port       = getenv('DB_PORT');
$db         = getenv('DB_NAME');
$user       = getenv('DB_USER');
$pass       = getenv('DB_PASS');


/** @var array $CONFIG */
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4;port=$s', $host, $db, $port);
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $options);
