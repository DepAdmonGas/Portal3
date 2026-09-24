<div id="container" class="mt-4 mb-5"
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-module-station-key="pedido-pinturas"
x-data="{ ...actions(), ...pedidoPinturasCatalogoComponent() }">

<div class="row">

<div class="col-12 mb-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">

<div class="d-flex align-items-center">
<a class="btn bg-danger-subtle text-danger" href="/departamento-operativo/comercializadora/pedido-pinturas">
<i class="ti ti-arrow-left me-1"></i> Regresar
</a>
</div>

<div class="d-flex align-items-center">
<div class="ms-auto">
<button type="button" class="btn bg-primary-subtle text-primary" @click="modalNuevoProducto()" x-show="puedeEditar">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>

</div>
</div>

<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-catalogos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<!---------- MODAL NUEVO / EDITAR PRODUCTO CATÁLOGO ---------->
<div class="modal fade" id="modalNuevoProducto" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-tools me-2"></i> <span x-text="productoForm.id ? 'Editar' : 'Nuevo'"></span> producto del catálogo de pinturas</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-12 col-sm-12">
<div class="mb-1 form-label">* Nombre del producto:</div>
<input type="text" class="form-control" x-model="productoForm.producto">
</div>
<div class="col-12">
    <div class="mb-1 form-label">* Unidad:</div>
    <select class="form-select" x-model="productoForm.unidad">
        <option value="">Selecciona una opción...</option>
        <option value="CUB.">CUB.</option>
        <option value="KILO.">KILO.</option>
        <option value="LT.">LT.</option>
        <option value="PZA.">PZA.</option>
    </select>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarProducto()" :disabled="guardandoProducto"><i class="ti ti-check me-1"></i> Guardar</button>
</div>
</div>
</div>
</div>

</div>