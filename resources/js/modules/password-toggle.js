// Show or hide a password field, and copy a generated password to the clipboard.
export function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) {
            return;
        }
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.querySelector('.bi').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        const source = document.getElementById(button.dataset.copyTarget);
        if (!source || !navigator.clipboard) {
            return;
        }
        button.addEventListener('click', async () => {
            await navigator.clipboard.writeText(source.textContent.trim());
            button.textContent = 'Copied';
        });
    });

    // Choosing "type a password" reveals the fields; generating or keeping hides them.
    document.querySelectorAll('input[name="password_mode"]').forEach((radio) => {
        const fields = document.getElementById('manual-password-fields');
        if (!fields) {
            return;
        }
        const sync = () => {
            const manual = document.querySelector('input[name="password_mode"]:checked')?.value === 'manual';
            fields.classList.toggle('d-none', !manual);
        };
        radio.addEventListener('change', sync);
        sync();
    });
}
