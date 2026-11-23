<?php
require_once __DIR__ . '/php-scripts/add-device.php';
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Devices | Plant System</title>
    <link rel="stylesheet" href="styles/navstyles.css">
    <link rel="stylesheet" href="styles/add-device.css?v=confirmation-2">
</head>
<body>
<?php require __DIR__ . '/navigation.php'; ?>
<main class="device-page">
    <header class="device-heading">
        <span class="device-badge">PLANT SYSTEM</span>
        <h1>Your devices</h1>
        <p>Connect a new device or manage the ones already in your account.</p>
    </header>
    <?php if ($error !== ''): ?><div class="device-message error" role="alert"><?= ps_e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="device-message success" role="status"><?= ps_e($success) ?></div><?php endif; ?>
    <div class="device-layout">
        <section class="device-card add-card" aria-labelledby="add-title">
            <h2 id="add-title">Add a device</h2>
            <p class="description">Enter the Device Key supplied with your device.</p>
            <form method="post" action="add-device.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
                <label for="device-name">Device name</label>
                <input id="device-name" name="device_name" maxlength="100" value="<?= ps_e($deviceName) ?>" placeholder="Living Room Plant" required>
                <label for="device-key">Device Key</label>
                <input id="device-key" name="device_key" maxlength="250" autocomplete="off" placeholder="Enter your Device Key" required>
                <button class="device-button" type="submit">Connect device</button>
            </form>
            <p class="device-help">A device can only belong to one account at a time.</p>
        </section>
        <section class="device-list" aria-labelledby="list-title">
            <h2 id="list-title">Connected devices <span class="count"><?= count($devices) ?></span></h2>
            <?php if (!$devices): ?>
                <div class="device-card empty-state">No devices yet. Connect your first device using its key.</div>
            <?php endif; ?>
            <?php foreach ($devices as $device): $id = (int) $device['id']; ?>
                <article class="device-card">
                    <div class="device-card-heading">
                        <h3><?= ps_e($device['device_name']) ?></h3>
                        <span class="status <?= (int) $device['is_online'] === 1 ? 'online' : 'offline' ?>"><?= (int) $device['is_online'] === 1 ? 'Online' : 'Offline' ?></span>
                    </div>
                    <p class="device-uid"><?= ps_e($device['device_uid']) ?></p>
                    <details>
                        <summary>Edit device name</summary>
                        <form method="post" action="add-device.php">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="device_id" value="<?= $id ?>">
                            <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
                            <label for="rename-<?= $id ?>">Device name</label>
                            <input id="rename-<?= $id ?>" name="device_name" maxlength="100" value="<?= ps_e($device['device_name']) ?>" required>
                            <button class="device-button" type="submit">Save name</button>
                        </form>
                    </details>
                    <details class="delete-section">
                        <summary>Remove from account</summary>
                        <p>This will remove the device from your account and delete all its existing measurements. Do you wish to continue?</p>
                        <form method="post" action="add-device.php">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="device_id" value="<?= $id ?>">
                            <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
                            <label class="confirmation">
                                <input type="checkbox" name="confirm_delete" value="yes" required
                                    oninvalid="this.setCustomValidity('Check the box if you want to continue.')"
                                    onchange="this.setCustomValidity('')">
                                <span>Check the box if you want to continue.</span>
                            </label>
                            <button class="device-button danger" type="submit">Confirm removal</button>
                        </form>
                    </details>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
