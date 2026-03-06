document.addEventListener('DOMContentLoaded', () => {

$('#table-puestos').DataTable({
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
{ data: 'id', width: '60px', className: 'text-center' },
{ data: 'tipo_puesto' },
{
data: 'estatus',
width: '80px',
className: 'text-center',
render: function (data) {
return data == 0
? '<span class="mb-1 badge text-bg-success">Activo</span>'
: '<span class="mb-1 badge text-bg-danger">Cancelado</span>';
}
},
{
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {
const disabled = row.estatus === 1
? 'disabled opacity-50 pointer-events-none'
: '';

return `<a href="configuracion-modulos-puesto/${row.id}" class="text-muted ${disabled}">
<i class="ti ti-edit fs-6"></i>
</a>`;
}
}
]
});

});
