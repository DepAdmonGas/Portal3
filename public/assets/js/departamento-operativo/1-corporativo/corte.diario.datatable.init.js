document.addEventListener('DOMContentLoaded', () => {

const container = document.getElementById('container');
const idYear = container.dataset.year;
const idMes = container.dataset.mes;
const multiestacion = container.dataset.multiestacion === 'true';
const moduleStationKey = container.dataset.moduleStationKey || '';
const puedeEditarCorte = container.dataset.puedeEditarCorte === 'true';

const messageEl = document.getElementById('corte-diario-empty-message');
const contentEl = document.getElementById('corte-diario-content');
var table = null;

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

const columns = [
{ title: 'Fecha', data: 'fecha', className: 'text-center align-middle' },
{ title: 'Ventas', data: 'ventas', width: '60px', className: 'text-center align-middle' },
{ title: 'TPV', data: 'tpv', width: '60px', className: 'text-center align-middle' },
{ title: 'Impuestos', data: 'impuestos', width: '60px', className: 'text-center align-middle' },
{ title: 'Monedero', data: 'monedero', width: '60px', className: 'text-center align-middle' },
{ title: 'Clientes', data: 'clientes', width: '60px', className: 'text-center align-middle' },
{ title: 'Editar', data: 'editar', width: '60px', className: 'text-center align-middle' }
];

function initTable() {
return $('#table-corte-diario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
paging: false,
destroy: true,
order: [[0, 'asc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/departamento-operativo/corporativo/corte-diario-datatable/' + idYear + '/' + idMes,
type: 'GET',
dataSrc: function (json) {
window._resumen = json.resumen || {};
return json.data;
}
},
columns: columns,
columnDefs: [
{ targets: [1,2,3,4,5,6], orderable: false },
{ targets: 0, orderable: true, searchable: true },
{ visible: puedeEditarCorte, targets: 6 }
],
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#table-corte-diario'));
}
}
});
}

function destroyTable() {
if (table) {
table.destroy();
table = null;
}
}

function getOrCreateTable() {
if (!table) {
table = initTable();
}
return table;
}

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showTable();
getOrCreateTable();
}

if (moduleStationKey) {
var actionsEl = document.getElementById('corte-diario-actions-wrapper');

function toggleActions(v) {
if (!actionsEl) return;
if (v.id_estacion && v.id_estacion !== null && v.id_estacion !== undefined) {
if (multiestacion && parseInt(v.id_estacion) === 8) {
actionsEl.style.display = 'none';
} else {
actionsEl.style.display = '';
}
} else {
actionsEl.style.display = 'none';
}
}

ModuleStationSelector.init(moduleStationKey, {
customReload: function (ms) {
var v = ms.getValue();
toggleActions(v);
if (v.id_estacion === null && v.id_depto === null) {
ms.hideBadge();
showEmptyMessage();
return;
}
showTable();
if (table) {
table.ajax.reload(null, false);
} else {
table = initTable();
}
}
});
}

});
