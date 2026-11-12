<?php
// includes/header.php - Global <head> for authenticated pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'ICMS-DPT-RRT | Surigao del Norte State University';
$isSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$assetPrefix = $isSubdir ? '../assets/' : 'assets/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
  <meta name="theme-color" content="#0b1b3d">
  <meta name="session-timeout" content="<?= SESSION_IDLE_TIMEOUT ?>">
  <?php require __DIR__ . '/head_assets.php'; ?>
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/variables.css<?= assetVersion('css/variables.css') ?>">
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/base.css<?= assetVersion('css/base.css') ?>">
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/layout.css<?= assetVersion('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/components.css<?= assetVersion('css/components.css') ?>">
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/timeline.css<?= assetVersion('css/timeline.css') ?>">
  <link rel="stylesheet" href="<?= $assetPrefix ?>css/messages.css<?= assetVersion('css/messages.css') ?>">
</head>
<body>
<div class="app-wrapper">
