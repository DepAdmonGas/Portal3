document.addEventListener('DOMContentLoaded', () => {

let permisos = {};

const container = document.getElementById('container');
const idEstacion = container.dataset.estacion;
const noSeguimiento = container.dataset.seguimiento;

const table = $('#table-tarjetas-formulario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: `/solicitud-tarjetas/datatable-formulario/${idEstacion}/${noSeguimiento}`,
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
{title: 'Tipo de Tarjeta', data: 'tipo_tarjeta', width: '100px', className: 'text-center align-middle'},
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const disabled = 'disabled opacity-50 pointer-events-none';
const noEdit = !permisos.editar;
const noDelete = !permisos.eliminar;

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- EDITAR -->
<li>
<a href="javascript:void(0)" class="dropdown-item ${noEdit ? 'disabled' : ''}" ${noEdit ? '' : `@click='$dispatch("open-edit", ${JSON.stringify(row)})'`} >
<i class="ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-1 ${noDelete ? disabled : ''}"
${noDelete ? '' : `
@click="async () => {
await deleteAction({
url: '/solicitud-tarjetas/delete-reporte-formulario',
id: ${row.id},
name: '${row.id}',
table: '#table-tarjetas-formulario'
});
}"
`}
>
<i class="fs-4 ti ti-trash"></i> Eliminar  
</a>
</li>

</ul>
</div>
</div>`;

}

}

]

});

$("#table-tarjetas-formulario tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});


});   