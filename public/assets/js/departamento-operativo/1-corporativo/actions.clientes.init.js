document.addEventListener('DOMContentLoaded', () => {
ModuleStationSelector.init('corte-diario');
});

document.addEventListener('alpine:init', () => {

Alpine.data('clientesComponent', () => ({

idDia: null,
idYear: null,
idMes: null,
idEstacion: null,
multiestacion: true,
puedeCrear: false,
puedeEliminar: false,
loading: true,

rows: [],
resumen: {
dc: 0, dp: 0, cc: 0, cp: 0,
total_consumo: 0, total_pago: 0,
},

clientes: [],

modalCliente: '',
modalTotal: '',
modalTipo: '',
modalFormaPago: '',
modalComprobante: null,
guardando: false,

listaLoading: false,
listaClientes: [],

init() {
const c = document.getElementById('container');
if (!c) return;
this.idDia = parseInt(c.dataset.idDia);
this.idYear = parseInt(c.dataset.idYear);
this.idMes = parseInt(c.dataset.idMes);
this.idEstacion = parseInt(c.dataset.idEstacion);
this.multiestacion = c.dataset.multiestacion === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.cargarDatos();
this.cargarClientes();
},

get puedeAgregar() {
return !this.multiestacion && this.puedeCrear;
},

get puedeBorrar() {
return !this.multiestacion && this.puedeEliminar;
},

get listaCreditos() {
return this.listaClientes.filter(c => c.tipo === 'Crédito');
},

get listaDebitos() {
return this.listaClientes.filter(c => c.tipo === 'Débito');
},

_initDataTable() {
const self = this;

if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tablaClientes')) {
$('#tablaClientes').DataTable().destroy();
}

const cols = [
{ title: "#", data: 'id', className: 'text-center align-middle fw-normal' },
{ title: "Cuenta", data: 'cuenta', className: 'align-middle text-center' },
{ title: "Cliente", data: 'cliente', className: 'align-middle' },
{
title: "Tipo",
data: 'tipo',
className: 'align-middle text-center',
render: function (d) {
const cls = d === 'Crédito' ? 'text-primary' : 'text-success';
return '<span class="' + cls + '">' + d + '</span>';
}
},
{ title: "Consumo/Pago", data: 'consumo_tipo', className: 'align-middle text-center' },
{
title: "Forma Pago",
data: 'pago',
className: 'align-middle text-center',
render: function (d) {
return d || '<span class="text-muted">N/A</span>';
}
},
{
title: "Comprobante",
data: 'comprobante',
className: 'align-middle text-center',
orderable: false,
searchable: false,
render: function (d) {
return d ? '<div x-data="actions()"><a href="javascript:void(0)" @click="download(\'comprobantes-clientes\', \'' + d + '\')"><i class="ti ti-file-text text-success fs-6"></i></a></div>' : '<span class="text-muted">N/A</span>';
}
},
{
title: "Total",
data: 'total',
className: 'align-middle text-end',
render: function (d) {
var v = parseFloat(d || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
return '<strong>$ ' + v + '</strong>';
}
},
{
title: "<i class='ti ti-trash fs-6 text-danger'></i>",
data: 'id',
width: '1%',
className: 'text-center align-middle td-small',
orderable: false,
searchable: false,
visible: this.puedeBorrar,
render: function (id, type, row) {
return '<div x-data="actions()"><a href="javascript:void(0)" class="text-danger" @click="await deleteAction({url: \'/departamento-operativo/clientes/eliminar\', id: ' + id + ', name: \'' + id + ' - ' + row.cliente + '\'}); cargarDatos();"><i class="ti ti-trash fs-6"></i></a></div>';
}
},
];

var table = $('#tablaClientes').DataTable({
data: this.rows,
columns: cols,
processing: true,
autoWidth: false,
stateSave: true,
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
pageLength: 10,
order: [[0, 'asc']],
destroy: true,
});

table.on('draw', function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tablaClientes'));
}
});

},

async cargarDatos() {
try {
const resp = await fetch('/departamento-operativo/clientes/data/' + this.idDia);
const json = await resp.json();
if (json.success) {
this.rows = json.data.rows;
this.resumen = json.data.resumen;
this._initDataTable();
}
} catch (e) {
console.error('Error cargando clientes:', e);
}
if (this.loading) this.loading = false;
},

async cargarClientes() {
try {
const resp = await fetch('/departamento-operativo/clientes/lista');
const json = await resp.json();
if (json.success) {
this.clientes = json.clientes;
}
} catch (e) {
console.error('Error cargando lista de clientes:', e);
}
},

async cargarListaClientes() {
this.listaLoading = true;
try {
const resp = await fetch('/departamento-operativo/clientes/lista');
const json = await resp.json();
if (json.success) {
this.listaClientes = json.clientes;
}
} catch (e) {
console.error('Error cargando lista de clientes:', e);
} finally {
this.listaLoading = false;
}
},

async abrirModalAgregar() {
this.modalCliente = '';
this.modalTotal = '';
this.modalTipo = '';
this.modalFormaPago = '';
this.modalComprobante = null;
await this.cargarClientes();
const modalEl = document.getElementById('modalAgregar');
const modal = new bootstrap.Modal(modalEl);
modal.show();
this.$nextTick(() => {
const $select = $('#selectCliente');
if (!$select.hasClass('select2-hidden-accessible')) {
$select.select2({
dropdownParent: $('#modalAgregar .modal-content'),
width: '100%',
placeholder: 'Selecciona un cliente...',
});
$select.off('change.select2modal').on('change.select2modal', (e) => {
this.modalCliente = $(e.target).val() || '';
});
window.dispatchEvent(new Event('resize'));
const wr = this.$refs.clienteWrapper;
if (wr) wr.classList.remove('is-select2-pending');
}
});
if (!modalEl._select2Cleanup) {
modalEl._select2Cleanup = true;
modalEl.addEventListener('hidden.bs.modal', () => {
const $select = $('#selectCliente');
if ($select.hasClass('select2-hidden-accessible')) {
$select.select2('destroy');
}
const wr = this.$refs.clienteWrapper;
if (wr) wr.classList.add('is-select2-pending');
});
}
},

async abrirListaClientes() {
const c = document.getElementById('container');
const idEstacion = c?.dataset.idEstacion;
const idYear = c?.dataset.idYear;
const idMes = c?.dataset.idMes;
const idDia = c?.dataset.idDia;
try {
await fetch('/departamento-operativo/clientes-lista/guardar-contexto', {
method: 'POST',
headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
body: 'idYear=' + idYear + '&idMes=' + idMes + '&idDia=' + idDia
});
} catch (e) { console.error(e); }
window.location.href = '/departamento-operativo/clientes-lista';
},

async guardar() {
if (this.guardando) return;
if (!this.modalCliente) {
if (window.Notify) Notify.error('Selecciona un cliente');
return;
}
if (!this.modalTotal || parseFloat(this.modalTotal) <= 0) {
if (window.Notify) Notify.error('Ingresa un total válido');
return;
}
if (!this.modalTipo) {
if (window.Notify) Notify.error('Selecciona Consumo o Pago');
return;
}

this.guardando = true;

if (this.modalTipo === 'Pago') {
if (!this.modalFormaPago) {
if (window.Notify) Notify.error('Selecciona una forma de pago');
this.guardando = false;
return;
}
const fd = new FormData();
fd.append('idReporte', this.idDia);
fd.append('Cliente', this.modalCliente);
fd.append('Total', this.modalTotal);
fd.append('FormaPago', this.modalFormaPago);
fd.append('Tipo', this.modalTipo);
if (this.modalComprobante) {
fd.append('Comprobante_file', this.modalComprobante);
}

try {
const resp = await fetch('/departamento-operativo/clientes/agregar/pago', {
method: 'POST',
body: fd,
});
const json = await resp.json();
if (json.success) {
const m = bootstrap.Modal.getInstance(document.getElementById('modalAgregar'));
if (m) m.hide();
await this.cargarDatos();
if (window.Notify) Notify.success('Pago agregado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el pago');
}
} catch (e) {
console.error('Error:', e);
if (window.Notify) Notify.error('Error al agregar el pago');
}
} else {
const fd = new FormData();
fd.append('idReporte', this.idDia);
fd.append('Cliente', this.modalCliente);
fd.append('Total', this.modalTotal);
fd.append('Tipo', this.modalTipo);

try {
const resp = await fetch('/departamento-operativo/clientes/agregar/consumo', {
method: 'POST',
body: fd,
});
const json = await resp.json();
if (json.success) {
const m = bootstrap.Modal.getInstance(document.getElementById('modalAgregar'));
if (m) m.hide();
await this.cargarDatos();
if (window.Notify) Notify.success('Consumo agregado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el consumo');
}
} catch (e) {
console.error('Error:', e);
if (window.Notify) Notify.error('Error al agregar el consumo');
}
}

this.guardando = false;
},


}));

});