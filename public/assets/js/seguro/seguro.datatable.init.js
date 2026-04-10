document.addEventListener('DOMContentLoaded', () => {

//---------- TABLA POLIZA DE SEGURO ----------//
if ($.fn.DataTable.isDataTable('#table-poliza')) {
return;
}

const table1 = $('#table-poliza').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/seguro/poliza-seguro/datatable',
type: 'GET',
dataSrc: function (json) {
// Guardar permisos globalmente
permisos = json.permisos;
return json.data;
}
},

columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },
{
title: 'Fecha y hora',
data: 'fecha_hora',
render: function (data, type) {
if (!data) return '';

const fecha = new Date(data);

if (type === 'display') {
return fecha.toLocaleString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric',
hour: '2-digit',
minute: '2-digit',
hour12: true
});
}

if (type === 'filter') {
return fecha.toLocaleString('es-MX') + ' ' + data;
}

return data; // Para ordenamiento correcto
}
},

{ title: 'Estatus', data: 'estatus', width: '140px', className: 'text-center align-middle',
render: function (data) {
const estatus = Number(data);

let clase = '';
let texto = '';

switch (estatus) {

case 0:
clase = 'success';
texto = 'Vigente';
break;

case 1:
clase = 'warning';
texto = 'No Vigente';
break;

case 2:
clase = 'danger';
texto = 'Eliminado';
break;

default:
clase = 'secondary';
texto = 'Desconocido';

}

return `<span class="badge rounded-pill bg-${clase}">${texto}</span>`;
}

},

{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
orderable: false,
searchable: false,
className: 'text-center',
render: function (data, type, row) {

const estatus = Number(row.estatus);

const noDesc = !permisos.descargar;
const noDelete = !permisos.eliminar;

let disabledDesc = '';
let disabledDelete = '';

// DESCARGAR
if (noDesc) {
disabledDesc = 'disabled opacity-50 pointer-events-none';
} else {
if (estatus === 2) {
disabledDesc = 'disabled opacity-50 pointer-events-none';
}

}

// ELIMINAR
if (noDelete) {
disabledDelete = 'disabled opacity-50 pointer-events-none';
} else {
if (estatus === 1 || estatus === 2) {
disabledDelete = 'disabled opacity-50 pointer-events-none';
}
}

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">

<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DESCARGAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item ${disabledDesc ? 'disabled' : ''}"
${!disabledDesc ? `@click="download('poliza-seguro','${row.archivo}')"` : ''}
>
<i class="ti ti-file-download"></i> Descargar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledDelete ? 'disabled' : ''}"
${!disabledDelete ? `
@click="async () => {
await deleteAction({
url: '/seguro/delete-poliza-seguro',
id: ${row.id},
name: '${row.id}',
table: '#table-poliza'
});
}"
` : ''}
>
<i class="fs-4 ti ti-trash"></i> Eliminar  
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

//---------- TABLA POLIZA DE SEGURO (COBERTURA) ----------//
if ($.fn.DataTable.isDataTable('#table-poliza-cobertura')) {
return;
}

const table2 = $('#table-poliza-cobertura').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/seguro/poliza-seguro-cobertura/datatable',
type: 'GET',
dataSrc: function (json) {
// Guardar permisos globalmente
permisos = json.permisos;
return json.data;
}
},

columns: [
{ title: '#', data: 'id', width: '60px', className: 'text-center align-middle' },

{
title: 'Fecha y hora',
data: 'fecha_hora',
render: function (data, type) {
if (!data) return '';

const fecha = new Date(data);

if (type === 'display') {
return fecha.toLocaleString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric',
hour: '2-digit',
minute: '2-digit',
hour12: true
});
}

if (type === 'filter') {
return fecha.toLocaleString('es-MX') + ' ' + data;
}

return data; // Para ordenamiento correcto
}
},

{ title: 'Estatus', data: 'estatus', width: '140px', className: 'text-center align-middle',
render: function (data) {
const estatus = Number(data);

let clase = '';
let texto = '';

switch (estatus) {

case 0:
clase = 'success';
texto = 'Vigente';
break;

case 1:
clase = 'warning';
texto = 'No Vigente';
break;

case 2:
clase = 'danger';
texto = 'Eliminado';
break;

default:
clase = 'secondary';
texto = 'Desconocido';

}

return `<span class="badge rounded-pill bg-${clase}">${texto}</span>`;
}

},

{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
orderable: false,
searchable: false,
className: 'text-center',
render: function (data, type, row) {

const estatus = Number(row.estatus);

const noDesc = !permisos.descargar;
const noDelete = !permisos.eliminar;

let disabledDesc = '';
let disabledDelete = '';

// DESCARGAR
if (noDesc) {
disabledDesc = 'disabled opacity-50 pointer-events-none';
} else {
if (estatus === 2) {
disabledDesc = 'disabled opacity-50 pointer-events-none';
}

}

// ELIMINAR
if (noDelete) {
disabledDelete = 'disabled opacity-50 pointer-events-none';
} else {
if (estatus === 1 || estatus === 2) {
disabledDelete = 'disabled opacity-50 pointer-events-none';
}
}

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">

<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DESCARGAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item ${disabledDesc ? 'disabled' : ''}"
${!disabledDesc ? `@click="download('poliza-seguro','${row.archivo}')"` : ''}
>
<i class="ti ti-file-download"></i> Descargar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledDelete ? 'disabled' : ''}"
${!disabledDelete ? `
@click="async () => {
await deleteAction({
url: '/seguro/delete-poliza-seguro-cobertura',
id: ${row.id},
name: '${row.id}',
table: '#table-poliza-cobertura'
});
}"
` : ''}
>
<i class="fs-4 ti ti-trash"></i> Eliminar  
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