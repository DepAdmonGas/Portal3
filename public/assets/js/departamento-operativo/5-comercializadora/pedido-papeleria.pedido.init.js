document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPapeleriaPedidoComponent', () => ({
...window.PedidoPapeleriaForm(),

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esEncargado: false,

pedidoActual: { id: 0, detalle: [], firmas: {}, status: 0 },
catalogos: [],
nuevoItem: { id_producto: '', otro_producto: '', piezas: 1 },
finalizando: false,
guardandoProducto: false,
signaturePad: null,
firmaVacia: true,
resizeFirma: null,

BASE: '/departamento-operativo/comercializadora/pedido-papeleria',
BASE_FIRMA: '/departamento-operativo/comercializadora/pedido-papeleria-firma',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeFirmar = c.dataset.puedeFirmar === 'true';
this.esEncargado = c.dataset.esEncargado === 'true';

if (c.dataset.pedido) {
try { this.pedidoActual = JSON.parse(c.dataset.pedido); } catch (e) { console.error('Error parseando pedido:', e); }
}

window.pedidoPapeleriaPedidoComponentInstance = this;

this.cargarCatalogos();
this.initSignaturePad();
},

async cargarPedido() {
try {
const resp = await axios.get(this.BASE + '/detalle?id=' + this.pedidoActual.id);
const json = resp.data;
if (json.success && json.pedido) {
this.pedidoActual = json.pedido;
}
} catch (e) {
console.error('Error cargando pedido:', e);
}
},

async cargarCatalogos() {
try {
const resp = await axios.get(this.BASE + '/catalogos?solo_activos=1');
const json = resp.data;
if (json.success) this.catalogos = json.catalogos || [];
} catch (e) {
console.error('Error cargando catálogos:', e);
}
},

initSignaturePad() {
this.$nextTick(() => {
if (this.pedidoActual.status !== 0 || !(this.esEncargado || this.puedeEditar)) return;
if (window.SignaturePad == null) {
console.error('SignaturePad no disponible');
return;
}
var w = document.getElementById('signature-pad');
var cv = w ? w.querySelector('canvas') : null;
if (!cv) {
this.firmaVacia = true;
return;
}
this.signaturePad = new SignaturePad(cv, {
backgroundColor: 'rgb(255, 255, 255)',
minWidth: 1,
maxWidth: 2.5,
penColor: '#000000',
onEnd: () => { this.firmaVacia = this.signaturePad.isEmpty(); }
});
this.signaturePad.addEventListener('endStroke', () => { this.firmaVacia = this.signaturePad.isEmpty(); if (!this.firmaVacia) this.clearField('firma'); });
this._redimensionarCanvas();
this.resizeFirma = () => this._redimensionarCanvas();
window.addEventListener('resize', this.resizeFirma);
this.firmaVacia = this.signaturePad.isEmpty();
});
},

_redimensionarCanvas() {
var w = document.getElementById('signature-pad');
var cv = w ? w.querySelector('canvas') : null;
if (!cv) return;
var data = null;
if (this.signaturePad && !this.signaturePad.isEmpty()) {
data = this.signaturePad.toData();
}
var r = Math.max(window.devicePixelRatio || 1, 1);
cv.width = cv.offsetWidth * r;
cv.height = cv.offsetHeight * r;
cv.getContext('2d').scale(r, r);
if (this.signaturePad) {
if (data) this.signaturePad.fromData(data);
this.firmaVacia = this.signaturePad.isEmpty();
}
},

limpiarFirma() {
if (this.signaturePad) this.signaturePad.clear();
this.firmaVacia = true;
},

destroy() {
if (this.resizeFirma) window.removeEventListener('resize', this.resizeFirma);
if (this.signaturePad) this.signaturePad.off();
},

firmaUrl(id) {
return this.BASE_FIRMA + '/' + id;
},

statusBadgeClass(status) {
if (status === 0) return 'bg-danger';
if (status === 1) return 'bg-warning-subtle text-warning-emphasis';
if (status === 2) return 'bg-success-subtle text-success-emphasis';
return 'bg-secondary';
},

async modalAgregarProducto() {
this.validationErrors = {};
if (!this.catalogos || !this.catalogos.length) {
await this.cargarCatalogos();
}
this.nuevoItem = { id_producto: '', otro_producto: '', piezas: 1 };
this._inicializarSelectProductoPedido();
this._actualizarEstadoOtroProducto();
const m = document.getElementById('modalAgregarProductoPedido');
bootstrap.Modal.getOrCreateInstance(m).show();
},

get productoSeleccionado() {
return !!(this.nuevoItem && this.nuevoItem.id_producto);
},

syncOtroProducto() {
const tieneOtro = (this.nuevoItem.otro_producto || '').trim() !== '';
if (tieneOtro) this.clearField('producto');
if (tieneOtro) {
this.clearField('producto');
this.clearField('producto');
this.nuevoItem.id_producto = '';
}
this._actualizarEstadoOtroProducto();
this.$nextTick(() => {
const sel = this.$refs.productoPedidoSelect;
if (!sel || !window.jQuery || !jQuery.fn.select2) return;
const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.prop('disabled', tieneOtro).trigger('change');
if (tieneOtro) $sel.val(null).trigger('change');
}
});
},

_inicializarSelectProductoPedido() {
const sel = this.$refs.productoPedidoSelect;
if (!sel) return;

sel.innerHTML = '<option value="">Selecciona un producto</option>';
(this.catalogos || []).forEach((p) => {
const opt = document.createElement('option');
opt.value = p.id;
opt.textContent = p.producto;
sel.appendChild(opt);
});

if (!window.jQuery || !jQuery.fn.select2) return;

const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.off('change.papeleria').select2('destroy').removeAttr('data-select2-id');
}

const $modalParentPapeleria = jQuery(sel).closest('.modal');
$sel.select2({
dropdownParent: $modalParentPapeleria.length ? $modalParentPapeleria : jQuery(sel).parent(),
width: '100%'
});
$sel.val('').trigger('change');
$sel.prop('disabled', false).trigger('change');
$sel.on('change.papeleria', () => {
this.nuevoItem.id_producto = $sel.val() ? String($sel.val()) : '';
if (this.nuevoItem.id_producto) {
this.clearField('producto');
this.clearField('otro');
this.nuevoItem.otro_producto = '';
}
this._actualizarEstadoOtroProducto();
});
},

_actualizarEstadoOtroProducto() {
const otroInput = this.$refs.otroProductoInput;
if (!otroInput) return;
const tieneProducto = !!(this.nuevoItem && this.nuevoItem.id_producto);
otroInput.disabled = tieneProducto;
},

async agregarProducto() {
if (this.guardandoProducto) return;

const idProducto = this.nuevoItem.id_producto;
const otroProducto = (this.nuevoItem.otro_producto || '').trim();
const piezas = String(this.nuevoItem.piezas ?? '').replace(/,/g, '');

if (!this.validateFields({ producto: !!(idProducto || otroProducto), piezas: this.positiveQuantity(piezas) })) return;

this.guardandoProducto = true;
try {
const fd = new FormData();
fd.append('id_pedido', this.pedidoActual.id);
fd.append('id_producto', idProducto);
fd.append('otro_producto', otroProducto);
fd.append('piezas', piezas);
const resp = await axios.post(this.BASE + '/agregar-producto', fd);
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarProductoPedido');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.nuevoItem = { id_producto: '', otro_producto: '', piezas: 1 };
this._inicializarSelectProductoPedido();
if (window.Notify) Notify.success(json.message || 'Producto agregado correctamente');
this.clearField('productos');
await this.cargarPedido();
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el producto');
}
} catch (e) {
console.error('Error agregando producto:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al agregar el producto');
} finally {
this.guardandoProducto = false;
}
},

async editarPiezas(id, valor) {
const piezas = String(valor ?? '').replace(/,/g, '');
if (!this.validateFields({ ['piezas_' + id]: this.positiveQuantity(piezas) })) return;
try {
const fd = new FormData();
fd.append('id', id);
fd.append('piezas', piezas);
const resp = await axios.post(this.BASE + '/editar-piezas', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Cantidad actualizada correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al actualizar la cantidad');
}
await this.cargarPedido();
} catch (e) {
console.error('Error editando piezas:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al actualizar la cantidad');
}
},

async eliminarItem(id) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El producto será eliminado del pedido',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;

try {
const resp = await axios.post(this.BASE + '/eliminar-item', { id: id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Producto eliminado correctamente');
await this.cargarPedido();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el producto');
}
} catch (e) {
console.error('Error eliminando item:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el producto');
}
},

async finalizarPedido() {
if (this.finalizando) return;

if (!this.validateFields({
productos: !!this.pedidoActual.detalle?.length && this.pedidoActual.detalle.every(it => this.positiveQuantity(it.piezas)),
firma: !!this.signaturePad && !this.signaturePad.isEmpty()
})) return;
const base64 = this.signaturePad.toDataURL('image/png');

const confirm = await Swal.fire({
title: '¿Finalizar pedido?',
text: 'El pedido pasará a espera de VoBo y se registrará la firma del encargado',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar'
});
if (!confirm.isConfirmed) return;

this.finalizando = true;
try {
const resp = await axios.post(this.BASE + '/finalizar', {
id: this.pedidoActual.id,
firma: base64
});
const json = resp.data;
if (json.success) {
Swal.fire({
icon: 'success',
title: 'Pedido finalizado',
text: json.message || 'Pedido finalizado correctamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
if (this.pedidoActual.status >= 1) {
window.location.href = this.BASE + '/' + this.pedidoActual.id;
} else {
window.location.href = this.BASE;
}
});
} else {
if (window.Notify) Notify.error(json.message || 'Error al finalizar el pedido');
}
} catch (e) {
console.error('Error finalizando pedido:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al finalizar el pedido');
} finally {
this.finalizando = false;
}
},

}));

});