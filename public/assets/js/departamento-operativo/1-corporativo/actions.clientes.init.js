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
selectedClientId: null,
selectedModalRows: { credito: null, debito: null },
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
validationErrors: { cliente: '', total: '', tipo: '', formaPago: '', comprobante: '' },

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

seleccionarFilaModal(tipo, id) {
this.selectedModalRows[tipo] = id;
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
const existingTable = $('#tablaClientes').DataTable();
existingTable.clear();
existingTable.rows.add(this.rows);
existingTable.draw(false);
return;
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
render: function (d, type, row) {
if (!d || !row.comprobante_disponible) return '<i class="ti ti-file-off fs-6 text-muted" title="Archivo no disponible"></i>';
return '<div x-data="actions()"><a class="pointer" @click="download(\'comprobantes-clientes\', \'' + d + '\')"><i class="ti ti-file-text text-success fs-6"></i></a></div>';
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
return '<div x-data="actions()"><a class="text-danger pointer" @click="await deleteAction({url: \'/departamento-operativo/clientes/eliminar\', id: ' + id + ', name: \'' + id + ' - ' + row.cliente + '\'}); cargarDatos();"><i class="ti ti-trash fs-6"></i></a></div>';
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
rowCallback: function (row, rowData) {
$(row).addClass('pointer').toggleClass('selected', self.selectedClientId === rowData.id);
},
});

$('#tablaClientes tbody')
.off('click.rowSelection')
.on('click.rowSelection', 'tr', function () {
const rowData = table.row(this).data();
if (!rowData) return;

self.selectedClientId = rowData.id;
$('#tablaClientes tbody tr').removeClass('selected');
$(this).addClass('selected');
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

formatInputNumber(value) {
if (value === null || value === undefined || value === '') return '';
const normalized = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
return normalized.includes('.') ? grouped + '.' + decimals : grouped;
},

formatNumericInput(event, row, field) {
const input = event.target;
const raw = input.value;
const cursor = input.selectionStart ?? raw.length;
const digitsBeforeCursor = raw.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
const normalized = raw.replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const formatted = normalized.includes('.') ? grouped + '.' + decimals : grouped;

row[field] = normalized;
input.value = formatted;

let nextCursor = 0;
let numericChars = 0;
while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
if (/[0-9.]/.test(formatted[nextCursor])) numericChars++;
nextCursor++;
}
input.setSelectionRange(nextCursor, nextCursor);
},

formatModalTotal(event) {
this.formatNumericInput(event, this, 'modalTotal');
if (parseFloat(this.modalTotal) > 0) this.validationErrors.total = '';
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
this.modalTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
this.resetModalAgregar();
await this.cargarClientes();
const modalEl = document.getElementById('modalAgregar');
const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
modal.show();
this.$nextTick(() => {
const $select = $('#selectCliente');
if (!$select.hasClass('select2-hidden-accessible')) {
$select.select2({
dropdownParent: $('#modalAgregar .modal-content'),
width: '100%',
placeholder: 'Selecciona un cliente...',
});
window.dispatchEvent(new Event('resize'));
const wr = this.$refs.clienteWrapper;
if (wr) wr.classList.remove('is-select2-pending');
}
$select.off('change.clientesModal').on('change.clientesModal', (e) => {
this.modalCliente = String($(e.target).val() || '');
if (this.modalCliente) this.validationErrors.cliente = '';
});
$select.val(this.modalCliente || '').trigger('change');
});
if (!modalEl._select2Cleanup) {
modalEl._select2Cleanup = true;
modalEl.addEventListener('hide.bs.modal', () => {
if (modalEl.contains(document.activeElement)) document.activeElement.blur();
});
modalEl.addEventListener('hidden.bs.modal', () => {
const $select = $('#selectCliente');
$select.off('.clientesModal');
if ($select.hasClass('select2-hidden-accessible')) {
$select.select2('destroy');
}
const wr = this.$refs.clienteWrapper;
if (wr) wr.classList.add('is-select2-pending');
this.resetModalAgregar();
if (this.modalTrigger?.isConnected) this.modalTrigger.focus();
});
}
},

resetModalAgregar() {
this.modalCliente = '';
this.modalTotal = '';
this.modalTipo = '';
this.modalFormaPago = '';
this.modalComprobante = null;
this.validationErrors = { cliente: '', total: '', tipo: '', formaPago: '', comprobante: '' };

const select = this.$refs.selectCliente;
if (select) select.value = '';
if (this.$refs.totalInput) this.$refs.totalInput.value = '';
if (this.$refs.comprobanteInput) this.$refs.comprobanteInput.value = '';
},

validarFormulario() {
const select = this.$refs.selectCliente;
const selectedClient = (select ? $(select).val() : '') || (select ? select.value : '') || this.modalCliente;
this.modalCliente = String(selectedClient || '').trim();

const total = String(this.modalTotal ?? '').replace(/,/g, '').trim();
this.modalTotal = total;
const amount = Number(total);

const fileInput = this.$refs.comprobanteInput;
this.modalComprobante = fileInput?.files?.[0] || this.modalComprobante || null;
this.validationErrors = { cliente: '', total: '', tipo: '', formaPago: '', comprobante: '' };

if (!this.modalCliente) this.validationErrors.cliente = 'Selecciona un cliente.';
if (!Number.isFinite(amount) || amount <= 0) this.validationErrors.total = 'Ingresa un total mayor a cero.';
if (!this.modalTipo) this.validationErrors.tipo = 'Selecciona Consumo o Pago.';
if (this.modalTipo === 'Pago') {
if (!this.modalFormaPago) this.validationErrors.formaPago = 'Selecciona una forma de pago.';
if (['Tarjeta', 'Transferencia'].includes(this.modalFormaPago) && !this.modalComprobante) {
this.validationErrors.comprobante = 'Adjunta el voucher.';
}
}

const firstError = Object.values(this.validationErrors).find(Boolean);
if (firstError) {
return false;
}
return true;
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
this.guardando = true;
try {
if (!this.validarFormulario()) return;

const tipoGuardado = this.modalTipo;
const fd = new FormData();
fd.append('idReporte', this.idDia);
fd.append('Cliente', this.modalCliente);
fd.append('Total', this.modalTotal);
fd.append('Tipo', this.modalTipo);
let endpoint = '/departamento-operativo/clientes/agregar/consumo';
if (this.modalTipo === 'Pago') {
endpoint = '/departamento-operativo/clientes/agregar/pago';
fd.append('FormaPago', this.modalFormaPago);
if (this.modalComprobante) fd.append('Comprobante_file', this.modalComprobante);
}

const resp = await fetch(endpoint, {
method: 'POST',
body: fd,
});
const json = await resp.json();
if (!json.success) {
if (window.Notify) Notify.error(json.message || 'No se pudo agregar el registro.');
return;
}

const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregar'));
if (modal) modal.hide();
await this.cargarDatos();
if (window.Notify) Notify.success(tipoGuardado === 'Pago' ? 'Pago agregado correctamente' : 'Consumo agregado correctamente');
} catch (e) {
console.error('Error al guardar cliente:', e);
if (window.Notify) Notify.error(e.message || 'Error al guardar el registro.');
} finally {
this.guardando = false;
}
},


}));

});
