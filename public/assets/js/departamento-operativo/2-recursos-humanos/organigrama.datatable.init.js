document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const $table = $('#tabla-organigrama-versions');
if (!$table.length) return;

const puedeEliminar = c.dataset.puedeEliminar === 'true';
const moduleStationKey = c.dataset.moduleStationKey || '';

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

window._orgVersionActual = null;

function getEstacionId() {
var sel = document.getElementById('module-station-selector-organigrama');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function buildUrl() {
var est = getEstacionId();
if (!est) return null;
return '/departamento-operativo/recursos-humanos/organigrama/get-versions?id_estacion=' + est;
}

function cls(enabled) { return enabled ? '' : ' disabled'; }

function renderAcciones(row) {
var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer btn-org-ver" data-archivo="' + row.archivo + '"><i class="ti ti-eye me-1"></i> Ver imagen</a>';
html += '<a class="dropdown-item pointer btn-org-eliminar' + cls(puedeEliminar) + '" data-id="' + row.id + '" data-version="' + row.version + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
html += '</div></div>';
return html;
}

var EMPTY_URL = '/departamento-operativo/recursos-humanos/organigrama/get-versions?id_estacion=0';
var initialUrl = buildUrl() || EMPTY_URL;

var columns = [
{ title: 'Versión', data: 'version', className: 'align-middle text-center text-nowrap', width: '80px' },
{ title: 'Fecha de creación', data: 'fechacreacion_format', className: 'align-middle text-center text-nowrap' },
{ title: 'Observaciones', data: 'observaciones', className: 'align-middle text-center text-nowrap',
render: (v) => v || '<span class="text-muted">Sin observaciones</span>' },
{ title: '<i class="ti ti-dots-vertical fs-5"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderAcciones(row) }
];

window.tablaOrganigrama = $table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: initialUrl,
dataSrc: function (json) {
if (!json.success) return [];
return json.data || [];
}
},
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columns,
drawCallback: function () {
var api = this.api();
var total = api.rows({ filter: 'applied' }).count();
if (!window._orgVersionActual && total > 0) {
var firstData = api.row(0).data();
if (firstData && firstData.archivo) {
window._orgVersionActual = firstData.version;
document.dispatchEvent(new CustomEvent('org:ver-imagen', {
detail: { archivo: firstData.archivo, version: firstData.version }
}));
}
}

if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-organigrama-versions'));
}
document.dispatchEvent(new Event('org-tabla-recargada'));
}
});

function recargarTabla() {
var url = buildUrl();
var dt = window.tablaOrganigrama;
if (!dt) return;
window._orgVersionActual = null;
dt.ajax.url(url || EMPTY_URL).load();
}

$table.on('click', '.btn-org-ver', function (e) {
e.preventDefault();
var archivo = this.dataset.archivo;
if (archivo) {
var tr = $(this).closest('tr');
var row = window.tablaOrganigrama.row(tr);
var rowData = row.data();
window._orgVersionActual = rowData.version;
document.dispatchEvent(new CustomEvent('org:ver-imagen', {
detail: { archivo: archivo, version: rowData.version }
}));
}
});

$table.on('click', '.btn-org-eliminar', function (e) {
e.preventDefault();
var puede = $(this).hasClass('disabled');
if (puede) return;
var id = parseInt(this.dataset.id);
var version = this.dataset.version;
document.dispatchEvent(new CustomEvent('org:eliminar', { detail: { id: id, version: version } }));
});

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function (ms) {
try {
recargarTabla();
} catch (e) {
console.error('[Organigrama] Error recargando tabla:', e);
}
document.dispatchEvent(new Event('org:estacion-cambio'));
}
});
}

});
