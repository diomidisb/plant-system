<?php require __DIR__.'/php-scripts/footer-state.php'; ?>
<link rel="stylesheet" href="styles/navstyles.css?v=20261009-footer-phone">
<footer class="footer">

    <div class="footer-item">
        <h3>Τοποθεσία</h3>
        <p>Τρίπολη, Ελλάδα</p>
    </div>

    <div class="footer-item">
        <h3>Τηλέφωνο</h3>
        <p><a href="tel:+306980034951">6980034951</a></p>
    </div>

    <div class="footer-item">
        <h3>Υποστήριξη</h3>
        <p>Δευτέρα - Παρασκευή</p>
        <p>09:00 - 17:00</p>
    </div>

    <div class="footer-item">
        <h3>Email</h3>
        <p><a href="mailto:<?= htmlspecialchars($footerSupportEmail,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($footerSupportEmail,ENT_QUOTES,'UTF-8') ?></a></p>
    </div>

</footer>
