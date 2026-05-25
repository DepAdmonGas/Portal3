document.addEventListener('DOMContentLoaded', () => {

const container = document.getElementById('container');
const idYear = container.dataset.year;
const idMes = container.dataset.mes;
const multiestacion = container.dataset.multiestacion === 'true';

const selectEl = document.getElementById('selectEstacion');
const estacionActual = selectEl ? parseInt(selectEl.value) : 0;
const esPredeterminada = multiestacion && estacionActual === 8;

const tableBody = document.querySelector('#table-corte-diario tbody');

if (esPredeterminada) {
tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Debes de seleccionar una estación del menú superior para poder visualizar la información del Corte Diario.</td></tr>';
return;
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

const table = $('#table-corte-diario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
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
{ visible: multiestacion, targets: 6 }
],
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#table-corte-diario'));
}
}
});

});
