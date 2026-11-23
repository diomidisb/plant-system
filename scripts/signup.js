const passwordInput = document.getElementById("password");
const strengthBar = document.getElementById("strength-bar");
const strengthText = document.getElementById("strength-text");

function updatePasswordStrength() {
    const password = passwordInput.value;

    if (password.length === 0) {
        strengthBar.style.width = "0%";
        strengthText.textContent = "Password strength";
        return;
    }

    let strength = 0;

    if (password.length >= 12) {
        strength++;
    }

    if (/[A-Z]/.test(password)) {
        strength++;
    }

    if (/[a-z]/.test(password)) {
        strength++;
    }

    if (/[0-9]/.test(password)) {
        strength++;
    }

    if (/[^A-Za-z0-9]/.test(password)) {
        strength++;
    }

    if (strength <= 2) {
        strengthBar.style.width = "33%";
        strengthText.textContent = "Weak";
        return;
    }

    if (strength <= 4) {
        strengthBar.style.width = "66%";
        strengthText.textContent = "Medium";
        return;
    }

    strengthBar.style.width = "100%";
    strengthText.textContent = "Strong";
}

passwordInput?.addEventListener(
    "input",
    updatePasswordStrength
);