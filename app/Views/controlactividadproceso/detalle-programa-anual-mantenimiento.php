<?php 
/** @var int $idPrograma */
?>
<div id="container" class="mb-4" data-idprograma="<?= $idPrograma ?>"
x-data="{ ...actions(), ...programaAnualMantenimiento() }">

    <div class="text-end">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
            <?= !empty($permisos['crear']) ? 
            '<li><a class="dropdown-item"  href="javascript:void(0)" @click="openModalNuevo()"><i class="ti ti-plus"></i> Nuevo </a></li>' 
            : '' 
            ?>
            <li>
                <a class="dropdown-item" 
                href="/sasisopa/control-actividades-procesos/pdf-programa-anual-mantenimiento/<?= $idPrograma ?>">
                    <i class="ti ti-download"></i>
                    Descargar
                </a>
            </li>
            </ul>
        </div>
    </div>

    <table class="table table-bordered table-sm mt-3">
    <tr>
    <td class="text-center align-middle"><img class="text-center" src="<?= $_ENV['APP_URL'] . '/assets/images/logos/Logo.png' ?>" style="width: 200px;"></td>
    <td colspan="2" class="text-center align-middle">Programa Anual de Mantenimiento</td>
    <td class="text-center align-middle">Fo. ADMONGAS.011</td>
    </tr>
    <tr>
    <td class="text-center align-middle">Realizado por: Nelly Estrada Garcia </td>
    <td class="text-center align-middle">Revisado por: Eduardo Galicia Flores </td>
    <td class="text-center align-middle">Autorizado por: Tomas Tarno Quinzaños </td>
    <td class="text-center align-middle">Fecha de autorizacion 01/10/2018</td>
    </tr>
</table>

    <div class="datatables">
        <div class="table-responsive">
        <table id="table-programa-anual" class="table table-bordered mb-0 align-middle">
            <thead>
            <tr>
            <th>#</th>
                <th>Equipo o instalación</th>
                <th style="max-width:100px;">Enero</th>
                <th>Febrero</th>
                <th>Marzo</th>
                <th>Abril</th>
                <th>Mayo</th>
                <th>Junio</th>
                <th>Julio</th>
                <th>Agosto</th>
                <th>Septiembre</th>
                <th>Octubre</th>
                <th>Noviembre</th>
                <th>Diciembre</th>
            <th class="text-center">
            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
            </th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

    <!-- Modal Nuevo --->

    <div class="modal fade" id="modalNuevo" tabindex="-1">
        <div class="modal-dialog">
        <div class="modal-content">

        <div class="modal-header head-modal">
            <h4 class="modal-title">Agregar equipo o instalación</h4>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" @click="limpiarNuevo()"></button>
        </div>

        <div class="modal-body">

        <!-- EQUIPO -->

            <div class="mb-3">

                <label class="form-label">* Equipo o instalación:</label>
                <select class="form-select"
                x-model="nuevo.id_mantenimiento"
                @change="selectEquipo()"
                :class="errors.id_mantenimiento ? 'is-invalid' : ''"
                @input="errors.id_mantenimiento = false">

                    <option value="">Selecciona</option>

                    <template
                    x-for="item in equipos"
                    :key="item.id">

                        <option
                        :value="item.id"
                        x-text="
                        item.id + '.- ' + item.detalle">
                        </option>

                    </template>

                </select>

            </div>

            <!-- PERIODICIDAD -->

            <div class="mb-3">
                <label class="form-label">Periodicidad:</label>
                <input
                type="text"
                class="form-control"
                x-model="nuevo.periodicidad"
                :class="errors.periodicidad ? 'is-invalid' : ''"
                @input="errors.periodicidad = false"
                disabled>
            </div>

            <!-- ULTIMA FECHA -->

            <div class="mb-3">
                <label class="form-label">Última fecha:</label>

                <input type="date"
                class="form-control"
                x-model="nuevo.ultimafecha"
                :max="year + '-12-31'"
                :class="errors.ultimafecha ? 'is-invalid' : ''"
                @input="errors.ultimafecha = false">
            </div>

        </div>
        <div class="modal-footer">
            <button 
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                aria-label="Close"
                @click="limpiarNuevo()">
                Cancelar
            </button>
            <button class="btn btn-primary" @click="guardarNuevo()">
                Guardar
            </button>
        </div>
        </div>
    </div>
    </div>

    <!-- Modal editar -->

   <div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header head-modal">
                <h4 class="modal-title"
                x-text="editarData.detalle">
                </h4>

                <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                @click="limpiarEditar()">
                </button>
            </div>

            <div class="modal-body">

                <div class="row">

                    <template
                    x-for="(config, mes) in editarData.meses"
                    :key="mes">

                       <div class="col-xl-4 col-lg-4 col-md-6 col-12">

                        <div class="card">

                            <div
                            class="card-header bg-light text-center fs-5 text-capitalize"
                            x-text="mes">
                            </div>

                            <div class="card-body">

                                <input
                                type="date"
                                class="form-control"
                                x-model="config.value"
                                :min="config.min"
                                :max="config.max"
                                :disabled="config.disabled">

                            </div>

                        </div>

                    </div>

                    </template>
                </div>
            </div>

            <div class="modal-footer">

                <button
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="limpiarEditar()">
                    Cancelar
                </button>

                <button
                class="btn btn-primary"
                @click="editar()">
                    Guardar
                </button>

            </div>

        </div>
    </div>
</div>


</div>