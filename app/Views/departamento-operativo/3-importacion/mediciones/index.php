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

    <div class="row g-0 ms-auto w-100 justify-content-end">
        <?php if ($puedeCrear): ?>
            <div class="col-12 col-md-auto d-grid mb-4">
                <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirNuevo()">
                    <i class="ti ti-plus me-1"></i> Nuevo
                </button>
            </div>
        <?php endif; ?>
    </div>



    


     
 

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-mediciones" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo -->
    <div class="modal fade" id="modalNuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-gas-station me-2"></i>Nueva medición</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">* Fecha:</label>
                            <input type="date" class="form-control" x-model="nuevoForm.fecha"
                                   :style="errors.fecha ? 'border: 2px solid #A52525 !important;' : ''"
                                   @input="errors.fecha = false">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">* Factura:</label>
                            <input type="text" class="form-control" x-model="nuevoForm.factura"
                                   :style="errors.factura ? 'border: 2px solid #A52525 !important;' : ''"
                                   @input="errors.factura = false">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">* Neto:</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   :value="formatInputNumber(nuevoForm.neto)"
                                   @input="formatNumericInput($event, 'neto')"
                                   :style="errors.neto ? 'border: 2px solid #A52525 !important;' : ''">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">* Bruto:</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   :value="formatInputNumber(nuevoForm.bruto)"
                                   @input="formatNumericInput($event, 'bruto')"
                                   :style="errors.bruto ? 'border: 2px solid #A52525 !important;' : ''">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">* Cuenta litros:</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   :value="formatInputNumber(nuevoForm.cuentaLitros)"
                                   @input="formatNumericInput($event, 'cuentaLitros')"
                                   :style="errors.cuentaLitros ? 'border: 2px solid #A52525 !important;' : ''">
                        </div>
                        <div class="col-12">
                            <label class="form-label">* Proveedor:</label>
                            <select class="form-select" x-model="nuevoForm.proveedor"
                                    :style="errors.proveedor ? 'border: 2px solid #A52525 !important;' : ''"
                                    @change="errors.proveedor = false">
                                <option value="">Selecciona un proveedor...</option>
                                <option value="Pemex">Pemex</option>
                                <option value="Delivery">Delivery</option>
                                <option value="Pick Up">Pick Up</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">

<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                       <button type="button" 
                    class="btn bg-danger-subtle text-danger" 
                    data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> 
                        Cancelar
                    </button>
                    </div>

                     <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" 
                    class="btn btn-success"
                     @click="guardarNuevo()" 
                     :disabled="guardando">
                        <template x-if="!guardando">
                            <i class="ti ti-check"></i>
                        </template>
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
    </div>

</div>
