import { Modal } from 'bootstrap';

// Forms marked data-confirm ask before submitting, using one shared accessible modal.
export function initConfirmations() {
    const element = document.getElementById('confirmModal');
    if (!element) {
        return;
    }
    const modal = new Modal(element);
    const message = element.querySelector('[data-confirm-message]');
    const accept = element.querySelector('[data-confirm-accept]');
    let pending = null;

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'yes') {
                return;
            }
            event.preventDefault();
            pending = form;
            message.textContent = form.dataset.confirm;
            modal.show();
        });
    });

    accept.addEventListener('click', () => {
        if (pending) {
            pending.dataset.confirmed = 'yes';
            modal.hide();
            pending.requestSubmit();
        }
    });
}
