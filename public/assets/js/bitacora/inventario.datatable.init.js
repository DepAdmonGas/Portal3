document.addEventListener('DOMContentLoaded', () => {

let permisos = {};
const messageEl = document.getElementById('aditivo-inventario-empty-message');
const contentEl = document.getElementById('aditivo-inventario-content');
var table = null;

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

function initTable() {
return $('#table-aditivo-inventario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/bitacora-aditivo/datatable-inventario',
type: 'GET',
dataSrc: function (json) {
permisos = json.permisos;
return json.data;
}
},
columns: [
{
data: null,
width: '60px',
className: 'text-center',
render: function (data, type, row, meta) {
return meta.row + 1;
}
},
{
data: 'fecha',
render: function (data, type) {
if (!data) return '';
const fecha = new Date(data);
const formateada = fecha.toLocaleDateString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric'
});
if (type === 'display') return formateada;
if (type === 'filter') return formateada + ' ' + data;
return data;
}
},
{ data: 'aditivo' },
{ data: 'galones' },
{ data: 'detalle' }
]
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

$('#table-aditivo-inventario').on('draw.dt', function () {
Alpine.initTree(document.querySelector('#table-aditivo-inventario'));
});

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showTable();
getOrCreateTable();
}

ModuleStationSelector.init('bitacora-aditivo', {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null) {
ms.hideBadge();
showEmptyMessage();
return;
}
showTable();
if (table) {
table.ajax.reload(null, false);
} else {
getOrCreateTable();
}
if (window.aditivoInstance && window.aditivoInstance.updateInventario) {
window.aditivoInstance.updateInventario();
}
}
});

});