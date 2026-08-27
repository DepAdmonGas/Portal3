<div id="container" class="pb-4"
    x-data="{ ...actions(), ...entregas()}">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item pointer" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="datatables mt-4">

        <div class="table-responsive">
            <table id="table-entregas" class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead>

                    <tr>
                        <th class="align-middle">#</th>
                        <th class="align-middle">Fecha</th>
                        <th class="align-middle">Estación</th>
                        <th class="align-middle">Destinatario</th>
                        <th class="align-middle">Estado</th>
                        <th class="text-center">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>

                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>

    <!-- Modal Nuevo -->
    <div
        class="modal fade"
        id="modalNuevo"
        x-ref="modalNuevo"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Agregar entrega
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">DESTINATARIO:</label>
                        <textarea class="form-control"
                            x-model="destinatario"
                            @input="errors.destinatario = false"
                            :class="errors.destinatario ? 'is-invalid' : ''"></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">* ESTACIÓN DE ENVIO:</label>

                        <div
                            class="select2-modal-field is-select2-pending"
                            x-ref="estacionWrapper">

                            <select
                                id="selectEstacion"
                                x-ref="selectEstacion">

                                <?php foreach ($estaciones as $estacion): ?>

                                    <option value="<?= htmlspecialchars($estacion->razonsocial) ?>">
                                        <?= htmlspecialchars($estacion->razonsocial) ?>
                                    </option>

                                <?php endforeach; ?>

                                <option value="Martin Quinzaños García">Martin Quinzaños García</option>
                                <option value="Aurelio Quinzaños Suarez">Aurelio Quinzaños Suarez</option>
                                <option value="Acueducto Guadalupe S.A. de C.V.">Acueducto Guadalupe S.A. de C.V.</option>
                                <option value="Wingate School S.C.">Wingate School S.C.</option>
                                <option value="Sabino Aguirre S.A. de C.V.">Sabino Aguirre S.A. de C.V.</option>
                                <option value="Servicio Ventura Puente S.A. de C.V.">Servicio Ventura Puente S.A. de C.V.</option>

                            </select>

                        </div>

                        <div
                            x-show="errors.estaciones"
                            class="text-danger small mt-1">

                            Debe seleccionar una estación.

                        </div>


                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar

                    </button>

                    <button
                        class="btn btn-success"
                        @click="guardar()">
                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Nuevo -->

</div>