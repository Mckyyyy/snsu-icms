<?php
// includes/head_assets.php - Shared <head> assets: bundled font + favicon (works offline)
$fontPrefix = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/student/') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../' : '';
?>
  <!-- Browser tab icon: SNSU seal (small copies of logo/snsu.png in assets/img/) -->
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $fontPrefix ?>assets/img/favicon-32.png">
  <link rel="icon" type="image/png" sizes="64x64" href="<?= $fontPrefix ?>assets/img/favicon-64.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $fontPrefix ?>assets/img/apple-touch-icon.png">
  <link rel="preload" href="<?= $fontPrefix ?>assets/fonts/inter-latin-var.woff2" as="font" type="font/woff2" crossorigin>
  <style>
    @font-face {
      font-family: 'Inter';
      font-style: normal;
      font-weight: 400 800;
      font-display: swap;
      src: url('<?= $fontPrefix ?>assets/fonts/inter-latin-var.woff2') format('woff2');
    }
  </style>
