<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= $idUsuario ?>"
     data-id-estacion="<?= (int)$idEstacion ?>"
     data-id-year="<?= (int)$idYear ?>"
     data-id-mes="<?= (int)$idMes ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...cuentaLitrosComponent() }">

    <style>
        @media (min-width: 992px) {
            #container .table-responsive { overflow-x: auto; }
        }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="float-end mb-3">
                <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirNuevo()" x-show="puedeCrear && estacionEspecifica">
                    <i class="ti ti-plus me-1"></i> Nuevo
                </button>
            </div>
        </div>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-cuenta-litros" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo -->
    <div class="modal fade" id="modalNuevo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-droplet me-2"></i>Nuevo cuenta litros</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">* Fecha: <span class="text-danger"></span></label>
                            <input type="date" class="form-control" x-model="nuevoForm.fecha">
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