document.addEventListener('DOMContentLoaded', () => {

const escapeCell = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));

let filas_mostrar = {};
let mostrarFilaEstacion = true;

const table = $('#table-tarjetas').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/solicitud-tarjetas/datatable',
type: 'GET',
dataSrc: function (json) {
//guardas permisos globalmente
filas_mostrar = json.filas_mostrar;
return json.data;
}
},

columns: [
{ title: '#', data: 'idSolicitud', width: '60px', className: 'text-center align-middle' },
{ title: 'Estación', data: 'nombre_estacion', className: 'text-center align-middle', visible: mostrarFilaEstacion },
{ title: 'No. Solicitud', data: 'no_solicitud', width: '100px', className: 'text-center align-middle'},
{ title: 'Fecha', data: 'fecha_solicitud', className: 'text-center align-middle',
render: function (data, type, row) {

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
return row.fecha_solicitud_formateada || fechaFormateada;
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
{ title: 'Comentarios', data: 'comentarios', className: 'text-center align-middle', render: data => escapeCell(data || 'Sin comentarios') },
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
title:  '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
width: '1%',
orderable: false,
searchable: false,
className: 'text-center align-middle td-small',
render: function (data, type, row) {

const permisos = row.permisos;

let disableDetail = permisos.disableDetail ? 'opacity-50 pointer-events-none' : '';
let disabledEdit = permisos.disabledEdit ? 'opacity-50 pointer-events-none' : '';
let disabledDelete = permisos.disabledDelete ? 'opacity-50 pointer-events-none' : '';

return `
<div x-data="actions()" class="d-flex gap-1 justify-content-center">
<div class="dropdown dropstart">

<a class="pointer"
data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-6"></i>
</a>

<ul class="dropdown-menu">

<!-- DETALLE -->
<li>
<a 
class="dropdown-item pointer d-flex align-items-center gap-2 ${disableDetail}"
${!permisos.disableDetail ? `
@click="goTo('/solicitud-tarjetas/detalle/${row.idEstacionReal}/${row.no_solicitud}')"
` : ''}>
<i class="fs-4 ti ti-eye"></i> Detalle
</a>
</li>

<!-- ACTUALIZAR ESTATUS -->
<li>
<a class="dropdown-item pointer d-flex align-items-center gap-2 ${permisos.puedeActualizar ? '' : 'opacity-50 pointer-events-none'}"
${permisos.puedeActualizar ? `onclick="actualizarEstatusTarjetas(${row.idEstacionReal}, ${row.no_solicitud}, ${row.siguientePaso})"` : ''}>
<i class="fs-4 ti ti-refresh"></i> Actualizar estatus
</a>
</li>

<!-- EDITAR -->
<li>
<a 
class="dropdown-item d-flex pointer align-items-center gap-2 ${disabledEdit}"
${!permisos.disabledEdit ? `
@click="goTo('/solicitud-tarjetas/formulario/${row.idEstacionReal}/${row.no_solicitud}')"
` : ''}>
<i class="fs-4 ti ti-edit"></i> Editar
</a>
</li>

<!-- ELIMINAR -->
<li>
<a 
class="dropdown-item d-flex pointer align-items-center gap-2 ${disabledDelete}"
${!permisos.disabledDelete ? `
@click="async () => {
await deleteAction({
url: '/solicitud-tarjetas/delete-reporte',
id: ${row.idSolicitud},
name: '${row.idSolicitud}',
table: '#table-tarjetas'
});
}"
` : ''}>
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

table.on('xhr', function () {
const json = table.ajax.json();

if (json && json.filas_mostrar) {
table.column(1).visible(json.filas_mostrar.mostrar_fila_estacion);
}
if (json?.pendientesData) {
    const selector = document.getElementById('module-station-selector-solicitud-tarjetas');
    if (selector) Array.from(selector.options).forEach(option => {
        const key = option.value.startsWith('estacion_') ? option.value : 'total';
        const count = Number(json.pendientesData[key] ?? 0);
        const label = option.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
        option.textContent = count > 0 ? `${label} (${count})` : label;
    });
}

});


$("#table-tarjetas tbody").on("click", "tr", function () {
if ($(this).hasClass("selected")) {
} else {
table.$("tr.selected").removeClass("selected");
$(this).addClass("selected");
}
});

ModuleStationSelector.init('solicitud-tarjetas');

const container = document.getElementById('container');
if (container?.dataset.puedeCrear === '1') {
    document.getElementById('tarjetas-toolbar').innerHTML = `<div class="col-12 col-md-auto d-grid mt-4 mb-3"><button class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Nuevo</button></div>`;
    const selector = document.getElementById('module-station-selector-solicitud-tarjetas');
    const toolbar = document.getElementById('tarjetas-toolbar');
    const syncCreateButton = () => { toolbar.classList.toggle('d-none', !!selector && !selector.value); };
    syncCreateButton();
    selector?.addEventListener('change', syncCreateButton);
}

window.actualizarEstatusTarjetas = async (idEstacion, noSolicitud, paso) => {
    const statusLabel = ({1: 'En proceso', 2: 'Finalizada', 3: 'Entrega finalizada'})[paso];
    const currentLabel = ({1: 'Pendiente', 2: 'En proceso', 3: 'Finalizada'})[paso];
    const config = { title: 'Actualizar estatus', text: `La solicitud cambiará de ${currentLabel} a ${statusLabel}.`, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, actualizar', cancelButtonText: 'Cancelar', confirmButtonColor: '#198754' };
    if (paso === 2) {
        config.input = 'textarea';
        config.inputPlaceholder = 'Comentario (opcional)';
        config.inputAttributes = { maxlength: 2000, 'aria-label': 'Comentario (opcional)' };
    }
    const result = await Swal.fire(config);
    if (!result.isConfirmed) return;
    Swal.fire({ title: 'Actualizando estatus...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/solicitud-tarjetas/seguimiento/update', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken}, body: JSON.stringify({ idEstacion, no_reporte: noSolicitud, idSeguimiento: paso, comentarios: paso === 2 ? String(result.value || '').trim() : '' }) });
        const payload = await response.json();
        if (!response.ok || !payload.success) { Swal.fire('No se pudo actualizar', payload.message || 'Revisa el estatus y tus permisos.', 'error'); return; }
        Swal.fire('Estatus actualizado', payload.message || 'Se guardó el cambio.', 'success');
        table.ajax.reload(null, false);
    } catch (error) {
        Swal.fire('No se pudo actualizar', 'Ocurrió un error de comunicación.', 'error');
    }
};

});
