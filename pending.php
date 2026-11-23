<?php require __DIR__.'/php-scripts/pending-page.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Account Pending | Plant System</title>

<link
    rel="stylesheet"
    href="styles/navstyles.css"
>

<link rel="stylesheet" href="/styles/pending.css?v=20261011">

</head>

<body>

<?php include "navigation.php"; ?>

<main class="pending-page">

<div class="pending-card">

<div class="pending-icon">
✉️
</div>

<h1>Account not activated</h1>

<div class="pending-name">

<?= ps_e($pending["name"]) ?>

</div>

<p class="pending-message">

Your account has been created, but your email
address has not been verified yet.

</p>

<div class="pending-email">

<?= ps_e($pending["email"]) ?>

</div>

<?php if ($error): ?>

<div class="pending-error">

<?= ps_e($error) ?>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="pending-success">

<?= ps_e($success) ?>

</div>

<a
    href="verify.php?email=<?= urlencode($pending["email"]) ?>"
    class="pending-verify"
>
Enter verification code →
</a>

<?php else: ?>

<form method="POST">
<input type="hidden" name="csrf" value="<?= ps_e(ps_csrf()) ?>">

<button type="submit">

Send verification code

</button>

</form>

<?php endif; ?>

</div>

</main>

<?php include "footer.php"; ?>

</body>
</html>
