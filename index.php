<?php require __DIR__.'/php-scripts/home-page.php'; ?>

<!DOCTYPE html>
<html lang="el">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php require_once __DIR__.'/php-scripts/public-seo.php'; ps_public_seo('/', 'Plant System Hellas | Έξυπνο πότισμα και αισθητήρες φυτών', 'Plant System Hellas: εφαρμογή για παρακολούθηση φυτών με αισθητήρες ESP32, μετρήσεις υγρασίας και θερμοκρασίας, ιστορικό και αυτόματο πότισμα.', true); ?>

    
    <link rel="stylesheet" href="styles/indexstyles.css">

</head>

<body>

    <?php include 'navigation.php'; ?>
    <main>

    <section class="hero">

        <div class="hero-text">

            <h1>Plant System</h1>

            <p>
                Έξυπνη τεχνολογία για πιο υγιή φυτά.
            </p>

            <p class="hero-description">
                Παρακολούθησε την κατάσταση των φυτών σου
                και φρόντισέ τα πιο εύκολα με τη βοήθεια
                έξυπνων αισθητήρων.
            </p>

        </div>


        <div class="moving-plants">

            <img src="media/kardia.png" class="plant plant1" alt="">
            <img src="media/kardia.png" class="plant plant2" alt="">
            <img src="media/kardia.png" class="plant plant3" alt="">

        </div>

    </section>

    <section class="info">

        <h2>Έξυπνη φροντίδα για τα φυτά σου</h2>

        <p>
            Το Plant System χρησιμοποιεί αισθητήρες για να
            παρακολουθεί τις συνθήκες του φυτού και να σου
            παρέχει χρήσιμες πληροφορίες.
        </p>


        <div class="info-cards">

            <div class="info-card">

                <img src="media/monitoring.png" class="monitoring" alt="">

                <h3>Παρακολούθηση</h3>

                <p>
                    Παρακολούθησε τις συνθήκες του φυτού
                    σου σε πραγματικό χρόνο.
                </p>

            </div>


            <div class="info-card">

                <img src="media/watertap.png" class="watertap" alt="">

                <h3>Αυτόματο πότισμα</h3>

                <p>
                    Έλεγξε το πότισμα και βοήθησε τα φυτά
                    σου να παραμένουν υγιή.
                </p>

            </div>


            <div class="info-card">

                <img src="media/brain.png" alt="">

                <h3>Έξυπνη τεχνολογία</h3>

                <p>
                    Όλα τα δεδομένα συγκεντρωμένα σε
                    μία εύχρηστη εφαρμογή.
                </p>

            </div>

        </div>

    </section>

  <?php

if (!isset($_SESSION['user_id'])):
?>

<section class="account">

    <h2>Ξεκίνα τώρα</h2>

    <p>
        Δημιούργησε λογαριασμό ή συνδέσου
        για να χρησιμοποιήσεις το Plant System.
    </p>

    <div class="account-buttons">

        <a href="<?= ps_e(ps_url('login.php')) ?>" class="login-button">
            Log-in
        </a>

        <a href="<?= ps_e(ps_url('signup.php')) ?>" class="signup-button">
            Sign-up
        </a>

    </div>

</section>

<?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>
