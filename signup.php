<?php
if (!defined('PS_PUBLIC_PAGE')) define('PS_PUBLIC_PAGE',true); require_once __DIR__ . '/php-scripts/signup-view.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Plant System</title>
    <link rel="stylesheet" href="<?= ps_e(ps_url('styles/navstyles.css')) ?>">
    <link rel="stylesheet" href="<?= ps_e(ps_url('styles/signupstyle.css')) ?>">
    <script src="<?= ps_e(ps_url('scripts/signup.js')) ?>" defer></script>
</head>

<body>

<?php require __DIR__ . '/navigation.php'; ?>

<main class="signup-page">

    <div class="signup-card">

        <div class="signup-visual">
            <img
                src="<?= ps_e(ps_url('media/plant.png')) ?>"
                alt="Plant"
                class="signup-plant"
            >

            <span>PLANT SYSTEM</span>
            <h2>Start growing.</h2>

            <p>
                Create your account and start taking better care of your plants.
            </p>
        </div>

        <div class="signup-content">

            <div class="signup-header">
                <span class="welcome">GET STARTED</span>
                <h1>Create account</h1>

                <?php if ($error !== ''): ?>
                    <div class="form-error" role="alert">
                        <?= ps_e($error) ?>
                    </div>
                <?php endif; ?>

                <p>
                    Enter your information to create your account.
                </p>
            </div>

            <form
                action="<?= ps_e(ps_url('php-scripts/signup.php')) ?>"
                method="post"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= ps_e($csrfToken) ?>"
                >

                <div class="form-row">

                    <div class="input-box">
                        <label for="name">Name</label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Your name"
                            value="<?= ps_e($old['name'] ?? '') ?>"
                            maxlength="100"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="input-box">
                        <label for="surname">Surname</label>

                        <input
                            type="text"
                            id="surname"
                            name="surname"
                            placeholder="Your surname"
                            value="<?= ps_e($old['surname'] ?? '') ?>"
                            maxlength="100"
                            autocomplete="family-name"
                            required
                        >
                    </div>

                </div>

                <div class="input-box">
                    <label for="email">Email address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= ps_e($old['email'] ?? '') ?>"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-row">

                    <div class="input-box">
                        <label for="phone">Phone number</label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="+30 69XXXXXXXX"
                            value="<?= ps_e($old['phone'] ?? '') ?>"
                            maxlength="30"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <div class="input-box">
                        <label for="nationality">Nationality</label>

                        <select
                            id="nationality"
                            name="nationality"
                            autocomplete="country-name"
                            required
                        >
                            <option value="">Select nationality</option>
                            <option value="Greek" <?= ($old['nationality'] ?? '') === 'Greek' ? 'selected' : '' ?>>Greek</option>
                            <option value="Albanian" <?= ($old['nationality'] ?? '') === 'Albanian' ? 'selected' : '' ?>>Albanian</option>
                            <option value="Italian" <?= ($old['nationality'] ?? '') === 'Italian' ? 'selected' : '' ?>>Italian</option>
                            <option value="German" <?= ($old['nationality'] ?? '') === 'German' ? 'selected' : '' ?>>German</option>
                            <option value="French" <?= ($old['nationality'] ?? '') === 'French' ? 'selected' : '' ?>>French</option>
                            <option value="British" <?= ($old['nationality'] ?? '') === 'British' ? 'selected' : '' ?>>British</option>
                            <option value="American" <?= ($old['nationality'] ?? '') === 'American' ? 'selected' : '' ?>>American</option>
                            <option value="Other" <?= ($old['nationality'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                </div>

                <div class="input-box">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        minlength="12"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >

                    <div class="password-strength">
                        <div class="strength-bar">
                            <span id="strength-bar"></span>
                        </div>

                        <span id="strength-text">
                            Password strength
                        </span>
                    </div>
                </div>

                <div class="input-box">
                    <label for="confirm-password">Confirm password</label>

                    <input
                        type="password"
                        id="confirm-password"
                        name="confirm_password"
                        placeholder="Repeat your password"
                        minlength="12"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <label class="terms">
                    <input
                        type="checkbox"
                        name="terms"
                        value="1"
                        required
                    >

                    <span>
                        I agree to the
                        <a href="#">Terms &amp; Conditions</a>
                    </span>
                </label>

                <button type="submit">
                    Create account
                    <span aria-hidden="true">→</span>
                </button>

            </form>

            <p class="login-text">
                Already have an account?
                <a href="<?= ps_e(ps_url('login.php')) ?>">Log in</a>
            </p>

        </div>

    </div>

</main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
