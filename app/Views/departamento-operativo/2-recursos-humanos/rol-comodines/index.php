<div id="container" class="mt-4 mb-5"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
x-data="{ ...actions(), ...rolComodinesComponent() }">


<template x-if="puedeCrear">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mt-3 mb-3">
                     <button type="button" class="btn bg-primary-subtle text-primary" @click="agregarRol()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
 
                    </div>
                  </div>



</template>


<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-rol-comodines" class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable">
<tbody></tbody>
</table>
</div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" aria-hidden="true" data-bs-keyboard="false">
<div class="modal-dialog modal-dialog-scrollable modal-xl">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
    <i class="ti ti-eye"></i>
Detalle Rol de Comodines</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body" id="modalDetalleBody">
<div class="text-center py-4">
<div class="spinner-border text-primary" role="status"></div>
</div>
</div>
<div class="modal-footer">
    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid">
 <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
 <i class="ti ti-x"></i> 
 Cerrar</button>   
                    </div>
                  </div>

</div>
</div>
</div>
</div>

</div>
