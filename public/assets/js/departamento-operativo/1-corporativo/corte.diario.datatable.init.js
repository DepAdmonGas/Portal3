document.addEventListener('DOMContentLoaded', () => {

const idYear = document.getElementById('container').dataset.year;
const idMes = document.getElementById('container').dataset.mes;

const table = $('#table-corte-diario').DataTable({

processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/departamento-operativo/corporativo/corte-diario-datatable/' + idYear + '/' + idMes,
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
return json.data;
}
},

columns: [
{ title: 'Fecha', data: 'fecha', className: 'text-center align-middle' },
{ title: 'Ventas', data: 'ventas', width: '60px', className: 'text-center align-middle' },
{ title: 'TPV', data: 'tpv', width: '60px', className: 'text-center align-middle' },
{ title: 'Impuestos', data: 'impuestos', width: '60px', className: 'text-center align-middle' },
{ title: 'Monedero', data: 'monedero', width: '60px', className: 'text-center align-middle' },
{ title: 'Clientes', data: 'clientes', width: '60px', className: 'text-center align-middle' },
{ title: 'Editar', data: 'editar', width: '60px', className: 'text-center align-middle' },
]

});



});