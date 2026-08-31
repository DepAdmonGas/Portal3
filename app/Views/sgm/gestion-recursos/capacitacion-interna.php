<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="capacitacion()">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item" @click="openBuscar()"><i class="ti ti-search"></i> Buscar</a>
                </li>
                <li>
                    <a class="dropdown-item"
                        :href="'/sgm/gestion-recursos/programa-capacitacion-interna/pdf/' + buscar.year" download>
                        <i class="ti ti-download"></i> Descargar</a>
                </li>
                <li>
                    <a class="dropdown-item"
                        :href="'/sgm/gestion-recursos/programa-capacitacion-interna/reconocimiento/year/' + buscar.year" download>
                        <i class="ti ti-certificate"></i> Reconocimientos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="datatables mt-3">
        <div class="table-responsive">
            <table id="table-capacitacion-interna" class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th class="text-center align-middle">No</th>
                        <th class="text-center align-middle">Nombre del curso</th>
                        <th class="text-center align-middle">Tipo de capacitacion</th>
                        <th class="text-center align-middle">Fecha programada</th>
                        <th class="text-center align-middle">Duracion</th>
                        <th class="text-center align-middle">Instructor</th>
                        <th class="text-center align-middle">Fecha real de la toma del curso</th>
                        <th class="text-center align-middle">Nombre de las personas que asistieron al curso</th>
                        <th class="text-center align-middle">Evidencia</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalBuscar"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Buscar programación interna
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <label class="form-label">
                        * Año
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        x-model="buscar.year"
                        @input="errors.year = false"
                        :class="errors.year ? 'is-invalid' : ''">

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
                        @click="buscarProgramacion()">
                        <i class="ti ti-search"></i>
                        Buscar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- ------------------------- -->
    <!-- inicio offcanvas -------- -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasExampleLabel">
                Ayuda
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body fs-4">

            <p>Bienvenido al elemento <b>6. GESTION DE LOS RECURSOS</b></p>

            <p><b>Capacitación del personal</b></p>

            <p>De manera anual verifica el programa de capacitación interna y externa de acuerdo al procedimiento con el formato 009, verifica los puestos estén capacitados conforme a lo establecido en el procedimiento.</p>

            <p>Recuerda que cada que haya personal nuevo en las instalaciones deberá tomar la capacitación de inducción, por lo que cada que agregues a un nuevo colaborador en el formato 008 en automático le saldrán los cursos que debe tomar como inducción en el formato 010. </p>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>

<?php endif; ?>

</div>