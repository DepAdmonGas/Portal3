<div id="container" class="mt-4 mb-5"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
x-data="{ ...actions(), ...rolComodinesComponent() }">

<div class="row mb-3 align-items-center">
<div class="col-12 text-end">
<template x-if="puedeCrear">
<button type="button" class="btn btn-primary" @click="agregarRol()">
<i class="ti ti-plus me-1"></i> Agregar
</button>
</template>
</div>
</div>

<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-rol-comodines" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-dialog-scrollable modal-xl">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Detalle Rol de Comodines</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body" id="modalDetalleBody">
<div class="text-center py-4">
<div class="spinner-border text-primary" role="status"></div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

</div>
