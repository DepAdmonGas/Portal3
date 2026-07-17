document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

const $table = $('#tabla-aclaracion-voucher');
if (!$table.length) return;

const esMultiestacion = c.dataset.multiestacion === 'true';
const puedeEliminar = c.dataset.puedeEliminar === 'true';
const puedeEditar = c.dataset.puedeEditar === 'true';

const ajaxUrl = '/departamento-operativo/corporativo/aclaracion-voucher/get-data?year=' + idYear + '&mes=' + idMes;

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

function cls(enabled) { return enabled ? '' : ' disabled'; }

function renderEstado(row) {
if (row.estado === 1) return '<span class="badge bg-success">Pagado (Finalizado)</span>';
var tieneTicket = row.doc_ticket && row.doc_ticket !== '';
var tieneVoucher = row.doc_voucher && row.doc_voucher !== '';
if (tieneTicket && tieneVoucher) {
if (row.pagado == 1) return '<span class="badge bg-success">Pagado</span>';
return '<span class="badge bg-warning text-dark">Falta realizar el pago</span>';
}
if (!tieneTicket && !tieneVoucher) {
if (row.pagado == 1) return '<span class="badge bg-warning text-dark">Pagado, pero falta agregar el ticket y el voucher</span>';
return '<span class="badge bg-danger">Falta el ticket, el voucher y realizar el pago</span>';
}
if (row.pagado == 1) return '<span class="badge bg-warning text-dark">Pagado, pero falta agregar documentación</span>';
return '<span class="badge bg-danger">Falta documentación y/o el pago</span>';
}

function renderComentarios(row) {
var badge = row.total_comentarios > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.total_comentarios + '</span>'
: '';
return '<a href="" class="btn-av-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + row.id + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}

function renderAcciones(row) {
//var puedeDel = puedeEliminar && row.estado === 0;
//var puedeEdi = puedeEditar && row.estado === 0;

var puedeDel = row.estado === 0;
var puedeEdi = row.estado === 0;

var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer btn-av-detalle" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
html += '<a class="dropdown-item pointer btn-av-anexos" data-id="' + row.id + '"><i class="ti ti-file me-1"></i> Anexos</a>';
html += '<a class="dropdown-item pointer btn-av-editar' + cls(puedeEdi) + '" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
html += '<a class="dropdown-item pointer btn-av-eliminar' + cls(puedeDel) + '" data-id="' + row.id + '" data-nombre="' + row.nombre_ticket + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
html += '</div></div>';
return html;
}

// Sync sessionStorage from the server-rendered ModuleStationSelector on first load
if (esMultiestacion) {
var estacionId = 0;
var sel = document.getElementById('module-station-selector-aclaracion-voucher');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) estacionId = parseInt(p[1]);
}
if (!estacionId && typeof ModuleStationSelector !== 'undefined') {
var ms = ModuleStationSelector._instances && ModuleStationSelector._instances['aclaracion-voucher'];
if (ms) {
var v = ms.getValue();
if (v.id_estacion) estacionId = v.id_estacion;
}
}
if (estacionId) {
sessionStorage.setItem('av_estacion', String(estacionId));
} else {
sessionStorage.removeItem('av_estacion');
}
}

// Determine initial column visibility based on actual selection
var estacionSeleccionada = esMultiestacion ? parseInt(sessionStorage.getItem('av_estacion') || '0') : parseInt(c.dataset.idEstacion || '0');
var showEstacion = esMultiestacion && !estacionSeleccionada;

const columns = [
{ title: '#', data: 'id', className: 'align-middle text-center text-nowrap fw-normal' },
{ title: 'Estación', data: 'estacion_nombre', className: 'align-middle text-center text-nowrap', visible: showEstacion,
render: (v) => v || '' },
{ title: 'Fecha de solicitud', data: 'fecha_creacion', className: 'align-middle text-center text-nowrap' },
{ title: 'Solicitante', data: 'solicitante_nombre', className: 'align-middle text-nowrap' },
{ title: 'Ticket', data: 'nombre_ticket', className: 'align-middle text-center text-nowrap' },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center text-nowrap' },
{ title: 'Hora', data: 'hora', className: 'align-middle text-center text-nowrap' },
{ title: 'Valera', data: 'valera', className: 'align-middle text-center text-nowrap' },
{ title: 'Importe', data: 'importe', className: 'align-middle text-end text-nowrap',
render: (v) => '$ ' + parseFloat(v || 0).toFixed(2) },
{ title: 'No. Aclaración', data: 'numero_aclaracion', className: 'align-middle text-center text-nowrap' },
{ title: 'Ticket Doc', data: 'doc_ticket', className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v) => v
? '<i class="ti ti-download pointer text-primary fs-5 btn-av-download" data-tipo="aclaracion-voucher" data-file="' + encodeURIComponent(v) + '"></i>'
: '<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>' },
{ title: 'Voucher Doc', data: 'doc_voucher', className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v) => v
? '<i class="ti ti-download pointer text-primary fs-5 btn-av-download" data-tipo="aclaracion-voucher" data-file="' + encodeURIComponent(v) + '"></i>'
: '<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>' },
{ title: 'Estado', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderEstado(row) },
{ title: '<i class="ti ti-message fs-7"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderComentarios(row) },
{ title: '<i class="ti ti-dots-vertical fs-5"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderAcciones(row) }
];

$table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: ajaxUrl,
data: function (d) {
var est = '';
if (esMultiestacion) {
est = sessionStorage.getItem('av_estacion') || '';
if (!est) {
var sel = document.getElementById('module-station-selector-aclaracion-voucher');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) est = p[1];
}
}
} else {
est = c.dataset.idEstacion || '';
}
if (est) d.id_estacion = est;
},
dataSrc: function (json) {
if (!json.success) return [];
return json.data || [];
}
},
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 15,
lengthMenu: [15, 30, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columns,
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-aclaracion-voucher'));
}
const event = new CustomEvent('av-tabla-recargada');
document.dispatchEvent(event);
}
});

$table.on('click', '.btn-av-detalle', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('av:ver-detalle', { detail: { id: id } }));
});

$table.on('click', '.btn-av-comentarios', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('av:ver-comentarios', { detail: { id: id } }));
});

$table.on('click', '.btn-av-anexos', function (e) {
var puede = $(this).hasClass('disabled');
if (puede) { e.preventDefault(); return; }
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('av:ver-anexos', { detail: { id: id } }));
});

$table.on('click', '.btn-av-editar', function (e) {
var puede = $(this).hasClass('disabled');
if (puede) { e.preventDefault(); return; }
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('av:editar', { detail: { id: id } }));
});

$table.on('click', '.btn-av-eliminar', function (e) {
var puede = $(this).hasClass('disabled');
if (puede) { e.preventDefault(); return; }
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
document.dispatchEvent(new CustomEvent('av:eliminar', { detail: { id: id, nombre_ticket: nombre } }));
});

$table.on('click', '.btn-av-download', function (e) {
e.preventDefault();
var tipo = this.dataset.tipo;
var file = this.dataset.file;
if (file) window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + file, '_blank');
});

var moduleStationKey = c.dataset.moduleStationKey || '';
if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion) {
sessionStorage.setItem('av_estacion', v.id_estacion);
} else {
sessionStorage.removeItem('av_estacion');
}
window.location.reload();
}
});
}

});
