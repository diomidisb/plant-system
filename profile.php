<?php require __DIR__.'/php-scripts/profile-page.php'; ?>

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

<title>Profile | Plant System</title>

<link
    rel="stylesheet"
    href="styles/navstyles.css"
>

<link rel="stylesheet" href="/styles/profile.css?v=20261011">

</head>

<body>

<?php include "navigation.php"; ?>

<main class="profile-page">

<div class="profile-card">

<h1>My Profile</h1>

<div class="profile-row">
<span class="profile-label">Name</span>
<span class="profile-value">
<?= ps_e($user["name"]) ?>
</span>
</div>

<div class="profile-row">
<span class="profile-label">Surname</span>
<span class="profile-value">
<?= ps_e($user["surname"]) ?>
</span>
</div>

<div class="profile-row">
<span class="profile-label">Email</span>
<span class="profile-value">
<?= ps_e($user["email"]) ?>
</span>
</div>

<div class="profile-row">
<span class="profile-label">Phone</span>
<span class="profile-value">
<?= ps_e($user["phone"]) ?>
</span>
</div>

<div class="profile-row">
<span class="profile-label">Nationality</span>
<span class="profile-value">
<?= ps_e($user["nationality"]) ?>
</span>
</div>

</div>

</main>

<?php include "footer.php"; ?>

</body>
</html>
