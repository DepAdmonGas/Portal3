document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const $table = $('#tabla-formatos');
if (!$table.length) return;

const puedeCrear = c.dataset.puedeCrear === 'true';
const puedeEditar = c.dataset.puedeEditar === 'true';
const puedeEliminar = c.dataset.puedeEliminar === 'true';
const puedeDescargar = c.dataset.puedeDescargar === 'true';
const esMultiestacion = c.dataset.multiestacion === 'true';
const moduleStationKey = c.dataset.moduleStationKey || '';

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

function getEstacionId() {
var sel = document.getElementById('module-station-selector-formatos');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function isTodasEstaciones() {
var sel = document.getElementById('module-station-selector-formatos');
return sel && sel.value === '';
}

function buildUrl() {
var est = getEstacionId();
if (!est && !isTodasEstaciones()) return null;
return '/departamento-operativo/recursos-humanos/formatos/get-data?id_estacion=' + (est || 0);
}

var EMPTY_URL = '/departamento-operativo/recursos-humanos/formatos/get-data?id_estacion=0';
var initialUrl = buildUrl() || EMPTY_URL;

function estatusBadge(status) {
if (status === 0) return '<span class="badge bg-danger">Pendiente</span>';
if (status === 3) return '<span class="badge bg-success">Autorizado</span>';
if (status === 4) return '<span class="badge bg-success">Finalizado</span>';
return '<span class="badge bg-warning text-white">En proceso</span>';
}

function renderFirma(row) {
var status = Number(row.status);
var cls = 'fmt-btn-firmar';
var icon = 'ti-writing fs-8';

switch (status) {
case 0: 
cls += ' text-muted opacity-50'; 
break;
case 1: 
cls += ' text-dark'; 
break;
case 2: 
cls += ' text-primary'; 
break;
case 3: 
cls += ' text-success'; 
break;
case 4: 
icon = 'ti-signature fs-10';
cls += ' text-success opacity-50'; 
break;
}

return '<i class="ti ' + icon + ' pointer ' + cls + '"'
+ ' data-id="' + row.id + '" data-id-localidad="' + row.id_localidad
+ '" data-formato="' + row.formato + '" data-status="' + row.status
+ '" data-toggle="tooltip" title="Firmar formato"></i>';
}

function renderComentarios(row) {
var count = row.num_comentarios || 0;
var badge = (count > 0)
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>'
: '';
return '<a href="javascript:void(0)" class="fmt-btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center"'
    + ' data-id="' + row.id + '" data-nombre="' + (row.nombre_empleado || '') + '" data-formato="' + row.formato + '" data-formato-nombre="' + (row.formato_nombre || '') + '"'
    + ' title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}

function renderAcciones(row) {
var st = row.status;
var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';

html += '<a class="dropdown-item pointer fmt-btn-detalle' + (row.puede_detalle ? '' : ' disabled') + '" data-id="' + row.id + '" data-formato="' + row.formato + '"><i class="ti ti-eye me-1"></i> Detalle</a>';

html += '<a class="dropdown-item pointer fmt-btn-pdf' + (row.puede_pdf ? '' : ' disabled') + '" data-id="' + row.id + '" data-formato="' + row.formato + '"><i class="ti ti-file-text me-1"></i> Descargar PDF</a>';

html += '<a class="dropdown-item pointer fmt-btn-editar' + (row.puede_editar ? '' : ' disabled') + '" data-id="' + row.id + '" data-id-localidad="' + row.id_localidad + '" data-formato="' + row.formato + '"><i class="ti ti-pencil me-1"></i> Editar</a>';

html += '<a class="dropdown-item pointer fmt-btn-eliminar' + (row.puede_eliminar ? '' : ' disabled') + '" data-id="' + row.id + '" data-id-localidad="' + row.id_localidad + '" data-formato="' + row.formato + '" data-nombre-empleado="' + (row.nombre_empleado || '') + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';

html += '</div></div>';
return html;
}

var columns = [
{ 
title: '#', 
data: 'id', 
className: 'align-middle text-center', 
width: '40px',
render: function (data, type, row) {
return '00' + data;
}
},
{ title: 'Estación/Departamento', data: 'nombre_localidad', className: 'align-middle text-center text-nowrap', visible: !isTodasEstaciones() },
{ title: 'Fecha y hora', data: 'fecha_hora', className: 'align-middle text-center text-nowrap' },
{ title: 'Nombre del empleado', data: 'nombre_empleado', className: 'align-middle text-start text-nowrap' },
{ title: 'Formato', data: 'formato_nombre', className: 'align-middle text-center text-nowrap' },
{ title: 'Firma', data: null, className: 'align-middle text-center', orderable: false, searchable: false,
render: function(v, t, row) { return renderFirma(row); } },
{ title: '<i class="ti ti-message fs-7"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false,
render: function(v, t, row) { return renderComentarios(row); } },
{ title: 'Estatus', data: 'status_label', className: 'align-middle text-center text-nowrap',
render: function(v, t, row) { return estatusBadge(row.status); } },
{ title: '<i class="fas fa-ellipsis-v"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false,
render: function(v, t, row) { return renderAcciones(row); } },
];

window.tablaFormatos = $table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: initialUrl,
dataSrc: function(json) {
if (!json.success) return [];
return json.data || [];
}
},
autoWidth: false,
stateSave: false,
order: [[0, 'asc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columns,
drawCallback: function() {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-formatos'));
}
}
});

function recargarTabla() {
var url = buildUrl();
var dt = window.tablaFormatos;
if (!dt) return;
dt.ajax.url(url || EMPTY_URL).load();
}

function toggleEstacionColumn(dt) {
if (!dt) return;
dt.column(1).visible(isTodasEstaciones());
}

$table.on('click', '.fmt-btn-firmar', function(e) {
e.preventDefault();
var el = $(this);
if (el.hasClass('text-muted')) return;
var id = parseInt(this.dataset.id);
var idLocalidad = parseInt(this.dataset.idLocalidad || '0');
var formato = parseInt(this.dataset.formato || '0');
if (window.formatosComponentInstance && typeof window.formatosComponentInstance.abrirFirmar === 'function') {
window.formatosComponentInstance.abrirFirmar(id, idLocalidad, formato);
}
});

$table.on('click', '.fmt-btn-comentarios', function(e) {
    e.preventDefault();
    var id = parseInt(this.dataset.id);
    var nombre = this.dataset.nombre || '';
    var formato = parseInt(this.dataset.formato || '0');
    var formatoNombre = this.dataset.formatoNombre || '';
    if (window.formatosComponentInstance && typeof window.formatosComponentInstance.abrirComentarios === 'function') {
        window.formatosComponentInstance.abrirComentarios(id, nombre, formato, formatoNombre);
    }
});

$table.on('click', '.fmt-btn-detalle', function(e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var formato = parseInt(this.dataset.formato || '0');
if (window.formatosComponentInstance && typeof window.formatosComponentInstance.abrirDetalle === 'function') {
window.formatosComponentInstance.abrirDetalle(id, formato);
}
});

$table.on('click', '.fmt-btn-pdf', function(e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var formato = parseInt(this.dataset.formato || '0');
if (window.formatosComponentInstance && typeof window.formatosComponentInstance.descargarPdf === 'function') {
window.formatosComponentInstance.descargarPdf(id, formato);
}
});

$table.on('click', '.fmt-btn-editar', function(e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var idLocalidad = parseInt(this.dataset.idLocalidad || '0');
var formato = parseInt(this.dataset.formato || '0');
if (window.formatosComponentInstance && typeof window.formatosComponentInstance.abrirEditar === 'function') {
window.formatosComponentInstance.abrirEditar(id, idLocalidad, formato);
}
});

$table.on('click', '.fmt-btn-eliminar', function(e) {
e.preventDefault();
if (this.classList.contains('disabled')) return;
var id = parseInt(this.dataset.id);
var idLocalidad = parseInt(this.dataset.idLocalidad || '0');
var formato = parseInt(this.dataset.formato || '0');
var nombreEmpleado = this.dataset.nombreEmpleado || '';
if (window.formatosComponentInstance && typeof window.formatosComponentInstance.eliminarFormulario === 'function') {
window.formatosComponentInstance.eliminarFormulario(id, idLocalidad, formato, nombreEmpleado);
}
});

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function(ms) {
try {
recargarTabla();
} catch (err) {
console.error('[Formatos] Error recargando tabla:', err);
}
toggleEstacionColumn(window.tablaFormatos);
document.dispatchEvent(new Event('formatos:estacion-cambio'));
}
});
}

toggleEstacionColumn(window.tablaFormatos);

});
