<?php
/** Shared <head> contents. Expects $title. */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Vitaliq') . ' · Vitaliq') ?></title>
<meta name="description" content="Vitaliq: COVID-19 public-health information and testing management. Health data, made meaningful.">
<meta name="theme-color" content="#fafaf7">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="app-base" content="<?= e(url('/')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('/assets/fonts/geist-variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('/assets/fonts/junicode-vitaliq-subset.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/vitaliq.css')) ?>">
<?php if (!empty($appCss)): ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php endif; ?>
