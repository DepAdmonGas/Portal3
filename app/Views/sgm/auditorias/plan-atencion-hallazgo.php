<div x-data="{...actions(), ...planatencion(<?= $id ?>)}">

    <!-- ===================================================== -->
    <!-- I. DATOS GENERALES -->
    <!-- ===================================================== -->

    <div class="bg-white p-3 mt-3">

        <div class="table-responsive">

            <table class="table table-bordered table-sm">

                <tbody>
                    <tr>
                        <td colspan="3" class="bg-muted text-white">
                            <b>
                                I. DATOS GENERALES DEL PERMISIONARIO
                            </b>
                        </td>
                    </tr>

                    <tr>

                        <td class="text-center align-middle fw-bolder bg-light">
                            NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            PERMISO CRE:
                        </td>

                        <td class="text-center align-middle fw-bolder bg-light">
                            FECHA DEL INFORME DE AUDITORÍA
                            (Reporte de hallazgos de auditorias):
                        </td>

                    </tr>


                    <tr>

                        <td class="text-center align-middle"
                            x-text="plan.razon_social || ''">
                        </td>

                        <td class="text-center align-middle"
                            x-text="plan.permiso_cre || ''">
                        </td>

                        <td class="p-0 m-0">

                            <input
                                type="date"
                                class="form-control text-center border-0 rounded-0"
                                x-model="plan.fecha"
                                @change="editar('fecha')">

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="2"
                            class="text-center align-middle fw-bolder bg-light">
                            SITIO/ÁREA:
                        </td>

                        <td
                            class="text-center align-middle fw-bolder bg-light">
                            RESPONSABLE:
                        </td>

                    </tr>


                    <tr>

                        <td
                            class="p-0 m-0"
                            colspan="2">

                            <input
                                type="text"
                                class="form-control border-0 rounded-0"
                                x-model="plan.sitio_area"
                                @change="editar('sitio_area')">

                        </td>


                        <td class="p-0 m-0">

                            <select
                                class="form-select rounded-0 border-0"
                                x-model="plan.responsable"
                                @change="editar('responsable')">

                                <option value="0">
                                    Seleccione responsable
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


                    <!-- ================================================= -->
                    <!-- II. HALLAZGO -->
                    <!-- ================================================= -->

                    <tr>
                        <td colspan="3" class="bg-muted text-white">
                            <b>
                                II. HALLAZGO:
                                (DESCRIPCIÓN/EVIDENCIA/CRITERIO)
                            </b>
                        </td>
                    </tr>

                    <tr>
                        <td
                            colspan="3"
                            class="p-0 m-0">
                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.hallazgo"
                                @change="editar('hallazgo')"></textarea>
                        </td>
                    </tr>


                    <!-- ================================================= -->
                    <!-- III. CAUSA RAÍZ -->
                    <!-- ================================================= -->

                    <tr>

                        <td colspan="3" class="bg-muted text-white">

                            <b>
                                III. ANÁLISIS DE LA CAUSA RAÍZ
                            </b>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="3"
                            class="p-0 m-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.analisis_causa"
                                @change="editar(
                                    'analisis_causa'
                                )"></textarea>

                        </td>

                    </tr>


                    <!-- ================================================= -->
                    <!-- IV. ACCIONES -->
                    <!-- ================================================= -->

                    <tr>

                        <td colspan="3" class="bg-muted text-white">

                            <b>
                                IV. ACCIONES PARA LA ATENCIÓN
                                DE LOS HALLAZGOS NO CONFORMES
                            </b>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="3"
                            class="p-0 m-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="5"
                                x-model="plan.acciones_hallazgos"
                                @change="editar(
                                    'acciones_hallazgos'
                                )"></textarea>

                        </td>

                    </tr>


                    <!-- ================================================= -->
                    <!-- V. RESPONSABLES -->
                    <!-- ================================================= -->

                    <tr>

                        <td
                            colspan="2"
                            class="bg-muted text-white align-middle">

                            <b>
                                V. NOMBRE DE LOS RESPONSABLES
                                DEL CUMPLIMIENTO DE LAS ACCIONES
                            </b>

                        </td>


                        <td class="p-0 m-0">

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


                    <!-- ================================================= -->
                    <!-- VI. FECHA COMPROMISO -->
                    <!-- ================================================= -->

                    <tr>

                        <td colspan="3" class="bg-muted text-white">

                            <b>
                                VI. FECHAS COMPROMISO PARA EL CUMPLIMIENTO
                                DE LA IMPLEMENTACIÓN DE ACCIONES
                            </b>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="3"
                            class="p-0 m-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="3"
                                x-model="plan.fecha_complimiento"
                                @change="editar(
                                    'fecha_complimiento'
                                )"></textarea>

                        </td>

                    </tr>


                    <!-- ================================================= -->
                    <!-- VII. RECURSOS -->
                    <!-- ================================================= -->

                    <tr>

                        <td colspan="3" class="bg-muted text-white">

                            <b>
                                VII. RECURSOS ASIGNADOS PARA
                                LA IMPLEMENTACIÓN DE ACCIONES
                            </b>

                        </td>

                    </tr>


                    <tr>

                        <td
                            colspan="3"
                            class="p-0 m-0">

                            <textarea
                                class="form-control rounded-0 border-0"
                                rows="3"
                                x-model="plan.recursos_implementacion"
                                @change="editar(
                                    'recursos_implementacion'
                                )"></textarea>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ===================================================== -->
    <!-- PARTE FINAL -->
    <!-- ===================================================== -->

    <div class="bg-white p-3 mt-3">

        <div class="table-responsive">

            <table class="table table-bordered table-sm">

                <tbody>

                    <tr>

                        <td class="fw-bolder bg-light">

                            FECHA DEL PLAN DE ATENCIÓN
                            DE HALLAZGOS:

                        </td>

                        <td class="p-0 m-0">

                            <input
                                type="date"
                                class="form-control border-0 rounded-0"
                                x-model="plan.fecha_atencion_hallazgos"
                                @change="editar(
                                    'fecha_atencion_hallazgos'
                                )">

                        </td>

                    </tr>


                    <tr>

                        <td class="fw-bolder bg-light">

                            RESPONSABLE DEL SGM:

                        </td>

                        <td class="p-0 m-0">

                            <select
                                class="form-select rounded-0 border-0"
                                x-model="plan.responsable_sgm"
                                @change="editar(
                                    'responsable_sgm'
                                )">

                                <option value="0">
                                    Seleccione responsable
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


            <div class="text-end">

                <button
                    type="button"
                    class="btn btn-success"
                    @click="finalizar">

                    <i class="ti ti-check"></i>
                    Finalizar Plan de atención de Hallazgos

                </button>

            </div>

        </div>

    </div>

</div>