<?php require __DIR__.'/php-scripts/login-view.php'; ?>
<form action="/php-scripts/login.php" method="post">
    <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
    <div class="input-box">
        <label for="email">Email</label>
        <input
            type="text"
            id="email"
            name="email"
            placeholder="Email"
            value="<?= ps_e($email) ?>"
            autocomplete="username"
            maxlength="255"
            required
        >
    </div>
    <div class="input-box">
        <div class="password-label">
            <label for="password">Password</label>
            <a href="<?= ps_e(ps_url('forgot-password.php')) ?>">Forgot?</a>
        </div>
        <div class="password-field">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >
            <button
                type="button"
                class="password-toggle"
                data-password-toggle="password"
                aria-controls="password"
                aria-label="Show password"
                aria-pressed="false"
                hidden
            >Show</button>
        </div>
    </div>
    <label class="remember">
        <input type="checkbox" name="remember" value="1">
        <span>Remember me for 30 days</span>
    </label>
    <button type="submit" class="auth-submit">
        <span>Log in</span>
        <span aria-hidden="true">→</span>
    </button>
</form>
<div class="divider"><span>OR</span></div>
<p class="create-account">
    Don't have an account?
    <a href="<?= ps_e(ps_url('signup.php')) ?>">Create one</a>
</p>
<?php require __DIR__ . '/partials/auth-end.php'; ?>
