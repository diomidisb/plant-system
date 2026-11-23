<?php require dirname(__DIR__).'/php-scripts/view-state.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= ps_e($pageTitle) ?> | Plant System</title>
    <link rel="stylesheet" href="<?= ps_e(ps_url('styles/navstyles.css')) ?>">
    <link rel="stylesheet" href="<?= ps_e(ps_url('styles/auth.css')) ?>">
    <script src="<?= ps_e(ps_url('scripts/login.js')) ?>" defer></script>
</head>
<body>
    <?php require dirname(__DIR__) . '/navigation.php'; ?>
    <main class="login-page">
        <div class="background-circle circle-one" aria-hidden="true"></div>
        <div class="background-circle circle-two" aria-hidden="true"></div>
        <div class="login-card">
            <div class="login-visual">
                <div class="plant-glow" aria-hidden="true"></div>
                <img src="<?= ps_e(ps_url('media/plant.png')) ?>" alt="" class="login-plant">
                <div class="visual-text">
                    <span>PLANT SYSTEM</span>
                    <h2>Grow smarter.</h2>
                    <p>Better care starts with better information.</p>
                </div>
            </div>
            <div class="login-content">
                <div class="login-header">
                    <span class="welcome"><?= ps_e($eyebrow) ?></span>
                    <h1><?= ps_e($pageTitle) ?></h1>
                    <?php if ($error !== ''): ?>
                        <div class="form-error" role="alert"><?= ps_e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success !== ''): ?>
                        <div class="form-success" role="status"><?= ps_e($success) ?></div>
                    <?php endif; ?>
                    <p><?= ps_e($description) ?></p>
                </div>
