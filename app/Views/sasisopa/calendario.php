<div id="container"
    class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
    x-data="{ ...actions(), ...calendario('<?= $modulo ?>') }">

    <?php if (empty($estacionId)): ?>

        <div id="<?= $modulo === 'sgm' ? 'sgm' : 'sasisopa' ?>-empty-message"
             class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            <?= $modulo === 'sgm'
                ? 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.'
                : 'Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.' ?>
        </div>

    <?php else: ?>

    <div id="<?= $modulo === 'sgm' ? 'sgm' : 'sasisopa' ?>-content">

    <!-- ===================================================== -->
    <!-- CALENDARIO -->
    <!-- ===================================================== -->

    <div class="calender-sidebar app-calendar mt-3">

        <div class="d-flex justify-content-between align-items-center my-3">

            <!-- Totales -->
            <div class="d-flex align-items-center">

                <div class="border-end pe-3">
                    <h6 class="text-muted fw-normal mb-1">
                        Pendientes
                    </h6>

                    <b
                        class="text-danger fs-5"
                        x-text="totales.pendientes"></b>
                </div>

                <div class="ms-5 border-end pe-3">
                    <h6 class="text-muted fw-normal mb-1">
                        Finalizados
                    </h6>

                    <b
                        class="text-success fs-5"
                        x-text="totales.finalizados"></b>
                </div>

                <div class="ms-5">
                    <h6 class="text-muted fw-normal mb-1">
                        Total
                    </h6>

                    <b
                        class="fs-5"
                        x-text="totales.total"></b>
                </div>

            </div>

            <!-- Acciones SASISOPA -->
            <?php if ($modulo === 'sasisopa'): ?>

                <div class="btn-group">

                    <button
                        type="button"
                        class="btn btn-light dropdown-toggle text-dark"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-4"></i>
                    </button>

                    <ul class="dropdown-menu animated rubberBand">

                        <?php if (!empty($permisos['crear'])): ?>

                            <li>
                                <a
                                    class="dropdown-item pointer"
                                    @click="abrirModalActividad()">
                                    <i class="ti ti-plus"></i>
                                    Agregar
                                </a>
                            </li>

                        <?php endif; ?>

                    </ul>

                </div>

            <?php endif; ?>

        </div>

        <div id="calendar"></div>

    </div>


    <!-- ===================================================== -->
    <!-- MODAL: ACTIVIDADES DEL DÍA -->
    <!-- ===================================================== -->

    <div
        class="modal fade"
        id="modalDia"
        tabindex="-1"
        aria-labelledby="modalDiaLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">

            <div class="modal-content">

                <div
                    class="modal-header modal-colored-header bg-primary text-white">

                    <h4
                        class="modal-title text-white"
                        id="modalDiaLabel"
                        x-text="fechaSeleccionada"></h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <div class="modal-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead>
                                <tr>
                                    <th width="60">
                                        #
                                    </th>

                                    <th>
                                        Nombre
                                    </th>

                                    <th width="120">
                                        Tipo
                                    </th>

                                    <th width="120">
                                        Estado
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                <!-- Registros -->
                                <template
                                    x-for="(item, index) in actividadesDia"
                                    :key="`${item.tipo}-${item.id}`">

                                    <tr
                                        class="pointer"
                                        @click="abrirDetalle(item)">

                                        <td
                                            x-text="index + 1"></td>

                                        <td>

                                            <strong
                                                x-text="item.nombre"></strong>

                                            <template
                                                x-if="item.tipo === 'curso'">

                                                <div
                                                    class="small text-muted mt-1">

                                                    <i
                                                        class="ti ti-user me-1"></i>

                                                    <span
                                                        x-text="item.usuario"></span>

                                                </div>

                                            </template>

                                        </td>

                                        <td>

                                            <span
                                                class="badge"
                                                :class="
                                                    item.tipo === 'actividad'
                                                        ? 'bg-primary'
                                                        : 'bg-info'
                                                "
                                                x-text="item.tipo"></span>

                                        </td>

                                        <td>

                                            <span
                                                class="badge"
                                                :class="
                                                    item.estado
                                                        ? 'bg-success'
                                                        : 'bg-danger'
                                                "
                                                x-text="
                                                    item.estado
                                                        ? 'Finalizado'
                                                        : 'Pendiente'
                                                "></span>

                                        </td>

                                    </tr>

                                </template>


                                <!-- Sin registros -->
                                <template
                                    x-if="actividadesDia.length === 0">

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-5">
                                            No existen actividades programadas para este día.
                                        </td>

                                    </tr>

                                </template>

                            </tbody>

                        </table>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cerrar
                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- MODAL: DETALLE -->
    <!-- ===================================================== -->

    <div
        class="modal fade"
        id="modalDetalle"
        tabindex="-1"
        aria-labelledby="modalDetalleLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div
                    class="modal-header modal-colored-header bg-primary text-white">

                    <h4
                        class="modal-title text-white"
                        id="modalDetalleLabel">

                        <span
                            x-text="
                                detalle.tipo === 'actividad'
                                    ? 'Detalle de la actividad'
                                    : 'Detalle del curso'
                            "></span>

                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <div class="modal-body">

                    <div class="row g-3">

                        <!-- Tema -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Tema
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="detalle.nombre ?? ''"></div>

                        </div>


                        <!-- Nombre -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Nombre
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="detalle.participante ?? ''"></div>

                        </div>


                        <!-- Fecha -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Fecha programada
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="detalle.fecha ?? ''"></div>

                        </div>


                        <!-- Estado -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Estado
                            </label>

                            <div>

                                <span
                                    class="badge"
                                    :class="
                                        detalle.estado
                                            ? 'bg-success'
                                            : 'bg-danger'
                                    "
                                    x-text="
                                        detalle.estado
                                            ? 'Finalizado'
                                            : 'Pendiente'
                                    "></span>

                            </div>

                        </div>


                        <!-- ============================================= -->
                        <!-- SOLO CURSOS -->
                        <!-- ============================================= -->

                        <template
                            x-if="detalle.tipo === 'curso'">

                            <div class="col-12">

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <label
                                            class="form-label fw-semibold">
                                            Resultado
                                        </label>

                                        <div class="form-control bg-light">

                                            <span
                                                x-text="detalle.resultado ?? 0"></span>
                                            %

                                        </div>

                                    </div>


                                    <div class="col-md-6">

                                        <label
                                            class="form-label fw-semibold">
                                            Participante
                                        </label>

                                        <div
                                            class="form-control bg-light"
                                            x-text="
                                                detalle.participante ?? ''
                                            "></div>

                                    </div>


                                    <div class="col-12">

                                        <label
                                            class="form-label fw-semibold">
                                            Observaciones
                                        </label>

                                        <div
                                            class="form-control bg-light"
                                            style="min-height: 90px;"
                                            x-text="
                                                detalle.observaciones ?? ''
                                            "></div>

                                    </div>

                                </div>

                            </div>

                        </template>


                        <!-- ============================================= -->
                        <!-- SOLO ACTIVIDADES -->
                        <!-- ============================================= -->

                        <template
                            x-if="detalle.tipo === 'actividad'">

                            <div class="col-12">

                                <div class="row g-3">

                                    <div class="col-md-6">

                                        <label
                                            class="form-label fw-semibold">
                                            Folio
                                        </label>

                                        <div
                                            class="form-control bg-light"
                                            x-text="detalle.folio ?? ''"></div>

                                    </div>


                                    <div class="col-md-6">

                                        <label
                                            class="form-label fw-semibold">
                                            Fecha término
                                        </label>

                                        <div
                                            class="form-control bg-light"
                                            x-text="
                                                detalle.fecha_termino ?? ''
                                            "></div>

                                    </div>

                                </div>

                            </div>

                        </template>

                    </div>

                </div>


                <div class="modal-footer">

                    <!-- Reagendar curso -->
                    <template
                        x-if="
                            detalle.tipo === 'curso'
                            && Number(detalle.estado) === 1
                            && Number(detalle.resultado) < 60
                        ">

                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="reagendarCurso()">
                            <i
                                class="ti ti-calendar-plus me-1"></i>

                            Reagendar para mañana
                        </button>

                    </template>


                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cerrar
                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- MODAL: NUEVA ACTIVIDAD -->
    <!-- ===================================================== -->

    <div
        class="modal fade"
        id="modalActividad"
        tabindex="-1"
        aria-labelledby="modalActividadLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div
                    class="modal-header modal-colored-header bg-primary text-white">

                    <h4
                        class="modal-title text-white"
                        id="modalActividadLabel">
                        Agregar actividad al calendario
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <div class="modal-body">

                    <div class="row g-3">

                        <!-- Actividad -->
                        <div class="col-12">

                            <label class="form-label">
                                * Actividad
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevaActividad.actividad">

                                <option value="">
                                    Seleccione...
                                </option>

                                <template
                                    x-for="grupo in actividadesDisponibles"
                                    :key="grupo.id">

                                    <optgroup
                                        :label="grupo.label">

                                        <template
                                            x-for="actividad in grupo.actividades"
                                            :key="actividad.id">

                                            <option
                                                :value="actividad.id"
                                                x-text="actividad.nombre"></option>

                                        </template>

                                    </optgroup>

                                </template>

                            </select>

                        </div>


                        <!-- Fecha -->
                        <div class="col-md-4">

                            <label class="form-label">
                                * Fecha
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="nuevaActividad.fecha">

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i>
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardarActividad()">
                        <i class="ti ti-check"></i>
                        Agregar actividad
                    </button>

                </div>

            </div>

        </div>

    </div>

    </div>

    <?php endif; ?>

</div>