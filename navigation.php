<?php require __DIR__.'/php-scripts/navigation-state.php'; ?>

<link rel="stylesheet" href="styles/navstyles.css?v=20261009-language-toggle">
<script src="scripts/navigation.js" defer></script>
<script src="/scripts/language.js?v=20261009" defer></script>

<nav class="plant-nav" aria-label="Main navigation">
    <div class="navigation">

        <div class="navigation-left">
            <a
                href="/"
                class="logo-link"
                aria-label="Plant System home"
            >
                <img
                    class="nav-logo"
                    src="media/plant.png"
                    alt=""
                    width="50"
                    height="50"
                >
            </a>

            <?php if ($currentPage !== 'index.php'): ?>
                <a class="nav-link navigationitemhome" href="/">
                    Home
                </a>
            <?php endif; ?>


            <?php if ($currentPage !== 'About.php'): ?>
                <a class="nav-link navigationitemabout" href="About.php">
                    About
                </a>
            <?php endif; ?>
        </div>

        <div class="navigation-center">
            
        </div>

        <div class="navigation-right">
            <a class="language-switch language-toggle"
                href="/language.php?lang=<?= ps_language()==='el'?'en':'el' ?>&amp;return=<?= rawurlencode((string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/')) ?>"
                lang="<?= ps_language()==='el'?'en':'el' ?>"
                aria-label="<?= ps_language()==='el'?'Switch to English':'Αλλαγή σε Ελληνικά' ?>"><?= ps_language()==='el'?'En':'Gr' ?></a>

            <?php if ($isLoggedIn): ?>

                <div class="user-menu">
                    <button
                        type="button"
                        class="user-button"
                        aria-expanded="false"
                        aria-controls="userDropdown"
                    >
                        <img
                            src="media/profile.png"
                            class="user-icon"
                            alt=""
                             aria-hidden="true" 
                         >

                        <span class="user-name">
                            <?= htmlspecialchars(
                                $userName,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>
                        </span>

                        <span class="user-arrow" aria-hidden="true">
                            ^
                        </span>
                    </button>

                    <div
                        id="userDropdown"
                        class="user-dropdown"
                        hidden
                    >
                        <?php if(!$supportOnly): ?><a href="dashboard.php">Dashboard</a>
                        <a href="profile.php">Profile</a>
                        <a href="settings.php">Settings</a>
                        <a href="add-device.php">Add Device</a><?php endif; ?><a href="support.php">Support</a>

                        <div class="dropdown-divider"></div>

                        <a href="logout.php" class="logout-link">
                            Logout
                        </a>
                    </div>
                </div>

            <?php elseif ($isPending): ?>

                <div class="pending-nav">
                    <span class="pending-name">
                        <?= htmlspecialchars(
                            $pendingName,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </span>

                    <a href="pending.php">
                        Not activated
                    </a>
                </div>

            <?php else: ?>

                <?php if ($currentPage !== 'login.php'): ?>
                    <a
                        class="nav-link navigationitemlogin"
                        href="login.php"
                    >
                        Log-in
                    </a>
                <?php endif; ?>

                <?php if ($currentPage !== 'signup.php'): ?>
                    <a
                        class="nav-link navigationitemsignup"
                        href="signup.php"
                    >
                        Sign-up
                    </a>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</nav>
