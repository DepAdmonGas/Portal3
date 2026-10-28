(() => {
    if (document.documentElement.dataset.portalTableSelectionBound === 'true') return;
    document.documentElement.dataset.portalTableSelectionBound = 'true';

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
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

        const table = target?.closest('table');
        const row = target?.closest('tbody tr');
        if (!table || !row || !table.contains(row) || row.querySelector('[colspan]')) return;
        if (table.closest('[data-portal-table-selection="false"]')) return;

        table.querySelectorAll('tbody tr.selected').forEach((selectedRow) => {
            selectedRow.classList.remove('selected');
        });
        row.classList.add('selected', 'pointer');
    }, true);
})();
