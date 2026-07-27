document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const $table = $('#tabla-control-docs');
const $tableInactivos = $('#tabla-control-docs-inactivos');
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
if ($.fn.DataTable && $.fn.DataTable.isDataTable($tableInactivos)) {
$tableInactivos.DataTable().destroy();
}

function getEstacionId() {
var sel = document.getElementById('module-station-selector-control-documentos-personal');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function isTodasEstaciones() {
var sel = document.getElementById('module-station-selector-control-documentos-personal');
return sel && sel.value === '';
}

function buildUrlActivos() {
var est = getEstacionId();
if (!est && !isTodasEstaciones()) return null;
return '/departamento-operativo/recursos-humanos/control-documentos-personal/get-data?id_estacion=' + (est || 0);
}

function buildUrlInactivos() {
var est = getEstacionId();
if (!est && !isTodasEstaciones()) return null;
return '/departamento-operativo/recursos-humanos/control-documentos-personal/get-data-inactivos?id_estacion=' + (est || 0);
}

var EMPTY_URL_ACTIVOS = '/departamento-operativo/recursos-humanos/control-documentos-personal/get-data?id_estacion=0';
var EMPTY_URL_INACTIVOS = '/departamento-operativo/recursos-humanos/control-documentos-personal/get-data-inactivos?id_estacion=0';
var initialUrlActivos = buildUrlActivos() || EMPTY_URL_ACTIVOS;

var tablaInactivosInit = null;
if (esMultiestacion) {
var initialUrlInactivos = buildUrlInactivos() || EMPTY_URL_INACTIVOS;
}

function docIcon(archivo, campo) {
if (archivo) {
return '<i class="ti ti-download fs-6 text-primary pointer cd-doc-download" data-archivo="' + archivo + '" data-campo="' + campo + '"></i>';
}
return '<i class="ti ti-file-off fs-6 text-muted"></i>';
}

function docPersonalIcon(archivo) {
if (archivo) {
return '<i class="ti ti-download fs-6 text-primary pointer cd-doc-download" data-archivo="' + archivo + '" data-campo="documentos"></i>';
}
return '<i class="ti ti-file-off fs-6 text-muted"></i>';
}

function estatusBadge(estatus) {
if (estatus === 'Finalizado') return '<span class="badge bg-success">Finalizado</span>';
if (estatus === 'Pendiente') return '<span class="badge bg-danger">Pendiente</span>';
return '<span class="badge bg-warning text-dark">En proceso</span>';
}

function renderComentarios(row) {
var count = row.num_comentarios || 0;
var badge = count > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>'
: '';
return '<a href="javascript:void(0)" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center cd-btn-comentarios" data-id="' + row.id + '" data-nombre="' + row.nombre_completo + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}

var docCampos = ['requisicion','curriculum','ine','acta_nacimiento','c_domicilio','nss','c_estudios','c_recomendacion','curp','a_infonavit','rfc','c_antecedentes','contrato'];
var docTitulos = ['RP','CV','IO','AN','CD','CAI','CE','CR','CURP','ARI','CSF','CANP','Contrato'];

function renderAccionesActivas(row) {
var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer cd-btn-asistencia" data-id="' + row.id + '"><i class="ti ti-clock me-1"></i> Asistencia</a>';
html += '<a class="dropdown-item pointer cd-btn-acceso" data-id="' + row.id + '" data-nombre="' + row.nombre_completo + '"><i class="ti ti-key me-1"></i> Acceso</a>';
html += '<a class="dropdown-item pointer cd-btn-editar" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar informacion</a>';
if (puedeEliminar) {
html += '<a class="dropdown-item pointer cd-btn-baja" data-id="' + row.id + '" data-nombre="' + row.nombre_completo + '"><i class="ti ti-user-off me-1"></i> Dar de Baja</a>';
}
html += '</div></div>';
return html;
}

function renderAccionesInactivas(row) {
var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
if (row.baja && row.baja.id_baja) {
html += '<a class="dropdown-item pointer cd-btn-detalle-baja" data-id-baja="' + row.baja.id_baja + '"><i class="ti ti-eye me-1"></i> Detalle de baja</a>';
}
html += '<a class="dropdown-item pointer cd-btn-asistencia-inactivo" data-id="' + row.id + '"><i class="ti ti-clock me-1"></i> Asistencia</a>';
html += '<a class="dropdown-item pointer cd-btn-acceso-inactivo" data-id="' + row.id + '" data-nombre="' + row.nombre_completo + '"><i class="ti ti-key me-1"></i> Acceso</a>';
if (!row.baja || !row.baja.id_baja) {
html += '<a class="dropdown-item pointer cd-btn-eliminar-inactivo" data-id="' + row.id + '" data-nombre="' + row.nombre_completo + '"><i class="ti ti-user-off me-1"></i> Dar de Baja</a>';
}
html += '</div></div>';
return html;
}

function renderFechaBaja(row) {
if (row.baja && row.baja.fecha_baja) {
return row.baja.fecha_baja;
}
return 'S/I';
}

function renderBajaColor(row) {
if (!row.baja) return '';
var ep = row.baja.estado_proceso;
if (ep === 0) return '#ffb6af';
if (ep === 1) return '#fcfcda';
if (ep === 2) return '#b0f2c2';
return '';
}

function buildDocColumns() {
var cols = [];
docCampos.forEach(function(campo, i) {
cols.push({
title: '<span title="' + docTitulos[i] + '">' + docTitulos[i] + '</span>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return docIcon(row.documentos[campo], campo); }
});
});
return cols;
}

var activasColumns = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '30px' },
{ title: 'Estacion/Departamento', data: 'nombre_estacion', className: 'align-middle text-start text-nowrap', visible: false },
{ title: 'Fecha ingreso', data: 'fecha_ingreso_format', className: 'align-middle text-center text-nowrap' },
{ title: 'No. Colaborador', data: 'no_colaborador', className: 'align-middle text-center' },
{ title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-start text-nowrap' },
{ title: 'Puesto', data: 'puesto', className: 'align-middle text-center text-nowrap' },
{ title: 'SD', data: 'sd', className: 'align-middle text-center', render: function(v) { return parseFloat(v || 0).toFixed(2); } },
{ title: 'Documentos Personales', data: 'documentos_archivo', className: 'align-middle text-center', orderable: false, searchable: false,
render: function(v) { return docPersonalIcon(v); } }
];

activasColumns = activasColumns.concat(buildDocColumns());

activasColumns.push({
title: '<i class="ti ti-message fs-7"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return renderComentarios(row); }
});

activasColumns.push({
title: 'Estatus',
data: 'estatus',
className: 'align-middle text-center text-nowrap',
render: function(v) { return estatusBadge(v); }
});

activasColumns.push({
title: '<i class="ti ti-dots-vertical fs-5"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return renderAccionesActivas(row); }
});

var inactivasColumns = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '30px' },
{ title: 'Estacion/Departamento', data: 'nombre_estacion', className: 'align-middle text-start text-nowrap', visible: false },
{ title: 'Fecha ingreso', data: 'fecha_ingreso_format', className: 'align-middle text-center text-nowrap' },
{ title: 'Fecha de baja', data: null, className: 'align-middle text-center text-nowrap',
render: function(v, t, row) { return renderFechaBaja(row); } },
{ title: 'No. Colaborador', data: 'no_colaborador', className: 'align-middle text-center' },
{ title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-start text-nowrap' },
{ title: 'Puesto', data: 'puesto', className: 'align-middle text-center text-nowrap' },
{ title: 'SD', data: 'sd', className: 'align-middle text-center', render: function(v) { return parseFloat(v || 0).toFixed(2); } },
{ title: 'Documentos Personales', data: 'documentos_archivo', className: 'align-middle text-center', orderable: false, searchable: false,
render: function(v) { return docPersonalIcon(v); } }
];

inactivasColumns = inactivasColumns.concat(buildDocColumns());

inactivasColumns.push({
title: '<i class="ti ti-message fs-7"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return renderComentarios(row); }
});

inactivasColumns.push({
title: 'Estatus',
data: 'estatus',
className: 'align-middle text-center text-nowrap',
render: function(v) { return estatusBadge(v); }
});

inactivasColumns.push({
title: '<i class="ti ti-dots-vertical fs-5"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return renderAccionesInactivas(row); }
});

window.tablaControlDocs = $table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: initialUrlActivos,
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
columns: activasColumns,
drawCallback: function() {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-control-docs'));
}
},
rowCallback: function(row, data) {
$(row).css('background-color', '');
}
});

if (esMultiestacion && $tableInactivos.length) {
window.tablaControlDocsInactivos = $tableInactivos.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: initialUrlInactivos,
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
columns: inactivasColumns,
drawCallback: function() {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-control-docs-inactivos'));
}
},
rowCallback: function(row, data) {
var bg = renderBajaColor(data);
$(row).css('background-color', bg || '');
}
});
}

function recargarTablaActivos() {
var url = buildUrlActivos();
var dt = window.tablaControlDocs;
if (!dt) return;
dt.ajax.url(url || EMPTY_URL_ACTIVOS).load();
}

function recargarTablaInactivos() {
var url = buildUrlInactivos();
var dt = window.tablaControlDocsInactivos;
if (!dt) return;
dt.ajax.url(url || EMPTY_URL_INACTIVOS).load();
}

function recargarAmbasTablas() {
recargarTablaActivos();
recargarTablaInactivos();
}

function toggleEstacionColumn(dt, thId) {
if (!dt) return;
var col = dt.column(1);
if (isTodasEstaciones()) {
col.visible(true);
$(thId).show();
} else {
col.visible(false);
$(thId).hide();
}
}

function bindTableEvents($tbl, prefix) {
$tbl.on('click', '.cd-doc-download', function(e) {
e.preventDefault();
var archivo = this.dataset.archivo;
var campo = this.dataset.campo;
if (archivo && window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.downloadDocumento(campo, archivo);
}
});

$tbl.on('click', '.cd-btn-comentarios', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirComentarios(id, nombre);
}
});

$tbl.on('click', '.cd-btn-asistencia, .cd-btn-asistencia-inactivo', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
window.location.href = '/departamento-operativo/recursos-humanos/control-documentos-personal/asistencia/' + id;
});

$tbl.on('click', '.cd-btn-acceso', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || '';
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirAcceso(id, nombre, false);
}
});

$tbl.on('click', '.cd-btn-acceso-inactivo', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || '';
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirAcceso(id, nombre, true);
}
});

$tbl.on('click', '.cd-btn-editar', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirEditar(id);
}
});

$tbl.on('click', '.cd-btn-eliminar', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.confirmarEliminar(id, nombre);
}
});

$tbl.on('click', '.cd-btn-baja', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirBaja(id, nombre);
}
});

$tbl.on('click', '.cd-btn-detalle-baja', function(e) {
e.preventDefault();
var idBaja = parseInt(this.dataset.idBaja);
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirDetalleBaja(idBaja);
}
});

$tbl.on('click', '.cd-btn-eliminar-inactivo', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
if (window.controlDocsComponentInstance) {
window.controlDocsComponentInstance.abrirBaja(id, nombre);
}
});
}

bindTableEvents($table, 'activos');
if (esMultiestacion && $tableInactivos.length) {
bindTableEvents($tableInactivos, 'inactivos');
}

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function(ms) {
try {
recargarAmbasTablas();
} catch (e) {
console.error('[ControlDocs] Error recargando tablas:', e);
}
toggleEstacionColumn(window.tablaControlDocs, '#th-estacion');
if (esMultiestacion) {
toggleEstacionColumn(window.tablaControlDocsInactivos, '#th-estacion-inactivos');
}
document.dispatchEvent(new Event('cd:estacion-cambio'));
}
});
}

toggleEstacionColumn(window.tablaControlDocs, '#th-estacion');
if (esMultiestacion) {
toggleEstacionColumn(window.tablaControlDocsInactivos, '#th-estacion-inactivos');
}

});
