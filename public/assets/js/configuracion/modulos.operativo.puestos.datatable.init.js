document.addEventListener('DOMContentLoaded', () => {

const table = $('#table-modulos-operativo-puestos').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true, 
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/puestos/datatable',
type: 'GET', 
dataSrc: function (json) {
return json.data; 
} 
},
columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center' },
{ title: 'Nombre del puesto', data: 'tipo_puesto' },
{
title: 'Estatus',
data: 'estatus',
width: '80px',
className: 'text-center',
render: function (data) {
return data == 0
? '<span class="mb-1 badge text-bg-success">Activo</span>'
: '<span class="mb-1 badge text-bg-danger">Eliminado</span>';
}
},
{
title: '<i class="ti ti-box fs-6"></i>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const disabled = row.estatus === 1 
? 'opacity-50' 
: '';

const click = row.estatus === 1 
? 'onclick="event.preventDefault();"' 
: '';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="text-muted">

<a class="pointer d-flex align-items-center gap-3 ${disabled ? 'disabled' : ''}"
${disabled ? '' : ` 
@click='async () => {
const res = await goTo("/configuracion/modulos-operativo-puestos/${row.id}");
}'
`}>
<i class="ti ti-box fs-6"></i>
</a>

</div>
</div>`;

}
}
]
});

$("#table-modulos-operativo-puestos tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

});
