document.addEventListener('DOMContentLoaded', () => {

let permisos = {};
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[char]));
 
const container = document.getElementById('container');
const puedeAdministrar = container?.dataset.puedeAdministrar === 'true';
document.querySelectorAll('.gafetes-admin-only').forEach((element) => element.classList.toggle('d-none', !puedeAdministrar));
const idEstacion = container.dataset.estacion;
const noReporte = container.dataset.reporte;

const table = $('#table-gafetes-formulario').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json',
emptyTable: 'No se encontró información',
zeroRecords: 'No se encontró información'
},
   
ajax: {
url: `/solicitud-gafetes/datatable-formulario/${idEstacion}/${noReporte}`,
type: 'GET',
dataSrc: function (json) {

permisos = json.permisos || {};
return Array.isArray(json.data) ? json.data : [];
}
},

columns: [
{title: '#', data: 'idGafete', width: '60px', className: 'text-center align-middle' },
{title: 'Clave', data: 'clave', width: '100px', className: 'text-center align-middle', render: (data, type) => type === 'display' ? escapeHtml(data || '') : (data || '')},
{title: 'Nombre Completo', data: 'nombre_completo', className: 'text-center align-middle', render: (data, type) => type === 'display' ? escapeHtml(data || '') : (data || '')},
{
title: '<i class="ti ti-dots-vertical fs-6"></i>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const disabled = 'disabled opacity-50 pointer-events-none';
const puedeEditar = Boolean(permisos.editar);
const puedeEliminar = Boolean(permisos.eliminar);
const noDesc = !permisos.descargar || !row.archivo_disponible;
const disabledEditar = puedeEditar ? '' : disabled;
const disabledEliminar = puedeEliminar ? '' : disabled;
const accionEliminar = puedeEliminar
    ? `@click="async () => { const result = await deleteAction({url: '/solicitud-gafetes/delete-reporte-registro-formulario', id: ${Number(row.idGafete)}, name: '${Number(row.idGafete)}', table: '#table-gafetes-formulario'}); if (result?.sinRegistros) window.location.href = '/solicitud-gafetes'; }"`
    : '';
const clave = escapeHtml(row.clave || '');
const nombre = escapeHtml(row.nombre_completo || '');
const archivo = escapeHtml(row.foto_gafete || '');

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a class="text-muted pointer" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<li>
<a 
class="dropdown-item pointer d-flex align-items-center gap-2 ${noDesc ? disabled : ''}"
${noDesc ? '' : `data-archivo="${archivo}" @click="download('solicitud-gafetes',$el.dataset.archivo)"`}>
<i class="ti ti-file-download fs-5"></i> Descargar
</a>
</li>

<li><a class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledEditar}" ${puedeEditar ? `data-edit-id="${Number(row.idGafete)}" data-edit-clave="${clave}" data-edit-nombre="${nombre}" @click="$dispatch('open-edit',{id:Number($el.dataset.editId),clave:$el.dataset.editClave,nombre_g:$el.dataset.editNombre})"` : ''}><i class="ti ti-edit fs-5"></i> Editar</a></li>

<!-- ELIMINAR -->
<li>
<a
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledEliminar}"
${accionEliminar}
>
<i class="ti ti-trash fs-5"></i> Eliminar
</a>
</li>

</ul>
</div>
</div>`;

}

}

]

});

table.on('draw', function () {
    const tbody = document.querySelector('#table-gafetes-formulario');
    if (window.Alpine && tbody) Alpine.initTree(tbody);
    document.querySelectorAll('#table-gafetes-formulario [data-bs-toggle="dropdown"]').forEach((element) => {
        bootstrap.Dropdown.getOrCreateInstance(element, { popperConfig: { strategy: 'fixed' } });
    });
});


$("#table-gafetes-formulario tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});


});
