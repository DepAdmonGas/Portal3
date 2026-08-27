<div class="pb-4" x-data="{ ...actions(), ...calendario()}">

    <div class="calender-sidebar app-calendar mt-3">

        <div class="text-end">
            <button type="button" class="btn bg-primary-subtle text-primary"
                @click="abrirModalNuevaJunta()">
                <i class="ti ti-plus"></i> Nuevo
            </button>
        </div>

        <div id="calendar"></div>

    </div>

    <div
        class="modal fade"
        id="modalDia"
        tabindex="-1">
        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Juntas del
                        <span
                            x-text="fechaSeleccionada"></span>
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <template
                        x-if="actividadesDia.length === 0">
                        <div class="text-center py-4">
                            No hay juntas programadas.
                        </div>
                    </template>

                    <template
                        x-for="junta in actividadesDia"
                        :key="junta.id">

                        <div
                            class="border rounded p-3 mb-2"
                            style="cursor:pointer"
                            @click="abrirJunta(junta.id)">

                            <div class="fw-bold">
                                <span
                                    x-text="junta.descripcion"></span>
                            </div>

                            <div>
                                <span
                                    x-text="junta.hora_inicio"></span>

                                -

                                <span
                                    x-text="junta.hora_termino"></span>
                            </div>

                            <div>
                                Convoca:
                                <span
                                    x-text="junta.convoca"></span>
                            </div>

                        </div>

                    </template>

                </div>

            </div>

        </div>
    </div>

    <div
        class="modal fade"
        id="modalJunta"
        tabindex="-1"
        aria-labelledby="modalJuntaLabel"
        aria-hidden="true">
        <div
            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="modalJuntaLabel">
                        Detalle de la junta
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>

                <div class="modal-body">

                    <!-- Tema -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Tema
                        </label>

                        <div
                            class="form-control bg-light"
                            x-text="
                            juntaSeleccionada?.descripcion
                            ?? ''
                        "></div>

                    </div>


                    <div class="row g-3">

                        <!-- Convoca -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Convoca
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="
                                juntaSeleccionada?.convoca
                                ?? ''
                            "></div>

                        </div>


                        <!-- Puesto -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Puesto
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="
                                juntaSeleccionada?.puesto
                                ?? ''
                            "></div>

                        </div>


                        <!-- Fecha -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Fecha
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="
                                juntaSeleccionada?.fecha
                                ?? ''
                            "></div>

                        </div>


                        <!-- Inicio -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Hora inicio
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="
                                juntaSeleccionada?.hora_inicio
                                ?? ''
                            "></div>

                        </div>


                        <!-- Término -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                Hora término
                            </label>

                            <div
                                class="form-control bg-light"
                                x-text="
                                juntaSeleccionada?.hora_termino
                                ?? ''
                            "></div>

                        </div>


                        <!-- Estatus -->
                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Estatus
                            </label>

                            <div>
                                <span
                                    class="badge"
                                    :class="{
                                    'bg-success':
                                        juntaSeleccionada?.estatus
                                        === 'Programada',

                                    'bg-warning':
                                        juntaSeleccionada?.estatus
                                        === 'Solicitud',

                                    'bg-danger':
                                        juntaSeleccionada?.estatus
                                        === 'Cancelada'
                                }"
                                    x-text="
                                    juntaSeleccionada?.estatus
                                    ?? 'S/I'
                                "></span>
                            </div>

                        </div>

                    </div>


                    <!-- Comentarios -->
                    <hr>

                    <h6 class="fw-semibold mb-3">
                        Comentarios
                    </h6>

                    <template
                        x-if="
                        !juntaSeleccionada?.comentarios
                        || juntaSeleccionada.comentarios.length === 0
                    ">
                        <div
                            class="text-center text-muted py-3">
                            No hay comentarios.
                        </div>
                    </template>


                    <template
                        x-for="
                        comentario
                        in (juntaSeleccionada?.comentarios ?? [])
                    "
                        :key="comentario.id">
                        <div
                            class="border rounded p-3 mb-2">

                            <div
                                class="small text-muted mb-1"
                                x-text="comentario.fecha_hora"></div>

                            <div
                                x-text="comentario.comentario"></div>

                        </div>
                    </template>

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
    <!-- MODAL: NUEVA REUNIÓN -->
    <!-- ===================================================== -->

    <div
        class="modal fade"
        id="modalNuevaJunta"
        tabindex="-1"
        aria-labelledby="modalNuevaJuntaLabel"
        aria-hidden="true">
        <div
            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <!-- HEADER -->
                <div
                    class="modal-header modal-colored-header bg-primary text-white">
                    <h5
                        class="modal-title text-white"
                        id="modalNuevaJuntaLabel">
                        Nueva Reunión
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>


                <!-- BODY -->
                <div class="modal-body">

                    <div class="row g-3">

                        <!-- Información -->
                        <div class="col-12">

                            <div class="table-responsive">

                                <table class="table custom-table mb-0">

                                    <thead class="title-table-bg">
                                        <tr>
                                            <th class="text-center">
                                                Información para agendar una reunión
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="bg-light">
                                        <tr>
                                            <td class="no-hover">

                                                <ol class="mb-0">

                                                    <li>
                                                        Quien convoca es la persona responsable de organizar la reunión.
                                                    </li>

                                                    <li>
                                                        Puede elegir convocar a todo un
                                                        <b>departamento</b>
                                                        sin seleccionar manualmente a cada integrante.
                                                    </li>

                                                    <li>
                                                        También puede seleccionar únicamente al personal específico requerido.
                                                    </li>

                                                    <li>
                                                        Puede combinar ambas opciones:
                                                        departamentos y personal específico.
                                                    </li>

                                                </ol>

                                            </td>
                                        </tr>
                                    </tbody>

                                </table>

                            </div>

                        </div>


                        <!-- Tema -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                * Tema de la reunión
                            </label>

                            <textarea
                                class="form-control"
                                rows="3"
                                placeholder="Describe el tema de la reunión"
                                x-model="nuevaJunta.descripcion"></textarea>

                        </div>


                        <!-- Convoca -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                * Quién convoca
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevaJunta.idUsuario">

                                <option value="">
                                    Seleccione...
                                </option>

                                <template
                                    x-for="usuario in usuariosConvocantes"
                                    :key="usuario.id">
                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>
                                </template>

                            </select>

                        </div>


                        <!-- Fecha -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                * Fecha
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="nuevaJunta.fecha"
                                :min="fechaMinima"
                                @change="cargarHorasDisponibles()">

                        </div>


                        <!-- Hora inicio -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                * Hora inicio
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevaJunta.hora_inicio"
                                @change="actualizarHorasTermino()"
                                :disabled="cargandoHoras">

                                <option value="">
                                    Seleccione...
                                </option>

                                <template
                                    x-for="hora in horasDisponibles"
                                    :key="hora">
                                    <option
                                        :value="hora"
                                        x-text="hora"></option>
                                </template>

                            </select>

                            <div
                                class="small text-muted mt-1"
                                x-show="cargandoHoras">
                                Consultando disponibilidad...
                            </div>

                        </div>


                        <!-- Hora término -->
                        <div class="col-md-4">

                            <label class="form-label fw-semibold">
                                * Hora término
                            </label>

                            <select
                                class="form-select"
                                x-model="nuevaJunta.hora_termino"
                                :disabled="
                                !nuevaJunta.hora_inicio
                                || horasTermino.length === 0
                            ">

                                <option value="">
                                    Seleccione...
                                </option>

                                <template
                                    x-for="hora in horasTermino"
                                    :key="hora">
                                    <option
                                        :value="hora"
                                        x-text="hora"></option>
                                </template>

                            </select>

                        </div>


                        <!-- Sin disponibilidad -->
                        <div
                            class="col-12"
                            x-show="
                            nuevaJunta.fecha
                            && !cargandoHoras
                            && horasDisponibles.length === 0
                        ">
                            <div class="alert alert-warning mb-0">

                                <i class="ti ti-alert-triangle me-1"></i>

                                No existen horarios disponibles para esta fecha.

                            </div>
                        </div>


                        <!-- Departamentos -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                * Dirigido a departamentos
                            </label>

                            <select
                                class="form-select"
                                multiple
                                x-model="nuevaJunta.departamentos">

                                <template
                                    x-for="puesto in puestosDisponibles"
                                    :key="puesto.id">
                                    <option
                                        :value="puesto.id"
                                        x-text="puesto.nombre"></option>
                                </template>

                            </select>

                            <div class="form-text">
                                Puede seleccionar uno o varios departamentos.
                            </div>

                        </div>


                        <!-- Personal específico -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                * Personal específico
                            </label>

                            <select
                                class="form-select"
                                multiple
                                x-model="nuevaJunta.personal">

                                <template
                                    x-for="usuario in personalDisponible"
                                    :key="usuario.id">
                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>
                                </template>

                            </select>

                            <div class="form-text">
                                Puede seleccionar personas adicionales aunque ya haya seleccionado un departamento.
                            </div>

                        </div>


                        <!-- Error selección -->
                        <div
                            class="col-12"
                            x-show="errorPersonal">
                            <div class="text-danger">
                                Debe seleccionar al menos un departamento o personal específico.
                            </div>
                        </div>


                        <!-- Error general -->
                        <div
                            class="col-12"
                            x-show="errorNuevaJunta">
                            <div
                                class="alert alert-danger mb-0"
                                x-text="errorNuevaJunta"></div>
                        </div>

                    </div>

                </div>


                <!-- FOOTER -->
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
                        class="btn btn-primary"
                        @click="guardarJunta()"
                        :disabled="guardandoJunta">

                        <span x-show="!guardandoJunta">

                            <i class="ti ti-check"></i>
                            Guardar

                        </span>

                        <span x-show="guardandoJunta">

                            <span
                                class="spinner-border spinner-border-sm me-1"></span>

                            Guardando...

                        </span>

                    </button>

                </div>

            </div>
        </div>
    </div>
</div>