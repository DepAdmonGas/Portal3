document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;
if (!document.getElementById('tabla-inventario')) return;

const moduleStationKey = c.dataset.moduleStationKey || 'pedido-pinturas';
const BASE = '/departamento-operativo/comercializadora/pedido-pinturas';

window.tablaInventario = null;

function getEstacionId() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(c.dataset.idEstacion || '0');
}

function esTodasEstaciones() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
return sel && sel.value === '';
}

function buildUrlInventario() {
var est = getEstacionId();
if (!est && !esTodasEstaciones()) return null;
return BASE + '/inventario/data?id_estacion=' + (est || 0);
}

var EMPTY_URL_INVENTARIO = BASE + '/inventario/data?id_estacion=0';

function recargarTablaInventario() {
var dt = window.tablaInventario;
if (!dt) return;
var url = buildUrlInventario() || EMPTY_URL_INVENTARIO;
dt.ajax.url(url).load();
}

window.pinturasRecargarTablas = function () {
recargarTablaInventario();
};

function initTablaInventario() {
var columnsInventario = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '50px' },
{ title: 'Nombre del producto', data: 'producto', className: 'align-middle text-start text-nowrap' },
{ title: 'Unidad', data: 'unidad', className: 'align-middle text-center text-nowrap', width:'96px' },
{ title: 'Piezas', data: 'piezas', className: 'align-middle text-center text-nowrap', width:'96px' },
{ title: '<i class="ti ti-trash text-danger fs-6"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false, width:'48px',
render: function (v, t, row) {
var inst = window.pedidoPinturasInventarioComponentInstance;
if (!inst || !inst.puedeEliminar) return '';
return '<i class="ti ti-trash text-danger pointer fs-6 pp-btn-eliminar-inventario" data-id="' + row.id + '" data-nombre="' + (row.producto || 'Item') + '"></i>';
} },
];

window.tablaInventario = $('#tabla-inventario').DataTable({
processing: true,
serverSide: false,
ajax: { type: 'GET', url: buildUrlInventario() || EMPTY_URL_INVENTARIO, dataSrc: function (json) { return (json && json.success) ? (json.inventario || []) : []; } },
autoWidth: false,
stateSave: false,
order: [[0, 'asc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columnsInventario
});
}

/* ============ SELECTOR DE ESTACIÓN ============ */
if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
ModuleStationSelector.init(moduleStationKey, {
customReload: function () {
recargarTablaInventario();
document.dispatchEvent(new Event('pedido-pinturas:estacion-cambio'));
}
});
}

initTablaInventario();

/* ============ CLICKS TABLA INVENTARIO ============ */
$('#tabla-inventario').on('click', '.pp-btn-eliminar-inventario', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || 'Item';
if (window.pedidoPinturasInventarioComponentInstance) {
window.pedidoPinturasInventarioComponentInstance.eliminarInventarioItem(id, nombre);
}
});

});

document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasInventarioComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
hayContexto: false,

catalogos: [],
inventarioForm: { id_producto: 0, piezas: 1 },
guardandoInventario: false,
guardandoDeleteInventario: false,

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';

window.pedidoPinturasInventarioComponentInstance = this;

this.actualizarHayContexto();
this.cargarCatalogos();

document.addEventListener('pedido-pinturas:estacion-cambio', () => {
this.actualizarHayContexto();
this.reiniciarFormInventario();
});
},

getSelector() {
return document.getElementById('module-station-selector-pedido-pinturas');
},

getIdEstacionContexto() {
const sel = this.getSelector();
if (sel && sel.value) {
const p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(document.getElementById('container').dataset.idEstacion || '0');
},

esTodasEstaciones() {
const sel = this.getSelector();
return sel && sel.value === '';
},

actualizarHayContexto() {
this.hayContexto = this.getIdEstacionContexto() > 0 && !this.esTodasEstaciones();
},

async cargarCatalogos() {
try {
const resp = await axios.get(this.BASE + '/catalogos?solo_activos=1');
const json = resp.data;
if (json.success) this.catalogos = json.catalogos || [];
this.poblarSelectProducto();
} catch (e) {
console.error('Error cargando catálogos:', e);
}
},

poblarSelectProducto() {
const sel = this.$refs.productoSelect;
if (!sel) return;

sel.innerHTML = '<option value="0">Selecciona un producto...</option>';
(this.catalogos || []).forEach((p) => {
const opt = document.createElement('option');
opt.value = p.id;
opt.textContent = p.producto + (p.unidad ? ' (' + p.unidad + ')' : '');
sel.appendChild(opt);
});

if (!window.jQuery || !jQuery.fn.select2) return;

const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.trigger('change');
return;
}

$sel.select2({
dropdownParent: this.$refs.productoWrapper || jQuery(sel).parent(),
width: '100%'
});

$sel.off('change.pinturas').on('change.pinturas', () => {
this.inventarioForm.id_producto = parseInt($sel.val() || '0', 10);
});
},

modalAgregarInventario() {
this.reiniciarFormInventario();
const m = document.getElementById('modalAgregarInventario');
bootstrap.Modal.getOrCreateInstance(m).show();
const sel = this.$refs.productoSelect;
if (sel && window.jQuery && jQuery.fn.select2 && jQuery(sel).hasClass('select2-hidden-accessible')) {
jQuery(sel).val('0').trigger('change');
}
},

reiniciarFormInventario() {
this.inventarioForm = { id_producto: 0, piezas: 1 };
},

async agregarInventario() {
if (this.guardandoInventario) return;

const idEstacion = this.getIdEstacionContexto();
if (!idEstacion || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación específica.');
return;
}
if (!this.inventarioForm.id_producto) {
if (window.Notify) Notify.error('Selecciona un producto.');
return;
}
if (!this.inventarioForm.piezas || this.inventarioForm.piezas <= 0) {
if (window.Notify) Notify.error('Ingresa un número de piezas válido.');
return;
}

this.guardandoInventario = true;
try {
const resp = await axios.post(this.BASE + '/agregar-inventario', {
id_estacion: idEstacion,
id_producto: this.inventarioForm.id_producto,
piezas: this.inventarioForm.piezas
});
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarInventario');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.reiniciarFormInventario();
if (window.tablaInventario) window.tablaInventario.ajax.reload(null, false);
if (window.Notify) Notify.success(json.message || 'Inventario actualizado correctamente');
this.cargarCatalogos();
} else {
if (window.Notify) Notify.error(json.message || 'Error al actualizar el inventario');
}
} catch (e) {
console.error('Error agregando inventario:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al actualizar el inventario');
} finally {
this.guardandoInventario = false;
}
},

async _confirmarEliminar(nombre) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El registro: ' + nombre + ' será eliminado',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
return result.isConfirmed;
},

async eliminarInventarioItem(id, nombre) {
if (this.guardandoDeleteInventario) return;
const ok = await this._confirmarEliminar(nombre);
if (!ok) return;

this.guardandoDeleteInventario = true;
try {
const resp = await axios.post(this.BASE + '/eliminar-inventario-item', { id: id });
const json = resp.data;
if (json.success) {
if (window.tablaInventario) window.tablaInventario.ajax.reload(null, false);
if (window.Notify) Notify.success(json.message || 'Item eliminado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el item');
}
} catch (e) {
console.error('Error eliminando item inventario:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el item');
} finally {
this.guardandoDeleteInventario = false;
}
},

}));

});