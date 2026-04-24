document.addEventListener('DOMContentLoaded', () => {

const tablaElement = document.getElementById('table-catalogo');

const permisoEditar = tablaElement?.dataset.permisoEditar == 1;
const permisoEliminar = tablaElement?.dataset.permisoEliminar == 1;

$('#table-catalogo').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true, 
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/configuracion-sistemas/catalogo-modulos/datatable',
type: 'GET', 
dataSrc: function (json) {
return json.data; 
}
},
columns: [
{ data: 'id', width: '60px', className: 'text-center' },
{ data: 'nombre_modulo' },
{ data: 'url', className: 'text-center ' },
{
data: 'status',
width: '80px',
className: 'text-center',
render: function (data) {
return data == 0
? '<span class="mb-1 badge text-bg-success">Activo</span>'
: '<span class="mb-1 badge text-bg-danger">Eliminado</span>';
}
},
{
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {
const disabled = row.status === 1
? 'disabled opacity-50 pointer-events-none'
: '';

return `
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>
<ul class="dropdown-menu">
<li>
<a class="dropdown-item d-flex align-items-center gap-3 btn-edit ${disabled}"
data-id="${row.id}"
data-nombre="${row.nombre_modulo}"
data-url="${row.url}"
data-permiso-editar="${permisoEditar}">
<i class="fs-4 ti ti-edit"></i>Editar  
</a>
</li>
<li>
<a class="dropdown-item d-flex align-items-center gap-3 btn-delete ${disabled}" 
data-id="${row.id}"
data-permiso-eliminar="${permisoEliminar}">
<i class="fs-4 ti ti-trash"></i>Cancelar
</a>
</li>
</ul>
</div>`;
}
}
] 
});

});