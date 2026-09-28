document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const moduleStationKey = c.dataset.moduleStationKey || 'pedido-aditivo';
const estaEnIndexTabla = document.getElementById('tabla-pedidos') !== null;

if (!estaEnIndexTabla) return;

const puedeEditar = c.dataset.puedeEditar === 'true';
const puedeEliminar = c.dataset.puedeEliminar === 'true';
const puedeFirmar = c.dataset.puedeFirmar === 'true';
const esMultiestacion = c.dataset.multiestacion === 'true';

const BASE = '/departamento-operativo/comercializadora/pedido-aditivo';

window.tablaPedidos = null;

function getEstacionId() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function esTodasEstaciones() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
return sel && sel.value === '';
}

function buildUrlPedidos() {
var est = getEstacionId();
if (!est && !esTodasEstaciones()) return null;
return BASE + '/get-data?id_estacion=' + (est || 0);
}

var EMPTY_URL_PEDIDOS = BASE + '/get-data?id_estacion=0';

function estatusPedidoBadge(status) {
if (status === 0) return '<span class="badge bg-danger">Pendiente</span>';
if (status === 1) return '<span class="badge bg-warning text-white">En proceso</span>';
if (status === 2) return '<span class="badge bg-success">Finalizado</span>';
return '<span class="badge bg-secondary">Desconocido</span>';
}

function renderFirma(row) {
if (row.status === 0) {
return '<span class="text-muted" title="Pendiente de finalizar"><i class="ti ti-writing text-dark fs-8"></i></span>';
}
if (row.status === 1) {
if (puedeFirmar) {
return '<a href="' + BASE + '-firma/' + row.id + '" title="Firmar el VoBo de la solicitud"><i class="ti ti-signature text-primary fs-10" style="width:20px;height:20px;cursor:pointer;"></i></a>';
}
return '<span class="text-muted" title="En espera de firma de Autorización"><i class="ti ti-clock-hour-4 fs-8" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default;"></i></span>';
}
return '<i class="ti ti-signature text-success fs-10" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default;"></i>';
}

function renderAccionesPedido(row) {
var puedeGestionar = !esMultiestacion && !esTodasEstaciones();
var detalleDisabled = false;
var editarDisabled = row.status !== 0 || !puedeEditar || !puedeGestionar;
var eliminarDisabled = row.status !== 0 || !puedeEliminar || !puedeGestionar;
var pdfDisabled = row.status !== 2;
var pagosDisabled = row.status !== 2;

var items = '';
items += '<a class="dropdown-item pointer pa-btn-detalle' + (detalleDisabled ? ' disabled' : '') + '" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
items += '<a class="dropdown-item pointer pa-btn-pdf' + (pdfDisabled ? ' disabled' : '') + '" data-id="' + row.id + '"><i class="ti ti-file-text me-1"></i> Descargar PDF</a>';
items += '<a class="dropdown-item pointer pa-btn-pagos' + (pagosDisabled ? ' disabled' : '') + '" data-id="' + row.id + '"><i class="ti ti-moneybag me-1"></i> Pagos</a>';
items += '<a class="dropdown-item pointer pa-btn-editar' + (editarDisabled ? ' disabled' : '') + '" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
items += '<a class="dropdown-item pointer pa-btn-eliminar' + (eliminarDisabled ? ' disabled' : '') + '" data-id="' + row.id + '" data-nombre="Solicitud #00' + row.id + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';

return '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">' + items + '</div></div>';
}

function renderComentarios(row) {
var count = row.total_comentarios || 0;
var badge = count > 0 ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>' : '';
return '<a href="javascript:void(0)" class="pa-btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-comentario" data-id="' + row.id + '" title="Comentarios"><i class="ti ti-message fs-7"></i>' + badge + '</a>';
}

var columnasPedidos = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '40px',
render: function (data) { return '00' + data; } },
{ title: 'Orden de compra', data: 'orden_compra', className: 'align-middle text-center text-nowrap' },
{ title: 'Solicitado por', data: 'personal', className: 'align-middle text-start text-nowrap' },
{ title: 'Estación / Departamento', data: 'nombre_estacion', className: 'align-middle text-center text-nowrap',
visible: esTodasEstaciones() },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center text-nowrap' },
{ title: 'Para', data: 'para', className: 'align-middle text-start text-nowrap' },
{ title: 'Fecha de entrega', data: 'fecha_entrega', className: 'align-middle text-center text-nowrap' },
{ title: '<i class="ti ti-message fs-7"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false, width: '48px',
render: function (v, t, row) { return renderComentarios(row); } },
{ title: 'Firma', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: function (v, t, row) { return renderFirma(row); } },
{ title: 'Estatus', data: 'status_label', className: 'align-middle text-center text-nowrap', width: '100px',
render: function (v, t, row) { return estatusPedidoBadge(row.status); } },
{ title: '<i class="ti ti-dots-vertical fs-5"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false,
render: function (v, t, row) { return renderAccionesPedido(row); } },
];

function destroyTables() {
if (window.tablaPedidos) { window.tablaPedidos.destroy(); window.tablaPedidos = null; }
}

function initTables() {
destroyTables();

var urlPedidos = buildUrlPedidos() || EMPTY_URL_PEDIDOS;
var mostrarEstacion = esTodasEstaciones();

window.tablaPedidos = $('#tabla-pedidos').DataTable({
processing: true,
serverSide: false,
ajax: { type: 'GET', url: urlPedidos, dataSrc: function (json) { return (json && json.success) ? (json.data || []) : []; } },
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columnasPedidos,
drawCallback: function () {
if (window.Alpine) { Alpine.initTree(document.querySelector('#tabla-pedidos')); }
}
});
window.tablaPedidos.column(3).visible(mostrarEstacion);
}

function recargarTablaPedidos() {
var dt = window.tablaPedidos;
if (!dt) return;
var url = buildUrlPedidos() || EMPTY_URL_PEDIDOS;
dt.ajax.url(url).load();
dt.column(3).visible(esTodasEstaciones());
}

window.aditivoRecargarTablas = function () {
recargarTablaPedidos();
};

initTables();

/* ============ SELECTOR DE ESTACIÓN ============ */
if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function () {
recargarTablaPedidos();
document.dispatchEvent(new Event('pedido-aditivo:estacion-cambio'));
}
});
}

/* ============ CLICKS TABLA PEDIDOS ============ */
$('#tabla-pedidos').on('click', '.pa-btn-detalle', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
if (window.pedidoAditivoComponentInstance && window.pedidoAditivoComponentInstance.abrirDetalleSolicitud) {
window.pedidoAditivoComponentInstance.abrirDetalleSolicitud(id);
}
});

$('#tabla-pedidos').on('click', '.pa-btn-editar', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
window.location.href = BASE + '/' + id;
});

$('#tabla-pedidos').on('click', '.pa-btn-pdf', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
window.location.href = BASE + '/pdf/' + id;
});

$('#tabla-pedidos').on('click', '.pa-btn-pagos', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
if (window.pedidoAditivoComponentInstance && window.pedidoAditivoComponentInstance.abrirModalPagos) {
window.pedidoAditivoComponentInstance.abrirModalPagos(id);
}
});

$('#tabla-pedidos').on('click', '.pa-btn-comentarios', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
if (window.pedidoAditivoComponentInstance && window.pedidoAditivoComponentInstance.abrirModalComentarios) {
window.pedidoAditivoComponentInstance.abrirModalComentarios(id);
}
});

$('#tabla-pedidos').on('click', '.pa-btn-eliminar', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || 'Solicitud';
if (window.pedidoAditivoComponentInstance && window.pedidoAditivoComponentInstance.eliminarSolicitud) {
window.pedidoAditivoComponentInstance.eliminarSolicitud(id, nombre);
}
});

});