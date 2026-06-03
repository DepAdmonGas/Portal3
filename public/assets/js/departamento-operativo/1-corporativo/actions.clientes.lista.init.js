
document.addEventListener('alpine:init', () => {

Alpine.data('clientesListaComponent', () => ({
loading: true,
credito: [],
debito: [],
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
this.cargarDatos();

this.$watch('formTipo', () => {
this.formRfc = '';
});
},

_renderDocIcon(val) {
return val
? '<div x-data="actions()"><a href="javascript:void(0)" @click="download(\'comprobantes-clientes\', \'' + val + '\')"><i class="ti ti-file-check text-success fs-6"></i></a></div>'
: '<i class="ti ti-file-off text-muted fs-6"></i>';
},

_renderStatus(val) {
const e = Number(val);
if (e === 1) return '<span class="badge rounded-pill bg-success">Habilitado</span>';
return '<span class="badge rounded-pill bg-danger">Deshabilitado</span>';
},

_renderToggle(row) {
let s = '<div class="dropdown dropstart">';
s += '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>';
s += '<ul class="dropdown-menu">';
s += '<li class="pointer"><a class="dropdown-item d-flex align-items-center gap-3 btn-edit-cliente" data-id="' + row.id + '"><i class="fs-4 ti ti-edit"></i>Editar</a></li>';
if (row.estado == 1) {
s += '<li><hr class="dropdown-divider"></li>';
s += '<li class="pointer"><a class="dropdown-item d-flex align-items-center gap-3 btn-toggle-cliente" data-id="' + row.id + '" data-idtipo="1"><i class="fs-4 ti ti-toggle-left"></i>Deshabilitar</a></li>';
} else {
s += '<li><hr class="dropdown-divider"></li>';
s += '<li class="pointer"><a class="dropdown-item d-flex align-items-center gap-3 btn-toggle-cliente" data-id="' + row.id + '" data-idtipo="0"><i class="fs-4 ti ti-toggle-right"></i>Habilitar</a></li>';
}
s += '</ul></div>';
return s;
},

_initDataTables() {
const self = this;

const cCols = [
{ data: null, className: 'text-center align-middle fw-normal', render: function (d, t, r, m) { return m.row + 1; } },
{ data: 'cuenta', className: 'align-middle text-center' },
{ data: 'cliente', className: 'align-middle' },
{ data: 'doc_rfc', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_cc', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_ac', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_cd', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_io', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_oc', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_np', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'estado', width: '100px', className: 'text-center align-middle', render: function (d) { return self._renderStatus(d); } },
{ data: null, width: '1%', className: 'text-center align-middle td-small', orderable: false, searchable: false, render: function (d, t, row) { return self._renderToggle(row); } },
];

const dCols = [
{ data: null, className: 'text-center align-middle fw-normal', render: function (d, t, r, m) { return m.row + 1; } },
{ data: 'cuenta', className: 'align-middle text-center' },
{ data: 'cliente', className: 'align-middle' },
{ data: 'doc_cd', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_io', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'doc_rfc', className: 'align-middle text-center', render: function (d) { return self._renderDocIcon(d); }, orderable: false, searchable: false },
{ data: 'estado', width: '100px', className: 'text-center align-middle', render: function (d) { return self._renderStatus(d); } },
{ data: null, width: '1%', className: 'text-center align-middle td-small', orderable: false, searchable: false, render: function (d, t, row) { return self._renderToggle(row); } },
];

['#tablaCredito', '#tablaDebito'].forEach(function(id) {
if ($.fn.DataTable && $.fn.DataTable.isDataTable(id)) {
$(id).DataTable().destroy();
}
});

const cols = { '#tablaCredito': cCols, '#tablaDebito': dCols };
const data = { '#tablaCredito': this.credito, '#tablaDebito': this.debito };

Object.keys(cols).forEach(function(id) {
$(id).DataTable({
data: data[id],
columns: cols[id],
autoWidth: false,
stateSave: false,
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
pageLength: 10,
order: [[1, 'asc']],
destroy: true,
createdRow: function (row, rowData) {
if (rowData.estado == 0) $(row).css('background-color', '#ffb6af');
},
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(this);
}
},
});
});

$('#tablaCredito tbody, #tablaDebito tbody')
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
if (el) el.style.borderColor = '#dc3545';
},

_limpiarErrores() {
['Cuenta', 'Cliente', 'Tipo', 'EditCuenta', 'EditCliente'].forEach(function (id) {
const el = document.getElementById(id);
if (el) el.style.borderColor = '';
});
},

abrirModalCrear() {
this.formCuenta = '';
this.formCliente = '';
this.formTipo = '';
this.formRfc = '';
this.files = [null, null, null, null, null, null, null];
this._limpiarErrores();
const modal = new bootstrap.Modal(document.getElementById('Modal'));
modal.show();
},

guardarCrear() {
this._limpiarErrores();
let valido = true;

const Cuenta = this.formCuenta.trim();
const Cliente = this.formCliente.trim();
const Tipo = this.formTipo;

if (!Cuenta) { this._marcarError('Cuenta'); valido = false; }
if (!Cliente) { this._marcarError('Cliente'); valido = false; }
if (!Tipo) { this._marcarError('Tipo'); valido = false; }
if (!valido) return;

const self = this;
const form = new FormData();
form.append('idEstacion', this.idEstacion);
form.append('Cuenta', this.formCuenta);
form.append('Cliente', this.formCliente);
form.append('Tipo', this.formTipo);
form.append('RFC', this.formRfc);

form.append('CartaCredito_file', this.files[0]);
form.append('ActaConstitutiva_file', this.files[1]);
form.append('ComprobanteDom_file', this.files[2]);
form.append('Identificacion_file', this.files[3]);
form.append('ConstanciaRFC_file', this.files[4]);
form.append('PoderNotarial_file', this.files[5]);
form.append('OpinionCumplimiento_file', this.files[6]);

axios.post('/departamento-operativo/clientes-lista/crear', form, {
headers: { 'X-CSRF-TOKEN': this._getCsrfToken() }
})
.then(function (r) {
if (r.data.success) {
bootstrap.Modal.getInstance(document.getElementById('Modal'))?.hide();
self.formCuenta = '';
self.formCliente = '';
self.formTipo = '';
self.formRfc = '';
self.files = [null, null, null, null, null, null, null];
self.cargarDatos();
Notify.success('Cliente agregado exitosamente');
} else {
Notify.error(r.data.message || 'Error al agregar cliente');
}
})
.catch(function (e) {
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexión al agregar cliente');
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

const self = this;
const form = new FormData();
form.append('idCliente', this.editId);
form.append('Cuenta', this.editCuenta);
form.append('Cliente', this.editCliente);
form.append('Tipo', this.editTipo);

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
if (r.data.success) {
bootstrap.Modal.getInstance(document.getElementById('ModalEditar'))?.hide();
self.cargarDatos();
Notify.success('Cliente editado exitosamente');
} else {
Notify.error(r.data.message || 'Error al editar cliente');
}
})
.catch(function (e) {
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexión al editar cliente');
Notify.error(msg);
console.error('[ERROR] editar:', e);
});
},

toggleEstado(idCliente, idTipo) {
const self = this;
const accion = idTipo == 1 ? 'deshabilitar' : 'habilitar';

Swal.fire({
title: (accion.charAt(0).toUpperCase() + accion.slice(1)) + ' cliente',
text: '¿Desea ' + accion + ' el cliente seleccionado?',
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
const msg = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Error de conexión');
Notify.error(msg);
console.error('[ERROR] toggle:', e);
});
});
},

}));
});

