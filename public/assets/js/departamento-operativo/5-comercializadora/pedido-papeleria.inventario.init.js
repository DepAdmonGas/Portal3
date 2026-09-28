document.addEventListener('DOMContentLoaded', () => {

const c = document.getElementById('container');
if (!c) return;
if (!document.getElementById('tabla-inventario')) return;

const moduleStationKey = c.dataset.moduleStationKey || 'pedido-papeleria';
const BASE = '/departamento-operativo/comercializadora/pedido-papeleria';

window.tablaInventario = null;

function getContexto() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) {
return { tipo: p[0], id: parseInt(p[1]) };
}
}
if (c.dataset.idEstacion) {
return { tipo: 'estacion', id: parseInt(c.dataset.idEstacion) };
}
return null;
}

function esTodasEstaciones() {
var sel = document.getElementById('module-station-selector-' + moduleStationKey);
return sel && sel.value === '';
}

function buildUrlInventario() {
var ctx = getContexto();
if (!ctx && !esTodasEstaciones()) return null;
var parametro = '';
if (ctx) {
parametro = ctx.tipo === 'depto' ? 'id_depto=' + ctx.id : 'id_estacion=' + (ctx.id || 0);
} else {
parametro = 'id_estacion=0';
}
return BASE + '/inventario/data?' + parametro;
}

var EMPTY_URL_INVENTARIO = BASE + '/inventario/data?id_estacion=0';

function recargarTablaInventario() {
var dt = window.tablaInventario;
if (!dt) return;
var url = buildUrlInventario() || EMPTY_URL_INVENTARIO;
dt.ajax.url(url).load();
}

window.papeleriaRecargarTablas = function () {
recargarTablaInventario();
};

function initTablaInventario() {
var columnsInventario = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '50px' },
{ title: 'Nombre del producto', data: 'producto', className: 'align-middle text-start text-nowrap' },
{ title: 'Piezas', data: 'piezas', className: 'align-middle text-center text-nowrap', width:'96px' },
{ title: '<i class="ti ti-trash text-danger fs-6"></i>', data: null, className: 'align-middle text-center', orderable: false, searchable: false, width:'48px',
render: function (v, t, row) {
var inst = window.pedidoPapeleriaInventarioComponentInstance;
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
document.dispatchEvent(new Event('papeleria:estacion-cambio'));
}
});
}

initTablaInventario();

/* ============ CLICKS TABLA INVENTARIO ============ */
$('#tabla-inventario').on('click', '.pp-btn-eliminar-inventario', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || 'Item';
if (window.pedidoPapeleriaInventarioComponentInstance) {
window.pedidoPapeleriaInventarioComponentInstance.eliminarInventarioItem(id, nombre);
}
});

});

document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPapeleriaInventarioComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
hayContexto: false,

catalogos: [],
inventarioForm: { id_producto: 0, piezas: 1 },
guardandoInventario: false,
guardandoDeleteInventario: false,

BASE: '/departamento-operativo/comercializadora/pedido-papeleria',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';

window.pedidoPapeleriaInventarioComponentInstance = this;

this.actualizarHayContexto();
this.cargarCatalogos();

document.addEventListener('papeleria:estacion-cambio', () => {
this.actualizarHayContexto();
this.reiniciarFormInventario();
});
},

getSelector() {
return document.getElementById('module-station-selector-pedido-papeleria');
},

getContexto() {
const sel = this.getSelector();
if (sel && sel.value) {
const p = sel.value.split('_');
if (p.length === 2 && p[1]) {
return { tipo: p[0], id: parseInt(p[1]) };
}
}
const idEstacion = parseInt(document.getElementById('container').dataset.idEstacion || '0');
if (idEstacion) {
return { tipo: 'estacion', id: idEstacion };
}
return null;
},

esTodasEstaciones() {
const sel = this.getSelector();
return sel && sel.value === '';
},

actualizarHayContexto() {
const ctx = this.getContexto();
this.hayContexto = !!ctx && !this.esTodasEstaciones();
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
opt.textContent = p.producto;
sel.appendChild(opt);
});

if (!window.jQuery || !jQuery.fn.select2) return;

const $sel = jQuery(sel);
if ($sel.hasClass('select2-hidden-accessible')) {
$sel.trigger('change');
return;
}

const $modalParentPapeleria = jQuery(sel).closest('.modal');
$sel.select2({
dropdownParent: $modalParentPapeleria.length ? $modalParentPapeleria : jQuery(sel).parent(),
width: '100%'
});

$sel.off('change.papeleria').on('change.papeleria', () => {
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

const ctx = this.getContexto();
if (!ctx || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación o departamento específica.');
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
const body = {
id_producto: this.inventarioForm.id_producto,
piezas: this.inventarioForm.piezas
};
body[ctx.tipo === 'depto' ? 'id_depto' : 'id_estacion'] = ctx.id;
const resp = await axios.post(this.BASE + '/agregar-inventario', body);
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