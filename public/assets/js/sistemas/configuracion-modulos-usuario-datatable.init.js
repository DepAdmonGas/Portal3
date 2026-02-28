document.addEventListener('DOMContentLoaded', () => {

const params = new URLSearchParams(window.location.search);
const idestacion = params.get('idEstacion');

$('#table-usuarios-configuracion').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true, 
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/usuarios/datatable',
type: 'GET',
data: function (d) {
d.idestacion = idestacion;
},             
dataSrc: function (json) {
return json.data;  
}
},
columns: [
{ data: 'id', width: '60px', className: 'text-center' },
{ data: 'nombre' },
{ data: 'puesto' },
{ data: 'razonsocial',
render: function (data) {
return data && data.trim() !== '' ? data : 'Todas';
}
},
{
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
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {
const disabled = row.estatus === 1
? 'disabled opacity-50 pointer-events-none'
: '';
return `<a href="configuracion-modulos-usuario/${row.id}" class="text-muted ${disabled}">
<i class="ti ti-edit fs-6"></i>
</a>`;
}
}
]
});

});
