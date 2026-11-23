<?php require __DIR__.'/php-scripts/logout-view.php'; ?>
<form action="<?= ps_e(ps_url('php-scripts/logout.php')) ?>" method="post">
    <input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">
    <button type="submit" class="auth-submit">Log out</button>
</form>
<p class="create-account"><a href="<?= ps_e(ps_url('index.php')) ?>">Cancel</a></p>
<?php require __DIR__ . '/partials/auth-end.php'; ?>
