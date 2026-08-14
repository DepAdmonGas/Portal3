document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const $table = $('#tabla-horario-personal');
if (!$table.length) return;

const puedeEditar = c.dataset.puedeEditar === 'true';
const moduleStationKey = c.dataset.moduleStationKey || '';

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

function getEstacionId() {
var sel = document.getElementById('module-station-selector-horario-personal');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function isTodasEstaciones() {
var sel = document.getElementById('module-station-selector-horario-personal');
return sel && sel.value === '';
}

function buildUrl() {
var est = getEstacionId();
if (!est && !isTodasEstaciones()) return null;
return '/departamento-operativo/recursos-humanos/horario-personal/get-data?id_estacion=' + (est || 0);
}

var EMPTY_URL = '/departamento-operativo/recursos-humanos/horario-personal/get-data?id_estacion=0';
var initialUrl = buildUrl() || EMPTY_URL;

var DIAS = [
{ key: 'lunes', num: 1, label: 'Lunes' },
{ key: 'martes', num: 2, label: 'Martes' },
{ key: 'miercoles', num: 3, label: 'Miércoles' },
{ key: 'jueves', num: 4, label: 'Jueves' },
{ key: 'viernes', num: 5, label: 'Viernes' },
{ key: 'sabado', num: 6, label: 'Sábado' },
{ key: 'domingo', num: 7, label: 'Domingo' }
];

function escHtml(str) {
return String(str || '').replace(/[&<>"']/g, function(m) {
return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
});
}

function formatHora(h) {
var parts = String(h || '').split(':');
if (parts.length < 2) return '';
var hh = parseInt(parts[0], 10);
var mm = parts[1];
var sufijo = hh >= 12 ? 'pm' : 'am';
var h12 = hh % 12;
if (h12 === 0) h12 = 12;
return h12 + ':' + mm + ' ' + sufijo;
}

function formatHorario(turno) {
return formatHora(turno.hora_entrada) + ' a ' + formatHora(turno.hora_salida);
}

function renderDiaSelect(diaKey, diaNum) {
return function(v, t, row) {
var turnos = row.turnos || [];
var cur = row[diaKey] || null;
var curHorario = cur ? cur.horario : '';
var tooltip = cur && cur.formateado ? cur.formateado : 'Sin asignar';

var html = '<select class="form-select hp-select-dia p-3 border-0 bg-transparent" data-personal="' + row.id + '" data-dia="' + diaNum + '" title="' + escHtml(tooltip) + '"' + (puedeEditar ? '' : ' disabled') + '>';
html += '<option value="">Sin asignar</option>';
turnos.forEach(function(turno) {
var titulo = turno.titulo || '';
var hora = formatHorario(turno);
html += '<option value="' + escHtml(titulo) + '" data-hora="' + escHtml(hora) + '"' + (curHorario === titulo ? ' selected' : '') + '>' + escHtml(titulo) + '</option>';
});
html += '<option value="Descanso"' + (curHorario === 'Descanso' ? ' selected' : '') + '>Descanso</option>';
html += '</select>';
return html;
};
}


function initSelect2() {
if (!$.fn.select2) return;
$('.hp-select-dia', $table).each(function() {
var $sel = $(this);
if ($sel.data('select2')) return;
$sel.select2({
width: '100%',
dropdownParent: $('body'),
minimumResultsForSearch: Infinity,
language: 'es',
templateSelection: function(data) {
if (!data.id) return data.text;
var hora = data.element && data.element.dataset ? (data.element.dataset.hora || '') : '';
return hora || data.text;
},
templateResult: function(data) {
var texto = data.text || '';
if (data.id && data.element && data.element.dataset && data.element.dataset.hora) {
texto = texto + ' (' + data.element.dataset.hora + ')';
}
return $('<span></span>').text(texto);
}
});
var $container = $sel.next('.select2-container');
if ($container.length) {
$container.css('width', '100%');
var $selection = $container.find('.select2-selection');
if ($selection.length) {
$selection.addClass('bg-transparent p-3 border-0').css('height', 'auto');
$container.find('.select2-selection__rendered').css({ height: 'auto', 'line-height': '24px' });
$container.find('.select2-selection__arrow').css({ top: '0', height: '100%' });
}
}
});
}

function renderAcciones(row) {
if (c.dataset.puedeEliminar !== 'true') return '';
return '<i class="ti ti-trash fs-5 text-danger pointer hp-btn-eliminar" data-id="' + row.id + '" data-nombre="' + escHtml(row.nombre_completo) + '" title="Eliminar horario del personal"></i>';
}

var columns = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '30px' },
{ title: 'Estación/Departamento', data: 'nombre_estacion', className: 'align-middle text-start text-nowrap', visible: false },
{ title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-start text-nowrap' },
{ title: 'Puesto', data: 'puesto', className: 'align-middle text-center text-nowrap' }
];

DIAS.forEach(function(d) {
columns.push({
title: d.label,
data: d.key,
className: 'align-middle text-center p-0 hp-col-dia',
orderable: false,
searchable: false,
render: renderDiaSelect(d.key, d.num)
});
});

/*
columns.push({
title: '<i class="ti ti-trash fs-5"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function(v, t, row) { return renderAcciones(row); }
});
*/

window.tablaHorarioPersonal = $table.DataTable({
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
order: [[1, 'asc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columns,
drawCallback: function() {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-horario-personal'));
}
initSelect2();

}
});

$table.on('change', '.hp-select-dia', function() {
var idPersonal = parseInt(this.dataset.personal, 10);
var dia = parseInt(this.dataset.dia, 10);
var horario = this.value;
if (window.horarioPersonalComponentInstance) {
window.horarioPersonalComponentInstance.guardarHorario(idPersonal, dia, horario);
}
});

$table.on('click', '.hp-btn-eliminar', function(e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || '';
if (window.horarioPersonalComponentInstance) {
window.horarioPersonalComponentInstance.eliminarHorarioPersonal(id, nombre);
}
});

function recargarTabla() {
var url = buildUrl();
var dt = window.tablaHorarioPersonal;
if (!dt) return;
dt.ajax.url(url || EMPTY_URL).load();
}

function toggleEstacionColumn(dt) {
if (!dt) return;
var col = dt.column(1);
col.visible(isTodasEstaciones());
}

if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function(ms) {
try {
recargarTabla();
} catch (e) {
console.error('[HorarioPersonal] Error recargando tabla:', e);
}
toggleEstacionColumn(window.tablaHorarioPersonal);
document.dispatchEvent(new Event('hp:estacion-cambio'));
}
});
}

toggleEstacionColumn(window.tablaHorarioPersonal);

});
