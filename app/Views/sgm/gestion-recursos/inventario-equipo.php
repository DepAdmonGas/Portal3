<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...inventario()}">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item pointer" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                </li>
                <li>
                    <a class="dropdown-item"
                        href="/sgm/gestion-recursos/inventario-equipo/pdf" download>
                        <i class=" ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="datatables mt-3">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="table-inventario-equipo" class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th class="text-center align-middle" whidth="98 px">#</th>
                        <th class="text-center align-middle">Nombre del equipo de medición</th>
                        <th class="text-center align-middle">Identificación</th>
                        <th class="text-center align-middle">Función que desempeña dentro de la ES</th>
                        <th class="text-center align-middle">Fecha de instalación</th>
                        <th class="text-center align-middle">Manuales, garantías o información </th>
                        <th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6 text-muted"></i></th>
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
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
<span>
    <i class="ti" :class="modo==='create'? ' ti-device-desktop-plus' :'ti-edit'"></i>
</span>
                        <template x-if="modo == 'create'">
                            <span>Nuevo inventario de equipo</span>
                        </template>

                        <template x-if="modo == 'edit'">
                            <span>Editar inventario de equipo</span>
                        </template>

                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                 

                        <label class="form-label mb-2">
                            * Nombre del equipo de medición:
                        </label>

                        <select
                            class="form-select mb-3"
                            x-model="form.nombre"
                            @input="errors.nombre = false"
                            :class="errors.nombre ? 'is-invalid' : ''">

                            <option value="">
                                Seleccione una opcion...
                            </option>

                            <option>
                                Tanques de almacenamiento
                            </option>

                            <option>
                                Sondas de nivel y temperatura
                            </option>

                            <option>
                                Dispensarios
                            </option>

                            <option>
                                Jarras patrón
                            </option>

                            <option>
                                Sistema de control de inventarios
                            </option>

                            <option>
                                Cinta petrolera
                            </option>

                            <option>
                                Termómetro
                            </option>

                            <option>
                                Cronómetros
                            </option>

                        </select>


                        <label class="form-label mb-2">
                            * Identificación:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="2"
                            x-model="form.identificacion"
                            @input="errors.identificacion = false"
                            :class="errors.identificacion ? 'is-invalid' : ''">
                    </textarea>

                   

              

                        <label class="form-label mb-2">
                            * Función que desempeña dentro de la ES:
                        </label>

                        <textarea
                            class="form-control mb-3"
                            rows="3"
                            x-model="form.funcion"
                            @input="errors.funcion = false"
                            :class="errors.funcion ? 'is-invalid' : ''">
                    </textarea>

                  


                        <label class="form-label mb-2">
                            * Fecha de instalación:
                        </label>

                        <input
                            type="date"
                            class="form-control mb-3"
                            x-model="form.fecha_instalacion">


                    <!-- Manuales -->

                    <template x-if="modo == 'edit'">

                        <div>
                            <label class="form-label fw-bolder mb-2">

                                Manuales, garantías o información documental:

                            </label>

                            <div class="input-group mb-3">

                                

                                    <input
                                        type="file"
                                        class="form-control"
                                        x-ref="manual">

                                

                               

                                    <button
                                        class="btn bg-primary-subtle text-primary"
                                        @click="subirManual()">

                                        <i class="ti ti-plus"></i>

                                        Agregar

                                    </button>

                                

                            </div>

                            <div class="table-responsive">

                                <table class="table table-striped table-bordered text-nowrap align-middle">

                                    <thead>

                                        <tr>

                                            <th class="text-center"> #
                                            </th>

                                            <th class="text-center">
                                                Fecha
                                            </th>

                                            <th>
                                                Archivo
                                            </th>

                                            <th class="text-center" width="48">
                                                <i class="ti ti-trash fs-6 text-muted"></i>
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <template
                                            x-if="form.manuales.length==0">

                                            <tr>

                                                <td
                                                    colspan="4"
                                                    class="text-center text-muted">

                                                    No hay manuales registrados.

                                                </td>

                                            </tr>

                                        </template>

                                        <template
                                            x-for="(manual,index) in form.manuales"
                                            :key="manual.id">

                                            <tr>

                                                <td
                                                    class="text-center"
                                                    whidth="98 px"
                                                    x-text="index+1">
                                                </td>

                                                <td
                                                    x-text="manual.fecha_hora">
                                                </td>

                                                <td>

                                                    <a
                                                        :href="manual.url"
                                                        target="_blank"
                                                        x-text="manual.archivo">
                                                    </a>

                                                </td>

                                                <td
                                                    class="text-center">

                                                    <a class="pointer" @click="eliminarManual(manual.id)">

                                                        <i class="ti ti-trash fs-6 text-danger"></i>

                                                    </a>

                                                </td>

                                            </tr>

                                        </template>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </template>

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

    <!-- Modal Detalle ---->
    <div
        class="modal fade"
        id="modalManuales"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                      <i class="ti ti-vocabulary fs-6"></i>  
                        Manuales, garantías o información documental del equipo
                    </h4>

                    <button
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div>

                        <div>
                            <label class="form-label fw-bolder mb-1">
                                Nombre del equipo:
                            </label>

                            <div
                            class="mb-3 "
                                x-text="manuales.nombre">
                            </div>
                        </div>



                    </div>

                    <div >

                        <div>
                            <label class="form-label fw-bolder mb-1">
                                Identificación:
                            </label>

                            <div
                            class="mb-3"
                                x-text="manuales.identificacion">
                            </div>
                        </div>



                    </div>

                    <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                        <thead>

                            <tr>

                                <th  class="text-center">
                                    #
                                </th>

                                <th class="text-center">
                                    Fecha
                                </th>

                                <th>
                                    Archivo
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <template
                                x-if="manuales.lista.length==0">

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center text-muted">

                                        No se encontraron manuales.

                                    </td>

                                </tr>

                            </template>

                            <template
                                x-for="(manual,index) in manuales.lista"
                                :key="manual.id">

                                <tr>

                                    <td
                                    class="text-center"
                                    whidth="96 px"
                                        x-text="index+1">
                                    </td>

                                    <td
                                    class="text-center"
                                        x-text="manual.fecha_hora">
                                    </td>

                                    <td>

                                        <a
                                            :href="manual.url"
                                            target="_blank"
                                            x-text="manual.archivo">
                                        </a>

                                    </td>

                                </tr>

                            </template>

                        </tbody>

                    </table>

                </div>

                <div class="modal-footer">

                    <button
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i>

                        Cerrar

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

            <p>Realiza y mantén actualizado el inventario de equipos de medición para cumplir los requisitos metrológicos, esta actividad la debes registrar en el formato 011 que a continuación se desplega. Entre los equipos que debes de registrar te dejo como dato los siguientes:</p>

            <ul class="list-group list-group-flush">
                <li class="list-group-item">Tanques de almacenamiento</li>
                <li class="list-group-item">Sondas de nivel</li>
                <li class="list-group-item">Sondas de temperatura</li>
                <li class="list-group-item">Dispensarios</li>
                <li class="list-group-item">Jarras patrón</li>
                <li class="list-group-item">Sistema de control de inventarios</li>
                <li class="list-group-item">Cinta petrolera</li>
                <li class="list-group-item">Termómetro</li>
                <li class="list-group-item">Cronómetros, entre otros</li>
            </ul>

        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>

<?php endif; ?>

</div>