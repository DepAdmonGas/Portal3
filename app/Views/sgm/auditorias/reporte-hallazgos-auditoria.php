<div class="pb-4" x-data="{...actions(), ...reporte(<?= $id ?>)}">


    <div class="bg-white mt-4">

        <div class="table-responsive">

            <table class="table table-bordered table-sm align-middle mb-0">

                <tbody>

                    <tr>
                        <td
                            colspan="3"
                            class="bg-muted text-white">
                            <b>
                                I. DATOS GENERALES DEL PERMISIONARIO
                            </b>
                        </td>
                    </tr>

                    <tr>
                        <td class="bg-light text-center fw-bolder">
                            NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:
                        </td>
                        <td class="bg-light text-center fw-bolder">
                            PERMISO CRE:
                        </td>
                        <td class="bg-light text-center fw-bolder">
                            FECHA DE ELABORACIÓN:
                        </td>
                    </tr>
                    <tr>
                        <td
                            class="text-center"
                            x-text="hallazgo.razon_social"></td>
                        <td
                            class="text-center"
                            x-text="hallazgo.permiso_cre"></td>
                        <td class="p-0 m-0">
                            <input
                                type="date"
                                class="form-control border-0 rounded-0 text-center"
                                x-model="hallazgo.fecha"
                                @change="editar('fecha')">
                        </td>
                    </tr>

                    <tr>

                        <td
                            class="bg-light text-center fw-bolder align-middle">
                            NOMBRES DEL RESPONSABLE DEL SGM:
                        </td>

                        <td
                            colspan="2"
                            class="p-0 m-0">

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

                </tbody>

            </table>

        </div>

    </div>

    <div class="bg-white mt-3">

        <table class="table table-sm table-bordered mb-0">

            <tbody>

                <tr>

                    <td
                        colspan="2"
                        class="bg-muted text-white">
                        <b>
                            I. DATOS DE LA AUDITORÍA
                        </b>
                    </td>

                </tr>


                <tr>

                    <td class="bg-light fw-bolder">
                        FECHA Y UBICACIÓN DE LA AUDITORÍA:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.fecha_ubicacion"
                            @keyup="editar('fecha_ubicacion')">

                    </td>

                </tr>


                <tr>

                    <td class="bg-light fw-bolder">
                        OBJETIVO DE LA AUDITORÍA:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.objetivo_auditoria"
                            @keyup="editar('objetivo_auditoria')">

                    </td>

                </tr>


                <tr>

                    <td class="bg-light fw-bolder">
                        ALCANCE DE LA AUDITORÍA:
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0"
                            x-model="hallazgo.alcance_auditoria"
                            @keyup="editar('alcance_auditoria')">

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

    <!-- -- PERSONAL ENTREVISTADO -- -->
    <div class="bg-white mt-3">

        <div class="text-end">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirEntrevistador()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>

        <div class="mt-3">

            <table class="table table-sm table-bordered mb-0">

                <tbody>

                    <tr>

                        <td
                            colspan="4"
                            class="bg-muted text-white text-start fw-bolder">
                            PERSONAL ENTREVISTADO
                        </td>

                    </tr>

                    <tr>

                        <td class="text-center fw-bolder bg-light">
                            NOMBRE
                        </td>

                        <td class="text-center fw-bolder bg-light">
                            PUESTO
                        </td>

                        <td class="text-center fw-bolder bg-light">
                            ÁREA DE ADSCRIPCIÓN
                        </td>

                        <td
                            class="text-center fw-bolder bg-light"
                            width="32">
                            <i class="ti ti-trash fs-6 text-muted"></i>
                        </td>

                    </tr>

                    <template
                        x-if="entrevistados.length === 0">

                        <tr>

                            <td
                                colspan="4"
                                class="text-center text-muted">

                                <small>
                                    No se encontró información para mostrar
                                </small>

                            </td>

                        </tr>

                    </template>

                    <template
                        x-for="entrevistado in entrevistados"
                        :key="entrevistado.id">

                        <tr>

                            <td
                                class="align-middle text-center fw-bold"
                                x-text="entrevistado.nombre">
                            </td>

                            <td
                                class="align-middle text-center"
                                x-text="entrevistado.puesto">
                            </td>

                            <td
                                class="align-middle text-center"
                                x-text="entrevistado.area_descripcion">
                            </td>

                            <td class="text-center align-middle">

                                <a
                                    class="pointer"
                                    title="Eliminar agenda"
                                    @click="eliminarEntrevistado(entrevistado.id)">

                                    <i class="ti ti-trash fs-5 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

    </div>
    <!-- -- PERSONAL ENTREVISTADO -- -->

    <!-- -- EQUIPO AUDITOR -- -->
    <div class="bg-white mt-3">

        <div class="text-end mt-3 mb-3">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirEquipoAuditor()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>

        <div class="bg-white mt-3">

            <div class="table-responsive">

                <table class="table table-sm table-bordered mb-0">

                    <tbody>

                        <tr>

                            <td
                                colspan="3"
                                class="bg-muted text-white text-center fw-bolder">

                                EQUIPO AUDITOR

                            </td>

                        </tr>


                        <tr>

                            <td class="text-center fw-bolder bg-light">
                                NOMBRE
                            </td>

                            <td class="text-center fw-bolder bg-light">
                                ROL (AUDITOR LÍDER, AUDITOR EXPERTO TÉCNICO, AUDITOR ESPECIALISTA)
                            </td>

                            <td
                                width="32"
                                class="text-center bg-light">
                                <i class="ti ti-trash fs-6 text-muted"></i>
                            </td>

                        </tr>


                        <template
                            x-for="auditor in equipoauditor"
                            :key="auditor.id">

                            <tr>

                                <td
                                    class="align-middle text-center fw-bold"
                                    x-text="auditor.nombre">
                                </td>

                                <td
                                    class="align-middle text-center"
                                    x-text="auditor.rol">
                                </td>

                                <td class="text-center align-middle">

                                    <a
                                        class="pointer"
                                        @click="eliminarAuditor(auditor.id)">

                                        <i class="ti ti-trash fs-6 text-danger"></i>

                                    </a>

                                </td>

                            </tr>

                        </template>


                        <template x-if="equipoauditor.length === 0">

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted">

                                    <small>
                                        No se encontró información para mostrar
                                    </small>

                                </td>

                            </tr>

                        </template>

                    </tbody>

                </table>

            </div>

        </div>

    </div>
    <!-- -- EQUIPO AUDITOR -- -->

    <!-- -- RESULTADO DE LA AUDITORÍA -- -->
    <div class="bg-white mt-3">

        <div class="table-responsive">

            <table class="table table-sm table-bordered mb-0">

                <tbody>

                    <tr>

                        <td
                            colspan="3"
                            class="bg-muted text-white">

                            <b>
                                II. RESULTADO DE LA AUDITORÍA
                            </b>

                        </td>

                    </tr>

                    <tr>

                        <td
                            colspan="3"
                            class="bg-light text-center">

                            ¿Durante la auditoría se revisaron
                            los siguientes elementos?

                            <br>

                            Marcar el resultado como
                            C= Conforme,
                            NC= No Conforme,
                            OM= Oportunidad de Mejora

                        </td>

                    </tr>

                    <tr class="bg-light">

                        <td
                            class="text-center align-middle fw-bolder">

                            No.

                        </td>

                        <td class="align-middle fw-bolder">

                            CRITERIO:

                        </td>

                        <td
                            class="text-center align-middle fw-bolder">

                            RESULTADO:

                        </td>

                    </tr>


                    <template
                        x-for="resultado in resultados"
                        :key="resultado.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bolder"
                                x-text="resultado.no">
                            </td>


                            <td
                                class="align-middle"
                                x-text="resultado.criterio">
                            </td>


                            <td class="m-0 p-0">

                                <select
                                    class="form-select rounded-0 border-0"
                                    x-model="resultado.resultado"
                                    @change="editarResultado(resultado)">

                                    <option value="">
                                        Seleccionar
                                    </option>

                                    <option value="C">
                                        C= Conforme
                                    </option>

                                    <option value="NC">
                                        NC= No Conforme
                                    </option>

                                    <option value="OM">
                                        OM= Oportunidad de Mejora
                                    </option>

                                </select>

                            </td>

                        </tr>

                    </template>


                    <template
                        x-if="resultados.length === 0">

                        <tr>

                            <td
                                colspan="3"
                                class="text-center text-muted">

                                <small>
                                    No se encontró información para mostrar
                                </small>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

    </div>
    <!-- -- RESULTADO DE LA AUDITORÍA -- -->

    <!-- -- III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES -- -->
    <div class="bg-white mt-3">

        <div class="text-end mt-3">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirConforme()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>

        <div class="table-responsive mt-3">

            <table class="table table-bordered table-sm">

                <tbody>

                    <tr>
                        <td
                            colspan="5"
                            class="bg-muted text-white">
                            <b>
                                III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
                            </b>
                        </td>
                    </tr>


                    <tr>

                        <td
                            class="text-center align-middle fw-bolder bg-light"
                            width="96px">
                            No.
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            DESCRIPCIÓN DEL HALLAZGO
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            EVIDENCIA
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            CRITERIO
                        </td>

                        <td width="32" class="text-center align-middle bg-light">
                            <i class="ti ti-trash fs-6 text-muted"></i>
                        </td>

                    </tr>


                    <template
                        x-for="(conforme, index) in conformes"
                        :key="conforme.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bold"
                                x-text="index + 1">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.descripcion">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.evidencia">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="conforme.criterio">
                            </td>

                            <td
                                class="text-center align-middle">

                                <a
                                    class="pointer"
                                    @click="eliminarConforme(conforme.id)">

                                    <i class="ti ti-trash fs-6 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="conformes.length === 0">

                        <td
                            colspan="5"
                            class="text-center text-muted"
                            style="font-size: .8em;">

                            No se encontró información para mostrar

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
    <!-- -- III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES -- -->

    <!-- -- IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES -- -->
    <div class="bg-white mt-3">

        <div class="text-end">

            <button
                type="button"
                class="btn btn-info"
                @click="abrirMejoras()">
                <i class="ti ti-plus"></i>
                Nuevo
            </button>

        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-sm mt-3">

                <tbody>

                    <tr>

                        <td
                            colspan="3"
                            class="bg-muted text-white">

                            <b>
                                IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES
                            </b>

                        </td>

                    </tr>


                    <tr>

                        <td
                            class="text-center align-middle fw-bolder bg-light"
                            width="96px">

                            No.

                        </td>

                        <td
                            class="text-start align-middle fw-bolder bg-light">

                            DESCRIPCIÓN

                        </td>

                        <td width="48px" class="text-center bg-light">
                            <i class="ti ti-trash fs-6 text-muted"></i>
                        </td>

                    </tr>


                    <template
                        x-for="(mejora, index) in mejoras"
                        :key="mejora.id">

                        <tr>

                            <td
                                class="text-center align-middle fw-bolder"
                                x-text="index + 1">
                            </td>

                            <td
                                class="text-center align-middle"
                                x-text="mejora.descripcion">
                            </td>

                            <td
                                class="text-center align-middle">

                                <a
                                    class="pointer"
                                    @click="eliminarMejora(mejora.id)">

                                    <i class="ti ti-trash fs-6 text-danger"></i>

                                </a>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="mejoras.length === 0">

                        <td
                            colspan="3"
                            class="text-center text-muted"
                            style="font-size: .8em;">

                            No se encontró información para mostrar

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
    <!-- -- IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES -- -->

    <!-- -- V. COMENTARIOS -- -->
    <div class="bg-white mt-3">

        <div class="table-responsive">

            <table class="table table-bordered table-sm">

                <tbody>

                    <tr>

                        <td
                            colspan="2"
                            class="bg-muted text-white">
                            <b>
                                V. COMENTARIOS
                            </b>
                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.comentarios"
                                @keyup="editar('comentarios')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="bg-light">
                            NOTA: EN CASO DE QUE DURANTE LA AUDITORÍA,
                            EL EQUIPO AUDITOR DETECTE UNA SITUACIÓN DE
                            RIESGO PARA LA SEGURIDAD INDUSTRIAL,
                            SEGURIDAD OPERATIVA O PARA EL MEDIO AMBIENTE
                            EN LAS INSTALACIONES DEL REGULADO, DEBERÁ
                            REPORTARLA EN ESTA SECCIÓN.
                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.nota"
                                @keyup="editar('nota')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="bg-light">
                            MOTIVOS DE FINALIZACIÓN DE AUDITORÍA
                            ANTES DE TIEMPO (SI APLICA):
                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.motivos"
                                @keyup="editar('motivos')"></textarea>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="bg-muted text-white">
                            <b>
                                VI. CONCLUSIONES
                            </b>
                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="p-0 m-0">

                            <textarea
                                class="form-control border-0"
                                x-model="hallazgo.conclusiones"
                                @keyup="editar('conclusiones')"></textarea>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
    <!-- -- V. COMENTARIOS -- -->

    <!-- -- VI. CONCLUSIONES -- -->
    <div class="bg-white mt-3">

        <div class="table-responsive">

            <table class="table table-sm table-bordered">

                <tbody>

                    <tr>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Lugar y fecha
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Auditor lider
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            Responsable del SGM
                        </td>

                    </tr>


                    <tr>

                        <td class="p-0 m-0">

                            <input
                                type="text"
                                class="form-control text-center border-0"
                                x-model="hallazgo.lugar_fecha"
                                @keyup="editar('lugar_fecha')">

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select text-center rounded-0 border-0"
                                x-model="hallazgo.auditor_lider"
                                @change="editar('auditor_lider')">

                                <option value="0">
                                    Seleccionar
                                </option>

                                <template
                                    x-for="usuario in usuarios"
                                    :key="usuario.id">

                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>

                                </template>

                            </select>

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select text-center rounded-0 border-0"
                                x-model="hallazgo.responsable_sgm"
                                @change="editar('responsable_sgm')">

                                <option value="0">
                                    Seleccionar
                                </option>

                                <template
                                    x-for="usuario in usuarios"
                                    :key="usuario.id">

                                    <option
                                        :value="usuario.id"
                                        x-text="usuario.nombre"></option>

                                </template>

                            </select>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <div class="text-end">

            <button
                class="btn btn-success"
                @click="window.history.back()">
                <i class="ti ti-check"></i>
                Finalizar Reporte de Hallazgos de Auditoria
            </button>

        </div>

    </div>
    <!-- -- VI. CONCLUSIONES -- -->



    <!-- Modal Personal Entrevistado -->
    <div
        class="modal fade"
        id="modalPersonalEntrevistado"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                        PERSONAL ENTREVISTADO
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            * Nombre:
                        </label>

                        <select
                            class="form-select"
                            x-model="formEntrevistador.id_usuario"
                            @change="errorsEntrevistador.id_usuario = false"
                            :class="errorsEntrevistador.id_usuario ? 'is-invalid' : ''">

                            <option value="">
                                Selecciona un usuario...
                            </option>

                            <template
                                x-for="usuario in usuarios"
                                :key="usuario.id">

                                <option
                                    :value="usuario.id"
                                    x-text="usuario.nombre">
                                </option>

                            </template>

                        </select>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            * Área de descripción:
                        </label>

                        <textarea
                            class="form-control"
                            rows="3"
                            x-model="formEntrevistador.area_descripcion"
                            @input="errorsEntrevistador.area_descripcion = false"
                            :class="errorsEntrevistador.area_descripcion ? 'is-invalid' : ''">
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
                        @click="guardarEntrevistador()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Personal Entrevistado -->

    <!-- Modal Auditor Equipo -->
    <div
        class="modal fade"
        id="modalEquipoAuditor"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary head-modal">

                    <h5 class="modal-title text-white">
                        EQUIPO AUDITOR
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <!-- PERSONAL EXTERNO -->

                    <div class="mb-3">

                        <label class="form-label">
                            * Nombre (Personal Externo):
                        </label>

                        <textarea
                            class="form-control"
                            rows="2"
                            x-model="formEquipoAuditor.nombre"
                            @input="
                            errorsEquipoAuditor.nombre = false;
                            if (formEquipoAuditor.nombre.trim() !== '') {
                                formEquipoAuditor.id_usuario = '';
                                errorsEquipoAuditor.id_usuario = false;
                            }
                        "
                            :class="errorsEquipoAuditor.nombre ? 'is-invalid' : ''"
                            :disabled="!!formEquipoAuditor.id_usuario">
                    </textarea>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            * Rol (auditor líder, auditor experto técnico, auditor especialista):
                        </label>

                        <textarea
                            class="form-control"
                            rows="2"
                            x-model="formEquipoAuditor.rol"
                            @input="
                            errorsEquipoAuditor.rol = false;
                            if (formEquipoAuditor.rol.trim() !== '') {
                                formEquipoAuditor.id_usuario = '';
                                errorsEquipoAuditor.id_usuario = false;
                            }
                        "
                            :class="errorsEquipoAuditor.rol ? 'is-invalid' : ''"
                            :disabled="!!formEquipoAuditor.id_usuario">
                    </textarea>

                    </div>


                    <div class="text-center my-3">

                        <span class="text-secondary">
                            O
                        </span>

                    </div>


                    <!-- PERSONAL INTERNO -->

                    <div class="mb-3">

                        <label class="form-label">
                            * Nombre (Personal Interno):
                        </label>

                        <select
                            class="form-select"
                            x-model="formEquipoAuditor.id_usuario"
                            @change="
                            errorsEquipoAuditor.id_usuario = false;

                            if (formEquipoAuditor.id_usuario) {
                                formEquipoAuditor.nombre = '';
                                formEquipoAuditor.rol = '';

                                errorsEquipoAuditor.nombre = false;
                                errorsEquipoAuditor.rol = false;
                            }
                        "
                            :class="errorsEquipoAuditor.id_usuario ? 'is-invalid' : ''"
                            :disabled="
                            formEquipoAuditor.nombre.trim() !== '' ||
                            formEquipoAuditor.rol.trim() !== ''
                        ">

                            <option value="">
                                Selecciona un usuario...
                            </option>

                            <template
                                x-for="usuario in usuarios"
                                :key="usuario.id">

                                <option
                                    :value="usuario.id"
                                    x-text="
                                    usuario.nombre +
                                    (usuario.puesto
                                        ? ' (' + usuario.puesto + ')'
                                        : '')
                                ">
                                </option>

                            </template>

                        </select>

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
                        @click="guardarAuditor()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Auditor Equipo -->

    <!-- Modal Documentación de Hallazgo -->
    <div
        class="modal fade"
        id="modalConforme"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        III. DOCUMENTACIÓN DE LOS HALLAZGOS NO CONFORMES
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            * Descripción del hallazgo:
                        </label>

                        <textarea
                            class="form-control rounded-0"
                            rows="3"
                            x-model="formConforme.descripcion"
                            @input="errorsConforme.descripcion = false"
                            :class="
                            errorsConforme.descripcion
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            * Evidencia:
                        </label>

                        <textarea
                            class="form-control rounded-0"
                            rows="3"
                            x-model="formConforme.evidencia"
                            @input="errorsConforme.evidencia = false"
                            :class="
                            errorsConforme.evidencia
                                ? 'is-invalid'
                                : ''
                        ">
                    </textarea>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            * Criterio:
                        </label>

                        <textarea
                            class="form-control rounded-0"
                            rows="3"
                            x-model="formConforme.criterio"
                            @input="errorsConforme.criterio = false"
                            :class="
                            errorsConforme.criterio
                                ? 'is-invalid'
                                : ''
                        ">
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
                        @click="guardarConforme()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Documentación de Hallazgo -->

    <!-- Modal Oportunidad de Mejora -->

    <div
        class="modal fade"
        id="modalMejora"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-lg">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h5 class="modal-title text-white">
                        IV. OPORTUNIDADES DE MEJORA/OBSERVACIONES
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            * Descripción:
                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            x-model="formMejora.descripcion"
                            @input="errorsMejora.descripcion = false"
                            :class="
                            errorsMejora.descripcion
                                ? 'is-invalid'
                                : ''
                        ">
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
                        @click="guardarMejora()">

                        <i class="ti ti-check"></i>

                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal Oportunidad de Mejora -->

</div>