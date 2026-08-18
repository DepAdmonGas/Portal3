document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const $table = $('#tabla-biometricos');
if (!$table.length) return;

const moduleStationKey = c.dataset.moduleStationKey || '';

function escHtml(str) {
return String(str || '').replace(/[&<>"']/g, function(m) {
return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
});
}

function getEstacionId() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel && sel.value) {
var val = sel.value;
if (val.indexOf('depto_') === 0) return val.replace('depto_', '');
return val.replace('estacion_', '');
}
return parseInt(c.dataset.idEstacion || '0');
}

function isTodasEstaciones() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
return sel && sel.value === '';
}

function buildUrl() {
var est = getEstacionId();
if (!est && !isTodasEstaciones()) return null;
return '/departamento-operativo/recursos-humanos/biometricos/get-data?id_estacion=' + (est || 0);
}

var EMPTY_URL = '/departamento-operativo/recursos-humanos/biometricos/get-data?id_estacion=0';
var initialUrl = buildUrl() || EMPTY_URL;

function fechaHoy() {
var hoy = new Date();
return hoy.getFullYear() + '-' +
String(hoy.getMonth() + 1).padStart(2, '0') + '-' +
String(hoy.getDate()).padStart(2, '0');
}

function renderDetalle(data, type, row) {
var detalle = row.detalle || '';
if (!detalle) return '<span class="text-muted">S/I</span>';
var cls = row.detalle_badge || 'bg-secondary';
return '<span class="badge ' + cls + ' fw-semibold">' + escHtml(detalle) + '</span>';
}

function renderIncidencia(data, type, row) {
var fechaRaw = row.fecha_raw || '';
var incidenciaDias = parseInt(row.incidencia_dias || 0);
var deadline = '';
if (fechaRaw && fechaRaw.length >= 10) {
var d = new Date(fechaRaw.substring(0, 10));
d.setDate(d.getDate() + incidenciaDias);
deadline = d.toISOString().substring(0, 10);
}

var tieneIncidencia = parseInt(row.total_incidencias || 0) > 0;

if (deadline && deadline < fechaHoy()) {
var cls = tieneIncidencia ? 'ti ti-alert-triangle text-warning' : 'ti ti-eye text-primary';
return '<i class="fs-7 pointer ' + cls + ' bio-btn-detalle-incidencia" data-id="' + row.id + '"></i>';
}

var cls2 = tieneIncidencia ? 'ti ti-alert-triangle text-warning' : 'ti ti-alert-triangle text-primary';
return '<i class="fs-7 pointer ' + cls2 + ' bio-btn-agregar-incidencia" data-id="' + row.id + '"></i>';
}

var columns = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '40px' },
{ title: 'Estación / Departamento', data: 'nombre_estacion', className: 'align-middle text-start text-nowrap', visible: false },
{ title: 'Nombre', data: 'nombre_completo', className: 'align-middle text-start text-nowrap' },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center text-nowrap' },
{ title: 'Sistema (Entrada)', data: 'hora_entrada', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sistema (Salida)', data: 'hora_salida', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sensor (Entrada)', data: 'hora_entrada_sensor', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Sensor (Salida)', data: 'hora_salida_sensor', className: 'align-middle text-center',
render: function(v) { return v || 'S/I'; } },
{ title: 'Detalle', data: null, className: 'align-middle text-center',
orderable: false, searchable: false,
render: renderDetalle },
{ title: 'Incidencia', data: null, className: 'align-middle text-center', width: '98px',
orderable: false, searchable: false,
render: renderIncidencia }
];

window.tablaBiometricos = $table.DataTable({
processing: false,
serverSide: false,
deferRender: true,
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 25,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
ajax: {
type: 'GET',
url: initialUrl,
dataSrc: function(json) {
if (!json.success) return [];
if (json.data && window.biometricosComponentInstance) {
window.biometricosComponentInstance.registros = json.data;
}
return json.data || [];
}
},
columns: columns
});

$table.off('click', '.bio-btn-detalle-incidencia');
$table.on('click', '.bio-btn-detalle-incidencia', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
if (window.biometricosComponentInstance) {
window.biometricosComponentInstance.verDetalleIncidencia(id);
}
});

$table.off('click', '.bio-btn-agregar-incidencia');
$table.on('click', '.bio-btn-agregar-incidencia', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
if (window.biometricosComponentInstance) {
window.biometricosComponentInstance.agregarIncidenciaModal(id);
}
});

function recargarTabla() {
var url = buildUrl();
var dt = window.tablaBiometricos;
if (!dt) return;
dt.ajax.url(url || EMPTY_URL).load();
}

function toggleEstacionColumn(dt) {
if (!dt) return;
dt.column(1).visible(isTodasEstaciones());
}

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function(ms) {
try {
recargarTabla();
} catch (e) {
console.error('[Biometricos] Error recargando tabla:', e);
}
toggleEstacionColumn(window.tablaBiometricos);
document.dispatchEvent(new Event('biometricos:estacion-cambio'));
}
});
}

toggleEstacionColumn(window.tablaBiometricos);

});
