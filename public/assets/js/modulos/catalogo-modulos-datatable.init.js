document.addEventListener('DOMContentLoaded', () => {

$('#table-catalogo').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true, 
order: [[0, 'desc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
ajax: {
url: '/configuracion-sistemas/catalogo-modulos/datatable',
type: 'GET', 
dataSrc: function (json) {
return json.data; 
}
},
columns: [
{ data: 'id', width: '60px', className: 'text-center' },
{ data: 'nombre_modulo' },
{ data: 'url', className: 'text-center' },
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

return `
<div class="dropdown dropstart">
    <a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown">
        <i class="ti ti-dots-vertical fs-6"></i>
    </a>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item d-flex align-items-center gap-3 btn-edit"
               data-id="${row.id}"
               data-nombre="${row.nombre_modulo}"
               data-url="${row.url}">
                <i class="fs-4 ti ti-edit"></i>Editar
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center gap-3 btn-delete ${disabled}" 
               data-id="${row.id}">
                <i class="fs-4 ti ti-trash"></i>Cancelar
            </a>
        </li>
    </ul>
</div>`;
}
}
]
});

});
