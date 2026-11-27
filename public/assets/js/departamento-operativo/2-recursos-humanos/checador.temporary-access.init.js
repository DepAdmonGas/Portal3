(() => {
    'use strict';
    const init = () => {
        const table = document.querySelector('#temporary-access-requests-table');
        if (table && window.jQuery?.fn?.DataTable && !window.jQuery.fn.DataTable.isDataTable(table)) {
            window.jQuery(table).DataTable({
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [5, 10, 25],
                order: [[3, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],
                language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
            });
            table.tBodies[0]?.addEventListener('click', (event) => {
                if (event.target.closest('a, button, input, label, select, textarea, form')) return;
                const row = event.target.closest('tr');
                if (!row || row.parentElement !== table.tBodies[0]) return;
                row.classList.toggle('table-primary');
                row.setAttribute('aria-selected', row.classList.contains('table-primary') ? 'true' : 'false');
            });
        }
        document.querySelectorAll('#configuration-pin-modal, #personnel-pin-modal').forEach((modal) => {
            modal.addEventListener('shown.bs.modal', () => {
                if (modal.id === 'personnel-pin-modal' && table && window.jQuery?.fn?.DataTable?.isDataTable(table)) {
                    window.jQuery(table).DataTable().columns.adjust();
                }
            });
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
