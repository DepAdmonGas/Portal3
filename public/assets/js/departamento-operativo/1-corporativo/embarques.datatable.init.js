document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

const $table = $('#tabla-embarques');
if (!$table.length) return;

function docLink(v, label, icon) {
if (!v) return '<i class="ti ti-file-off text-muted fs-6"></i>';
return '<span x-data="actions()"><i class="' + icon + ' fs-6 pointer" @click.prevent="download(\'embarques\', \'' + v + '\')" title="' + label + '"></i></span>';
}

function prohibido() {
return '<i class="ti ti-ban text-dark fs-6"></i>';
}

function estadoBadge(semaforo) {
if (semaforo === 0) return '<span class="badge bg-danger">Pendiente</span>';
if (semaforo === 1) return '<span class="badge bg-warning text-white">En proceso</span>';
if (semaforo === 2) return '<span class="badge bg-success">Finalizado</span>';
return '<span class="badge bg-secondary">N/A</span>';
}

function isPemexDelivery(d) {
return d.embarque === 'Pemex' || d.embarque === 'Delivery';
}

function isSipci(d) {
return (d.nom_transporte || '').toUpperCase() === 'SIPCI';
}

function isPetroAsfaltosSantaFe(d) {
var t = (d.nom_transporte || '').toUpperCase();
return t.indexOf('PETRO ASFALTOS') !== -1 || t.indexOf('SANTA FE') !== -1;
}

if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
$table.DataTable().destroy();
}

$table.DataTable({
processing: true,
serverSide: false,
ajax: {
url: '/departamento-operativo/embarques/data/' + idYear + '/' + idMes,
dataSrc: (json) => {
if (!json.success) return [];
window.__embarquesPermisos = json.permisos || {};
return json.data || [];
}
},
autoWidth: false,
stateSave: true,
order: [[1, 'desc']],
pageLength: 25,
lengthMenu: [25, 50, 100],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
columns: [
{ title: '#', data: 'id', className: 'align-middle text-center fw-normal' },
{ title: 'Fecha', data: 'fecha', className: 'align-middle text-center' },
{ title: 'Embarque', data: 'embarque', className: 'align-middle text-center' },
{ title: 'Producto', data: 'producto', className: 'align-middle text-center' },
{
title: 'Documento',
data: 'documento',
className: 'align-middle text-center',
orderable: false,
render: (v) => docLink(v, 'Documento', 'ti ti-file-text text-primary')
},
{ title: 'No. Doc. CV', data: 'documentocv', className: 'align-middle text-center' },
{
title: 'Litros Factura',
data: 'importef',
className: 'align-middle text-end',
render: (v) => v != null ? window.formatNum(v) : ''
},
{
title: '$ / Litro',
data: 'precio_litro',
className: 'align-middle text-end',
render: (v) => v != null ? window.formatNum(v) : ''
},
{
title: 'Merma',
data: 'merma',
className: 'align-middle text-end',
render: (v) => {
if (v == null) return '';
const cls = parseFloat(v) < 0 ? 'text-danger fw-bold' : '';
return '<span class="' + cls + '">' + window.formatNum(v) + '</span>';
}
},
{ title: 'TAD', data: 'tad', className: 'align-middle text-center' },
{ title: 'Transporte', data: 'nom_transporte', className: 'align-middle text-center' },
{ title: 'Chofer', data: 'chofer', className: 'align-middle text-center' },
{ title: 'Unidad', data: 'unidad', className: 'align-middle text-center' },
{
title: 'PDF',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => isPemexDelivery(d) ? prohibido() : docLink(d.pdf, 'Factura PDF', 'ti ti-file-type-pdf text-danger')
},
{
title: 'XML',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => isPemexDelivery(d) ? prohibido() : docLink(d.xml, 'Factura XML', 'ti ti-file-type-xml text-primary')
},
{
title: 'CoPa',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => isPemexDelivery(d) ? prohibido() : docLink(d.comprobante_p, 'Comprobante de pago', 'ti ti-file-type-pdf text-danger')
},
{
title: 'NC <br> (PDF)',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => (isPemexDelivery(d) || isSipci(d)) ? prohibido() : docLink(d.nc_pdf, 'Nota de crédito PDF', 'ti ti-file-type-pdf text-danger')
},
{
title: 'NC <br> (XML)',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => (isPemexDelivery(d) || isSipci(d) || isPetroAsfaltosSantaFe(d)) ? prohibido() : docLink(d.nc_xml, 'Nota de crédito XML', 'ti ti-file-type-xml text-primary')
},
{
title: 'Com <br> (PDF)',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => (isPemexDelivery(d) || isSipci(d)) ? prohibido() : docLink(d.comPDF, 'Complemento PDF', 'ti ti-file-type-pdf text-danger')
},
{
title: 'Com <br> (XML)',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => (isPemexDelivery(d) || isSipci(d)) ? prohibido() : docLink(d.comXML, 'Complemento XML', 'ti ti-file-type-xml text-primary')
},
{
title: '<i class="ti ti-message fs-7"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => {
const badge = d.num_comentarios > 0
? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">'
+ d.num_comentarios +
'</span>'
: '';

return '<a href="" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + d.id + '" title="Comentarios">'
+ '<i class="ti ti-message fs-7"></i>'
+ badge
+ '</a>';
}
},
{
title: 'Estado',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => estadoBadge(d.semaforo)
},
{
title: '<i class="ti ti-dots-vertical fs-5"></i>',
data: null,
className: 'align-middle text-center',
orderable: false,
render: (d) => {
const p = window.__embarquesPermisos || {};
let html = '<div class="dropdown"><a class="btn btn-sm btn-icon-only text-dropdown-light" type="button" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu dropdown-menu-right dropdown-menu-arrow">';
if (p.puede_editar) {
html += '<a class="dropdown-item pointer btn-editar" data-id="' + d.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
}
if (p.puede_eliminar) {
html += '<a class="dropdown-item pointer btn-eliminar" data-id="' + d.id + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
}
if (!p.puede_editar && !p.puede_eliminar) {
html += '<span class="dropdown-item pointer text-muted"><i class="ti ti-lock me-1"></i> Sin acciones</span>';
}
html += '</div></div>';
return html;
}
}
],
columnDefs: [
{ orderable: false, targets: [4, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22] },
{ searchable: false, targets: [4, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22] }
],
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-embarques'));
}
}
});

$table.on('click', '.btn-comentarios', function (e) {
e.preventDefault();
const id = parseInt(this.dataset.id);
const event = new CustomEvent('abrir-comentarios', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-editar', function () {
const id = parseInt(this.dataset.id);
const event = new CustomEvent('editar-embarque', { detail: { id } });
document.dispatchEvent(event);
});

$table.on('click', '.btn-eliminar', function () {
const id = parseInt(this.dataset.id);
const event = new CustomEvent('eliminar-embarque', { detail: { id } });
document.dispatchEvent(event);
});
});
