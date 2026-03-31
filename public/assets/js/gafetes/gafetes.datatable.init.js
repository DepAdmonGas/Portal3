document.addEventListener('DOMContentLoaded', () => {

let permisos = {};

const table = $('#table-gafetes').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},

ajax: {
url: '/solicitud-gafetes/datatable',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data;
}
},

rowCallback: function (row, data) {

// Configuracion del color de las filas
const estatus = Number(data.estatus);
let color = '';

switch (estatus) {

case 0:
color = '#ffb6af'; // rojo
break;

case 1:
case 2:
case 3:
color = '#fcfcda'; // amarillo
break;

case 4:
color = '#b0f2c2'; // verde
break;

}

// Aqui se aplica el color a cada celda 
if (color) {
$(row).find('td').css({'background-color': color});
}

},

columns: [
{data: 'id', width: '60px', className: 'text-center align-middle' },
{data: 'no_reporte', width: '100px', className: 'text-center align-middle'},
{ data: 'fecha', className: 'text-center align-middle',
render: function (data, type) {

if (!data) return '';

if (type !== 'display') {
return data;
}

const fecha = new Date(data);
return fecha.toLocaleDateString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric'
});

}
},

{data: 'usuario', className: 'text-center align-middle'},
{data: 'id_estacion', className: 'text-center align-middle'},
{data: 'estatus', width: '140px', className: 'text-center align-middle',
render: function (data) {
const estatus = Number(data);

let clase = '';
let texto = '';

switch (estatus) {

case 0:
clase = 'danger';
texto = 'Sin atender';
break;

case 1:
case 2:
case 3:
clase = 'warning';
texto = 'En proceso';
break;

case 4:
clase = 'success';
texto = 'Entregada';
break;

default:
clase = 'secondary';
texto = 'Desconocido';

}

return `<span class="badge rounded-pill bg-${clase}">${texto}</span>`;
}

},

{
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const estatus = Number(row.estatus);

let disabledEdit = '';
let disabledDelete = '';



// EDITAR
if (estatus === 4) {
disabledEdit = 'disabled opacity-50 pointer-events-none';
}

// ELIMINAR
if (estatus !== 0) {
disabledDelete = 'disabled opacity-50 pointer-events-none';
}

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)"
class="text-muted"
data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DETALLE (siempre activo) -->
<li>
<a class="dropdown-item d-flex align-items-center gap-2 btn-detail" 
data-id="${row.id}" data-estacion="${row.id_estacion_real}" data-reporte="${row.no_reporte}">
<i class="fs-4 ti ti-eye"></i>
Detalle
</a>
</li>

<!-- EDITAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledEdit ? 'disabled' : ''}"
${!disabledEdit ? `@click="goTo('/solicitud-gafetes/${row.id_estacion_real}/${row.no_reporte}')"` : ''}>
<i class="fs-4 ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a href="javascript:void(0)"
class="dropdown-item d-flex align-items-center gap-2 ${disabledDelete ? 'disabled' : ''}"
${disabledDelete ? '' : `
@click="async () => {
await deleteAction({
url: '/solicitud-gafetes/delete-reporte',
id: ${row.id},
name: '${row.id}',
table: '#table-gafetes'
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

});