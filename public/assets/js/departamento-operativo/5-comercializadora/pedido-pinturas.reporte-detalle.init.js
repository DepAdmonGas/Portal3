document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasReporteDetalleComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,

reporteActual: { id: 0, id_estacion: 0, status: 0, status_label: 'Desconocido', fecha_hora: '', detalle_items: [] },
reporteForm: { detalle: '' },
reporteItem: { id_producto: 0, unidad: 1, observaciones: '' },
inventarioDisponible: [],
guardandoReporte: false,
guardandoReporteItem: false,
fechaAutosaveTimer: null,
guardadoPendiente: false,
formularioSucio: false,
formularioInicializado: false,

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';

if (c.dataset.reporte) {
try { this.reporteActual = JSON.parse(c.dataset.reporte); } catch (e) { console.error('Error parseando reporte:', e); }
}

this.reporteForm = {
fecha: this.reporteActual.fecha_iso || '',
hora: this.reporteActual.hora_iso || '',
detalle: this.reporteActual.detalle || ''
};

window.pedidoPinturasReporteDetalleComponentInstance = this;

this.cargarReporte();
this.cargarInventarioDisponible();
},

async cargarReporte() {
if (this.guardandoReporte) return;
try {
if (this.formularioSucio) {
await this.guardarReporteDatos(true);
}
const resp = await axios.get(this.BASE + '/reporte/data?id=' + this.reporteActual.id);
const json = resp.data;
if (json.success && json.reporte) {
this.reporteActual = json.reporte;
if (!this.formularioInicializado && !this.formularioSucio) {
this._sincronizarFormulario();
this.formularioInicializado = true;
}
}
} catch (e) {
console.error('Error cargando reporte:', e);
}
},

_sincronizarFormulario() {
if (this.formularioSucio || this.guardandoReporte) return;
this.reporteForm.fecha = this.reporteActual.fecha_iso || '';
this.reporteForm.hora = this.reporteActual.hora_iso || '';
this.reporteForm.detalle = (this.reporteActual.detalle && this.reporteActual.detalle !== 'S/I') ? this.reporteActual.detalle : '';
},

programarAutosave() {
this.formularioSucio = true;
if (this.fechaAutosaveTimer) clearTimeout(this.fechaAutosaveTimer);
if (this.guardandoReporte) {
this.guardadoPendiente = true;
return;
}
this.fechaAutosaveTimer = setTimeout(() => {
this.fechaAutosaveTimer = null;
this.guardarReporteDatos(true);
}, 1200);
},

async guardarReporteDatos(silencioso = false) {
if (this.guardandoReporte) {
this.guardadoPendiente = true;
return;
}

this.guardandoReporte = true;
try {
const resp = await axios.post(this.BASE + '/guardar-reporte-datos', {
id: this.reporteActual.id,
fecha: this.reporteForm.fecha || '',
hora: this.reporteForm.hora || '',
detalle: this.reporteForm.detalle || ''
});
const json = resp.data;
if (json.success) {
this.guardadoPendiente = false;
this.formularioSucio = false;
if (this.fechaAutosaveTimer) {
clearTimeout(this.fechaAutosaveTimer);
this.fechaAutosaveTimer = null;
}
this.reporteActual.fecha_iso = this.reporteForm.fecha || this.reporteActual.fecha_iso;
this.reporteActual.hora_iso = this.reporteForm.hora || this.reporteActual.hora_iso;
this.reporteActual.detalle = this.reporteForm.detalle || '';
if (!silencioso) {
if (window.Notify) Notify.success(json.message || 'Reporte guardado correctamente');
await this.cargarReporte();
}
return true;
} else {
if (window.Notify) Notify.error(json.message || 'Error al guardar el reporte');
return false;
}
} catch (e) {
console.error('Error guardando reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al guardar el reporte');
return false;
} finally {
this.guardandoReporte = false;
if (this.guardadoPendiente) {
this.guardadoPendiente = false;
this.programarAutosave();
}
}
},

async modalAgregarProducto() {
this.reporteItem = { id_producto: 0, unidad: 1, observaciones: '' };
await this.cargarInventarioDisponible();
this._inicializarSelectProducto(null);
const m = document.getElementById('modalAgregarProductoReporte');
bootstrap.Modal.getOrCreateInstance(m).show();
},

async cargarInventarioDisponible() {
try {
const resp = await axios.get(this.BASE + '/inventario/data?id_estacion=' + (this.reporteActual.id_estacion || 0));
const json = resp.data;
this.inventarioDisponible = (json && json.success) ? (json.inventario || []) : [];
} catch (e) {
console.error('Error cargando inventario disponible:', e);
}
},

_inicializarSelectProducto(valorActual) {
const sel = this.$refs.productoSelect;
if (!sel) return;

sel.innerHTML = '<option value="0">Selecciona del inventario</option>';
(this.inventarioDisponible || []).forEach((p) => {
const opt = document.createElement('option');
opt.value = p.id_producto;
opt.textContent = p.producto + ' (disponible: ' + p.piezas + ')';
sel.appendChild(opt);
});

if (!window.jQuery || !jQuery.fn.select2) return;

const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.off('change.pinturas').select2('destroy').removeAttr('data-select2-id');
}

$sel.select2({
dropdownParent: this.$refs.productoWrapper || jQuery(sel).parent(),
width: '100%'
});

if (valorActual !== null && valorActual !== undefined) {
$sel.val(String(valorActual));
}

$sel.on('change.pinturas', () => {
this.reporteItem.id_producto = parseInt($sel.val() || '0', 10);
});
},

reporteStatusBadgeClass(status) {
if (status === 0) return 'bg-danger text-white';
if (status === 1) return 'bg-success text-white';
return 'bg-secondary text-white';
},

async agregarProductoReporte() {
if (this.guardandoReporteItem) return;

if (!this.reporteItem.id_producto) {
if (window.Notify) Notify.error('Selecciona un producto del inventario');
return;
}
if (!this.reporteItem.unidad || this.reporteItem.unidad <= 0) {
if (window.Notify) Notify.error('Ingresa un número de piezas válido');
return;
}

this.guardandoReporteItem = true;
try {
const resp = await axios.post(this.BASE + '/agregar-producto-reporte', {
id_reporte: this.reporteActual.id,
id_estacion: this.reporteActual.id_estacion,
id_producto: this.reporteItem.id_producto,
unidad: this.reporteItem.unidad,
observaciones: this.reporteItem.observaciones || ''
});
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarProductoReporte');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.reporteItem = { id_producto: 0, unidad: 1, observaciones: '' };
if (window.Notify) Notify.success(json.message || 'Producto agregado correctamente');
await this.cargarReporte();
await this.cargarInventarioDisponible();
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el producto');
}
} catch (e) {
console.error('Error agregando producto al reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al agregar el producto');
} finally {
this.guardandoReporteItem = false;
}
},

async eliminarProductoReporte(item) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El producto: ' + (item.producto || '') + ' será eliminado del reporte y se devolverá al inventario',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;

try {
const resp = await axios.post(this.BASE + '/eliminar-producto-reporte', {
id_reporte: this.reporteActual.id,
id_estacion: this.reporteActual.id_estacion,
id_item: item.id,
id_producto: item.id_producto
});
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Producto eliminado correctamente');
await this.cargarReporte();
await this.cargarInventarioDisponible();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el producto');
}
} catch (e) {
console.error('Error eliminando producto del reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el producto');
}
},

async aprobarReporte() {
if (this.guardandoReporte) return;

const result = await Swal.fire({
title: '¿Finalizar reporte?',
text: 'Al finalizar el reporte no podrá modificarse',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar'
});
if (!result.isConfirmed) return;

if (this.formularioSucio) {
this.guardandoReporte = true;
const guardar = await this.guardarReporteDatos(true);
this.guardandoReporte = false;
if (!guardar) return;
}

this.guardandoReporte = true;
try {
const resp = await axios.post(this.BASE + '/aprobar-reporte', { id: this.reporteActual.id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Reporte finalizado correctamente');
setTimeout(() => { window.location.href = this.BASE + '/reporte'; }, 800);
} else {
if (window.Notify) Notify.error(json.message || 'Error al finalizar el reporte');
}
} catch (e) {
console.error('Error aprobando reporte:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al finalizar el reporte');
} finally {
this.guardandoReporte = false;
}
},

}));

});