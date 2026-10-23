document.addEventListener('DOMContentLoaded', function () {
    var tableElement = document.getElementById('checador-daily-attendance-table');
    if (!tableElement || !window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;

    var body = tableElement.tBodies && tableElement.tBodies[0];
    if (!body || body.rows.length === 0 || body.rows[0].cells.length !== 9) return;
    if (jQuery.fn.DataTable.isDataTable(tableElement)) return;

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
});
