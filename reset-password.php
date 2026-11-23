<?php require __DIR__.'/php-scripts/reset-password-view.php'; ?>
<form action="<?= ps_e(ps_url('php-scripts/reset-password.php')) ?>" method="post">
    <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
    <div class="input-box">
        <label for="password">New password</label>
        <div class="password-field">
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                aria-describedby="passwordHelp"
                required
            >
            <button
                type="button"
                class="password-toggle"
                data-password-toggle="password"
                aria-controls="password"
                aria-label="Show new password"
                aria-pressed="false"
                hidden
            >Show</button>
        </div>
    </div>
    <div class="input-box">
        <label for="passwordConfirm">Confirm new password</label>
        <div class="password-field">
            <input
                type="password"
                id="passwordConfirm"
                name="password_confirm"
                autocomplete="new-password"
                required
            >
            <button
                type="button"
                class="password-toggle"
                data-password-toggle="passwordConfirm"
                aria-controls="passwordConfirm"
                aria-label="Show password confirmation"
                aria-pressed="false"
                hidden
            >Show</button>
        </div>
    </div>
    <p id="passwordHelp" class="auth-note">
        Use at least 12 characters, preferably a unique passphrase.
    </p>
    <button type="submit" class="auth-submit">Save new password</button>
</form>
<p class="create-account"><a href="<?= ps_e(ps_url('forgot-password.php')) ?>">Request a new link</a></p>
<?php require __DIR__ . '/partials/auth-end.php'; ?>
