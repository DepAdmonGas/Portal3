document.addEventListener('DOMContentLoaded', () => {

let filas_mostrar = {};
let mostrarFilaEstacion = true;
 
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
filas_mostrar = json.filas_mostrar;
return json.data;
}
},

columns: [
{ title: '#', data: 'idReporte', width: '60px', className: 'text-center align-middle' },
{ title: 'No. Solicitud', data: 'no_reporte', width: '100px', className: 'text-center align-middle'},
{ title: 'Fecha', data: 'fecha_reporte', className: 'text-center align-middle',
render: function (data, type) {

if (!data || data === 'S/I') return 'S/I';

const partes = data.split('-');
if (partes.length !== 3) return 'S/I';

const fecha = new Date(partes[0], partes[1] - 1, partes[2]);

const fechaFormateada = fecha.toLocaleDateString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric'
});


if (type === 'display' || type === 'filter') {
return fechaFormateada;
}

return data;
},
orderable: true,
searchable: true
},

{ title: 'Solicita', data: 'nombre_usuario', className: 'text-center align-middle',
render: function (data, type) {
if (!data) return 'S/I';  
return data;
}
},
{ title: 'Estación', data: 'nombre_estacion', className: 'text-center align-middle', visible: mostrarFilaEstacion,
render: function (data, type) {
if (!data) return 'S/I';  

return data;
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
let disableDetail = permisos.disableDetail ? 'disabled opacity-50 pointer-events-none' : '';
let disabledEdit = permisos.disabledEdit ? 'disabled opacity-50 pointer-events-none' : '';
let disabledDelete = permisos.disabledDelete ? 'disabled opacity-50 pointer-events-none' : '';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)"
class="text-muted"
data-bs-toggle="dropdown"
data-bs-display="static">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DETALLE -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${disableDetail}"
${!permisos.disableDetail ? `@click="goTo('/solicitud-gafetes/detalle/${row.idEstacionReal}/${row.no_reporte}')"` : ''}>
<i class="fs-4 ti ti-eye"></i> Detalle
</a>
</li>

<!-- EDITAR -->
<li>
<a 
href="javascript:void(0)"
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledEdit}"
${!permisos.disabledEdit ? `@click="goTo('/solicitud-gafetes/formulario/${row.idEstacionReal}/${row.no_reporte}')"` : ''}>
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
url: '/solicitud-gafetes/delete-reporte',
id: ${row.idReporte},
name: '${row.idReporte}',
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

// Alpine + Dropdown FIX en cada render
table.on('draw', function () {

if (window.Alpine) {
Alpine.initTree(document.querySelector('#table-gafetes'));
}

// Dropdown fuera del contexto del TR (clave)
document.querySelectorAll('#table-gafetes [data-bs-toggle="dropdown"]').forEach(el => {
new bootstrap.Dropdown(el, {
popperConfig: {
strategy: 'fixed'
}
});
});

});


// Control columna
table.on('xhr', function () {
const json = table.ajax.json();
if (json && json.filas_mostrar) {
table.column(4).visible(json.filas_mostrar.mostrar_fila_estacion);
}
});

$("#table-gafetes tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

ModuleStationSelector.init('solicitud-gafetes');

});