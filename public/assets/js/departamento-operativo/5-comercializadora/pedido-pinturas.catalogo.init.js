document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasCatalogoComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,

catalogos: [],
productoForm: {},
guardandoProducto: false,

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';

window.pedidoPinturasCatalogoComponentInstance = this;

this.cargarCatalogos();
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

async cargarCatalogos() {
try {
const resp = await axios.get(this.BASE + '/catalogos?solo_activos=1');
const json = resp.data;
if (json.success) {
this.catalogos = json.catalogos || [];
this._initTabla();
}
} catch (e) {
console.error('Error cargando catálogos:', e);
}
},

_initTabla() {
const $t = $('#tabla-catalogos');
if (!$t.length) return;

if ($.fn.DataTable && $.fn.DataTable.isDataTable($t)) {
$t.DataTable().clear();
$t.DataTable().rows.add(this.catalogos || []);
$t.DataTable().draw();
return;
}

var columnasCatalogos = [
{ title: '#', data: 'id', className: 'align-middle text-center', width: '50px' },
{ title: 'Nombre del producto', data: 'producto', className: ' text-start align-middle text-nowrap' },
{ title: 'Unidad', data: 'unidad', className: 'align-middle text-center text-nowrap', width: '96px' },
{
    data: 'id',
    title: '<i class="ti ti-dots-vertical fs-6"></i>',
    width: '48px',
    orderable: false,
    searchable: false,
    className: 'text-center align-middle',
    render: function (data, type, row) {
        return type === 'display' ? renderAcciones(row) : '';
    }
},
];

function renderAcciones(row) {
    // Nota: Como en tu código original validabas mediante una función global/componente, 
    // adaptamos las validaciones utilizando tus funciones 'puedeEditarRef' y 'puedeEliminarRef'.
    
    var editar = '';
    if (puedeEditarRef()) {
        editar = '<a href="javascript:void(0)" class="dropdown-item pp-btn-editar-catalogo" data-id="' + row.id + '"><i class="ti ti-pencil fs-6"></i> Editar</a>';
    } else {
        editar = '<a class="dropdown-item disabled"><i class="ti ti-pencil fs-6"></i> Editar</a>';
    }

    var eliminar = '';
    if (puedeEliminarRef()) {
        eliminar = '<a href="javascript:void(0)" class="dropdown-item pp-btn-eliminar-catalogo" data-id="' + row.id + '" data-nombre="' + (row.producto || 'Producto') + '"><i class="ti ti-trash fs-6"></i> Eliminar</a>';
    }

    return '<div class="dropdown dropstart">' +
        '<a href="javascript:void(0)" data-bs-toggle="dropdown">' +
        '<i class="ti ti-dots-vertical fs-6"></i>' +
        '</a>' +
        '<div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">' +
        editar + eliminar +
        '</div>' +
        '</div>';
}

function puedeEditarRef() {
    var inst = window.pedidoPinturasCatalogoComponentInstance;
    return inst ? inst.puedeEditar : false;
}

function puedeEliminarRef() {
    var inst = window.pedidoPinturasCatalogoComponentInstance;
    return inst ? inst.puedeEliminar : false;
}

window.tablaCatalogos = $t.DataTable({
processing: true,
serverSide: false,
data: this.catalogos || [],
autoWidth: false,
stateSave: false,
order: [[0, 'asc']],
pageLength: 10,
lengthMenu: [10, 25, 50, 100],
language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
columns: columnasCatalogos
});
},

modalNuevoProducto() {
this.productoForm = { id: 0, unidad: '', producto: '' };
const m = document.getElementById('modalNuevoProducto');
bootstrap.Modal.getOrCreateInstance(m).show();
},

modalEditarProducto(row) {
this.productoForm = { id: row.id, unidad: row.unidad, producto: row.producto };
const m = document.getElementById('modalNuevoProducto');
bootstrap.Modal.getOrCreateInstance(m).show();
},

async guardarProducto() {
if (this.guardandoProducto) return;
if (!this.productoForm.unidad.trim() || !this.productoForm.producto.trim()) {
if (window.Notify) Notify.error('Campos obligatorios faltantes');
return;
}

this.guardandoProducto = true;
try {
const resp = await axios.post(this.BASE + '/guardar-producto', {
id: this.productoForm.id,
unidad: this.productoForm.unidad,
producto: this.productoForm.producto
});
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalNuevoProducto');
bootstrap.Modal.getOrCreateInstance(m).hide();
await this.cargarCatalogos();
if (window.Notify) Notify.success(json.message || 'Producto guardado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al guardar el producto');
}
} catch (e) {
console.error('Error guardando producto:', e);
if (window.Notify) Notify.error('Error al guardar el producto');
} finally {
this.guardandoProducto = false;
}
},

async eliminarProducto(id, nombre) {
const ok = await this._confirmarEliminar(nombre || 'Producto');
if (!ok) return;
try {
const resp = await axios.post(this.BASE + '/eliminar-producto', { id: id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Producto eliminado correctamente');
await this.cargarCatalogos();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el producto');
}
} catch (e) {
console.error('Error eliminando producto:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el producto');
}
},

}));

});

document.addEventListener('DOMContentLoaded', () => {
$(document).on('click', '.pp-btn-editar-catalogo', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var inst = window.pedidoPinturasCatalogoComponentInstance;
if (!inst || !window.tablaCatalogos) return;
var row = null;
var idx = window.tablaCatalogos.rows().indexes().filter(function (i) {
return window.tablaCatalogos.row(i).data().id === id;
}).toArray();
if (idx.length) row = window.tablaCatalogos.row(idx[0]).data();
if (row) inst.modalEditarProducto(row);
});

$(document).on('click', '.pp-btn-eliminar-catalogo', function (e) {
e.preventDefault();
var id = parseInt(this.dataset.id);
var nombre = this.dataset.nombre || 'Producto';
var inst = window.pedidoPinturasCatalogoComponentInstance;
if (inst) inst.eliminarProducto(id, nombre);
});
});