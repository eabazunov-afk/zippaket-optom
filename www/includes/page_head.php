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
