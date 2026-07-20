document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

const $table = $('#tabla-factura-monedero');
if (!$table.length) return;

const esMultiestacion = c.dataset.multiestacion === 'true';
const puedeEliminar = c.dataset.puedeEliminar === 'true';

const ajaxUrl = '/departamento-operativo/corporativo/factura-monedero/get-data?year=' + idYear + '&mes=' + idMes;

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

function cls(enabled) { return enabled ? '' : ' disabled'; }

function renderEstado(row) {
if (row.estado === 1) return '<span class="badge bg-success">Finalizado</span>';
return '<span class="badge bg-warning text-dark">Pendiente</span>';
}

function renderDocumentoCol(file, tipo) {
if (!file) return '<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>';
return '<i class="ti ti-download pointer text-primary fs-5 btn-fm-download" data-tipo="factura-monedero" data-file="' + encodeURIComponent(file) + '" title="' + tipo + '"></i>';
}

function renderComentarios(row) {
var badge = row.total_comentarios > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.total_comentarios + '</span>'
: '';
return '<a href="" class="btn-fm-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + row.id + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}

function renderAcciones(row) {
var puedeDel = puedeEliminar;
var puedeEdi = true;
var puedeVer = true;

var html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer btn-fm-detalle" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
html += '<a class="dropdown-item pointer btn-fm-editar' + cls(puedeEdi) + '" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
html += '<a class="dropdown-item pointer btn-fm-eliminar' + cls(puedeDel) + '" data-id="' + row.id + '" data-nombre="Folio ' + row.folio_display + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
html += '</div></div>';
return html;
}

// Sync sessionStorage from server-rendered ModuleStationSelector on first load
if (esMultiestacion) {
var estacionId = 0;
var sel = document.getElementById('module-station-selector-factura-monedero');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p[0] === 'estacion' && p[1]) estacionId = parseInt(p[1]);
}
if (!estacionId && typeof ModuleStationSelector !== 'undefined') {
var ms = ModuleStationSelector._instances && ModuleStationSelector._instances['factura-monedero'];
if (ms) {
var v = ms.getValue();
if (v.id_estacion) estacionId = v.id_estacion;
}
}
if (estacionId) {
sessionStorage.setItem('fm_estacion', String(estacionId));
} else {
sessionStorage.removeItem('fm_estacion');
}
}

var estacionSeleccionada = esMultiestacion ? parseInt(sessionStorage.getItem('fm_estacion') || '0') : parseInt(c.dataset.idEstacion || '0');
var showEstacion = esMultiestacion && !estacionSeleccionada;

const columns = [
{ title: 'Folio', data: 'folio_display', className: 'align-middle text-center text-nowrap' },
{ title: 'Estación', data: 'estacion_nombre', className: 'align-middle text-center text-nowrap', visible: showEstacion,
render: (v) => v || '' },
{ title: 'Fecha de creación', data: 'fecha_creacion_format', className: 'align-middle text-center text-nowrap' },
{ title: 'No. Factura', data: 'no_factura', className: 'align-middle text-center text-nowrap' },
{ title: 'Monto', data: 'monto', className: 'align-middle text-end text-nowrap',
render: (v) => '$ ' + parseFloat(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
{ title: 'Factura (PDF)', data: 'archivo_factura', className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v) => renderDocumentoCol(v, 'Factura PDF') },
{ title: 'Factura (XML)', data: 'archivo_factura_xml', className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v) => renderDocumentoCol(v, 'Factura XML') },
{ title: 'Comprobante pago', data: 'archivo_comprobante_pago', className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v) => renderDocumentoCol(v, 'Comprobante de pago') },
{ title: 'Estado', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderEstado(row) },
{ title: '<i class="ti ti-message fs-7"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderComentarios(row) },
{ title: '<i class="ti ti-dots-vertical fs-5"></i>', data: null, className: 'align-middle text-center text-nowrap', orderable: false, searchable: false,
render: (v, t, row) => renderAcciones(row) }
];

window.tablaFacturaMonedero = $table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'GET',
url: ajaxUrl,
data: function (d) {
var est = '';
if (esMultiestacion) {
est = sessionStorage.getItem('fm_estacion') || '';
if (!est) {
var sel = document.getElementById('module-station-selector-factura-monedero');
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
drawCallback: function (settings) {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-factura-monedero'));
}
const event = new CustomEvent('fm-tabla-recargada');
document.dispatchEvent(event);
}
});

$table.on('xhr.dt', function () {
var c = document.getElementById('container');
if (!c) return;
var idYear = parseInt(c.dataset.idYear);
var idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;
fetch('/departamento-operativo/corporativo/factura-monedero/get-pendientes?year=' + idYear + '&mes=' + idMes)
.then(r => r.json())
.then(json => {
if (!json.success || !json.data) return;
var span = document.getElementById('fm-pendientes-data');
if (span) span.textContent = JSON.stringify(json.data);
if (typeof actualizarBadgePendientes === 'function') actualizarBadgePendientes();
})
.catch(function () {});
});

$table.on('click', '.btn-fm-detalle', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('fm:ver-detalle', { detail: { id: id } }));
});

$table.on('click', '.btn-fm-comentarios', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('fm:ver-comentarios', { detail: { id: id } }));
});

$table.on('click', '.btn-fm-editar', function (e) {
var puede = $(this).hasClass('disabled');
if (puede) { e.preventDefault(); return; }
var id = parseInt(this.dataset.id);
document.dispatchEvent(new CustomEvent('fm:editar', { detail: { id: id } }));
});

$table.on('click', '.btn-fm-eliminar', function (e) {
var puede = $(this).hasClass('disabled');
if (puede) { e.preventDefault(); return; }
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre;
document.dispatchEvent(new CustomEvent('fm:eliminar', { detail: { id: id, nombre: nombre } }));
});

$table.on('click', '.btn-fm-download', function (e) {
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
sessionStorage.setItem('fm_estacion', v.id_estacion);
} else {
sessionStorage.removeItem('fm_estacion');
}
window.location.reload();
}
});
}

});
