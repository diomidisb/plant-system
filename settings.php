<?php

require_once __DIR__ . '/php-scripts/settings.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | Plant System</title>

    <link
        rel="stylesheet"
        href="<?= ps_e(ps_url('styles/navstyles.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= ps_e(ps_url('styles/settings.css?v=account-delete-1')) ?>"
    >
</head>

<body>

<?php require __DIR__ . '/navigation.php'; ?>

<main class="settings-page">

    <div class="settings-card">

        <div class="settings-header">
            <span>ACCOUNT</span>

            <h1>Settings</h1>

            <p>
                Manage your personal information.
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="settings-error" role="alert">
                <?= ps_e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="settings-success" role="status">
                <?= ps_e($success) ?>
            </div>
        <?php endif; ?>

        <form
            action="<?= ps_e(ps_url('settings.php')) ?>"
            method="post"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= ps_e(ps_csrf()) ?>"
            >

            <div class="settings-row">

                <div class="settings-input">
                    <label for="name">Name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= ps_e($user['name']) ?>"
                        maxlength="100"
                        autocomplete="given-name"
                        required
                    >
                </div>

                <div class="settings-input">
                    <label for="surname">Surname</label>

                    <input
                        type="text"
                        id="surname"
                        name="surname"
                        value="<?= ps_e($user['surname']) ?>"
                        maxlength="100"
                        autocomplete="family-name"
                        required
                    >
                </div>

            </div>

            <div class="settings-input">
                <label for="email">Email</label>

                <input
                    type="email"
                    id="email"
                    value="<?= ps_e($user['email']) ?>"
                    autocomplete="email"
                    readonly
                >
            </div>

            <div class="settings-input">
                <label for="phone">Phone</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= ps_e($user['phone']) ?>"
                    maxlength="30"
                    autocomplete="tel"
                    required
                >
            </div>

            <div class="settings-input">
                <label for="nationality">Nationality</label>

                <input
                    type="text"
                    id="nationality"
                    name="nationality"
                    value="<?= ps_e($user['nationality']) ?>"
                    maxlength="100"
                    autocomplete="country-name"
                    required
                >
            </div>

            <button type="submit">
                Save changes
                <span aria-hidden="true">→</span>
            </button>

        </form>

        <section class="account-danger" aria-labelledby="delete-account-title">
            <h2 id="delete-account-title">Delete account</h2>
            <p>Permanently remove your account and its data.</p>
            <a class="danger-link" href="/delete-account.php">Delete account</a>
        </section>

    </div>

</main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
