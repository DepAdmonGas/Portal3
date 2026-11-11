document.addEventListener('DOMContentLoaded', () => {
    if (!window.jQuery || !window.jQuery.fn?.DataTable) return;

    const $ = window.jQuery;
    const canonicalTable = document.getElementById('checador-role-comodines-canonical-table');
    const legacyTable = document.getElementById('checador-role-comodines-legacy-table');
    let tableApi = null;

    if (canonicalTable && !$.fn.dataTable.isDataTable(canonicalTable)) {
        tableApi = $(canonicalTable).DataTable({
            processing: false,
            serverSide: false,
            autoWidth: false,
            stateSave: false,
            order: [],
            pageLength: 10,
            columnDefs: [{ targets: -1, orderable: false, searchable: false, className: 'text-center' }],
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        });
    } else if (canonicalTable) {
        tableApi = $(canonicalTable).DataTable();
    }

    if (legacyTable && !$.fn.dataTable.isDataTable(legacyTable)) {
        $(legacyTable).DataTable({
            processing: false,
            serverSide: false,
            autoWidth: false,
            stateSave: false,
            order: [],
            pageLength: 10,
            language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        });
    }

    const filterModal = document.getElementById('checador-comodines-search-modal');
    if (!tableApi || !canonicalTable || !filterModal) return;

    const fields = {
        from: document.getElementById('checador-comodines-filter-from'),
        to: document.getElementById('checador-comodines-filter-to'),
        worker: document.getElementById('checador-comodines-filter-worker'),
        workplace: document.getElementById('checador-comodines-filter-workplace'),
        status: document.getElementById('checador-comodines-filter-status'),
    };
    const filterMessage = filterModal.querySelector('[data-comodines-filter-message]');
    const applied = { from: '', to: '', worker: '', workplace: '', status: '' };
    const readDraft = () => ({
        from: fields.from.value,
        to: fields.to.value,
        worker: fields.worker.value,
        workplace: fields.workplace.value,
        status: fields.status.value,
    });
    const writeDraft = (filters) => {
        fields.from.value = filters.from;
        fields.to.value = filters.to;
        fields.worker.value = filters.worker;
        fields.workplace.value = filters.workplace;
        fields.status.value = filters.status;
        if (window.jQuery.fn.select2) {
            [fields.worker, fields.workplace].forEach((field) => {
                if (window.jQuery(field).hasClass('select2-hidden-accessible')) {
                    window.jQuery(field).trigger('change.select2');
                }
            });
        }
    };
    const showFilterMessage = (text = '', error = false) => {
        if (!filterMessage) return;
        filterMessage.textContent = text;
        filterMessage.classList.toggle('d-none', text === '');
        filterMessage.classList.toggle('alert-danger', error);
        filterMessage.classList.toggle('alert-info', !error);
    };

    const filterKey = 'checador-role-comodines-filter';
    if (!window[filterKey]) {
        $.fn.dataTable.ext.search.push((settings, _data, rowIndex) => {
            if (settings.nTable?.id !== canonicalTable.id) return true;
            const row = settings.aoData[rowIndex]?.nTr;
            if (!row) return false;
            const from = row.dataset.fromLocal || '';
            const toExclusive = row.dataset.toLocalExclusive || '';
            const workerId = row.dataset.workerId || '';
            const homeId = row.dataset.homeWorkplaceId || '';
            const coverageId = row.dataset.coverageWorkplaceId || '';
            const status = row.dataset.statusCode || '';

            if (applied.from && toExclusive && toExclusive <= applied.from) return false;
            if (applied.to && from && from > applied.to) return false;
            if (applied.worker && workerId !== applied.worker) return false;
            if (applied.workplace && homeId !== applied.workplace && coverageId !== applied.workplace) return false;
            if (applied.status && status !== applied.status) return false;
            return true;
        });
        window[filterKey] = true;
    }

    filterModal.addEventListener('show.bs.modal', () => {
        writeDraft(applied);
        showFilterMessage('');
    });
    filterModal.querySelector('[data-comodines-filter-clear]')?.addEventListener('click', () => {
        const empty = { from: '', to: '', worker: '', workplace: '', status: '' };
        Object.assign(applied, empty);
        writeDraft(empty);
        tableApi.draw();
        showFilterMessage('Filtros limpiados.');
    });
    filterModal.querySelector('[data-comodines-filter-apply]')?.addEventListener('click', () => {
        const next = readDraft();
        if (next.from && next.to && next.to < next.from) {
            showFilterMessage('Revisa el periodo: la fecha final debe ser igual o posterior a la inicial.', true);
            return;
        }
        Object.assign(applied, next);
        tableApi.draw();
        window.bootstrap?.Modal.getOrCreateInstance(filterModal).hide();
    });
});
