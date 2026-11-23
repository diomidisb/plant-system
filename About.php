<?php require __DIR__.'/php-scripts/about-page.php'; ?>

<!DOCTYPE html>
<html lang="el">

<head>
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php require_once __DIR__.'/php-scripts/public-seo.php'; ps_public_seo('/About.php', 'Σχετικά με το Plant System Hellas | Αισθητήρες και αυτόματη άρδευση', 'Γνώρισε το Plant System, το σύστημα παρακολούθησης φυτών με αισθητήρες υγρασίας χώματος και θερμοκρασίας, για έξυπνη φροντίδα και αυτόματο πότισμα.'); ?>

    <link rel="stylesheet" href="styles/navstyles.css">
    <link rel="stylesheet" href="styles/aboutstyles.css">
</head>

<body>

    <?php include 'navigation.php'; ?>


    <main class="about-page">

        <section class="about-section">

            <h1>Σχετικά με το Plant System Hellas</h1>

            <p>
                Το Plant System είναι ένα έξυπνο σύστημα παρακολούθησης
                και φροντίδας φυτών. Στόχος του είναι να βοηθά τον χρήστη
                να παρακολουθεί τις συνθήκες του φυτού και να γνωρίζει
                πότε χρειάζεται νερό ή περισσότερη φροντίδα.
            </p>

            <p>
                Οι συμβατές συσκευές ESP32 στέλνουν μετρήσεις υγρασίας χώματος,
                θερμοκρασίας, υγρασίας αέρα και φωτισμού στην εφαρμογή.
                Το dashboard παρουσιάζει τις τελευταίες μετρήσεις και το ιστορικό τους.
                Με τον κατάλληλο εξοπλισμό, ο χρήστης μπορεί να ελέγχει το πότισμα
                και να ορίζει κανόνες αυτόματης άρδευσης.
            </p>

        </section>


        <section class="contact-section">

            <h2>Επικοινωνία</h2>

            <p>Για ερωτήσεις σχετικά με τη χρήση της εφαρμογής, επικοινώνησε στο
                <a href="mailto:plantsystemhellas@gmail.com">plantsystemhellas@gmail.com</a>.
            </p>
            <p>Αν έχεις λογαριασμό, μπορείς επίσης να στείλεις αίτημα από το
                Support στο μενού χρήστη και να δεις την κατάσταση και τις απαντήσεις του.</p>

        </section>

    </main>


    <?php include 'footer.php'; ?>

</body>

</html>
