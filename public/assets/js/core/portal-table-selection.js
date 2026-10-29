(() => {
    if (document.documentElement.dataset.portalTableSelectionBound === 'true') return;
    document.documentElement.dataset.portalTableSelectionBound = 'true';

    const DIALOG_SCOPE = '.modal, .offcanvas, [role="dialog"][aria-modal="true"]';

    const getDialogScope = (element) => element?.closest(DIALOG_SCOPE) ?? null;

    const clearSelectionInScope = (scope) => {
        document.querySelectorAll('table > tbody > tr.selected').forEach((selectedRow) => {
            if (getDialogScope(selectedRow) !== scope) return;
            selectedRow.classList.remove('selected', 'pointer');
        });
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        const table = target?.closest('table');
        const row = target?.closest('tbody tr');
        const scope = getDialogScope(target);

        // La página principal y cada modal/offcanvas mantienen selecciones
        // independientes. Un clic dentro del diálogo nunca cambia el fondo.
        clearSelectionInScope(scope);

        const calendar = target?.closest('.app-calendar');

        if (calendar) {
            // En calendarios sólo se marca el día pulsado. Los eventos, enlaces
            // de "+ más" y controles del calendario conservan su comportamiento.
            if (target.closest('.fc-event, .fc-more-link, .fc-button')) return;

            const dayCell = target.closest('.fc-daygrid-day');
            if (!dayCell || !calendar.contains(dayCell)) return;

            calendar.querySelectorAll('.fc-daygrid-day.portal-day-selected').forEach((selectedDay) => {
                selectedDay.classList.remove('portal-day-selected');
            });
            dayCell.classList.add('portal-day-selected');
            return;
        }

        if (!table || !row || !table.contains(row) || row.querySelector('[colspan]')) return;
        if (table.closest('[data-portal-table-selection="false"]')) return;

        row.classList.add('selected', 'pointer');
    }, true);

    // Si un diálogo se cierra con Escape, también limpiamos sólo su propia
    // selección; las tablas de la página que queda detrás no se modifican.
    document.addEventListener('hidden.bs.modal', (event) => {
        clearSelectionInScope(event.target);
    });
    document.addEventListener('hidden.bs.offcanvas', (event) => {
        clearSelectionInScope(event.target);
    });
})();
