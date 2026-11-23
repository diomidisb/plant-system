(() => {
    function initializePasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input || button.dataset.initialized === 'true') return;
            button.dataset.initialized = 'true';
            button.hidden = false;
            const label = document.querySelector(`label[for="${input.id}"]`);
            const name = label ? label.textContent.trim().toLowerCase() : 'password';
            button.setAttribute('aria-label', `Show ${name}`);
            button.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'Show';
                button.setAttribute('aria-pressed', String(show));
                button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${name}`);
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePasswordToggles, { once: true });
    } else {
        initializePasswordToggles();
    }
})();
