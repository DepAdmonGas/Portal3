$(document).ready(function () {

const container = document.getElementById('container');
if (!container) return;

const idYear = container.dataset.idYear;
const idMes = container.dataset.idMes;
const idEstacion = container.dataset.idEstacion;
const puedeEditar = container.dataset.puedeEditar === 'true';
const puedeEliminar = container.dataset.puedeEliminar === 'true';
const puedeCrear = container.dataset.puedeCrear === 'true';
const mostrarCuenta = container.dataset.mostrarCuenta === 'true';
const getDataUrl = container.dataset.getDataUrl || '/departamento-operativo/corporativo/solicitud-vales/get-data';

const dt = $('#tabla-solicitud-vales').DataTable({
processing: true,
serverSide: false,
stateSave: true,
ajax: {
url: getDataUrl,
type: 'GET',
cache: false,
data: function (d) {
d.year = idYear;
d.mes = idMes;
},
dataSrc: function (json) {
if (json.success && json.data) {
return json.data;
}
return [];
}
},
columns: [
{ title: 'No.', data: 'folio_display', width: '62px', className: 'align-middle text-center', },
{ title: 'Fecha y Hora', data: null, className: 'align-middle text-center',
render: function (data, type, row) {
return (row.fecha || '') + ', ' + (row.hora || '');
}
},
{
title: 'Monto',
data: 'monto',
className: 'align-middle text-center',
render: function (data, type, row) {
const monto = parseFloat(data) || 0;

return '$' + monto.toLocaleString('es-MX', {
minimumFractionDigits: 2,
maximumFractionDigits: 2
}) + ' ' + (row.moneda || 'MXN');
}
},
{ title: 'Cargo a Cuenta', data: 'cargo_cuenta_display', className: 'align-middle text-center', visible: mostrarCuenta },
{ title: 'Concepto', data: 'concepto', className: 'align-middle text-start' },
{ title: 'Solicitante', data: 'solicitante', className: 'align-middle text-center' },
{ title: 'Autorizado por', data: 'autorizado_por', className: 'align-middle text-center'},
{ title: '<i class="ti ti-message fs-7"></i>', data: null, width: '48px', className: 'align-middle text-center', orderable: false, searchable: false,
render: function (data, type, row) {
var badge = row.total_comentarios > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.total_comentarios + '</span>'
: '';
return '<a href="" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + row.id + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}
},
{ title: '<i class="ti ti-dots-vertical fs-5"></i>', data: null, width: '48px', className: 'align-middle text-center',
render: function (data, type, row) {
const id = row.id;
const puedeEditarFila = puedeEditar;
const puedeEliminarFila = puedeEliminar;

let html = '<div class="dropdown dropstart pointer"><a data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer" data-action="detalle" data-id="' + id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
html += '<a class="dropdown-item pointer" data-action="pdf" data-id="' + id + '"><i class="ti ti-download me-1"></i> Descargar PDF</a>';
html += '<a class="dropdown-item pointer" data-action="documentacion" data-id="' + id + '"><i class="ti ti-file me-1"></i> Documentación</a>';
html += '<a class="dropdown-item pointer' + (!puedeEditarFila ? ' disabled' : '') + '" href="' + (!puedeEditarFila ? '#' : '/departamento-operativo/corporativo/solicitud-vales-editar/' + idYear + '/' + idMes + '/' + idEstacion + '/' + id) + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
html += '<a class="dropdown-item pointer' + (!puedeEliminarFila ? ' disabled' : '') + '" href="' + (!puedeEliminarFila ? '#' : '#') + '"' + (!puedeEliminarFila ? '' : ' data-action="eliminar" data-id="' + id + '" data-name="00' + row.folio + '"') + '><i class="ti ti-trash me-1"></i> Eliminar</a>';
html += '</div></div>';
return html;
}
}
],
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
columnDefs: [
{ orderable: false, searchable: false, targets: [7, 8] }
],
initComplete: function () {
this.api().column(3).visible(mostrarCuenta);
},
drawCallback: function () {
$('[data-bs-toggle="tooltip"]').tooltip();
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-solicitud-vales'));
}
}
});

window.tablaSolicitudVales = dt;

$(document).on('click', '[data-action="detalle"]', function (e) {
e.preventDefault();
const id = $(this).data('id');
document.dispatchEvent(new CustomEvent('ver-detalle', { detail: { id } }));
});

$(document).on('click', '[data-action="pdf"]', function (e) {
e.preventDefault();
const id = $(this).data('id');
window.open('/departamento-operativo/corporativo/solicitud-vales-pdf/' + id, '_blank');
});

$(document).on('click', '[data-action="documentacion"]', function (e) {
e.preventDefault();
const id = $(this).data('id');
document.dispatchEvent(new CustomEvent('ver-documentacion', { detail: { id } }));
});

$(document).on('click', '[data-action="comentarios"], .btn-comentarios', function (e) {
e.preventDefault();
const id = $(this).data('id');
document.dispatchEvent(new CustomEvent('abrir-comentarios', { detail: { id } }));
});

$(document).on('click', '[data-action="eliminar"]', function (e) {
e.preventDefault();
const id = $(this).data('id');
const name = $(this).data('name');
document.dispatchEvent(new CustomEvent('eliminar-solicitud', { detail: { id, name } }));
});

});
