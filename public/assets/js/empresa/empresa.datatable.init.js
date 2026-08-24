document.addEventListener('DOMContentLoaded', () => {

let permisos = {};
//---------- TABLA DE ACTIVIDADES TECNICAS ----------//
const table = $('#table-empresa').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/empresa/datatable',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data;
}
},

columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },
{ title: 'Descripción', data: 'descripcion', className: 'text-center align-middle' },
{ title: 'Tipo', data: 'tipo', className: 'text-center align-middle' },
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
orderable: false,
searchable: false,
className: 'text-center',
render: function (data, type, row) {

const noDesc = !permisos.descargar;

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">

<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer ${noDesc ? 'disabled' : ''}"
${noDesc ? '' : `@click="download('empresa','${row.archivo}')"`}
>
<i class="ti ti-file-download"></i> Descargar 
</a>
</li>

</ul>
</div>

</div>
`;
}
}
]

});


});