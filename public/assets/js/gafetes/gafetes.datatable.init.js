document.addEventListener('DOMContentLoaded', () => {

const root = document.getElementById('container');
const puedeAdministrar = root?.dataset.puedeAdministrar === 'true';
document.querySelectorAll('.gafetes-admin-only').forEach((element) => element.classList.toggle('d-none', !puedeAdministrar));
const selector = document.getElementById('module-station-selector-solicitud-gafetes');
const botonNuevo = document.querySelector('.gafetes-admin-only [data-bs-target="#nuevo"]');
const actualizarBotonNuevo = () => {
    if (!botonNuevo || !selector) return;
    const todas = selector.value === '';
    botonNuevo.disabled = todas;
    botonNuevo.title = todas ? 'Selecciona una estación específica para crear una solicitud.' : '';
};
selector?.addEventListener('change', actualizarBotonNuevo);
actualizarBotonNuevo();

const actualizarContadores = (pendientes = {}) => {
    if (!selector) return;
    Array.from(selector.options).forEach((option) => {
        const etiqueta = option.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
        const key = option.value.startsWith('estacion_') ? option.value : 'total';
        const count = Number(pendientes[key] || 0);
        option.textContent = count > 0 ? `${etiqueta} (${count})` : etiqueta;
    });
};
 
const table = $('#table-gafetes').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],

language: {
url: '/assets/libs/datatables.net/js/es-ES.json',
emptyTable: 'No se encontró información',
zeroRecords: 'No se encontró información',
processing: 'Cargando información...'
},

ajax: {
url: '/solicitud-gafetes/datatable',
type: 'GET',
dataSrc: function (json) {
actualizarContadores(json.pendientes || {});
return Array.isArray(json.data) ? json.data : [];
}
},

columns: [
{ title: '#', data: 'idReporte', width: '60px', className: 'text-center align-middle' },
{ title: 'Estación', data: 'nombre_estacion', className: 'text-center align-middle', visible: false },
{ title: 'No. Solicitud', data: 'no_reporte', width: '100px', className: 'text-center align-middle'},
{ title: 'Fecha', data: 'fecha_reporte', className: 'text-center align-middle',
render: function (data, type, row) {
if (type !== 'display' && type !== 'filter') return data;
return row.fecha_reporte_formateada || data || 'S/I';
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
{ title: 'Comentarios', data: 'comentarios', className: 'text-start align-middle',
render: function (data, type) {
if (type !== 'display') return data || '';
return $('<div>').text(data || 'Sin comentarios').html();
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
const siguientePaso = Number(permisos.siguientePaso || 0);
const disabledStatus = permisos.disabledStatus ? 'disabled opacity-50 pointer-events-none' : '';
const statusLabel = { 0: 'Pendiente', 1: 'En proceso', 2: 'Finalizada', 3: 'Finalizada', 4: 'Entregada' };
const nextStatus = { 1: 'En proceso', 2: 'Finalizada', 3: 'Entregada' };
const statusMessage = {
1: `La solicitud cambiará de ${statusLabel[Number(row.estatus_reporte)]} a En proceso.`,
2: 'La solicitud cambiará de En proceso a Finalizada.',
3: 'La solicitud cambiará de Finalizada a Entregada.'
};
const statusAction = siguientePaso
? `<li><a class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledStatus}" title="${permisos.disabledStatus ? 'Este paso no está disponible para tu usuario.' : ''}" ${permisos.disabledStatus ? '' : `@click="async () => { const r = await Swal.fire({title: 'Actualizar estatus', text: '${statusMessage[siguientePaso]}', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, actualizar', cancelButtonText: 'Cancelar', confirmButtonColor: '#198754', ${siguientePaso === 2 ? "input: 'textarea', inputPlaceholder: 'Comentario (opcional)', inputAttributes: { maxlength: 2000, 'aria-label': 'Comentario de finalización' }," : ''}}); if (!r.isConfirmed) return; await createAction({url: '/solicitud-gafetes/seguimiento/update', data: {idEstacion: ${row.idEstacionReal}, no_reporte: ${row.no_reporte}, idSeguimiento: ${siguientePaso}${siguientePaso === 2 ? ', comentario: r.value || \'\'' : ''}}, table: '#table-gafetes'}); }"`}><i class="ti ti-refresh fs-5"></i> Actualizar estatus: ${nextStatus[siguientePaso]}</a></li>`
: '<li><a class="dropdown-item disabled d-flex align-items-center gap-2"><i class="ti ti-circle-check fs-5"></i> Proceso completado</a></li>';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a
class="text-muted pointer"
data-bs-toggle="dropdown"
data-bs-display="static">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DETALLE -->
<li>
<a 
class="dropdown-item pointer d-flex align-items-center gap-2 ${disableDetail}"
${!permisos.disableDetail ? `@click="goTo('/solicitud-gafetes/detalle/${row.idEstacionReal}/${row.no_reporte}')"` : ''}>
<i class="ti ti-eye fs-5"></i> Detalle
</a>
</li>

<!-- ACTUALIZAR ESTATUS -->
${statusAction}

<!-- EDITAR -->
<li>
<a 
class="dropdown-item pointer d-flex align-items-center gap-2 ${disabledEdit}"
${!permisos.disabledEdit ? `@click="goTo('/solicitud-gafetes/formulario/${row.idEstacionReal}/${row.no_reporte}')"` : ''}>
<i class="ti ti-edit fs-5"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a
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
if (json) {
table.column(1).visible(Boolean(json.filas_mostrar?.mostrar_fila_estacion));
actualizarContadores(json.pendientes || {});
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
