document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;
if (!document.getElementById('tabla-reportes')) return;

const moduleStationKey = c.dataset.moduleStationKey || 'pedido-pinturas';
const puedeCrear = c.dataset.puedeCrear === 'true';
const puedeEditar = c.dataset.puedeEditar === 'true';
const puedeEliminar = c.dataset.puedeEliminar === 'true';

const BASE = '/departamento-operativo/comercializadora/pedido-pinturas';

window.tablaReportes = null;

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

function buildUrlReportes() {
var est = getEstacionId();
if (!est && !esTodasEstaciones()) return null;
return BASE + '/reportes?id_estacion=' + (est || 0);
}

var EMPTY_URL_REPORTES = BASE + '/reportes?id_estacion=0';

function estatusReporteBadge(status) {
if (status === 0) return '<span class="badge bg-danger text-white">Pendiente</span>';
if (status === 1) return '<span class="badge bg-success">Finalizado</span>';
return '<span class="badge bg-secondary">Desconocido</span>';
}

function renderAccionesReporte(row) {
var editable = row.status === 0;
var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';

html += '<a class="dropdown-item pointer pp-btn-detalle-reporte" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';

html += '<a class="dropdown-item pointer pp-btn-editar-reporte' + ((puedeEditar && editable) ? '' : ' disabled') + '" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';

html += '<a class="dropdown-item pointer pp-btn-eliminar-reporte' + ((puedeEliminar && editable) ? '' : ' disabled') + '" data-id="' + row.id + '" data-nombre="Reporte #00' + row.id + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';

html += '</div></div>';
return html;
}

var columnasReportes = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '98px',
render: function (data) { return '00' + data; } },
{ title: 'Nombre del personal', data: 'personal', className: 'align-middle text-start text-nowrap' },
{ title: 'Estación', data: 'nombre_estacion', className: 'align-middle text-center text-nowrap',
visible: esTodasEstaciones() },
{ title: 'Fecha y hora', data: 'fecha_hora', className: 'align-middle text-center text-nowrap' },
{ title: 'Detalle', data: 'detalle', className: 'align-middle text-center' },
{ title: 'Estatus', data: 'status_label', className: 'align-middle text-center text-nowrap', width: '120px',
render: function (v, t, row) { return estatusReporteBadge(row.status); } },
{ title: '<i class="fas fa-ellipsis-v"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false,
render: function (v, t, row) { return renderAccionesReporte(row); } },
];

function recargarTablaReportes() {
var dt = window.tablaReportes;
if (!dt) return;
var url = buildUrlReportes() || EMPTY_URL_REPORTES;
dt.ajax.url(url).load();
var mostrarEstacion = esTodasEstaciones();
dt.column(2).visible(mostrarEstacion);
}

window.pinturasRecargarTablas = function () {
recargarTablaReportes();
};

function initTablaReportes() {
window.tablaReportes = $('#tabla-reportes').DataTable({
processing: true,
serverSide: false,
ajax: { type: 'GET', url: buildUrlReportes() || EMPTY_URL_REPORTES, dataSrc: function (json) { return (json && json.success) ? (json.reportes || []) : []; } },
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columnasReportes,
drawCallback: function () {
if (window.Alpine) { Alpine.initTree(document.querySelector('#tabla-reportes')); }
}
});
}

/* ============ SELECTOR DE ESTACIÓN ============ */
if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function () {
recargarTablaReportes();
document.dispatchEvent(new Event('pedido-pinturas:estacion-cambio'));
}
});
}

initTablaReportes();

/* ============ CLICKS TABLA REPORTES ============ */
$('#tabla-reportes').on('click', '.pp-btn-detalle-reporte', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
if (window.pedidoPinturasReporteComponentInstance && window.pedidoPinturasReporteComponentInstance.abrirDetalleReporte) {
window.pedidoPinturasReporteComponentInstance.abrirDetalleReporte(id);
}
});

$('#tabla-reportes').on('click', '.pp-btn-editar-reporte', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
window.location.href = BASE + '/reporte/' + id;
});

$('#tabla-reportes').on('click', '.pp-btn-eliminar-reporte', function (e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || 'Reporte';
if (window.pedidoPinturasReporteComponentInstance) {
window.pedidoPinturasReporteComponentInstance.eliminarReporte(id, nombre);
}
});

});

document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasReporteComponent', () => ({

puedeAcceso: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
hayContexto: false,

reporteActual: { id: 0, detalle_items: [], status: 0 },

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';

window.pedidoPinturasReporteComponentInstance = this;

this.actualizarHayContexto();

document.addEventListener('pedido-pinturas:estacion-cambio', () => {
this.actualizarHayContexto();
});
},

getSelector() {
return document.getElementById('module-station-selector-pedido-pinturas');
},

getIdEstacionContexto() {
const sel = this.getSelector();
if (sel && sel.value) {
const p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(document.getElementById('container').dataset.idEstacion || '0');
},

esTodasEstaciones() {
const sel = this.getSelector();
return sel && sel.value === '';
},

actualizarHayContexto() {
this.hayContexto = this.getIdEstacionContexto() > 0 && !this.esTodasEstaciones();
},

async nuevoReporte() {
const idEstacion = this.getIdEstacionContexto();
if (!idEstacion || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación específica para crear un reporte.');
return;
}

try {
const fd = new FormData();
fd.append('id_estacion', idEstacion);
const resp = await axios.post(this.BASE + '/crear-reporte', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Reporte creado correctamente');
setTimeout(() => { window.location.href = this.BASE + '/reporte/' + json.id; }, 800);
} else {
if (window.Notify) Notify.error(json.message || 'Error al crear el reporte');
}
} catch (e) {
console.error('Error creando reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al crear el reporte');
}
},

async abrirDetalleReporte(id) {
try {
const resp = await axios.get(this.BASE + '/reporte/data?id=' + id);
const json = resp.data;
if (!json.success) {
if (window.Notify) Notify.error('Error al cargar el detalle');
return;
}
this.reporteActual = json.reporte;
const m = document.getElementById('modalDetalleReporte');
bootstrap.Modal.getOrCreateInstance(m).show();
} catch (e) {
console.error('Error cargando detalle reporte:', e);
if (window.Notify) Notify.error('Error al cargar el detalle');
}
},

reporteStatusBadgeClass(status) {
if (status === 0) return 'bg-danger text-white';
if (status === 1) return 'bg-success text-white';
return 'bg-secondary text-white';
},

async aprobarReporte(id) {
try {
const resp = await axios.post(this.BASE + '/aprobar-reporte', { id: id });
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalDetalleReporte');
if (m) bootstrap.Modal.getOrCreateInstance(m).hide();
if (window.tablaReportes) window.tablaReportes.ajax.reload(null, false);
if (window.Notify) Notify.success(json.message || 'Reporte finalizado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al finalizar el reporte');
}
} catch (e) {
console.error('Error aprobando reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al finalizar el reporte');
}
},

async _confirmarEliminar(nombre) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El registro: ' + nombre + ' será eliminado',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
return result.isConfirmed;
},

async eliminarReporte(id, nombre) {
const ok = await this._confirmarEliminar(nombre);
if (!ok) return;

const idEstacion = this.getIdEstacionContexto();
try {
const resp = await axios.post(this.BASE + '/eliminar-reporte', { id: id, id_estacion: idEstacion });
const json = resp.data;
if (json.success) {
if (window.tablaReportes) window.tablaReportes.ajax.reload(null, false);
if (window.Notify) Notify.success(json.message || 'Reporte eliminado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el reporte');
}
} catch (e) {
console.error('Error eliminando reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el reporte');
}
},

}));

});