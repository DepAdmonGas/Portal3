window.initChecadorAttendanceDataTable = function (root) {
    root = root || document;
    var tableElement = root.querySelector('#checador-daily-attendance-table');
    var pageContainer = root.querySelector('#container')
        || (root.closest ? root.closest('#container') : null)
        || root;
    if (!tableElement) return false;
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return false;

    var body = tableElement.tBodies && tableElement.tBodies[0];
    if (!body || (body.rows.length > 0 && body.rows[0].cells.length !== 9)) return false;
    if (jQuery.fn.DataTable.isDataTable(tableElement)) return true;

    var $table = jQuery(tableElement);
    var actionDropdownSelector = '[data-checador-attendance-actions]';
    var initializeActionDropdowns = function () {
        if (!window.bootstrap || !window.bootstrap.Dropdown) return;
        tableElement.querySelectorAll(actionDropdownSelector).forEach(function (trigger) {
            window.bootstrap.Dropdown.getOrCreateInstance(trigger, {
                popperConfig: { strategy: 'fixed' }
            });
        });
    };

    $table.on('preDraw.dt.portal3AttendanceDropdown', function () {
        if (!window.bootstrap || !window.bootstrap.Dropdown) return;
        tableElement.querySelectorAll(actionDropdownSelector).forEach(function (trigger) {
            var dropdown = window.bootstrap.Dropdown.getInstance(trigger);
            if (dropdown) dropdown.dispose();
        });
    });
    $table.on('draw.dt.portal3AttendanceDropdown', initializeActionDropdowns);

    $table.DataTable({
        processing: false,
        serverSide: false,
        deferRender: true,
        autoWidth: false,
        stateSave: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, searchable: false, targets: [8] }
        ],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: pageContainer && pageContainer.dataset.attendanceEmptyMessage
                ? pageContainer.dataset.attendanceEmptyMessage
                : 'No hay marcaciones observadas para esta consulta.',
            zeroRecords: 'No hay colaboradores que coincidan con la búsqueda.'
        }
    });
    initializeActionDropdowns();
    return true;
};

document.addEventListener('DOMContentLoaded', function () {
    window.initChecadorAttendanceDataTable(document);
});
