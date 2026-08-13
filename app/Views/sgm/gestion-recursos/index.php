<div id="container" class="pb-4" x-data="{ ...actions(), ...gestionRecursos() }">

    <div class="mt-4 fs-6">
        1. Gestión de personal, funciones y roles
    </div>
    <div class="row mt-3">
        <div class="col-md-6">

            <div class="card">
                <div class="card-body">

                    <div class="float-end">
                        <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn btn-primary" @click="openNuevo()">
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                    </div>

                    <h4 class="card-title mb-0">Fo.SGM.007 Designación de responsable SGM</h4>


                    <table class="table table-bordered table-striped table-hover table-sm mt-4 align-middle">

                        <thead class="bg-primary text-white">

                            <tr>

                                <th class="text-center">#</th>

                                <th class="text-center">Fecha</th>

                                <th class="text-center"><a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a></th>

                            </tr>

                        </thead>


                        <tbody>


                            <template x-for="(item,index) in lista" :key="item.id">


                                <tr>

                                    <td
                                        class="text-center fw-bold"
                                        x-text="index+1">
                                    </td>


                                    <td
                                        class="text-center"
                                        x-text="item.fecha">
                                    </td>



                                    <td class="text-center">

                                        <div class="dropdown dropstart">
                                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical fs-6"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-delete" :href="'/sgm/gestion-recursos/responsable/pdf/' + item.id" download>
                                                        <i class="fs-4 ti ti-download"></i>Descargar
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-3"
                                                        @click="eliminar(item.id)">
                                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>


                                    </td>


                                </tr>


                            </template>



                            <tr x-show="lista.length==0">

                                <td colspan="3" class="text-center text-secondary">

                                    No se encontró información

                                </td>

                            </tr>


                        </tbody>


                    </table>

                </div>
            </div>

        </div>

        <div class="col-md-6">

            <div class="card">
                <div class="card-body">

                    <div class="d-flex align-items-center">
                        <h4 class="card-title mb-0">Fo.SGM.008 Lista de personal</h4>
                    </div>

                    <div class="text-end mt-4">
                        <a type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info"
                            href="/personal/SGM">
                            <i class="ti ti-eye"></i>
                            Ver detalle
                        </a>
                    </div>


                </div>
            </div>


        </div>
    </div>

    <div class="mt-2 fs-6">
        2. Capacitación del personal
    </div>

    <div class="row mt-2">

        <div class="col-md-3">
            <a href="/sgm/gestion-recursos/programa-capacitacion-interna">
                <div class="card bg-primary">
                    <div class="card-body text-white fs-5">Programa Capacitacion Interna</div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="/sgm/gestion-recursos/programa-capacitacion-externa">
                <div class="card bg-primary">
                    <div class="card-body text-white fs-5">Programa Capacitacion Externa</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="/sgm/gestion-recursos/programa-capacitacion-induccion">
                <div class="card bg-primary">
                    <div class="card-body text-white fs-5">Capacitación de inducción</div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">


        <div class="col-md-3">
            <div class="fs-6">
                3. Gestión de equipos
            </div>
            <div class="card bg-primary mt-2">
                <a href="/sgm/gestion-recursos/inventario-equipo">
                    <div class="card-body text-white fs-5">Inventario de equipo</div>
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="fs-6">
                4. Evaluación de proveedores y servicios
            </div>
            <div class="card bg-primary mt-2">
                <a href="/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores">
                    <div class="card-body text-white fs-5">Orden de servicio y Evaluación de proveedores</div>
                </a>
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

            <p><b>1. Gestión de personal, funciones y roles</b></p>

            <p>El representante legal deberá asignar al responsable de la implementación del SGM (asi como a su auxiliar de apoyo) mediante el formato 007, en caso de rotación o cambio de funciones y responsabilidades la designación del nuevo responsable deberá volverse a realizar el formato.</p>
            <p>Mantén actualizada la lista del personal que labora en la empresa mediante el formato 008</p>
            <hr>

            <p><b>2. Capacitación del personal</b></p>
            <p>De manera anual verifica el programa de capacitación interna y externa de acuerdo al procedimiento con el formato 009, verifica los puestos estén capacitados conforme a lo establecido en el procedimiento.</p>
            <p>Recuerda que cada que haya personal nuevo en las instalaciones deberá tomar la capacitación de inducción, por lo que cada que agregues a un nuevo colaborador en el formato 008 en automático le saldrán los cursos que debe tomar como inducción en el formato 010.
            </p>
            <hr>

            <p><b>3. Gestión de equipos</b></p>
            <p>Realiza y mantén actualizado el inventario de equipos de medición para cumplir los requisitos metrológicos, esta actividad la debes registrar en el formato 011 que a continuación se desplega. Entre los equipos que debes de registrar te dejo como dato los siguientes:</p>

            <ul class="list-group list-group-flush">
                <li class="list-group-item">Tanques de almacenamiento </li>
                <li class="list-group-item">Sondas de nivel y temperatura </li>
                <li class="list-group-item">Dispensarios </li>
                <li class="list-group-item">Jarras patrón </li>
                <li class="list-group-item">Sistema de control de inventarios </li>
                <li class="list-group-item">Cinta petrolera</li>
                <li class="list-group-item">Termómetro </li>
                <li class="list-group-item">Cronómetros, entre otros</li>
            </ul>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

    <div
        class="modal fade"
        id="modalNuevo"
        tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Fo.SGM.007 Designación de responsable SGM
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">


                    <label class="form-label">* Fecha:</label>

                    <input
                        type="date"
                        class="form-control"
                        x-model="form.fecha"
                        @input="errors.fecha = false"
                        :class="errors.fecha ? 'is-invalid' : ''">


                    <label class="form-label mt-3">
                        * Nombre y firma de conformidad del responsable de implementación del Sistema de Gestión de Medición
                    </label>


                    <select
                        class="form-control"
                        x-model="form.responsable"
                        @change="errors.responsable = false"
                        :class="errors.responsable ? 'is-invalid' : ''">

                        <option value=""></option>

                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario->id ?>">
                                <?= $usuario->nombre ?>
                            </option>
                        <?php endforeach; ?>

                    </select>


                    <label class="form-label mt-3">
                        * Personal especializado que auxiliará en las tareas de implementación del Sistema de Gestión de Medición
                    </label>


                    <select
                        class="form-control"
                        x-model="form.auxiliar"
                        @change="errors.auxiliar = false"
                        :class="errors.auxiliar ? 'is-invalid' : ''">

                        <option value=""></option>

                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario->id ?>">
                                <?= $usuario->nombre ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i> Cancelar

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

</div>