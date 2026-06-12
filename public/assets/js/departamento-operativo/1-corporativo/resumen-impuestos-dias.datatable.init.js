document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('resumen-impuestos-container');
if (!c) return;

const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);
if (!idYear || !idMes) return;

fetch('/departamento-operativo/resumen-impuestos/dias/' + idYear + '/' + idMes)
.then(r => r.json())
.then(json => {
if (!json.success) {
document.querySelector('#tabla-resumen-impuestos tbody').innerHTML =
'<tr><th colspan="2" class="text-center text-secondary p-3"><small>' +
(json.message || 'Error al cargar datos') + '</small></th></tr>';
return;
}

const data = json.dias || [];

if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tabla-resumen-impuestos')) {
$('#tabla-resumen-impuestos').DataTable().destroy();
}

$('#tabla-resumen-impuestos').DataTable({
data: data,
processing: false,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'asc']],
language: {
url: '/assets/libs/datatables.net/js/es-ES.json'
},
lengthMenu: [15, 30, 50, 100],
columns: [
{
title: 'Fecha',
data: 'fecha_formateada',
className: 'text-start align-middle fw-normal'
},
{
title: '<i class="ti ti-eye text-primary fs-6"></i>',
data: null,
width: '48px',
className: 'text-center align-middle',
orderable: false,
searchable: false,
render: function (data, type) {
if (type !== 'display') return '';

return '<a href="#" class="btn-detalle" data-id-dia="' + 
data.id_dia + 
'" data-fecha="' + 
$('<span>').text(data.fecha_formateada).html() + 
'"><i class="ti ti-eye text-primary fs-6"></i></a>';
}
}
]
});
});
});

$(document).on('click', '.btn-detalle', function (e) {
e.preventDefault();
const idDia = parseInt($(this).data('id-dia'));
const fecha = $(this).data('fecha');
document.dispatchEvent(new CustomEvent('abrir-detalle-impuesto', {
detail: { idDia: idDia, fecha: fecha }
}));
});
