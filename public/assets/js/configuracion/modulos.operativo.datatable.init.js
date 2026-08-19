
document.addEventListener('DOMContentLoaded', () => {
const table = $('#table-modulos-operativo').DataTable({

processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/configuracion/datatable-modulos-operativo',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
return json.data;
}
},

columns: [
{ title: '#', data: 'idModulo', width: '60px', className: 'text-center align-middle' },
{ title: 'Nombre del Módulo', data: 'nombre_modulo', className: 'text-center align-middle'},
{ title: 'Clave', data: 'clave', className: 'text-center align-middle'},
{ title: 'Ruta', data: 'ruta', className: 'text-center align-middle'},
{ title: 'Icono', data: 'icono', className: 'text-center align-middle',
render: function (data) {
return `<i class="${data} fs-8"></i>`;
}
},
{ title: 'Estatus', data: 'estatus', width: '140px', className: 'text-center align-middle',
render: function (data) {
const estatus = Number(data);

if (!data || !data.titulo) return '';
return `
<span class="badge rounded-pill ${data.color_badge}">
${data.titulo}
</span>
`;
}

},
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
width: '1%',
orderable: false, 
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const permisos = row.permisos;
let disabledCreate = permisos.disabledCreate ? 'disabled opacity-50 pointer-events-none' : '';
let disabledEdit = permisos.disabledEdit ? 'disabled opacity-50 pointer-events-none' : '';
let disabledDelete = permisos.disabledDelete ? 'disabled opacity-50 pointer-events-none' : '';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown" data-bs-display="static"><i class="ti ti-dots-vertical fs-6"></i></a> 

<ul class="dropdown-menu">

<!-- SUBMODULOS -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledCreate}"
${!permisos.disabledCreate  
? ` @click='async () => { 
const res = await goTo("/configuracion/modulos-operativo/${row.idModulo}"); }' ` : '' }>
<i class="ti ti-box fs-6"></i> Submódulos
</a>
</li>

<!-- EDITAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledEdit}"
${!permisos.disabledEdit 
? `@click='$dispatch("open-edit", ${JSON.stringify(row)})'` : ''}>
<i class="fs-4 ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledDelete}"
${permisos.disabledDelete ? '' : `
@click="async () => {
await deleteAction({
url: '/configuracion/delete-modulos-operativo',
id: ${row.idModulo},
name: '${row.nombre_modulo}',
table: '#table-modulos-operativo'
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

$("#table-modulos-operativo tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

});