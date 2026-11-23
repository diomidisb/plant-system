<?php require __DIR__.'/php-scripts/delete-account-handler.php'; ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delete account | Plant System Hellas</title>
<link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
<link rel="stylesheet" href="/styles/settings.css?v=account-delete-1">
</head><body>
<?php require __DIR__.'/navigation.php'; ?>
<main class="settings-page"><section class="settings-card delete-account-card">
<div class="settings-header"><span>ACCOUNT</span><h1>Delete account</h1><p>This action is permanent and cannot be undone.</p></div>
<p>Your account, measurements, watering rules, support requests and login history will be deleted. Your devices will be disconnected from your account and remain registered without an owner. They can be assigned again later.</p>
<p>Stop any active watering before continuing.</p>
<?php if ($error): ?><div class="settings-error" role="alert"><?= ps_e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="settings-success" role="status"><?= ps_e($success) ?></div><?php endif; ?>
<?php if ($account['auth_provider']==='google'): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="send_code"><button type="submit">Send confirmation code</button></form>
<p>Since you sign in with Google, confirm ownership using the code sent to your account email.</p>
<?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>"><input type="hidden" name="action" value="delete_account">
<div class="settings-input"><label for="confirm-email">Confirm your account email</label><input id="confirm-email" type="email" name="confirm_email" autocomplete="email" maxlength="255" required></div>
<?php if ($account['auth_provider']==='local'): ?>
<div class="settings-input"><label for="delete-password">Current password</label><input id="delete-password" type="password" name="password" autocomplete="current-password" required></div>
<?php else: ?>
<div class="settings-input"><label for="delete-code">Confirmation code</label><input id="delete-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
<?php endif; ?>
<label class="delete-check"><input type="checkbox" name="confirm" value="yes" required><span>I understand that my account and its data will be permanently deleted.</span></label>
<button class="danger-button" type="submit">Permanently delete my account</button>
</form><p><a href="/">Cancel and return home</a></p>
</section></main>
<?php require __DIR__.'/footer.php'; ?>
</body></html>
