<div id="container" class="pb-4" x-data="{...actions(), ...capacitacion()}">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                </li>
                <li>
                    <a class="dropdown-item" @click="openBuscar()"><i class="ti ti-search"></i> Buscar</a>
                </li>
                <li>
                    <a class="dropdown-item"
                        :href="'/sgm/gestion-recursos/programa-capacitacion-externa/pdf/' + buscar.year" download>
                        <i class="ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="datatables mt-3">
        <div class="table-responsive">
            <table id="table-capacitacion-externa" class="table table-striped table-bordered align-middle">
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
                        <th class="text-center align-middle" width="35px"><i class="ti ti-dots-vertical fs-6 text-muted"></i></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo ---->
    <div
        class="modal fade"
        id="modalNuevo"
        x-ref="modalNuevo"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Agregar programa anual de capacitacion externa
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <label class="form-label">
                        * Nombre del curso:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        x-model="form.nombre_curso"
                        @input="errors.nombre_curso = false"
                        :class="errors.nombre_curso ? 'is-invalid' : ''">

                    <div class="row">
                        <div class="col-sm-6 col-6">
                            <label class="form-label mt-3">
                                * Fecha programada:
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="form.fecha_programada"
                                @input="errors.fecha_programada = false"
                                :class="errors.fecha_programada ? 'is-invalid' : ''">
                        </div>
                        <div class="col-sm-6 col-6">
                            <label class="form-label mt-3">
                                * Duración:
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                x-model="form.duracion"
                                @input="errors.duracion = false"
                                :class="errors.duracion ? 'is-invalid' : ''">
                        </div>
                    </div>


                    <label class="form-label mt-3">
                        * Instructor:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        x-model="form.instructor"
                        @input="errors.instructor = false"
                        :class="errors.instructor ? 'is-invalid' : ''">

                    <div x-show="modo=='edit'">

                        <div>

                            <label class="form-label mt-3">
                                Fecha real
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="form.fecha_real">

                        </div>

                    </div>

                    <div x-show="modo=='edit'">

                        <div class="mt-3">

                            <hr>

                            <label class="form-label">
                                Nombre de las personas que asistieron al curso
                            </label>
                            <div
                                class="select2-modal-field is-select2-pending"
                                x-ref="personalWrapper">

                                <select
                                    id="selectPersonal"
                                    x-ref="selectPersonal"
                                    multiple>

                                    <template
                                        x-for="usuario in form.usuarios"
                                        :key="usuario.id">

                                        <option
                                            :value="usuario.id"
                                            x-text="usuario.nombre">
                                        </option>

                                    </template>

                                </select>

                            </div>

                            <div class="text-end">
                                <button
                                    class="btn btn-info mt-2"
                                    @click="guardarPersonal()">

                                    <i class="ti ti-check"></i> Agregar Personal

                                </button>
                            </div>

                            <table class="table table-sm table-bordered mt-3">

                                <thead>

                                    <tr>

                                        <th>Nombre</th>

                                        <th class="text-center" width="70">

                                            <i class="ti ti-trash fs-6 text-muted"></i>

                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <template
                                        x-for="persona in form.personal"
                                        :key="persona.id">

                                        <tr>

                                            <td
                                                x-text="persona.nombre">
                                            </td>

                                            <td class="text-center">

                                                <a
                                                    @click="eliminarPersonal(persona.id)">

                                                    <i class="ti ti-trash fs-6 text-danger"></i>

                                                </a>

                                            </td>

                                        </tr>

                                    </template>

                                </tbody>

                            </table>

                            <hr>
                        </div>

                    </div>

                    <div x-show="modo=='edit'">

                        <div class="mt-4">

                            <label class="form-label">
                                Evidencia
                            </label>

                            <div class="d-flex gap-2">

                                <input
                                    id="FileEvidencia"
                                    type="file"
                                    class="form-control">

                                <button
                                    class="btn btn-info"
                                    @click="guardarEvidencia()">
                                    Agregar

                                </button>

                            </div>

                            <table
                                class="table table-sm mt-3">

                                <tbody>

                                    <template
                                        x-for="evidencia in form.evidencias"
                                        :key="evidencia.id">

                                        <tr>

                                            <td>

                                                <a
                                                    :href="'/uploads/archivos/sgm/'+evidencia.archivo"
                                                    target="_blank"
                                                    x-text="evidencia.archivo">
                                                </a>

                                            </td>

                                            <td width="60">

                                                <a @click="eliminarEvidencia(evidencia.id)">

                                                    <i class="ti ti-trash fs-7 text-danger"></i>

                                                </a>

                                            </td>

                                        </tr>

                                    </template>

                                </tbody>

                            </table>

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
                        @click="guardarRegistro()">
                        <i class="ti ti-check"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal Nuevo ---->

    <!-- Modal Buscar ---->
    <div
        class="modal fade"
        id="modalBuscar"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Buscar programación externa
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
    <!-- Modal Buscar ---->

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