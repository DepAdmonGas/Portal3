document.addEventListener('DOMContentLoaded', () => {
const id = document.getElementById('container').dataset.id;

const iconoPermiso = (valor) => valor ? `<i class="ti ti-check fs-6 text-success"></i>` : `<i class="ti ti-x text-danger fs-6"></i>`;
const table = $('#table-modulos-operativo-puestos-configuracion').DataTable({

processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/configuracion/datatable-modulos-operativo-puestos/idPuesto/' + id,
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
return json.data;
}
},

columns: [
{ title: '#', data: 'idModuloPuesto', width: '60px', className: 'text-center align-middle' },
{ title: 'Nombre del módulo', data: 'nombre_modulo', className: 'text-center align-middle' },
{ title: 'Submódulos', data: 'no_submodulos', width: '80px', className: 'text-center align-middle',
render: function (data) {
return `
<span class="badge bg-primary">${data.submodulos_activos} / ${data.total_submodulos}</span>
`;
}
},
{ title: 'Leer', data: 'tbLeer', width: '60px', className: 'text-center align-middle', render: (data) => iconoPermiso(data)},
{ title: 'Crear', data: 'tbCrear', width: '60px', className: 'text-center align-middle', render: (data) => iconoPermiso(data)},
{ title: 'Editar', data: 'tbEditar', width: '60px', className: 'text-center align-middle', render: (data) => iconoPermiso(data)},
{ title: 'Eliminar', data: 'tbEliminar', width: '60px', className: 'text-center align-middle', render: (data) => iconoPermiso(data)},
{ title: 'Descargar', data: 'tbDescargar', width: '60px', className: 'text-center align-middle', render: (data) => iconoPermiso(data)},
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {
const { noCreate, noEdit, noDelete } = row.permisos;

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown" data-bs-display="static">
<i class="ti ti-dots-vertical fs-6"></i>
</a> 

<ul class="dropdown-menu">

<!-- EDITAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${!noEdit ? 'disabled opacity-50 pointer-events-none' : ''}"
${!noEdit 
? ''
: `@click="window.modulosPuestoOperativoInstance .openEditar(${row.idModuloPuesto})"'` 
}>
<i class="fs-4 ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${!noDelete ? 'disabled opacity-50 pointer-events-none' : ''}"
${!noDelete 
? '' 
: `@click="async () => {
await deleteAction({
url: '/configuracion/modulos-operativo-puestos/delete',
id: ${row.idModuloPuesto},
name: '${row.nombre_modulo}',
table: '#table-modulos-operativo-puestos-configuracion'
});
}"`
}>
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

$("#table-modulos-operativo-puestos-configuracion tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

});