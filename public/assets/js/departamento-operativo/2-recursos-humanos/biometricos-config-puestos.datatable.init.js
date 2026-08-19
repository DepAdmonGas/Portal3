document.addEventListener('DOMContentLoaded', () => {

const table = $('#table-puestos').DataTable({

processing: true,
serverSide: false,
autoWidth: false,
stateSave: false,
order: [[0, 'asc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/departamento-operativo/recursos-humanos/biometricos/configuracion/puestos/datatable',
type: 'GET',
dataSrc: function (json) {
return json.data || [];
}
},

columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },
{ title: 'Puesto', data: 'puesto', className: 'align-middle text-start' },
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const permisos = row.permisos;
let disabledEdit = permisos.disabledEdit ? 'disabled opacity-50 pointer-events-none' : '';
let disabledDelete = permisos.disabledDelete ? 'disabled opacity-50 pointer-events-none' : '';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown" data-bs-display="static"><i class="ti ti-dots-vertical fs-6"></i></a>

<ul class="dropdown-menu">

<!-- EDITAR -->
<li>
<a
href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledEdit}"
${!permisos.disabledEdit
? `@click='$dispatch("open-edit", ${JSON.stringify(row)})'` : ''}>
<i class="fs-4 ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledDelete}"
${permisos.disabledDelete ? '' : `
@click="async () => {
await deleteAction({
url: '/departamento-operativo/recursos-humanos/biometricos/configuracion/puestos/delete',
id: ${row.id},
name: '${row.puesto.replace(/'/g, "\\'")}',
table: '#table-puestos'
});
}"`}
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

$("#table-puestos tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

});
