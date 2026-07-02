document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

const $table = $('#tabla-solicitud-cheque');
if (!$table.length) return;

const esMultiestacion = c.dataset.multiestacion === 'true';
const esGestoria = c.dataset.esGestoria === 'true';

var storedEst, storedDep;

if (esGestoria) {
storedEst = '8';
storedDep = '5';
sessionStorage.setItem('sc_estacion', '8');
sessionStorage.setItem('sc_depto', '5');
} else if (esMultiestacion) {
storedEst = sessionStorage.getItem('sc_estacion') || '';
storedDep = sessionStorage.getItem('sc_depto') || '';
if (!storedEst && !storedDep) {
storedEst = c.dataset.idEstacion || '';
storedDep = c.dataset.idDepto || '';
}
} else {
storedEst = c.dataset.idEstacion || '';
storedDep = c.dataset.idDepto || '';
}

const hasFilter = (storedEst > 0 || storedDep > 0);
const showEstacionColumna = !hasFilter;

const showRazonSocial = esGestoria || (esMultiestacion && parseInt(storedDep) === 5);
const showTelefono = parseInt(storedEst) > 0 && parseInt(storedEst) !== 8;

const ajaxBase = '/departamento-operativo/solicitud-cheque/data/' + idYear + '/' + idMes;

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

function cls(enabled) { return enabled ? '' : ' disabled'; }

function iconoFirma(d) {
const nf = d.num_firmas || 0;
const st = d.status;
var src;
if (nf >= 3 || st === 2) src = '';
else if (nf >= 2) src = '<i class="ti ti-writing text-primary fs-8"></i>';
else src = '<i class="ti ti-writing text-dark fs-8"></i>';
if (st === 2 || st === 3) {
return '<i class="ti ti-signature text-success fs-10" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default;"></i>';
}
return '<a href="/departamento-operativo/solicitud-cheque-firmar/' + d.id + '" class="firma-link">' + src + '</a>';
}

function statusBadge(status) {
var cls = 'bg-danger text-white';
if (status === 1) cls = 'bg-warning text-white';
else if (status === 2) cls = 'bg-success';
else if (status === 3) cls = 'bg-info text-dark';
var labels = ['Pendiente','En proceso','Autorizado','Pausada'];
return '<span class="badge rounded-pill ' + cls + '">' + (window.__statusLabels ? window.__statusLabels[status] : (labels[status] || '')) + '</span>';
}

const columns = [
{ title: '#', data: 'id', className: 'align-middle text-center fw-normal' },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center' },
{ title: 'Raz\u00f3n Social', data: 'razonsocial', className: 'align-middle', visible: showRazonSocial },
{ title: 'Estaci\u00f3n / Departamento', data: 'estacion_nombre', className: 'align-middle text-center fw-semibold', visible: showEstacionColumna,
render: (v) => v ? '<div style="white-space:normal;word-break:break-word;">' + v + '</div>' : ''
},
{ title: 'Beneficiario', data: 'beneficiario', className: 'align-middle',
render: (v) => v ? '<div style="white-space:normal;word-break:break-word;">' + v + '</div>' : ''
},
{ title: 'Monto', data: 'monto', className: 'align-middle text-end', render: (v) => v != null ? '$' + window.formatNum(v) : '' },
{ title: 'No. Factura', data: 'no_factura', className: 'align-middle text-center' },
{ title: 'Concepto', data: 'concepto', className: 'align-middle',
render: (v) => v ? '<div style="white-space:normal;word-break:break-word;">' + v + '</div>' : ''
},
{ title: 'Tel\u00e9fono', data: 'telefono', className: 'align-middle text-center', visible: showTelefono },
{ title: 'M\u00e9todo Pago', data: 'metodo_pago', className: 'align-middle text-center' },
{
title: 'Firmar',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: (d) => iconoFirma(d)
},
{
title: '<i class="ti ti-message fs-7"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: (d) => {
const badge = d.num_comentarios > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + d.num_comentarios + '</span>'
: '';
return '<a href="" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + d.id + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
}
},
{
title: 'Estatus',
data: 'status_label',
className: 'align-middle text-center',
searchable: true,
render: (v, t, d) => {
if (t === 'display') {
var st = d.status;
var cls = 'bg-danger text-white';
if (st === 1) cls = 'bg-warning text-white';
else if (st === 2) cls = 'bg-success';
else if (st === 3) cls = 'bg-info text-dark';
return '<span class="badge rounded-pill ' + cls + '">' + d.status_label + '</span>';
}
return d.status_label;
}
},
{
title: '<i class="fas fa-ellipsis-v"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: (d) => {
const puedePdf = d.puede_pdf;
const puedeArchivos = d.puede_archivos;
const puedeEditar = d.puede_editar_row;
const puedeEliminar = d.puede_eliminar_row;
const puedePagos = d.puede_pagos;
let html = '<div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';
html += '<a class="dropdown-item pointer btn-detalle" data-id="' + d.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
html += '<a class="dropdown-item pointer btn-pdf' + cls(puedePdf) + '" data-id="' + d.id + '" data-pdf="' + (puedePdf ? 1 : 0) + '"><i class="ti ti-file-text me-1"></i> PDF</a>';
html += '<a class="dropdown-item pointer btn-documentos' + cls(puedeArchivos) + '" data-id="' + d.id + '" data-documentos="' + (puedeArchivos ? 1 : 0) + '"><i class="ti ti-file me-1"></i> Archivos</a>';
html += '<a class="dropdown-item pointer btn-pagos' + cls(puedePagos) + '" data-id="' + d.id + '" data-pagos="' + (puedePagos ? 1 : 0) + '"><i class="ti ti-moneybag me-1"></i> Pagos</a>';
html += '<a class="dropdown-item pointer btn-editar' + cls(puedeEditar) + '" data-id="' + d.id + '" data-editar="' + (puedeEditar ? 1 : 0) + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
html += '<a class="dropdown-item pointer btn-eliminar' + cls(puedeEliminar) + '" data-id="' + d.id + '" data-eliminar="' + (puedeEliminar ? 1 : 0) + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
html += '</div></div>';
return html;
}
}
];

$table.DataTable({
processing: true,
serverSide: false,
ajax: {
type: 'POST',
url: ajaxBase,
data: function(d) {
if (c.dataset.esGestoria === 'true') {
d.estacion = '8';
d.depto = '5';
return;
}
var est, dep;
if (esMultiestacion) {
est = sessionStorage.getItem('sc_estacion') || '';
dep = sessionStorage.getItem('sc_depto') || '';
if (!est && !dep) {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
} else {
est = c.dataset.idEstacion || '';
dep = c.dataset.idDepto || '';
}
if (est) d.estacion = est;
if (dep) d.depto = dep;
},
dataSrc: (json) => {
if (!json.success) return [];
window.__scPermisos = json.permisos || {};
return json.data || [];
}
},
autoWidth: false,
stateSave: false,
order: [[0, 'desc']],
pageLength: 25,
lengthMenu: [25, 50, 100],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
columns: columns,
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-solicitud-cheque'));
}
}
});



$table.on('xhr.dt', function () {
const event = new CustomEvent('tabla-recargada');
document.dispatchEvent(event);
var curEst, curDep;
if (c.dataset.esGestoria === 'true') {
curEst = '8'; curDep = '5';
} else if (esMultiestacion) {
curEst = sessionStorage.getItem('sc_estacion') || '';
curDep = sessionStorage.getItem('sc_depto') || '';
if (!curEst && !curDep) {
curEst = c.dataset.idEstacion || '';
curDep = c.dataset.idDepto || '';
}
} else {
curEst = c.dataset.idEstacion || '';
curDep = c.dataset.idDepto || '';
}
var hasFilterNow = (curEst > 0 || curDep > 0);
var showRazonNow = c.dataset.esGestoria === 'true' || (esMultiestacion && curDep == '5');
$table.DataTable().columns().every(function() {
var header = this.header().textContent.trim();
if (header === 'Estación / Departamento') {
this.visible(!hasFilterNow);
} else if (header === 'Razón Social') {
this.visible(showRazonNow);
}
});
});

$table.on('click', '.btn-comentarios', function (e) {
e.preventDefault();
const id = parseInt(this.dataset.id);
const event = new CustomEvent('abrir-comentarios', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-detalle', function () {
const id = parseInt(this.dataset.id);
const event = new CustomEvent('ver-detalle', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-pdf', function (e) {
const puede = parseInt(this.dataset.pdf);
if (!puede) { e.preventDefault(); return; }
const id = parseInt(this.dataset.id);
const event = new CustomEvent('ver-pdf', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-editar', function (e) {
const puede = parseInt(this.dataset.editar);
if (!puede) { e.preventDefault(); return; }
const id = parseInt(this.dataset.id);
window.location.href = '/departamento-operativo/solicitud-cheque-editar/' + id;
});

$table.on('click', '.btn-eliminar', function (e) {
const puede = parseInt(this.dataset.eliminar);
if (!puede) { e.preventDefault(); return; }
const id = parseInt(this.dataset.id);
const event = new CustomEvent('eliminar-solicitud', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-documentos', function (e) {
const puede = parseInt(this.dataset.documentos);
if (!puede) { e.preventDefault(); return; }
const id = parseInt(this.dataset.id);
const event = new CustomEvent('ver-documentos', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-pagos', function (e) {
const puede = parseInt(this.dataset.pagos);
if (!puede) { e.preventDefault(); return; }
const id = parseInt(this.dataset.id);
const event = new CustomEvent('ver-pagos', { detail: { id } });
document.dispatchEvent(event);
});

});