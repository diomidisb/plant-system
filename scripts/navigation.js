(() => {
    function initializeNavigation() {
        const nav = document.querySelector('.plant-nav');

        if (!nav) return;

        const userMenu = nav.querySelector('.user-menu');
        const button = nav.querySelector('.user-button');
        const dropdown = nav.querySelector('.user-dropdown');

        if (!userMenu || !button || !dropdown) return;
        if (button.dataset.initialized === 'true') return;

        button.dataset.initialized = 'true';
        button.setAttribute('aria-label', 'Μενού χρήστη');

        if (dropdown.id) {
            button.setAttribute('aria-controls', dropdown.id);
        }

        function setMenuOpen(open) {
            dropdown.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
        }

        setMenuOpen(false);

        button.addEventListener('click', () => {
            setMenuOpen(dropdown.hidden);
        });

        document.addEventListener('click', (event) => {
            if (!userMenu.contains(event.target)) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !dropdown.hidden) {
                setMenuOpen(false);
                button.focus();
            }
        });

        document.addEventListener('focusin', (event) => {
            if (!userMenu.contains(event.target)) {
                setMenuOpen(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeNavigation,
            { once: true }
        );
    } else {
        initializeNavigation();
    }
})();