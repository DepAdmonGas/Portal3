<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...gestionRecursos() }">

    <h5 class="fw-semibold mt-4">
        1. Gestión de personal, funciones y roles
    </h5>
    <div class="row mt-3">
        <div class="col-md-6">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
<h4 class="card-title mb-0">Fo.SGM.007 Designación de responsable SGM</h4>
<div class="text-nowrap">
  <?=
                        !empty($permisos['crear']) ?
                            '<button type="button" class="btn bg-primary-subtle text-primary" @click="openNuevo()">
                            <i class="ti ti-plus"></i> Nuevo
                            </button>'
                            : ''
                        ?>
                   
</div>

                </div>
                <div class="card-body p-0">

                    <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

                        <thead>

                            <tr>

                                <th class="text-center">#</th>

                                <th class="text-center">Fecha</th>

                                <th class="text-center" width="48-px"><a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a></th>

                            </tr>

                        </thead>


                        <tbody>


                            <template x-for="(item,index) in lista" :key="item.id">


                                <tr>

                                    <td
                                        class="text-center fw-bold"
                                        width="96px"
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

<div class="col-md-6 d-flex align-items-stretch mb-4">
        <a href="/personal/SGM"
           class="card h-75 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-user text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Fo.SGM.008 Lista de personal
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver personal</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>



    
    </div>

    <h5 class="mt-2 fw-semibold">
        2. Capacitación del personal
    </h5>

    <div class="row mt-3">

    <!----------card nueva programa de capacitacion interna------->
  <div class="col-md-4 d-flex align-items-stretch mb-4">
       <a href="/sgm/gestion-recursos/programa-capacitacion-interna"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-school text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Programa Capacitacion Interna
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>



    <!----------card nueva programa de capacitacion externa------->
  <div class="col-md-4 d-flex align-items-stretch mb-4">
       <a href="/sgm/gestion-recursos/programa-capacitacion-externa"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-school text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Programa Capacitacion Externa
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>


      <!----------card nueva programa de capacitacion de introduccion------->
  <div class="col-md-4 d-flex align-items-stretch mb-4">
       <a href="/sgm/gestion-recursos/programa-capacitacion-induccion"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-school text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Capacitación de inducción
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    </div>

    <div class="row">


        <div class="col-md-6">
            <h5 class="fw-semibold mb-3">
                3. Gestión de equipos
            </h5>


           <!----------card nueva gestion de equipos------->
  <div class="col-md-12 d-flex align-items-stretch mb-4">
       <a href="/sgm/gestion-recursos/inventario-equipo"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4  mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-device-desktop-plus text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Inventario de equipo
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>
        </div>



        <div class="col-md-6">
            <h5 class="fw-semibold mb-3">
                4. Evaluación de proveedores y servicios
            </h5>

            
                <!----------card nueva evaluacion de proveedores y servicios------->
  <div class="col-md-12 d-flex align-items-stretch mb-4">
       <a href="/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4  mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-file-invoice text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Orden de servicio y Evaluación de proveedores
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
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

                    <h4 class="modal-title text-white d-flex align-items-center">
                    <i class="ti ti-user-cog fs-6 me-2"></i>
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

                        <option value="">Seleccione una opcion...</option>

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

                        <option value="">Seleccione una opcion...</option>

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

<?php endif; ?>

</div>