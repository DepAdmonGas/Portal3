(() => {
    'use strict';
    const init = () => {
        document.querySelectorAll('#configuration-pin-modal, #personnel-pin-modal').forEach((modal) => {
            modal.addEventListener('hidden.bs.modal', () => {
                modal.querySelectorAll('[data-one-time-pin]').forEach((pin) => { pin.textContent = ''; });
            });
            modal.querySelectorAll('[data-copy-pin]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const pin = modal.querySelector('[data-one-time-pin]')?.textContent?.trim();
                    if (!pin || !navigator.clipboard) return;
                    try { await navigator.clipboard.writeText(pin); button.textContent = 'Copiado'; }
                    catch (_) { button.textContent = 'No se pudo copiar'; }
                });
            });
            if (modal.dataset.showFeedback === '1' && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
