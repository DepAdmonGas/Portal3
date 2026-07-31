document.addEventListener('DOMContentLoaded', function () {

var messageEl = document.getElementById('contratos-empty-message');
var contentEl = document.getElementById('contratos-content');

var table = null;
var permisos = {};

function showEmptyMessage() {
if (contentEl) contentEl.style.display = 'none';
if (messageEl) messageEl.style.display = '';
}

function showTable() {
if (contentEl) contentEl.style.display = '';
if (messageEl) messageEl.style.display = 'none';
}

function esModoGlobal() {
var sel = document.getElementById('module-station-selector-contratos');
if (sel) return !sel.value;
var container = document.getElementById('container');
return container ? container.dataset.multiestacion === 'true' && !(parseInt(container.dataset.idEstacion || '0') > 0) : false;
}

function ctActualizarToolOpciones() {
var global = esModoGlobal();
var btn = document.getElementById('contratos-nuevo-btn');
if (btn) btn.style.display = global ? 'none' : '';
if (table) {
table.column(1).visible(global);
}
}

function escStr(s) {
return (s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

function initTable() {
var dt = $('#tabla-contratos').DataTable({
processing: true,
serverSide: false,
autoWidth: false,
stateSave: true,
order: [[0, 'desc']],
ajax: {
url: '/departamento-operativo/contratos/data',
type: 'GET',
data: function (d) {
d.categoria = document.getElementById('container').dataset.categoria || 'Corporativo';
},
dataSrc: function (json) {
if (!json.success) return [];
permisos = json.permisos || {};
window.__contratosPermisos = permisos;
return json.data || [];
}
},
columns: [
{ title:'#', data: 'num', className: 'text-center', width: '40px' },
{ title:'Estación', data: 'estacion_nombre', className: 'text-center', visible: esModoGlobal() },
{ title:'Fecha', data: 'fecha_formateada', className: 'text-center' },
{ title:'Descripción del contrato', data: 'descripcion' },
{
title: '<a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>',
data: null,
className: 'text-center',
width: '50px',
orderable: false,
searchable: false,
render: function (d) {
var p = permisos;
var puedeEditar = p.id_puesto !== 6;
var puedeEliminar = p.id_puesto !== 6;
var archivo = d.archivo || '';
var desc = escStr(d.descripcion).replace(/S\/I/g, '').trim() || 'Contrato';
var html = '<div x-data="{}">'
+ '<div class="dropdown dropstart">'
+ '<a href="javascript:void(0)" data-bs-toggle="dropdown">'
+ '<i class="ti ti-dots-vertical fs-5"></i>'
+ '</a>'
+ '<div class="dropdown-menu pointer">'
+ '<a class="dropdown-item" @click="$dispatch(\'contrato:detalle\', { id: ' + d.id + ' })">'
+ '<i class="ti ti-eye me-1"></i> Detalle</a>';
if (archivo) {
html += '<a class="dropdown-item" @click="$dispatch(\'contrato:descargar\', { tipo: \'contratos\', archivo: \'' + escStr(archivo) + '\' })">'
+ '<i class="ti ti-download me-1"></i> Descargar</a>';
}
if (puedeEditar) {
html += '<a class="dropdown-item" @click="$dispatch(\'contrato:editar\', { id: ' + d.id + ' })">'
+ '<i class="ti ti-pencil me-1"></i> Editar</a>';
}
if (puedeEliminar) {
html += '<a class="dropdown-item" @click="$dispatch(\'contrato:eliminar\', { id: ' + d.id + ', descripcion: \'' + desc + '\' })">'
+ '<i class="ti ti-trash me-1"></i> Eliminar</a>';
}
html += '</div></div></div>';
return html;
}
}
],
columnDefs: [
{ targets: '_all', render: function (d) { return d || ''; } }
],
drawCallback: function () {
if (window.Alpine) {
Alpine.initTree(document.querySelector('#tabla-contratos'));
}
}
});

dt.on('xhr', function (e, settings, json) {
if (!json || !json.success) return;
window.__contratosGlobal = !!json.global;
ctActualizarToolOpciones();
});

return dt;
}

function destroyTable() {
if (table) {
table.destroy();
table = null;
}
}

function getOrCreateTable() {
if (!table) {
table = initTable();
}
return table;
}

if (messageEl && messageEl.style.display !== 'none') {
showEmptyMessage();
} else {
showTable();
getOrCreateTable();
}

ctActualizarToolOpciones();

var selContratos = document.getElementById('module-station-selector-contratos');
if (selContratos) {
selContratos.addEventListener('change', ctActualizarToolOpciones);
}

ModuleStationSelector.init('contratos', {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null && !esModoGlobal()) {
ms.hideBadge();
showEmptyMessage();
return;
}
showTable();
if (table) {
table.ajax.reload(null, false);
} else {
getOrCreateTable();
}
ctActualizarToolOpciones();
}
});

});

document.addEventListener('alpine:init', function () {
Alpine.data('contratosComponent', function () {
return {
modalTitle: '',
modo: 'detalle',
detalle: {},
form: {
fecha: '',
descripcion: '',
objeto: '',
proveedor: '',
vencimiento: '',
firmas: '',
comentario: '',
},
editId: 0,
categoria: '',
contexto: '',
puedeEditar: false,
puedeEliminar: false,

init: function () {
this.categoria = this.$el.dataset.categoria || 'Corporativo';
this.contexto = this.$el.dataset.contexto || 'corporativo';

var self = this;
this.$el.addEventListener('show.bs.modal', function () {
if (self.modo === 'detalle' && !self.editId) {
self.modo = 'agregar';
self.modalTitle = 'Agregar contrato';
self.editId = 0;
self.form = { fecha: '', descripcion: '', objeto: '', proveedor: '', vencimiento: '', firmas: '', comentario: '' };
}
});
this.$el.addEventListener('hidden.bs.modal', function () {
self.modo = 'detalle';
self.editId = 0;
self.detalle = {};
});
},

abrirModalDetalle: function (id) {
this.modo = 'detalle';
this.modalTitle = 'Detalle del contrato';
this.editId = id;
var self = this;
axios.get('/departamento-operativo/contratos/detalle', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
self.detalle = res.data.data;
var p = window.__contratosPermisos || {};
self.puedeEditar = p.id_puesto !== 6;
self.puedeEliminar = p.id_puesto !== 6;
var modal = new bootstrap.Modal(document.getElementById('modal-contrato'));
modal.show();
}
});
},

abrirModalAgregar: function () {
this.modo = 'agregar';
this.modalTitle = 'Agregar contrato';
this.editId = 0;
this.form = { fecha: '', descripcion: '', objeto: '', proveedor: '', vencimiento: '', firmas: '', comentario: '' };
var modal = new bootstrap.Modal(document.getElementById('modal-contrato'));
modal.show();
},

abrirModalEditar: function (id) {
if (!id && this.editId) id = this.editId;
if (!id) return;
this.modo = 'editar';
this.modalTitle = 'Editar contrato';
this.editId = id;
var self = this;
axios.get('/departamento-operativo/contratos/detalle', {
params: { id: id }
}).then(function (res) {
if (res.data.success) {
var d = res.data.data;
self.form = {
fecha: d.fecha || '',
descripcion: d.descripcion === 'S/I' ? '' : d.descripcion,
objeto: d.objeto === 'S/I' ? '' : d.objeto,
proveedor: d.proveedor === 'S/I' ? '' : d.proveedor,
vencimiento: d.vencimiento_raw || '',
firmas: d.firmas === 'S/I' ? '' : d.firmas,
comentario: d.comentario === 'Sin comentarios.' ? '' : d.comentario,
};
var modal = new bootstrap.Modal(document.getElementById('modal-contrato'));
modal.show();
}
});
},

cerrarModal: function () {
var modal = bootstrap.Modal.getInstance(document.getElementById('modal-contrato'));
if (modal) modal.hide();
},

guardarContrato: function () {
if (!this.form.fecha || !this.form.descripcion) {
this.notify('error', 'Fecha y descripción son requeridos');
return;
}
if (this.loading) return;
this.loading = true;
var self = this;
var fd = new FormData();
fd.append('fecha', this.form.fecha);
fd.append('descripcion', this.form.descripcion);
fd.append('objeto', this.form.objeto || '');
fd.append('proveedor', this.form.proveedor || '');
fd.append('vencimiento', this.form.vencimiento || '');
fd.append('firmas', this.form.firmas || '');
fd.append('comentario', this.form.comentario || '');
fd.append('categoria', this.categoria);

var fileInput = this.$refs.archivoInput;
if (fileInput && fileInput.files && fileInput.files[0]) {
fd.append('archivo', fileInput.files[0]);
}

if (this.modo === 'editar') {
fd.append('id', this.editId);
}

var url = this.modo === 'editar'
? '/departamento-operativo/contratos/editar'
: '/departamento-operativo/contratos/guardar';

axios.post(url, fd, {
headers: { 'Content-Type': 'multipart/form-data' }
}).then(function (res) {
if (res.data.success) {
self.notify('success', 'Contrato ' + (self.modo === 'editar' ? 'editado' : 'agregado') + ' exitosamente.');
self.cerrarModal();
if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tabla-contratos')) {
$('#tabla-contratos').DataTable().ajax.reload(null, false);
}
} else {
self.notify('error', res.data.message || 'Error al guardar');
}
}).catch(function () {
self.notify('error', 'Error de conexión');
}).then(function () {
self.loading = false;
});
},

async eliminarContrato(id, descripcion) {
var res = await this.deleteAction({
url: '/departamento-operativo/contratos/eliminar',
id: id,
name: descripcion || 'Contrato',
table: '#tabla-contratos'
});
if (res && res.success) {
this.cerrarModal();
}
},

notify: function (tipo, mensaje) {
if (typeof Notify !== 'undefined') {
if (tipo === 'success') Notify.success(mensaje);
else Notify.error(mensaje);
} else {
alert(mensaje);
}
}
};
});
});
