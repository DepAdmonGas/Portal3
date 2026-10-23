<div id="constancia-situacion-fiscal"
     class="mt-3 mb-4"
     x-data="{ ...actions(), ...constanciaSituacionFiscalComponent() }"
     data-tipo-descarga="<?= htmlspecialchars($tipoDescarga, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-mostrar-estacion="<?= $mostrarFilaEstacion ? 'true' : 'false' ?>"
     data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>">

    <?php /* El selector de estación/departamento lo imprime el layout 'main'
            a partir de moduleStationKey; ver ModuleStationService::render(). */ ?>

<div class="row g-3">
        
        <div class="col-12">

            <button type="button"
                    class="btn bg-primary-subtle text-primary float-end"
                    id="csf-btn-agregar"
                    x-show="puedeAgregar && entidadId > 0"
                    @click="abrirModalAgregar()">
                <i class="ti ti-plus"></i> Nuevo
            </button>
        </div>

    <div class="table-responsive pb-5">
        <table id="tabla-constancia-situacion-fiscal"
               class="table table-striped table-bordered mb-0 text-nowrap align-middle"></table>
    </div> 

    <!-- Modal: alta de constancia -->
    <div class="modal fade" id="modalConstancia" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalConstancia">
        <div class="modal-lg modal-dialog-centered">
            <div class="modal-content">  
                <div class="modal-header modal-colored-header bg-primary text-white">
                   <i class="ti ti-certificate me-2"></i>
                    <h5 class="modal-title text-white">Nueva constancia</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">* Fecha de actualización:</label>
                            <input type="date" class="form-control" x-ref="inputFecha">
                        </div>

                        <div class="col-12">
                            <label class="form-label">* Constancia de Situación Fiscal:</label>
                            <input type="file" class="form-control" accept="application/pdf" x-ref="inputArchivo">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success"
                                    @click="guardar()"
                                    :disabled="guardando">
                                <i class="ti ti-check"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>