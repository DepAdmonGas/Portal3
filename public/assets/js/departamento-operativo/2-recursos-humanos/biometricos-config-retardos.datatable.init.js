document.addEventListener('DOMContentLoaded', function () {

var c = document.getElementById('container');
if (!c) return;

var moduleStationKey = c.dataset.moduleStationKey || 'biometricos';

var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel) {
var deptGroup = sel.querySelector('optgroup[label="Departamentos"]');
if (deptGroup) deptGroup.remove();
var firstOpt = sel.querySelector('option[value=""]');
if (firstOpt) firstOpt.textContent = 'Selecciona una estación';
}

var permisos = {
crear: c.dataset.puedeCrear === 'true',
editar: c.dataset.puedeEditar === 'true',
eliminar: c.dataset.puedeEliminar === 'true'
};

var messageEl = document.getElementById('retardos-empty-message');
var contentEl = document.getElementById('retardos-content');

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

function getEstacionParam() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel && sel.value) {
var val = sel.value;
if (val.indexOf('depto_') === 0) return parseInt(val.replace('depto_', ''), 10);
return parseInt(val.replace('estacion_', ''), 10);
}
return 0;
}

function isNoEstacion() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
return !sel || sel.value === '';
}

function toggleAgregarBtn() {
var btn = document.getElementById('btn-agregar-horario');
if (!btn) return;
btn.style.display = (!permisos.crear || isNoEstacion()) ? 'none' : '';
}

var table = $('#table-horarios').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: false,
destroy: true,
order: [[0, 'asc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/datatable-horarios',
type: 'GET',
data: function () {
var idEst = getEstacionParam();
return idEst ? { id_estacion: idEst } : {};
},
dataSrc: function (json) {
return json.data || [];
}
},
columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },
{ title: 'Titulo horario', data: 'titulo', className: 'align-middle text-start' },
{ title: 'Hora entrada', data: 'hora_entrada', className: 'align-middle text-center' },
{ title: 'Hora salida', data: 'hora_salida', className: 'align-middle text-center' },
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {
var p = row.permisos || {};
var disabledEdit = p.disabledEdit ? 'disabled opacity-50 pointer-events-none' : '';
var disabledDelete = p.disabledDelete ? 'disabled opacity-50 pointer-events-none' : '';

var editClick = !p.disabledEdit
? '@click=\'$dispatch("open-horario-edit", ' + JSON.stringify(row) + ')\''
: '';

var deleteClick = p.disabledDelete ? '' : '@click="async () => { await deleteAction({ url: \'/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/delete-horario\', id: ' + row.id + ', name: \'' + (row.titulo || '').replace(/'/g, "\\'") + '\', table: \'#table-horarios\' }); }"';

return '<div x-data="actions()" class="d-flex gap-1 justify-content-center">'
+ '<div class="dropdown dropstart">'
+ '<a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown" data-bs-display="static"><i class="ti ti-dots-vertical fs-6"></i></a>'
+ '<ul class="dropdown-menu">'
+ '<li><a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 ' + disabledEdit + '" ' + editClick + '><i class="fs-4 ti ti-edit"></i> Editar</a></li>'
+ '<li><a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 ' + disabledDelete + '" ' + deleteClick + '><i class="fs-4 ti ti-trash"></i> Eliminar</a></li>'
+ '</ul>'
+ '</div>'
+ '</div>';
}
}
]
});

function recargarHorarios() {
var idEst = getEstacionParam();
var url = '/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/datatable-horarios';
if (idEst) url += '?id_estacion=' + idEst;
table.ajax.url(url).load();
toggleAgregarBtn();
}

function cargarRetardoIncidencia() {
var idEst = getEstacionParam();
if (!idEst) {
var evt = new CustomEvent('retardo-infidencia-loaded', { detail: { retardo: 0, incidencia: 0 } });
document.dispatchEvent(evt);
return;
}
fetch('/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/get-retardo-incidencia?id_estacion=' + idEst)
.then(function (r) { return r.json(); })
.then(function (json) {
var data = json.data || { retardo: 0, incidencia: 0 };
var evt = new CustomEvent('retardo-infidencia-loaded', { detail: data });
document.dispatchEvent(evt);
});
}

if (isNoEstacion()) {
showEmptyMessage();
} else {
showTable();
cargarRetardoIncidencia();
}

toggleAgregarBtn();

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null) {
ms.hideBadge();
showEmptyMessage();
toggleAgregarBtn();
return;
}
showTable();
recargarHorarios();
cargarRetardoIncidencia();
}
});
}

});
