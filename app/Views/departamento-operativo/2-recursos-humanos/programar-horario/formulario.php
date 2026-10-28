<div id="container" class="mt-4 mb-5"
     data-id-reporte="<?= $idReporte ?>"
     x-data="{ ...actions(), ...programarHorarioNuevoComponent() }">

    <div class="row">

        <div class="col-md-6 mb-4">
            <div class="programar-horario-date-filter d-inline-flex align-items-center bg-light border rounded-pill px-3 px-md-4 py-2 gap-2 w-100">
                <label for="fecha-programacion" class="text-muted fw-semibold text-nowrap d-flex align-items-center gap-1 mb-0">
                    <i class="ti ti-calendar fs-5 text-primary"></i>
                    <span>Fecha:</span>
                </label>
                <input type="date" class="form-control border-0 bg-transparent fw-semibold flex-grow-1"
                       id="fecha-programacion" :value="fechaSeleccionada" @change="changeFecha($event)">
            </div>
        </div>
        <div class="col-md-6 mb-4 text-end d-flex align-items-end justify-content-end">
            <button type="button" class="btn btn-success me-2" @click="guardarReporte()"
                    :disabled="guardando || (reporte && reporte.id && reporte.estado != 0)">
                <i class="ti ti-check me-1"></i> Finalizar
            </button>
        </div>
    </div>

    <div id="programar-horario-loading" class="text-center py-5" x-show="cargando">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <template x-if="secciones.length > 0">
        <div>
            <template x-for="(seccion, idx) in secciones" :key="idx">
                <div class="mb-4">
                    <div class="datatables">
                        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                            <table :id="'ph-form-table-' + idx" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100 portal-table portal-table--selectable horario-personal-seleccionable">
                                <thead>
                                    <tr>
                                        <th class="text-center align-middle" width="48">#</th>
                                        <th class="text-start align-middle">Nombre completo</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Lunes</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Martes</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Miércoles</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Jueves</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Viernes</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Sábado</th>
                                        <th class="text-center align-middle" style="min-width:130px;">Domingo</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="!cargando && secciones.length === 0">
        <div class="text-center text-muted py-5">
            <i class="ti ti-info-circle fs-1"></i>
            <p class="mt-2">No se encontró personal para mostrar.</p>
        </div>
    </template>

</div>
