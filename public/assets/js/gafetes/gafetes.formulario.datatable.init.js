document.addEventListener('DOMContentLoaded', () => {

let permisos = {};

const container = document.getElementById('container');
const idEstacion = container.dataset.estacion;
const noReporte = container.dataset.reporte;

const table = $('#table-gafetes-formulario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: `/solicitud-gafetes/datatable-formulario/${idEstacion}/${noReporte}`,
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data; 
}
},

columns: [
{data: 'id', width: '60px', className: 'text-center align-middle' },
{data: 'clave', width: '100px', className: 'text-center align-middle'},
{data: 'nombre', className: 'text-center align-middle'},
{data: 'id_estacion', className: 'text-center align-middle'},
{
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const estatus = Number(row.estatus);
const disabled = estatus === 0 ? 'disabled opacity-50 pointer-events-none' : '';

const noDesc = !permisos.descargar || disabled;
const noDelete = !permisos.eliminar || disabled;

return `
<div class="dropdown dropstart">

<a href="javascript:void(0)"
class="text-muted"
data-bs-toggle="dropdown">

<i class="ti ti-dots-vertical fs-6"></i>

</a>

<ul class="dropdown-menu">

<li>
<a href="javascript:void(0)" class="dropdown-item ${noDesc ?! 'disabled' : ''}"
${noDesc ?! '' : ` @click="download('basico','${row.foto}')"`}>
<i class="ti ti-file-download"></i> Descargar 
</a>
</li>

<li>
<a class="dropdown-item d-flex align-items-center gap-3 btn-delete ${disabled}" 
data-id="${row.id}">
<i class="fs-4 ti ti-trash"></i>
Eliminar
</a>
</li>

</ul>

</div>`;

}

}

]

});

});