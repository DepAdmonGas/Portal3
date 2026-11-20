document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;

    document.querySelectorAll('[data-checador-configuration-datatable]').forEach(function (table) {
        if (jQuery.fn.DataTable.isDataTable(table)) return;

        const hasActions = table.tHead && table.tHead.rows.length > 0
            && table.tHead.rows[0].querySelector('th:last-child .ti-dots-vertical');

        jQuery(table).DataTable({
            autoWidth: false,
            pageLength: 15,
            lengthMenu: [15, 30, 50, 100],
            paging: true,
            searching: true,
            lengthChange: true,
            info: true,
            scrollX: '',
            scrollY: '',
            order: [[0, 'asc']],
            columnDefs: hasActions ? [{ orderable: false, searchable: false, targets: -1 }] : [],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json'
            },
            drawCallback: function () {
                if (!window.bootstrap || !window.bootstrap.Dropdown) return;
                this.api().table().container().querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (button) {
                    window.bootstrap.Dropdown.getOrCreateInstance(button, {
                        popperConfig: function (defaultConfig) {
                            return Object.assign({}, defaultConfig || {}, { strategy: 'fixed' });
                        }
                    });
                });
            }
        });
    });
});
