document.addEventListener('DOMContentLoaded', () => {

let permisos = {};

//---------- TABLA DE ACTIVIDADES TECNICAS ----------//
const table = $('#table-actividades-tecnicas').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/procedimientos/actividades-tecnicas/datatable',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data;
}
},

columns: [
{ title: '#', data: 'id_actividades_tecnicas', width: '60px', className: 'text-center align-middle' },
{ title: 'Nombre de la actividad', data: 'nombre_a', className: 'text-center align-middle' },
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
orderable: false,
searchable: false,
className: 'text-center',
render: function (data, type, row) {

const noDesc = !permisos.descargar;
const noEdit = !permisos.editar;

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
${noDesc ? '' : `@click="download('procedimientos-actividades-tecnicas','${row.archivo}')"`}
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

//---------- TABLA DE VISITAS A LA ESTACION ----------//
const table2 = $('#table-visitas-estacion').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/procedimientos/visita-estacion/datatable',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data;
}
},

columns: [
{ title: '#', data: 'id_visita_estacion', width: '60px', className: 'text-center align-middle' },
{ title: 'Nombre de la visita', data: 'nombre_a', className: 'text-center align-middle' },
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
orderable: false,
searchable: false,
className: 'text-center',
render: function (data, type, row) {

const noDesc = !permisos.descargar;
const noEdit = !permisos.editar;

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
${noDesc ? '' : `@click="download('procedimientos-visita-estacion','${row.archivo}')"`}
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