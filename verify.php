<?php
if (!defined('PS_PUBLIC_PAGE')) define('PS_PUBLIC_PAGE',true);

require_once __DIR__ . '/php-scripts/verify.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Email | Plant System</title>

    <link
        rel="stylesheet"
        href="<?= ps_e(ps_url('styles/navstyles.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= ps_e(ps_url('styles/verify.css')) ?>"
    >
</head>

<body>

<?php require __DIR__ . '/navigation.php'; ?>

<main class="verify-page">

    <div class="verify-card">

        <div class="verify-header">
            <span>PLANT SYSTEM</span>
            <h1>Verify your email</h1>

            <p>
                Enter the 6-digit code we sent to your email.
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="verify-error" role="alert">
                <?= ps_e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>

            <div class="verify-success" role="status">
                <?= ps_e($success) ?>
            </div>

            <a
                href="<?= ps_e(ps_url('login.php')) ?>"
                class="login-link"
            >
                Continue to Login
                <span aria-hidden="true">→</span>
            </a>

        <?php else: ?>

            <form
                action="<?= ps_e(ps_url('verify.php')) ?>"
                method="post"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= ps_e(ps_csrf()) ?>"
                >

                <div class="verify-input">

                    <label for="email">Email address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= ps_e($email) ?>"
                        autocomplete="email"
                        readonly
                        required
                    >

                </div>

                <div class="verify-input">

                    <label for="code">Verification code</label>

                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="verification-code"
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                    >

                </div>

                <button type="submit">
                    Verify Email
                    <span aria-hidden="true">→</span>
                </button>

            </form>

        <?php endif; ?>

    </div>

</main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
