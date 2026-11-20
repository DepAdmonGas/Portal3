window.initChecadorAttendanceDataTable = function (root) {
    root = root || document;
    var tableElement = root.querySelector('#checador-daily-attendance-table');
    var pageContainer = root.querySelector('#container')
        || (root.closest ? root.closest('#container') : null)
        || root;
    if (!tableElement || !window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return false;

    var body = tableElement.tBodies && tableElement.tBodies[0];
    if (!body) return false;
    if (body.rows.length > 0 && body.rows[0].cells.length !== 9) {
        var resultsRoot = tableElement.closest
            ? tableElement.closest('[data-checador-attendance-results]')
            : null;
        var emptyStateRow = body.rows.length === 1
            && body.rows[0].cells.length === 1
            && body.rows[0].cells[0].colSpan === 9;
        var verifiedRosterFallback = resultsRoot
            && resultsRoot.dataset.attendanceRosterFallback === 'true';
        if (!emptyStateRow || !resultsRoot
            || (resultsRoot.dataset.attendanceState !== 'success' && !verifiedRosterFallback)) return false;
        body.removeChild(body.rows[0]);
    }
    if (jQuery.fn.DataTable.isDataTable(tableElement)) return true;

    var $table = jQuery(tableElement);
    var selectedWorkerId = null;
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
        searching: true,
        lengthChange: true,
        paging: true,
        info: true,
        ordering: true,
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
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
        },
        rowCallback: function (row) {
            row.classList.add('pointer');
            row.classList.toggle('selected', Number(row.dataset.workerId || 0) === selectedWorkerId);
        }
    });
    $table.find('tbody').off('click.portal3AttendanceSelection', 'tr').on('click.portal3AttendanceSelection', 'tr', function (event) {
        if (event.target.closest('a, button, input, select, .dropdown-menu')) return;
        var workerId = Number(this.dataset.workerId || 0);
        if (workerId <= 0) return;
        selectedWorkerId = workerId;
        tableElement.querySelectorAll('tbody tr').forEach(function (row) {
            row.classList.toggle('selected', Number(row.dataset.workerId || 0) === selectedWorkerId);
        });
    });
    initializeActionDropdowns();
    return true;
};

document.addEventListener('DOMContentLoaded', function () {
    window.initChecadorAttendanceDataTable(document);
});
