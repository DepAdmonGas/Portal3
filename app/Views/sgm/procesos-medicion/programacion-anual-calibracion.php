<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
    data-year="<?= (int) ($year ?? date('Y')) ?>">

    <?php if (empty($estacionId)): ?>

        <div id="sgm-empty-message"
            class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
        </div>

    <?php else: ?>

        <div id="sgm-content" x-data="{ ...actions(), ...programacionAnual() }">

            <div class="clearfix mt-2 mb-3">
                <h5 class="float-start mb-0 mt-1">Año <span x-text="year"></span></h5>
                <div class="btn-group float-end">
                    <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-4"></i>
                    </button>
                    <ul class="dropdown-menu animated rubberBand">
                        <li>
                            <button type="button" class="dropdown-item" @click="openModalNuevo()"><i class="ti ti-plus"></i> Nuevo</button>
                        </li>
                        <li>
                            <a class="dropdown-item" :href="pdf"><i class="ti ti-download"></i> Descargar</a>
                        </li>
                    </ul>
                </div>
            </div>

            <template x-for="grupo in lista" :key="grupo.categoria">

                <div class="mb-4">
                    <div class="card">
                        <div class="card-header bg-primary mb-0">
                            <i class="ti ti-abacus fs-6 text-white"></i>
                            <span
                                class="card-title text-white"
                                x-text="grupo.categoria">
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                                <thead>
                                    <tr>
                                        <th
                                            class="text-center"
                                            x-text="grupo.categoria"></th>
                                        <th class="text-center">Periodicidad</th>
                                        <th class="text-center">Fecha</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <template x-for="item in grupo.items" :key="item.id">

                                        <tr>
                                            <td class="text-center" x-text="item.nombre"></td>

                                            <td class="text-center" x-text="item.periodicidad"></td>

                                            <td class="text-center" x-text="item.fecha"></td>
                                        </tr>

                                    </template>

                                </tbody>

                            </table>
                        </div>
                    </div>



                </div>

            </template>



            <div
                class="modal fade"
                id="modalNuevo"
                tabindex="-1"
                data-bs-backdrop="static"
                data-bs-keyboard="false">

                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h4 class="modal-title text-white">
                                <i class="ti ti-settings-plus"></i>
                                Nuevo instrumento
                            </h4>
                            <button
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">

                            <label class="form-label">* Instrumento:</label>

                            <select
                                class="form-select"
                                x-model="formNuevo.equipo_id"
                                @change="errors.equipo_id = false"
                                :class="errors.equipo_id ? 'is-invalid' : ''">

                                <option value="">Seleccione una opcion...</option>

                                <template
                                    x-for="equipo in equipos"
                                    :key="equipo.id">

                                    <option
                                        :value="equipo.id"
                                        x-text="equipo.nombre">
                                    </option>

                                </template>

                            </select>

                            <label class="form-label mt-3">
                                * Fecha programada:
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="formNuevo.fecha"
                                @change="errors.fecha = false"
                                :class="errors.fecha ? 'is-invalid' : ''">

                        </div>

                        <div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                       <button type="button"
                                class="btn bg-danger-subtle text-danger"
                                data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cancelar
                            </button>

                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                       <button
                                class="btn btn-success"
                                @click="guardar()">

                                <i class="ti ti-check"></i> Guardar

                            </button>
                    </div>
                  </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>
