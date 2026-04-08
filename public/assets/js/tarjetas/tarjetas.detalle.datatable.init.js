document.addEventListener('DOMContentLoaded', () => {

let permisos = {};

const container = document.getElementById('container');
const idEstacion = container.dataset.estacion;
const noSolicitud = container.dataset.solicitud;

const table = $('#table-tarjetas-detalle').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: `/solicitud-tarjetas/datatable-formulario/${idEstacion}/${noSolicitud}`,
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data; 
}
},

columns: [
{title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },
{title: 'Razon Social', data: 'razon_social', width: '100px', className: 'text-center align-middle'},
{title: 'Usuario', data: 'no_flotilla', width: '100px', className: 'text-center align-middle'},
{title: 'Vehiculo', data: 'vehiculo', width: '100px', className: 'text-center align-middle'},
{title: 'Placas', data: 'placas', width: '100px', className: 'text-center align-middle'},
{title: 'No. Unidad', data: 'no_unidad', width: '100px', className: 'text-center align-middle'},
{title: 'Tarjeta', data: 'tarjeta', width: '100px', className: 'text-center align-middle'},
{title: 'Tipo de Tarjeta', data: 'tipo_tarjeta', width: '100px', className: 'text-center align-middle'}
]

});

});   