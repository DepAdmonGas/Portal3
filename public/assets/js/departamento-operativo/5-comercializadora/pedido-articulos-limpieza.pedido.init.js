document.addEventListener('alpine:init', () => {

Alpine.data('pedidoLimpiezaPedidoComponent', () => ({
...window.PedidoLimpiezaForm(),

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esEncargado: false,

pedidoActual: null,
catalogos: [],
nuevoItem: { id_producto: '', otro_producto: '', unidad: '', piezas: 1 },
usarOtroProducto: false,
finalizando: false,
entregando: false,
guardandoProducto: false,
reintentosFirma: 0,

BASE: '/departamento-operativo/comercializadora/pedido-articulos-limpieza',

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

window.pedidoLimpiezaPedidoComponentInstance = this;

this.cargarCatalogos();
this.initSignaturePad();
},

initSignaturePad() {
this.$nextTick(() => {
requestAnimationFrame(() => {
if (Number(this.pedidoActual?.status) !== 0 || !(this.esEncargado || this.puedeEditar)) return;
if (window.SignaturePad == null) { console.error('SignaturePad no disponible'); return; }
const canvas = document.querySelector('#signature-pad canvas');
if (!canvas) return;
if (canvas.getBoundingClientRect().width <= 0 || canvas.getBoundingClientRect().height <= 0) {
if (this.reintentosFirma < 10) {
this.reintentosFirma++;
setTimeout(() => this.initSignaturePad(), 100);
}
return;
}
this.reintentosFirma = 0;
if (this.signaturePad) this.signaturePad.off();
this.signaturePad = new SignaturePad(canvas, {
backgroundColor: 'rgb(255, 255, 255)', minWidth: 1, maxWidth: 2.5, penColor: '#000000',
onEnd: () => { this.firmaVacia = this.signaturePad.isEmpty(); if (!this.firmaVacia) this.clearField('firma'); }
});
this.signaturePad.addEventListener('endStroke', () => {
this.firmaVacia = this.signaturePad.isEmpty();
if (!this.firmaVacia) this.clearField('firma');
});
this._redimensionarCanvas();
this.resizeFirma = () => this._redimensionarCanvas();
window.addEventListener('resize', this.resizeFirma);
this.firmaVacia = this.signaturePad.isEmpty();
});
});
},

_redimensionarCanvas() {
const wrapper = document.getElementById('signature-pad');
const canvas = wrapper?.querySelector('canvas');
if (!canvas) return;
const width = canvas.getBoundingClientRect().width;
const height = canvas.getBoundingClientRect().height;
if (width <= 0 || height <= 0) return;
const data = this.signaturePad && !this.signaturePad.isEmpty() ? this.signaturePad.toData() : null;
const ratio = Math.max(window.devicePixelRatio || 1, 1);
canvas.width = Math.max(1, Math.round(width * ratio));
canvas.height = Math.max(1, Math.round(height * ratio));
canvas.getContext('2d').scale(ratio, ratio);
if (this.signaturePad && data) this.signaturePad.fromData(data);
if (this.signaturePad) this.firmaVacia = this.signaturePad.isEmpty();
},

limpiarFirma() {
if (this.signaturePad) this.signaturePad.clear();
this.firmaVacia = true;
},

destroy() {
if (this.resizeFirma) window.removeEventListener('resize', this.resizeFirma);
if (this.signaturePad) this.signaturePad.off();
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
this.nuevoItem = { id_producto: '', otro_producto: '', unidad: '', piezas: 1 };
this.usarOtroProducto = false;
this._inicializarSelectProductoPedido();
const m = document.getElementById('modalAgregarProductoPedido');
bootstrap.Modal.getOrCreateInstance(m).show();
},

syncUsarOtroProducto() {
if (this.usarOtroProducto) this.clearField('producto');
const sel = this.$refs.productoPedidoSelect;
if (!sel || !window.jQuery || !jQuery.fn.select2) return;
const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.prop('disabled', this.usarOtroProducto).trigger('change');
if (this.usarOtroProducto) $sel.val(null).trigger('change');
}
if (this.usarOtroProducto) {
this.nuevoItem.id_producto = '';
}
},

_inicializarSelectProductoPedido() {
const sel = this.$refs.productoPedidoSelect;
if (!sel) return;

sel.innerHTML = '<option value="">Selecciona un producto</option>';
(this.catalogos || []).forEach((p) => {
const opt = document.createElement('option');
opt.value = p.id;
opt.textContent = p.producto + (p.unidad ? ' (' + p.unidad + ')' : '');
sel.appendChild(opt);
});

if (!window.jQuery || !jQuery.fn.select2) return;

const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.off('change.limpieza').select2('destroy').removeAttr('data-select2-id');
}

const $modalParent = jQuery(sel).closest('.modal');
$sel.select2({
dropdownParent: $modalParent.length ? $modalParent : jQuery(sel).parent(),
width: '100%'
});
$sel.val('').trigger('change');
$sel.prop('disabled', false).trigger('change');
$sel.on('change.limpieza', () => {
this.nuevoItem.id_producto = $sel.val() ? String($sel.val()) : '';
});
if (this.usarOtroProducto) {
$sel.val(null).trigger('change');
$sel.prop('disabled', true).trigger('change');
}
},

async agregarProducto() {
if (this.guardandoProducto) return;

const idProducto = this.usarOtroProducto ? '' : this.nuevoItem.id_producto;
const otroProducto = (this.nuevoItem.otro_producto || '').trim();
const unidad = (this.nuevoItem.unidad || '').trim();
const piezas = String(this.nuevoItem.piezas ?? '').replace(/,/g, '');
if (!this.validateFields({ producto: !!(idProducto || otroProducto), otro: !this.usarOtroProducto || !!unidad,
piezas: this.positiveQuantity(piezas) })) return;

this.guardandoProducto = true;
try {
const fd = new FormData();
fd.append('id_pedido', this.pedidoActual.id);
fd.append('id_producto', idProducto);
fd.append('otro_producto', otroProducto);
fd.append('unidad', unidad);
fd.append('piezas', piezas);
const resp = await axios.post(this.BASE + '/agregar-producto', fd);
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarProductoPedido');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.clearField('productos');
this.nuevoItem = { id_producto: '', otro_producto: '', unidad: '', piezas: 1 };
this.usarOtroProducto = false;
this._inicializarSelectProductoPedido();
if (window.Notify) Notify.success(json.message || 'Producto agregado correctamente');
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
if (!this.validateFields({ ['piezas_' + id]: this.positiveQuantity(piezas) })) {
await this.cargarPedido();
return;
}
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

const tieneDetalle = !!(this.pedidoActual.detalle && this.pedidoActual.detalle.length);
this.firmaVacia = !this.signaturePad || this.signaturePad.isEmpty();
if (!this.validateFields({ productos: tieneDetalle, firma: !this.firmaVacia })) return;

const confirm = await Swal.fire({
title: '¿Finalizar pedido?',
text: 'El pedido pasará a espera de VoBo',
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
firma_base64: this.signaturePad.toDataURL('image/png')
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
if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
history.back();
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

async entregarPedido() {
if (this.entregando) return;

if (this.pedidoActual.status !== 2) {
if (window.Notify) Notify.error('El pedido debe estar firmado para poder entregarlo');
return;
}

const result = await Swal.fire({
title: '¿Entregar pedido?',
text: 'Los artículos del pedido se sumarán al inventario de la estación',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, entregar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#3085d6'
});
if (!result.isConfirmed) return;

this.entregando = true;
try {
const resp = await axios.post(this.BASE + '/entregar', { id: this.pedidoActual.id });
const json = resp.data;
if (json.success) {
Swal.fire({
icon: 'success',
title: 'Pedido entregado',
text: json.message || 'Pedido entregado e inventario actualizado',
timer: 2000,
showConfirmButton: false
}).then(() => {
if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
history.back();
} else {
window.location.href = this.BASE;
}
});
} else {
if (window.Notify) Notify.error(json.message || 'Error al entregar el pedido');
}
} catch (e) {
console.error('Error entregando pedido:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al entregar el pedido');
} finally {
this.entregando = false;
}
},

}));

});
