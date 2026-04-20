document.addEventListener('DOMContentLoaded', () => {

let permisos = {};
 
const container = document.getElementById('container');
const idEstacion = container.dataset.estacion;
const noReporte = container.dataset.reporte;

const table = $('#table-gafetes-detalle').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
  
ajax: {
url: `/solicitud-gafetes/datatable-detalle/${idEstacion}/${noReporte}`,
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
permisos = json.permisos;
return json.data; 
}
},

columns: [
{title: '#', data: 'idGafete', width: '60px', className: 'text-center align-middle' },
{title: 'Clave', data: 'clave', width: '100px', className: 'text-center align-middle'},
{title: 'Nombre Completo', data: 'nombre_completo', className: 'text-center align-middle'},
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const disabled = 'disabled opacity-50 pointer-events-none';
const noDesc = !permisos.descargar;

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<li>
<a href="javascript:void(0)" 
class="dropdown-item d-flex align-items-center gap-1 ${noDesc ? disabled : ''}"
${noDesc ? '' : ` @click="download('solicitud-gafetes','${row.foto_gafete}')"`}>
<i class="ti ti-file-download"></i> Descargar 
</a>
</li>

</ul>
</div>
</div>`;

}

}

]

});

$("#table-gafetes-detalle tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

});