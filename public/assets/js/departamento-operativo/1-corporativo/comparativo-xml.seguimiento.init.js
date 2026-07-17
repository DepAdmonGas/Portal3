$(document).ready(function () {
var container = document.getElementById('seguimiento-container');
if (!container) return;

var idYear = container.dataset.year;
var idEstacion = container.dataset.idEstacion;

var monthNames = { 1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril', 5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto', 9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre' };
var stationParam = idEstacion ? '&id_estacion=' + idEstacion : '';

$.getJSON('/departamento-operativo/comparativo-xml/logs?year=' + idYear + stationParam, function (resp) {
if (!resp.success) return;

function buildTable(tableId, columns, data, init) {
if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableId)) {
$(tableId).DataTable().destroy();
}
$(tableId + ' tbody').empty();
if (data.length === 0) {
$(tableId + ' tbody').html('<tr><td colspan="' + columns.length + '" class="text-center text-muted">Sin registros</td></tr>');
return;
}
$(tableId).DataTable({
data: data,
columns: columns,
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
pageLength: 15,
lengthMenu: [[15, 30, 50, 100], [15, 30, 50, 100]],
order: [[0, 'desc']],
columnDefs: [{ targets: '_all', className: 'align-middle' }]
});
}

// --- Accesos ---
buildTable('#tabla-accesos', [
{ data: 'num', width: '72px', className: 'text-center' },
{ data: 'usuario' },
{ data: 'puesto', className: 'text-center' },
{ data: 'fecha_hora', className: 'text-center' }
], resp.access.map(function (item, idx) {
return {
num: idx + 1,
usuario: item.usuario || '-',
puesto: item.puesto || '-',
fecha_hora: item.fecha_hora || '-'
};
}));

// --- Edits ---
buildTable('#tabla-edits', [
{ data: 'num', className: 'text-center' },
{ data: 'responsable' },
{ data: 'fecha_hora', className: 'text-center' },
{ data: 'mes_nombre', className: 'text-center' },
{ data: 'seccion', className: 'text-center' },
{ data: 'descripcion', className: 'text-center' },
{ data: 'monto', className: 'text-center fw-bold' }
], resp.edits.map(function (item, idx) {
return {
num: idx + 1,
responsable: item.responsable || '-',
fecha_hora: item.fecha_hora || '-',
mes_nombre: monthNames[item.mes] || item.mes || '-',
seccion: item.seccion || '-',
descripcion: item.descripcion || '-',
monto: '$' + (Number(item.monto) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
};
}));

// --- SAT Edits ---
buildTable('#tabla-sat-edits', [
{ data: 'num', className: 'text-center' },
{ data: 'responsable' },
{ data: 'fecha_hora', className: 'text-center' },
{ data: 'mes_nombre', className: 'text-center' },
{ data: 'categoria', className: 'text-center' },
{ data: 'descripcion', className: 'text-center' },
{ data: 'monto', className: 'text-center fw-bold' }
], resp.sat_edits.map(function (item, idx) {
return {
num: idx + 1,
responsable: item.responsable || '-',
fecha_hora: item.fecha_hora || '-',
mes_nombre: monthNames[item.mes] || item.mes || '-',
categoria: item.categoria || '-',
descripcion: item.descripcion || '-',
monto: '$' + (Number(item.monto) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
};
}));
});
});
