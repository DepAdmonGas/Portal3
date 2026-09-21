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
       
            
            
                <button class="btn bg-primary-subtle text-primary">

                    <a class="dropdown-item pointer" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                </button>
            
      
    </div>

    <div class="table-responsive mt-4">
        <table class="table table-striped table-bordered mb-0  align-middle">
            <thead>
                <tr>
                    <th class="text-center align-middle"whidt="96 px">#</th>
                    <th class="text-center align-middle text-nowrap">Fecha</th>
                    <th class="text-center align-middle text-nowrap">Hora</th>
                    <th class="align-middle">Descripción detallada del servicio</th>
                    <th colspan="3" class="text-center align-middle">
                        Fo.SGM.012 Orden de servicio
                    </th>
                    <th colspan="3" class="text-center align-middle">
                        Fo.SGM.013 Evaluación de proveedores
                    </th>
                    <th class="text-center align-middle"><i class="ti ti-trash text-muted fs-6"></i></th>
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
                            class="text-center align-middle|
                            text-nowrap"
                            x-text="orden.fecha">
                        </td>
                        <td
                            class="text-center align-middle
                            text-nowrap"
                            x-text="orden.hora">
                        </td>
                        <td class="align-middle"
                            x-text="orden.descripcion">
                        </td>
                        <!-- Orden -->
                        <td class="text-center align-middle">

                            <span
                                class="pointer"
                                @click="openEditar(orden.id)">

                                <i class="ti ti-edit fs-6 text-warning"></i>

                            </span>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="detalleOrden(orden.id)">

                                <i class="ti ti-file-description fs-6 text-info"></i>

                            </a>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                target="_blank"
                                :href="'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/pdf/'+orden.id">

                                <i class="ti ti-file-type-pdf fs-6 text-danger"></i>

                            </a>

                        </td>

                        <!-- Evaluación -->

                        <td class="text-center align-middle">

                            <span
                                class="pointer"
                                @click="openEvaluacion(orden.id)">

                                <i class="ti ti-edit fs-6 text-warning"></i>

                            </span>

                        </td>

                        <td class="text-center align-middle">

                            <template x-if="orden.evaluacion">

                                <a
                                    class="pointer"
                                    @click="detalleEvaluacion(orden.id)">

                                    <i class="ti ti-file-description fs-6 text-info"></i>

                                </a>

                            </template>

                            <template x-if="!orden.evaluacion">

                                <i class="ti ti-x fs-6"></i>

                            </template>

                        </td>

                        <td class="text-center align-middle">

                            <template x-if="orden.evaluacion">

                                <a
                                    class="pointer"
                                    target="_blank"
                                    :href="'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/pdf/'+orden.id">

                                    <i class="ti ti-file-type-pdf fs-6 text-danger"></i>

                                </a>

                            </template>

                            <template x-if="!orden.evaluacion">

                                <i class="ti ti-x fs-6"></i>

                            </template>

                        </td>

                        <td class="text-center align-middle">

                            <a
                                class="pointer"
                                @click="eliminar(orden.id)">
                                <i class="ti ti-trash fs-6 text-danger"></i>
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
<span class="ti" :class="modo==='create'?'ti-clipboard-check':'ti-edit'"></span>
<span x-text="modo==='create'?'Nueva orden de servicio':'Editar orden de servicio'"></span> 
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">

                            * Descripción detallada del servicio que requiere:

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

                            * Justificación del servicio:

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
                        <i class="ti ti-folder-plus fs-6"></i>
                        Orden de servicio
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle mb-3">

                        <tr>
                            <th class="text-center" width="180">Fecha</th>
                            <td x-text="detalle.fecha"></td>
                        </tr>

                        <tr>
                            <th class="text-center">Hora</th>
                            <td x-text="detalle.hora"></td>
                        </tr>

                        <tr>
                            <th class="text-center">Solicitante</th>
                            <td x-text="detalle.solicitante"></td>
                        </tr>

                        <tr>
                            <th class="text-center">Puesto</th>
                            <td x-text="detalle.puesto"></td>
                        </tr>

                        <tr>
                            <th class="text-center">Razón social</th>
                            <td x-text="detalle.razon_social"></td>
                        </tr>

                        <tr>
                            <th class="text-center">RFC</th>
                            <td x-text="detalle.rfc"></td>
                        </tr>

                        <tr>
                            <th class="text-center">Dirección</th>
                            <td x-text="detalle.direccion"></td>
                        </tr>

                    </table>

                    <div>

                        <label class="form-label fw-bolder text-black mb-1">
                            Descripción detallada del servicio equipo que requiere:
                        </label>

                        <div>
                            <p
                        class="mb-3  text-black"
                            x-text="detalle.descripcion">
                            </p>
                        </div>

                    </div>

                    <div>

                        <label class="form-label fw-bolder text-black mb-1">
                            Justificación del servicio que requiere:
                        </label>

                        <div>
                        <p class="text-black"
                            x-text="detalle.justificacion">
                        </p>
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
                        <i class="ti ti-file-analytics fs-6"></i>
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

                    
<!--------interferencia de mb----->


                    <div class="mb-3">

                        <label class="form-label mb-2">
                            Fecha de ejecución:
                        </label>

                        <input
                            type="date"
                            class="form-control mb-3"
                            x-model="evaluacion.fecha">

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <label class="form-label mb-2">
                                Hora inicio:
                            </label>

                            <input
                                type="time"
                                class="form-control mb-3"
                                x-model="evaluacion.hora_inicio">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label mb-2">
                                Hora término:
                            </label>

                            <input
                                type="time"
                                class="form-control mb-3"
                                x-model="evaluacion.hora_termino">

                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <label class="form-label mb-2">
                                Proveedor:
                            </label>

                            <input
                                class="form-control mb-3"
                                x-model="evaluacion.nombre_proveedor">

                        </div>

                        <div class="col-md-6">

                            <label class="form-label mb-2">
                                No. acreditación:
                            </label>

                            <input
                                class="form-control mb-3"
                                x-model="evaluacion.no_acreditacion">

                        </div>

                    </div>

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle mt-2 mb-3">

                        <thead>

                            <tr>

                                <th class="text-center" >#</th>

                                <th>Aspecto</th>

                                <th class="text-center" width="180">Respuesta</th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-for="(item,index) in preguntas">

                                <tr>

                                    <td 
                                    class="text-center"
                                    width="96px"
                                    x-text="index+1"></td>

                                    <td x-text="item.texto"></td>

                                    <td class="p-0 m-0 align-middle text-center">

                                        <select
                                            class="form-select border-0 text-center"
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

                        <label class="form-label mb-2">
                            Observaciones:
                        </label>

                        <textarea
                            rows="3"
                            class="form-control"
                            x-model="evaluacion.observaciones">
                    </textarea>

                    </div>

                    <div>

                        <label class="form-label mb-2">
                            Personal que realiza la evaluación:
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
                            <option>Selecciona una opcion...</option>

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
<i class="ti ti-chart-bar fs-6"></i>
                        Evaluación de proveedores
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body" x-show="detalle">

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle mb-4">
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

                    <table class="table table-striped table-bordered mb-4  align-middle">

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

                    <div>

                        <label class="mb-2 form-label fw-bolder">
                            Observaciones:
                        </label>

                        <div>
                            <p
                            class="mb-3 text-dark"
                            x-text="detalle.observaciones">
                            </p>
                        </div>

                    </div>

                    <table class="table table-striped table-bordered  text-nowrap align-middle">

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
                <div class="modal-footer">
                      <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i> Cancelar

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