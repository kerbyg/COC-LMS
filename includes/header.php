<?php
/**
 * ============================================================
 * CIT-LMS Header Include
 * ============================================================
 * Modern Milwaukee Bucks Theme (#00461B + #EFEBD2)
 * Contains: DOCTYPE, head section, meta tags, CSS links
 * ============================================================
 */

// Ensure config files are loaded
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../config/auth.php';
}

// Default page title if not set
$pageTitle = $pageTitle ?? 'CIT-LMS';
$appName = defined('APP_NAME') ? APP_NAME : 'CIT-LMS';
$appDesc = defined('APP_DESCRIPTION') ? APP_DESCRIPTION : 'Learning Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Page Title -->
    <title><?= e($pageTitle) ?> | <?= $appName ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?= $appDesc ?>">
    <meta name="theme-color" content="#00461B">
    <meta name="robots" content="noindex, nofollow">

    <!-- Cache Control (can be overridden by specific pages) -->
    <?php if (isset($preventCache) && $preventCache === true): ?>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/phinma_logo1.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/phinma_logo1.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">

    <!-- Page-specific CSS (optional) -->
    <?php if (isset($pageCSS) && !empty($pageCSS)): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/<?= $pageCSS ?>.css">
    <?php endif; ?>
</head>
<body data-user-id="<?= Auth::id() ?>" data-user-role="<?= Auth::role() ?>">
    <div class="wrapper">