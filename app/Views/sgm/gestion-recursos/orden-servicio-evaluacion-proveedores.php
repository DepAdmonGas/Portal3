<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...evaluacion()}">

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

    <div class="table-responsive mt-4">
        <table class="table table-sm table-bordered table-striped">
            <thead>
                <tr>
                    <th class="text-center align-middle">#</th>
                    <th class="text-center align-middle">Fecha</th>
                    <th class="text-center align-middle">Hora</th>
                    <th class="align-middle">Descripción detallada del servicio</th>
                    <th colspan="3" class="text-center align-middle">
                        Fo.SGM.012 Orden de servicio
                    </th>
                    <th colspan="3" class="text-center align-middle">
                        Fo.SGM.013 Evaluación de proveedores
                    </th>
                    <th class="text-center align-middle"><i class="ti ti-trash text-muted fs-7"></i></th>
                </tr>
            </thead>
            <tbody>

                <template
                    x-if="ordenes.length===0">

                    <tr>
                        <td
                            colspan="11"
                            class="text-center text-muted">
                            No se encontró información para mostrar.
                        </td>
                    </tr>
                </template>

                <template
                    x-for="orden in ordenes"
                    :key="orden.id">
                    <tr>
                        <td
                            class="text-center fw-bolder align-middle"
                            x-text="orden.numero">
                        </td>
                        <td
                            class="text-center align-middle"
                            x-text="orden.fecha">
                        </td>
                        <td
                            class="text-center align-middle"
                            x-text="orden.hora">
                        </td>
                        <td class="align-middle"
                            x-text="orden.descripcion">
                        </td>
                        <!-- Orden -->
                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="openEditar(orden.id)">

                                <i class="ti ti-edit fs-7"></i>

                            </a>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="detalleOrden(orden.id)">

                                <i class="ti ti-file-description fs-7 text-info"></i>

                            </a>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                target="_blank"
                                :href="'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/pdf/'+orden.id">

                                <i class="ti ti-file-type-pdf fs-7 text-danger"></i>

                            </a>

                        </td>

                        <!-- Evaluación -->

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="openEvaluacion(orden.id)">

                                <i class="ti ti-edit fs-7"></i>

                            </a>

                        </td>

                        <td class="text-center align-middle">

                            <template x-if="orden.evaluacion">

                                <a
                                    class="pointer"
                                    @click="detalleEvaluacion(orden.id)">

                                    <i class="ti ti-file-description fs-7 text-info"></i>

                                </a>

                            </template>

                            <template x-if="!orden.evaluacion">

                                <i class="ti ti-x fs-7"></i>

                            </template>

                        </td>

                        <td class="text-center align-middle">

                            <template x-if="orden.evaluacion">

                                <a
                                    class="pointer"
                                    target="_blank"
                                    :href="'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/pdf/'+orden.id">

                                    <i class="ti ti-file-type-pdf fs-7 text-danger"></i>

                                </a>

                            </template>

                            <template x-if="!orden.evaluacion">

                                <i class="ti ti-x fs-7"></i>

                            </template>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="eliminar(orden.id)">
                                <i class="ti ti-trash fs-7 text-danger"></i>
                            </a>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>



    <!-- Modal Nuevo ---->
    <div
        class="modal fade"
        id="modalNuevo"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">

                        Orden de servicio

                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">

                            * Descripción detallada del servicio que requiere

                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            x-model="form.descripcion"
                            @input="errors.descripcion = false"
                            :class="errors.descripcion ? 'is-invalid' : ''">
                        </textarea>

                    </div>

                    <div>

                        <label class="form-label">

                            * Justificación del servicio

                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            x-model="form.justificacion"
                            @input="errors.justificacion = false"
                            :class="errors.justificacion ? 'is-invalid' : ''">
                        </textarea>
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
                        @click="guardarRegistro()">

                        <i class="ti ti-check"></i>

                        <span
                            x-text="modo=='create'
                        ? 'Guardar'
                        : 'Actualizar'">
                        </span>

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Nuevo ---->

    <!-- Modal Detalle -->
    <div
        class="modal fade"
        id="modalDetalle"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Orden de servicio
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <table class="table table-sm">

                        <tr>
                            <th width="180">Fecha</th>
                            <td x-text="detalle.fecha"></td>
                        </tr>

                        <tr>
                            <th>Hora</th>
                            <td x-text="detalle.hora"></td>
                        </tr>

                        <tr>
                            <th>Solicitante</th>
                            <td x-text="detalle.solicitante"></td>
                        </tr>

                        <tr>
                            <th>Puesto</th>
                            <td x-text="detalle.puesto"></td>
                        </tr>

                        <tr>
                            <th>Razón social</th>
                            <td x-text="detalle.razon_social"></td>
                        </tr>

                        <tr>
                            <th>RFC</th>
                            <td x-text="detalle.rfc"></td>
                        </tr>

                        <tr>
                            <th>Dirección</th>
                            <td x-text="detalle.direccion"></td>
                        </tr>

                    </table>

                    <div class="mt-3">

                        <label class="fw-bolder">
                            Descripción detallada del servicio equipo que requiere:
                        </label>

                        <div
                            class="border rounded p-3 bg-light"
                            x-text="detalle.descripcion">
                        </div>

                    </div>

                    <div class="mt-3">

                        <label class="fw-bolder">
                            Justificación del servicio que requiere:
                        </label>

                        <div
                            class="border rounded p-3 bg-light"
                            x-text="detalle.justificacion">
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

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Detalle --->

    <!-- Modal Editar Proveedor -->
    <div
        class="modal fade"
        id="modalEvaluacion"
        tabindex="-1">

        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Evaluación de proveedores
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div
                    class="modal-body"
                    x-show="evaluacion.id">

                    <h5
                        class="mb-3"
                        x-text="evaluacion.descripcion">
                    </h5>

                    <div class="mb-3">

                        <label class="form-label">
                            Fecha de ejecución
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="evaluacion.fecha">

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora inicio
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                x-model="evaluacion.hora_inicio">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora término
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                x-model="evaluacion.hora_termino">

                        </div>

                    </div>

                    <div class="row mt-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Proveedor
                            </label>

                            <input
                                class="form-control"
                                x-model="evaluacion.nombre_proveedor">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                No. acreditación
                            </label>

                            <input
                                class="form-control"
                                x-model="evaluacion.no_acreditacion">

                        </div>

                    </div>

                    <table class="table table-bordered mt-4">

                        <thead>

                            <tr>

                                <th width="50">#</th>

                                <th>Aspecto</th>

                                <th width="180">Respuesta</th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-for="(item,index) in preguntas">

                                <tr>

                                    <td x-text="index+1"></td>

                                    <td x-text="item.texto"></td>

                                    <td class="p-0 m-0 align-middle">

                                        <select
                                            class="form-select border-0"
                                            x-model="evaluacion[item.campo]">

                                            <option value="2"></option>
                                            <option value="1">SI</option>
                                            <option value="0">NO</option>

                                        </select>

                                    </td>

                                </tr>

                            </template>

                        </tbody>

                    </table>

                    <div class="mb-3">

                        <label class="form-label">
                            Observaciones
                        </label>

                        <textarea
                            rows="3"
                            class="form-control"
                            x-model="evaluacion.observaciones">
                    </textarea>

                    </div>

                    <div>

                        <label class="form-label">
                            Personal que realiza la evaluación
                        </label>

                        <select
                            class="form-select"
                            x-model="evaluacion.id_personal_evaluacion">

                            <template
                                x-for="usuario in usuarios">

                                <option
                                    :value="usuario.id"
                                    x-text="usuario.nombre">
                                </option>

                            </template>

                        </select>

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
                        @click="guardarEvaluacion()">

                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Editar Proveedor -->

    <!-- Modal Detalle Evaluación -->
    <div class="modal fade" id="modalDetalleEvaluacion" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white">
                        Evaluación de proveedores
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body" x-show="detalle">

                    <table class="table table-bordered">
                        <tbody>

                            <tr>
                                <th width="35%">
                                    Trabajo realizado o producto adquirido:
                                </th>
                                <td x-text="detalle.descripcion"></td>
                            </tr>

                            <tr>
                                <th>Fecha de ejecución del servicio:</th>
                                <td x-text="detalle.fecha"></td>
                            </tr>

                            <tr>
                                <th>Hora de inicio del servicio:</th>
                                <td x-text="detalle.hora_inicio"></td>
                            </tr>

                            <tr>
                                <th>Hora de culminación del servicio:</th>
                                <td x-text="detalle.hora_termino"></td>
                            </tr>

                            <tr>
                                <th>Nombre del proveedor o prestador de servicio:</th>
                                <td x-text="detalle.nombre_proveedor"></td>
                            </tr>

                            <tr>
                                <th>No de acreditación o aprobación:</th>
                                <td x-text="detalle.no_acreditacion"></td>
                            </tr>

                        </tbody>
                    </table>

                    <table class="table table-sm table-bordered mt-4">

                        <thead>
                            <tr>
                                <th class="text-center" width="60">No.</th>
                                <th>Aspecto evaluado</th>
                                <th class="text-center" width="120">Respuesta</th>
                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td class="text-center">1</td>
                                <td>El trabajo fue ejecutado conforme a lo solicitado.</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :class="detalle.respuesta_1 ? 'bg-success' : 'bg-danger'"
                                        x-text="detalle.respuesta_1 ? 'SI' : 'NO'">
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-center">2</td>
                                <td>Se verificó que el proveedor contara con procedimientos para ejecutar los trabajos.</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :class="detalle.respuesta_2 ? 'bg-success' : 'bg-danger'"
                                        x-text="detalle.respuesta_2 ? 'SI' : 'NO'">
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-center">3</td>
                                <td>Mientras el personal permaneció en las instalaciones ocupó EPP.</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :class="detalle.respuesta_3 ? 'bg-success' : 'bg-danger'"
                                        x-text="detalle.respuesta_3 ? 'SI' : 'NO'">
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-center">4</td>
                                <td>Los trabajos ejecutados tomaron en cuenta los procedimientos de seguridad.</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :class="detalle.respuesta_4 ? 'bg-success' : 'bg-danger'"
                                        x-text="detalle.respuesta_4 ? 'SI' : 'NO'">
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-center">5</td>
                                <td>Al culminar el trabajo se encuentra a entera satisfacción.</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :class="detalle.respuesta_5 ? 'bg-success' : 'bg-danger'"
                                        x-text="detalle.respuesta_5 ? 'SI' : 'NO'">
                                    </span>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                    <div class="mt-4">

                        <label class="fw-bolder">
                            Observaciones:
                        </label>

                        <div
                            class="border rounded p-3 mt-2"
                            style="min-height:80px"
                            x-text="detalle.observaciones">
                        </div>

                    </div>

                    <table class="table mt-4">

                        <tbody>

                            <tr>
                                <th width="35%">
                                    Nombre de quien realiza la evaluación:
                                </th>
                                <td x-text="detalle.usuario"></td>
                            </tr>

                            <tr>
                                <th>Puesto:</th>
                                <td x-text="detalle.puesto"></td>
                            </tr>

                        </tbody>

                    </table>

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

            <p>Los proveedores juegan un papel sumamente importante en los procesos el SGM y en la confirmación metrológica, por lo que es nuestra función y responsabilidad verificar que los trabajos sean ejecutados conforme a lo contratado.</p>

            <p>Para dar cumplimiento a este punto siempre que asista un proveedor o prestador de servicios a realizar una actividad a la estación recuerda llenar previamente el formato 012 Orden de servicio.</p>

            <p>Una vez que asista el proveedor a ejecutar el servicio realiza el registro 013 Evaluación de proveedores. Recuerda que una vez que hayas hecho la evaluación el sistema sumará el porcentaje de cumplimiento, en caso de quedar por debajo del 80% no podrá realizar otro servicio y se deberá buscar un nuevo proveedor.</p>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>

<?php endif; ?>

</div>