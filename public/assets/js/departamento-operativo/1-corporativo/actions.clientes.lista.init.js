document.addEventListener('DOMContentLoaded', () => {
var messageEl = document.getElementById('clientes-lista-empty-message');
var contentEl = document.getElementById('clientes-lista-content');

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showContent() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showContent();
}
});

document.addEventListener('alpine:init', () => {

Alpine.data('clientesListaComponent', () => ({
loading: true,
credito: [],
debito: [],
selectedRows: { '#tablaCredito': null, '#tablaDebito': null },
idEstacion: '',
idYear: '',
idMes: '',
idDia: '',
multiestacion: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
esDireccionOperaciones: false,

formCuenta: '',
formCliente: '',
formTipo: '',
formRfc: '',
files: [null, null, null, null, null, null, null],
validationErrors: { cuenta: '', cliente: '', tipo: '' },

guardandoCrear: false,
guardandoEditar: false,
modalTrigger: null,

editId: 0,
editCuenta: '',
editCliente: '',
editTipo: '',
editRfcCredito: '',
editRfcDebito: '',
editFiles: [null, null, null, null, null, null, null],

init() {
const c = document.getElementById('container');
if (!c) return;
this.idEstacion = c.dataset.idEstacion;
this.idDia = c.dataset.idDia;
this.idYear = c.dataset.idYear;
this.idMes = c.dataset.idMes;
this.multiestacion = c.dataset.multiestacion === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.esDireccionOperaciones = c.dataset.esDireccionOperaciones === 'true';

if (this.idEstacion) {
this.cargarDatos();
}

this.$watch('formTipo', () => {
this.formRfc = '';
});
},

_renderDocIcon(val, disponible) {
if (!val || !disponible) return '<i class="ti ti-file-off text-muted fs-6" title="Archivo no disponible"></i>';
const url = '/download?tipo=comprobantes-clientes&amp;file=' + encodeURIComponent(String(val));
return '<a class="pointer" href="' + url + '" target="_blank" rel="noopener" title="Descargar archivo"><i class="ti ti-file-check text-success fs-6"></i></a>';
},

_renderStatus(val) {
const e = Number(val);
if (e === 1) return '<span class="badge rounded-pill bg-success">Habilitado</span>';
return '<span class="badge rounded-pill bg-danger">Deshabilitado</span>';
},

_renderToggle(row) {
let s = '<div class="dropdown dropstart">';
s += '<a class="pointer" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>';
s += '<ul class="dropdown-menu">';
s += '<li><a class="dropdown-item pointer d-flex align-items-center gap-3 btn-edit-cliente" data-id="' + row.id + '"><i class="fs-4 ti ti-edit"></i>Editar</a></li>';
if (row.estado == 1) {
s += '<li><hr class="dropdown-divider"></li>';
s += '<li><a class="dropdown-item pointer d-flex align-items-center gap-3 btn-toggle-cliente" data-id="' + row.id + '" data-idtipo="1"><i class="fs-4 ti ti-toggle-left"></i>Deshabilitar</a></li>';
} else {
s += '<li><hr class="dropdown-divider"></li>';
s += '<li><a class="dropdown-item pointer d-flex align-items-center gap-3 btn-toggle-cliente" data-id="' + row.id + '" data-idtipo="0"><i class="fs-4 ti ti-toggle-right"></i>Habilitar</a></li>';
}
s += '</ul></div>';
return s;
},

_initDataTables() {
const self = this;

const cCols = [
{ title: "#", data: null, className: 'text-center align-middle fw-normal', render: function (d, t, r, m) { return m.row + 1; } },
{ title: "Cuenta", data: 'cuenta', className: 'align-middle text-center' },
{ title: "Cliente", data: 'cliente', className: 'align-middle' },
{ title: "RFC", data: 'doc_rfc', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_rfc_disponible); }, orderable: false, searchable: false },
{ title: "CC", data: 'doc_cc', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_cc_disponible); }, orderable: false, searchable: false },
{ title: "AC", data: 'doc_ac', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_ac_disponible); }, orderable: false, searchable: false },
{ title: "CD", data: 'doc_cd', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_cd_disponible); }, orderable: false, searchable: false },
{ title: "ID", data: 'doc_io', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_io_disponible); }, orderable: false, searchable: false },
{ title: "OC", data: 'doc_oc', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_oc_disponible); }, orderable: false, searchable: false },
{ title: "NP", data: 'doc_np', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_np_disponible); }, orderable: false, searchable: false },
{ title: "Estatus", data: 'estado', width: '100px', className: 'text-center align-middle', render: function (d) { return self._renderStatus(d); } },
{ title: "<i class='ti ti-dots-vertical fs-6'></i>", data: null, width: '1%', className: 'text-center align-middle td-small', orderable: false, searchable: false, render: function (d, t, row) { return self._renderToggle(row); } },
];

const dCols = [
{ title: "#", data: null, className: 'text-center align-middle fw-normal', render: function (d, t, r, m) { return m.row + 1; } },
{ title: "Cuenta", data: 'cuenta', className: 'align-middle text-center' },
{ title: "Cliente", data: 'cliente', className: 'align-middle' },
{ title: "CD", data: 'doc_cd', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_cd_disponible); }, orderable: false, searchable: false },
{ title: "ID", data: 'doc_io', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_io_disponible); }, orderable: false, searchable: false },
{ title: "RFC", data: 'doc_rfc', className: 'align-middle text-center', render: function (d, t, r) { return self._renderDocIcon(d, r.doc_rfc_disponible); }, orderable: false, searchable: false },
{ title: "Estatus", data: 'estado', width: '100px', className: 'text-center align-middle', render: function (d) { return self._renderStatus(d); } },
{ title: "<i class='ti ti-dots-vertical fs-6'></i>", data: null, width: '1%', className: 'text-center align-middle td-small', orderable: false, searchable: false, render: function (d, t, row) { return self._renderToggle(row); } },
];

const cols = { '#tablaCredito': cCols, '#tablaDebito': dCols };
const data = { '#tablaCredito': this.credito, '#tablaDebito': this.debito };

Object.keys(cols).forEach(function(id) {
if ($.fn.DataTable && $.fn.DataTable.isDataTable(id)) {
const table = $(id).DataTable();
table.clear();
table.rows.add(data[id]);
table.draw(false);
return;
}

$(id).DataTable({
data: data[id],
columns: cols[id],
autoWidth: false,
stateSave: false,
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
pageLength: 10,
order: [[1, 'asc']],
createdRow: function (row, rowData) {
if (rowData.estado == 0) $(row).css('background-color', '#ffb6af');
},
rowCallback: function (row, rowData) {
$(row).addClass('pointer').toggleClass('selected', self.selectedRows[id] === rowData.id);
},
});
});

$('#tablaCredito tbody, #tablaDebito tbody')
.off('click.rowSelection')
.on('click.rowSelection', 'tr', function () {
const selector = '#' + $(this).closest('table').attr('id');
const rowData = $(selector).DataTable().row(this).data();
if (!rowData) return;

self.selectedRows[selector] = rowData.id;
$(selector + ' tbody tr').removeClass('selected');
$(this).addClass('selected');
})
.off('click', '.btn-edit-cliente')
.on('click', '.btn-edit-cliente', function () {
self.abrirModalEditar(parseInt($(this).data('id')));
})
.off('click', '.btn-toggle-cliente')
.on('click', '.btn-toggle-cliente', function () {
self.toggleEstado(parseInt($(this).data('id')), parseInt($(this).data('idtipo')));
});
},

cargarDatos() {
const self = this;
axios.get('/departamento-operativo/clientes-lista/data')
.then(function (r) {
self.credito = r.data.credito || [];
self.debito = r.data.debito || [];
})
.catch(function (e) {
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error al cargar la lista de clientes');
Notify.error(msg);
console.error('[ERROR] cargarDatos:', e);
})
.finally(function () {
self.loading = false;
self.$nextTick(function () {
self._initDataTables();
});
});
},

_getCsrfToken() {
const meta = document.querySelector('meta[name="csrf-token"]');
return meta ? meta.getAttribute('content') : '';
},

_marcarError(id) {
const el = document.getElementById(id);
if (el) {
el.classList.add('is-invalid');
el.setAttribute('aria-invalid', 'true');
}
},

_limpiarErrores() {
['Cuenta', 'Cliente', 'Tipo', 'EditCuenta', 'EditCliente'].forEach(function (id) {
const el = document.getElementById(id);
if (el) {
el.classList.remove('is-invalid');
el.removeAttribute('aria-invalid');
}
});
},

_resetCrearModal() {
this.formCuenta = '';
this.formCliente = '';
this.formTipo = '';
this.formRfc = '';
this.files = [null, null, null, null, null, null, null];
this.validationErrors = { cuenta: '', cliente: '', tipo: '' };
const modalEl = this.$refs.modalCrear;
if (modalEl) modalEl.querySelectorAll('input[type="file"]').forEach((input) => { input.value = ''; });
this._limpiarErrores();
},

_bindCrearModalLifecycle() {
const modalEl = this.$refs.modalCrear;
if (!modalEl || modalEl.dataset.lifecycleBound === 'true') return;
modalEl.dataset.lifecycleBound = 'true';
modalEl.addEventListener('hide.bs.modal', () => {
if (modalEl.contains(document.activeElement)) document.activeElement.blur();
});
modalEl.addEventListener('hidden.bs.modal', () => {
this._resetCrearModal();
if (this.modalTrigger?.isConnected) this.modalTrigger.focus();
});
},

abrirModalCrear() {
this.modalTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
this._resetCrearModal();
this._bindCrearModalLifecycle();
const modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalCrear);
modal.show();
},

guardarCrear() {
if (this.guardandoCrear) return;
this.validationErrors = { cuenta: '', cliente: '', tipo: '' };
let valido = true;

const Cuenta = String(this.formCuenta || '').trim();
const Cliente = String(this.formCliente || '').trim();
const Tipo = this.formTipo;

if (!Cuenta) { this.validationErrors.cuenta = 'Ingresa la cuenta.'; this._marcarError('Cuenta'); valido = false; }
if (!Cliente) { this.validationErrors.cliente = 'Ingresa el nombre del cliente.'; this._marcarError('Cliente'); valido = false; }
if (!['Crédito', 'Débito'].includes(Tipo)) { this.validationErrors.tipo = 'Selecciona el tipo de cliente.'; this._marcarError('Tipo'); valido = false; }
if (!valido) {
Notify.error('Completa los campos obligatorios.');
return;
}
this.guardandoCrear = true;

const self = this;
const form = new FormData();
form.append('Cuenta', this.formCuenta);
form.append('Cliente', this.formCliente);
form.append('Tipo', this.formTipo);
form.append('RFC', this.formRfc);
form.append('_csrf_token', this._getCsrfToken());

this.files.forEach((file, index) => {
if (file) form.append([
'CartaCredito_file', 'ActaConstitutiva_file', 'ComprobanteDom_file',
'Identificacion_file', 'ConstanciaRFC_file', 'PoderNotarial_file',
'OpinionCumplimiento_file'
][index], file);
});

axios.post('/departamento-operativo/clientes-lista/crear', form, {
headers: { 'X-CSRF-TOKEN': this._getCsrfToken() }
})
.then(function (r) {
self.guardandoCrear = false;
if (r.data.success) {
bootstrap.Modal.getInstance(self.$refs.modalCrear)?.hide();
self.cargarDatos();
Notify.success('Cliente agregado exitosamente');
} else {
Notify.error(r.data.message || 'Error al agregar cliente');
}
})
.catch(function (e) {
self.guardandoCrear = false;
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexion al agregar cliente');
Notify.error(msg);
console.error('[ERROR] crear:', e);
});
},

abrirModalEditar(idCliente) {
this.editId = idCliente;
this.editFiles = [null, null, null, null, null, null, null];
const c = (this.credito.concat(this.debito)).find(function (x) { return x.id === idCliente; });
if (!c) return;
this.editCuenta = c.cuenta;
this.editCliente = c.cliente;
this.editTipo = c.tipo;
this.editRfcCredito = c.rfc || '';
this.editRfcDebito = c.rfc || '';
const modal = new bootstrap.Modal(document.getElementById('ModalEditar'));
modal.show();
},

guardarEditar() {
this._limpiarErrores();
let valido = true;

const Cuenta = this.editCuenta.trim();
const Cliente = this.editCliente.trim();

if (!Cuenta) { this._marcarError('EditCuenta'); valido = false; }
if (!Cliente) { this._marcarError('EditCliente'); valido = false; }
if (!valido) return;
this.guardandoEditar = true;

const self = this;
const form = new FormData();
form.append('idCliente', this.editId);
form.append('Cuenta', this.editCuenta);
form.append('Cliente', this.editCliente);
form.append('Tipo', this.editTipo);
form.append('_csrf_token', this._getCsrfToken());

const RFC = this.editTipo === 'Crédito' ? this.editRfcCredito : this.editRfcDebito;
form.append('RFC', RFC);

form.append('CartaCredito_file', this.editFiles[0]);
form.append('ActaConstitutiva_file', this.editFiles[1]);
form.append('ComprobanteDom_file', this.editFiles[2]);
form.append('Identificacion_file', this.editFiles[3]);
form.append('ConstanciaRFC_file', this.editFiles[4]);
form.append('OpinionCumplimiento_file', this.editFiles[5]);
form.append('PoderNotarial_file', this.editFiles[6]);

axios.post('/departamento-operativo/clientes-lista/editar', form, {
headers: { 'X-CSRF-TOKEN': this._getCsrfToken() }
})
.then(function (r) {
self.guardandoEditar = false;
if (r.data.success) {
bootstrap.Modal.getInstance(document.getElementById('ModalEditar'))?.hide();
self.cargarDatos();
Notify.success('Cliente editado exitosamente');
} else {
Notify.error(r.data.message || 'Error al editar cliente');
}
})
.catch(function (e) {
self.guardandoEditar = false;
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexion al editar cliente');
Notify.error(msg);
console.error('[ERROR] editar:', e);
});
},

toggleEstado(idCliente, idTipo) {
const self = this;
const accion = idTipo == 1 ? 'deshabilitar' : 'habilitar';

Swal.fire({
title: (accion.charAt(0).toUpperCase() + accion.slice(1)) + ' cliente',
text: 'Desea ' + accion + ' el cliente seleccionado?',
icon: 'warning',
showCancelButton: true,
confirmButtonColor: '#d33',
confirmButtonText: 'Aceptar',
cancelButtonText: 'Cancelar',
}).then(function (result) {
if (!result.isConfirmed) return;

axios.post('/departamento-operativo/clientes-lista/toggle', {
id: idCliente,
idTipo: idTipo
})
.then(function (r) {
if (r.data.success) {
self.cargarDatos();
Notify.success('Cliente ' + (idTipo == 1 ? 'deshabilitado' : 'habilitado') + ' exitosamente');
} else {
Notify.error(r.data.message || 'Error al ' + accion + ' el cliente');
}
})
.catch(function (e) {
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexion');
Notify.error(msg);
console.error('[ERROR] toggle:', e);
});
});
},

}));
});
