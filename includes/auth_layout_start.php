<?php
// includes/auth_layout_start.php - Shared two-panel layout for public auth pages
// Expects: $pageTitle, $authHeading, $authSubheading, optional $authTall (bool)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'ICMS-DPT-RRT') ?></title>
  <?php require __DIR__ . '/head_assets.php'; ?>
  <link rel="stylesheet" href="assets/css/variables.css<?= assetVersion('css/variables.css') ?>">
  <link rel="stylesheet" href="assets/css/base.css<?= assetVersion('css/base.css') ?>">
  <link rel="stylesheet" href="assets/css/components.css<?= assetVersion('css/components.css') ?>">
  <link rel="stylesheet" href="assets/css/auth.css<?= assetVersion('css/auth.css') ?>">
</head>
<body>

<div class="login-container <?= !empty($authTall) ? 'auth-tall' : '' ?>">
  <div class="login-hero">
    <div class="hero-top">
      <div class="hero-icon-bubble">
        <?= icon('message-square', '', 38) ?>
      </div>
      <h1 class="hero-title">AI-Powered Institutional Concern Management System</h1>
      <p class="hero-subtitle">Dynamic Prioritization &amp; Resolution Tracking</p>
      <div class="hero-badges">
        <span class="hero-badge">AI Urgency Engine</span>
        <span class="hero-badge">SLA Tracking</span>
        <span class="hero-badge">Rapid Response</span>
      </div>
    </div>
    <div class="hero-footer">
      "Your voice matters. We're here to listen and act."
      <div style="font-size: 0.725rem; opacity: 0.8; margin-top: 4px;">Surigao del Norte State University (SNSU)</div>
    </div>
  </div>

  <div class="login-form-pane">
    <div class="form-header">
      <h2><?= e($authHeading ?? '') ?></h2>
      <p><?= e($authSubheading ?? '') ?></p>
    </div>
    <?php renderFlash(); ?>
