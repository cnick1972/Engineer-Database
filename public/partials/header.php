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

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define which page should highlight which menu
$active_map = [
    'dashboard.php' => 'dashboard',
    'insert_aircraft.php' => 'aircraft',
    'edit_aircraft.php' => 'aircraft',
    'insert_engineer.php' => 'engineer',
    'edit_engineer.php' => 'engineer',
    'insert_task.php' => 'task',
    'edit_task.php' => 'task',
];

// Determine current active menu
$active_menu = $active_map[$current_page] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aircraft Maintenance Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="/assets/img/icon-plane.png" type="image/png">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Maintenance Log</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link <?= $active_menu == 'dashboard' ? 'active' : '' ?>" href="/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_menu == 'aircraft' ? 'active' : '' ?>" href="/aircraft.php">Aircraft</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_menu == 'engineer' ? 'active' : '' ?>" href="/engineers.php">Engineers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active_menu == 'task' ? 'active' : '' ?>" href="/tasks.php">Tasks</a>
        </li>
        <?php if (isset($_SESSION['logged_in'])) : ?>
        <li class="nav-item">
            <a class="nav-link" href="/auth/change_password.php">Change Password</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/auth/logout.php">Logout</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
