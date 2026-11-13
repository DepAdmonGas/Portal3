window.initChecadorAttendanceDataTable = function (root) {
    root = root || document;
    var tableElement = root.querySelector('#checador-daily-attendance-table');
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
        if (!emptyStateRow || !resultsRoot || resultsRoot.dataset.attendanceState !== 'success') return false;
        body.removeChild(body.rows[0]);
    }
    if (jQuery.fn.DataTable.isDataTable(tableElement)) return true;

    jQuery(tableElement).DataTable({
        processing: false,
        serverSide: false,
        deferRender: true,
        autoWidth: false,
        stateSave: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, searchable: false, targets: [8] }
        ],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: 'No hay colaboradores atribuibles para esta consulta.',
            zeroRecords: 'No hay colaboradores que coincidan con la búsqueda.'
        }
    });
    return true;
};

document.addEventListener('DOMContentLoaded', function () {
    window.initChecadorAttendanceDataTable(document);
});
