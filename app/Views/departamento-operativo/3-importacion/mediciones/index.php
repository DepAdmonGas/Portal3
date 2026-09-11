<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= $idUsuario ?>"
     data-id-estacion="<?= $idEstacion ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...medicionesComponent() }">

<style>
@media (min-width: 992px) {
    #container .table-responsive { overflow-x: auto; }
}
</style>

    <div class="row">
        <div class="col-12">

<div class="float-end mb-3" x-show="puedeCrear">
    <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirNuevo()">
        <i class="ti ti-plus me-1"></i> Nuevo
    </button>
</div>

        </div>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-mediciones" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo -->
    <div class="modal fade" id="modalNuevo" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-gas-station me-2"></i>Nueva medición</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Fecha: <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" x-model="nuevoForm.fecha">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Factura: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" x-model="nuevoForm.factura">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Neto: <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" class="form-control" x-model="nuevoForm.neto">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Bruto: <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" class="form-control" x-model="nuevoForm.bruto">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Cuenta litros: <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" class="form-control" x-model="nuevoForm.cuentaLitros">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Proveedor: <span class="text-danger">*</span></label>
                            <select class="form-select" x-model="nuevoForm.proveedor">
                                <option value="">Selecciona un proveedor...</option>
                                <option value="Pemex">Pemex</option>
                                <option value="Delivery">Delivery</option>
                                <option value="Pick Up">Pick Up</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="guardarNuevo()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>