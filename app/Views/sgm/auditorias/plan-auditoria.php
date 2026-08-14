<div
    x-data="{...actions(), ...planauditoria(<?= $id ?>)}">

    <div class="bg-white p-3 mt-3">

        <!-- PLAN -->

        <table class="table table-bordered table-sm mb-1">

            <tbody>

                <tr>
                    <td colspan="3" class="bg-muted text-white">
                        <b>I. DATOS GENERALES DEL PERMISIONARIO</b>
                    </td>
                </tr>

                <tr>

                    <td class="bg-light text-center fw-bolder">
                        NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:
                    </td>

                    <td class="bg-light text-center fw-bolder">
                        Permiso CRE:
                    </td>

                    <td class="bg-light text-center fw-bolder">
                        FECHA DE ELABORACIÓN:
                    </td>

                </tr>

                <tr>

                    <td class="text-center">
                        <span x-text="plan.razon_social"></span>
                    </td>

                    <td class="text-center">
                        <span x-text="plan.permiso_cre"></span>
                    </td>

                    <td class="p-0">

                        <input
                            type="date"
                            class="form-control border-0"
                            x-model="plan.fecha"
                            @change="editar('fecha')">

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder">
                        NOMBRE DEL DIRECTOR (ALTA DIRECCIÓN):
                    </td>

                    <td colspan="2" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="plan.nom_director"
                            @change="editar('nom_director')">

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder align-middle">
                        NOMBRE DEL(LOS) RESPONSABLE DEL SGM:
                    </td>

                    <td colspan="2" class="p-0">

                        <select
                            class="form-select border rounded-0"
                            x-model="usuarioResponsable"
                            @change="agregarResponsable()">
                            <option value="">Seleccione un usuario</option>

                            <template
                                x-for="usuario in usuariosDisponibles"
                                :key="usuario.id">
                                <option
                                    :value="usuario.id"
                                    x-text="usuario.nombre"></option>
                            </template>
                        </select>

                        <ul class="list-group list-group-flush">

                            <template
                                x-for="responsable in responsables"
                                :key="responsable.id">
                                <li
                                    class="list-group-item d-flex justify-content-between align-items-center p-2">

                                    <small x-text="responsable.nombre"></small>

                                    <a
                                        href="javascript:void(0)"
                                        class="pointer"
                                        @click="eliminarResponsable(responsable.id)"
                                        title="Eliminar responsable">
                                        <i class="ti ti-trash fs-4 text-danger"></i>
                                    </a>

                                </li>
                            </template>

                        </ul>

                    </td>

                </tr>

                <tr>

                    <td class="bg-light fw-bolder">
                        UBICACIÓN DE LA INSTALACIÓN:
                    </td>

                    <td colspan="2" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="plan.ubicacion_instalacion"
                            @change="editar('ubicacion_instalacion')">

                    </td>

                </tr>

            </tbody>

        </table>

        <!-- AUDITORES -->

        <div class="text-end mt-3 mb-3">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirAuditor()">
                <i class="ti ti-plus"></i>
                Agregar auditor
            </button>

        </div>

        <table class="table table-bordered table-sm">

            <tbody>

                <tr class="bg-secondary text-white">

                    <td colspan="4" class="bg-muted text-white">
                        <b>II. DATOS DEL AUDITOR</b>
                    </td>

                </tr>

                <tr>

                    <td class="fw-bolder bg-light">
                        Equipo auditor
                    </td>

                    <td class="fw-bolder bg-light">
                        Nombre
                    </td>

                    <td class="fw-bolder bg-light">
                        Área/proceso/actividad que audita:
                    </td>

                    <td class="text-center bg-light" width="35"><i class="ti ti-trash fs-6 text-muted"></i></td>

                </tr>

                <template
                    x-for="auditor in auditores"
                    :key="auditor.id">

                    <tr>

                        <td class="align-middle" x-text="auditor.categoria"></td>

                        <td class="align-middle" x-text="auditor.nombre"></td>

                        <td class="align-middle" x-text="auditor.area_actividad"></td>

                        <td class="text-center">

                            <a
                                class="pointer"
                                @click="eliminarAuditor(auditor.id)">
                                <i class="ti ti-trash fs-6 text-danger"></i>
                            </a>

                        </td>

                    </tr>

                </template>

                <template x-if="auditores.length === 0">

                    <tr>

                        <td
                            colspan="4"
                            class="text-center text-muted">
                            No hay auditores registrados.
                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

        <!-- AUXILIARES -->

        <div class="text-end mb-3">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirAuxiliar()">
                <i class="ti ti-plus"></i>
                Agregar auxiliar
            </button>

        </div>

        <table class="table table-bordered table-sm align-middle">

            <tbody>

                <tr class="bg-secondary text-white">

                    <td colspan="3" class="bg-muted text-white">
                        <b>
                            III. DATOS DEL EQUIPO AUXILIAR DEL AUDITOR
                        </b>
                    </td>

                </tr>

                <tr class="bg-light">

                    <td class="fw-bolder bg-light">
                        Equipo auditor
                    </td>

                    <td class="fw-bolder bg-light">
                        Nombre
                    </td>

                    <td class="text-center bg-light" width="35"><i class="ti ti-trash fs-6 text-muted"></i></td>

                </tr>

                <template
                    x-for="auxiliar in auxiliares"
                    :key="auxiliar.id">

                    <tr>

                        <td x-text="auxiliar.categoria"></td>

                        <td x-text="auxiliar.nombre"></td>

                        <td class="text-center">

                            <a
                                class="pointer"
                                @click="eliminarAuxiliar(auxiliar.id)">
                                <i class="ti ti-trash fs-6 text-danger">
                                    </button>

                        </td>

                    </tr>

                </template>

                <template x-if="auxiliares.length === 0">

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No hay auxiliares registrados.

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>


        <!-- AUDITORÍA -->

        <table class="table table-bordered table-sm">

            <tbody>

                <tr>

                    <td colspan="3" class="bg-muted text-white">
                        <b>IV. AUDITORÍA</b>
                    </td>

                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        OBJETIVOS DE LA AUDITORÍA
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.objetivo_auditoria"
                            @change="editar('objetivo_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        ALCANCE DE LA AUDITORÍA
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.alcance_auditoria"
                            @change="editar('alcance_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        FECHA PROGRAMADA DE AUDITORÍA
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <input
                            type="date"
                            class="form-control border-0"
                            x-model="plan.fecha_programada"
                            @change="editar('fecha_programada')">

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        SITIO
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="plan.sitio"
                            @change="editar('sitio')">

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        MÉTODOS DE AUDITORÍA
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.metodo_auditoria"
                            @change="editar('metodo_auditoria')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        AJUSTES AL PLAN
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.ajuste_plan"
                            @change="editar('ajuste_plan')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        ASIGNACIÓN DE RECURSOS APROPIADOS
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.asignacion_recursos"
                            @change="editar('asignacion_recursos')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        PREPARATIVOS LOGÍSTICOS Y DE COMUNICACIONES
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.preparativos_logisticos"
                            @change="editar('preparativos_logisticos')"></textarea>

                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="bg-light fw-bolder">
                        ACCIONES DE SEGUIMIENTO
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="p-0">

                        <textarea
                            class="form-control border-0"
                            x-model="plan.acciones"
                            @change="editar('acciones')"></textarea>

                    </td>
                </tr>

            </tbody>

        </table>

        <!-- AGENDA -->

        <div class="text-end mt-3 mb-3">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirAgenda()">
                <i class="ti ti-plus"></i>
                Agregar agenda
            </button>

        </div>

        <table class="table table-bordered table-striped table-sm">

            <thead>

                <tr>
                    <th colspan="6" class="bg-muted text-white">V. AGENDA.<br>
                        Nota: Elaborar una Agenda para cada sitio a ser auditado.</th>
                </tr>

                <tr>

                    <th class="text-center align-middle bg-light">
                        HORARIO
                    </th>

                    <th class="text-center align-middle bg-light">
                        PROCESO
                    </th>

                    <th class="text-center align-middle bg-light">
                        ELEMENTO DEL SISTEMA DE GESTIÓN DE MEDICIÓN
                    </th>

                    <th class="text-center align-middle bg-light">
                        NOMBRE Y ROL DEL AUDITOR
                    </th>

                    <th class="text-center align-middle bg-light">
                        GUÍA
                    </th>

                    <th
                        class="text-center align-middle bg-light"
                        width="32">
                        <i class="ti ti-trash fs-5 text-muted"></i>
                    </th>

                </tr>

            </thead>


            <tbody>

                <template
                    x-for="item in agenda"
                    :key="item.id">

                    <tr>

                        <td
                            class="text-center align-middle fw-bold">

                            De
                            <span x-text="item.hora_inicio"></span>
                            a
                            <span x-text="item.hora_termino"></span>

                        </td>


                        <td
                            class="text-center align-middle"
                            x-text="item.proceso">
                        </td>


                        <td class="text-center align-middle" x-text="item.elemento_sistema"></td>


                        <td
                            class="text-center align-middle"
                            x-text="item.nombre_rol">
                        </td>


                        <td
                            class="text-center align-middle"
                            x-text="item.guia">
                        </td>


                        <td class="text-center align-middle">

                            <a
                                href="javascript:void(0)"
                                class="pointer"
                                title="Eliminar agenda"
                                @click="eliminarAgenda(item.id)">

                                <i class="ti ti-trash fs-5 text-danger"></i>

                            </a>

                        </td>

                    </tr>

                </template>


                <template x-if="agenda.length === 0">

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted">

                            <small>
                                No se encontró información
                            </small>

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

        <div class="text-end">
            <button class="btn btn-success" @click="finalizar"><i class="ti ti-check"></i> Finalizar Plan de Auditoria</button>
        </div>

    </div>

    <!-- Modal -->
    <div
        class="modal fade"
        id="modalPrincipal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <!-- AUDITOR -->

                <template x-if="modalTipo === 'auditor'">

                    <div>

                        <div class="modal-header bg-primary head-modal">

                            <h4 class="modal-title text-white">
                                II. DATOS DEL AUDITOR
                            </h4>

                            <button
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal">
                            </button>

                        </div>


                        <div class="modal-body">

                            <div class="mb-2">

                                <label class="form-label">
                                    * Equipo auditor:
                                </label>

                                <select
                                    class="form-select"
                                    x-model="formAuditor.categoria"
                                    @change="errors.categoria = false"
                                    :class="errors.categoria ? 'is-invalid' : ''">

                                    <option value="">
                                        Selecciona...
                                    </option>

                                    <option value="AUDITOR LÍDER">
                                        AUDITOR LÍDER
                                    </option>

                                    <option value="AUDITOR">
                                        AUDITOR
                                    </option>

                                </select>

                            </div>


                            <div class="mb-2">

                                <label class="form-label">
                                    * Nombre del auditor:
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="formAuditor.nombre"
                                    @input="limpiarAuditorInterno()"
                                    @change="errors.nombre = false"
                                    :disabled="tieneAuditorInterno"
                                    :class="errors.nombre ? 'is-invalid' : ''">

                            </div>

                            <div class="mb-2">

                                <label class="form-label">
                                    * Nombre (Auditor Interno):
                                </label>

                                <select
                                    class="form-select"
                                    x-model="formAuditor.auditorInterno"
                                    @change="limpiarNombreAuditor()"
                                    :disabled="tieneNombreAuditor">

                                    <option value="">
                                        Seleccione un usuario
                                    </option>

                                    <template
                                        x-for="auditor in usuarios"
                                        :key="auditor.id">
                                        <option
                                            :value="auditor.id"
                                            x-text="auditor.nombre"></option>
                                    </template>

                                </select>

                            </div>


                            <div class="mb-2">

                                <label class="form-label">
                                    * Área/proceso/actividad que audita:
                                </label>

                                <textarea
                                    class="form-control"
                                    x-model="formAuditor.area_actividad"
                                    @change="errors.area_actividad = false"
                                    :class="errors.area_actividad ? 'is-invalid' : ''">
                            </textarea>

                            </div>

                        </div>


                        <div class="modal-footer">

                            <button
                                class="btn bg-danger-subtle text-danger"
                                data-bs-dismiss="modal">

                                <i class="ti ti-x"></i> Cancelar

                            </button>

                            <button
                                type="button"
                                class="btn btn-success"
                                @click="guardarAuditor">
                                <i class="ti ti-check"></i>

                                Guardar

                            </button>

                        </div>

                    </div>

                </template>


                <!-- AUXILIAR -->

                <template x-if="modalTipo === 'auxiliar'">

                    <div>

                        <div class="modal-header bg-primary head-modal">

                            <h5 class="modal-title text-white">
                                III. DATOS DEL EQUIPO AUXILIAR DEL AUDITOR
                            </h5>

                            <button
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal">
                            </button>

                        </div>


                        <div class="modal-body">

                            <div class="mb-2">

                                <label class="form-label">
                                    * Equipo auditor:
                                </label>

                                <select
                                    class="form-select"
                                    x-model="formAuxiliar.categoria"
                                    @change="errors.categoria = false"
                                    :class="errors.categoria ? 'is-invalid' : ''">

                                    <option value="">
                                        Selecciona...
                                    </option>

                                    <option value="GUÍAS">
                                        GUÍAS
                                    </option>

                                    <option value="OBSERVADORES">
                                        OBSERVADORES
                                    </option>

                                    <option value="EXPERTO(S) TÉCNICO(S)">
                                        EXPERTO(S) TÉCNICO(S)
                                    </option>

                                </select>

                            </div>


                            <div class="mb-2">

                                <label class="form-label">
                                    * Nombre:
                                </label>

                                <textarea
                                    class=" form-control"
                                    x-model="formAuxiliar.nombre"
                                    @change="errors.nombre = false"
                                    :class="errors.nombre ? 'is-invalid' : ''">
                                    </textarea>

                            </div>

                        </div>


                        <div class="modal-footer">

                            <button
                                class="btn bg-danger-subtle text-danger"
                                data-bs-dismiss="modal">

                                <i class="ti ti-x"></i> Cancelar

                            </button>

                            <button
                                type="button"
                                class="btn btn-success"
                                @click="guardarAuxiliar">
                                <i class="ti ti-check"></i>

                                Guardar

                            </button>

                        </div>

                    </div>

                </template>

            </div>

        </div>

    </div>

    <!-- Modal Agenda -->
    <div
        class="modal fade"
        id="modalAgenda"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                        V. AGENDA
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-2">

                            <label class="form-label">
                                * Hora inicio:
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                x-model="formAgenda.hora_inicio"
                                @change="errors.hora_inicio = false"
                                :class="errors.hora_inicio ? 'is-invalid' : ''">

                        </div>


                        <div class="col-md-6 mb-2">

                            <label class="form-label">
                                * Hora término:
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                x-model="formAgenda.hora_termino"
                                @change="errors.hora_termino = false"
                                :class="errors.hora_termino ? 'is-invalid' : ''">

                        </div>

                    </div>


                    <div class="mb-2">

                        <label class="form-label">
                            * Proceso:
                        </label>

                        <textarea
                            class="form-control"
                            rows="2"
                            x-model="formAgenda.proceso"
                            @change="errors.proceso = false"
                            :class="errors.proceso ? 'is-invalid' : ''">
            </textarea>

                    </div>


                    <div class="mb-2">

                        <label class="form-label">
                            * Elemento del sistema de gestión de medición:
                        </label>

                        <select
                            class="form-select"
                            x-model="formAgenda.elemento_sistema"
                            @change="errors.elemento_sistema = false"
                            :class="errors.elemento_sistema ? 'is-invalid' : ''">

                            <option value="">
                                Selecciona una opción...
                            </option>

                            <template
                                x-for="elemento in elementos"
                                :key="elemento.id">

                                <option
                                    :value="elemento.no + ' ' + elemento.criterio"
                                    x-text="elemento.no + ' ' + elemento.criterio">
                                </option>

                            </template>

                        </select>

                    </div>


                    <div class="mb-2">

                        <label class="form-label">
                            * Nombre y rol del auditor:
                        </label>

                        <textarea
                            class="form-control"
                            rows="2"
                            x-model="formAgenda.nombre_rol"
                            @change="errors.nombre_rol = false"
                            :class="errors.nombre_rol ? 'is-invalid' : ''">
            </textarea>

                    </div>


                    <div class="mb-2">

                        <label class="form-label">
                            Guía:
                        </label>

                        <textarea
                            class="form-control"
                            rows="2"
                            x-model="formAgenda.guia">
            </textarea>

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
                        @click="guardarAgenda">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>
        </div>
    </div>

</div>