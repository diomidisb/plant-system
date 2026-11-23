<?php require __DIR__.'/php-scripts/forgot-password-view.php'; ?>
<form action="<?= ps_e(ps_url('php-scripts/forgot-password.php')) ?>" method="post">
    <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
    <div class="input-box">
        <label for="email">Email address</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="you@example.com"
            autocomplete="email"
            maxlength="255"
            required
        >
    </div>
    <button type="submit" class="auth-submit">Send reset link</button>
</form>
<p class="auth-note">For Google accounts, reset your password through Google.</p>
<p class="create-account"><a href="<?= ps_e(ps_url('login.php')) ?>">Back to login</a></p>
<?php require __DIR__ . '/partials/auth-end.php'; ?>
