<?php
// Общая шапка простых контентных (юридических) страниц — тёмная тема.
require_once __DIR__ . '/config.php';
if (!isset($pageTitle)) { $pageTitle = 'ZLOCK'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | ZLOCK</title>
    <?php if (!empty($pageDescription)): ?><meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?><meta name="robots" content="<?= htmlspecialchars($pageRobots ?? 'index, follow') ?>">
    <?php if (!empty($pageCanonical)): ?><link rel="canonical" href="<?= htmlspecialchars($pageCanonical) ?>">
    <?php endif; ?><meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?> | ZLOCK">
    <?php if (!empty($pageDescription)): ?><meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?><?php if (!empty($pageCanonical)): ?><meta property="og:url" content="<?= htmlspecialchars($pageCanonical) ?>">
    <?php endif; ?>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/shop-dark.css">
</head>
<body class="zlock">
<div class="site-wrapper">
    <?php include __DIR__ . '/../header.php'; ?>
    <main class="main-content">
        <section class="catalog-section"><div class="container legal-page">
            <div class="pm-pagehead"><h1><?= htmlspecialchars($pageTitle) ?></h1></div>
