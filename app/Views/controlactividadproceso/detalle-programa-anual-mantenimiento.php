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
            '<li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openModalNuevo()"><i class="ti ti-plus"></i> Nuevo </a></li>' 
            : '' 
            ?>
            <li>
                <a class="dropdown-item pointer" 
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
            <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
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

        <div class="modal-header modal-colored-header bg-primary text-white">
            <h4 class="modal-title text-white">
            <i class="ti ti-settings-plus"></i>   
            Nuevo equipo o instalación</h4>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" @click="limpiarNuevo()"></button>
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

                    <option value="">Selecciona una opción...</option>

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
                <i class="ti ti-x"></i>
                Cancelar
            </button>
            <button class="btn btn-success" @click="guardarNuevo()">
                <i class="ti ti-check"></i>
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

            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                    <i class="ti ti-edit"></i> 
                <label x-text="editarData.detalle">
                </label>
                
            </h4>

                <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="modal"
                @click="limpiarEditar()">
                </button>
            </div>

            <div class="modal-body">

                <div class="table-responsive">

        <table class="table table-striped table-bordered mb-0 align-middle">

<thead>
    <tr>
     <th class="text-center align-middle" width="100px">Mes</th>
     <th class="text-center align-middle">Fecha</th>
    </tr>
</thead>

<tbody>
<template
x-for="(config, mes) in editarData.meses"
:key="mes">

<tr>
    <td class="text-center align-middle text-capitalize" x-text="mes"></td>
    <td class="text-center align-middle p-0">
        <input
type="date"
class="form-control bg-transparent border-0 p-3 text-center align-middle"
x-model="config.value"
:min="config.min"
:max="config.max"
:disabled="config.disabled">
</td>
</tr>



</template>
</tbody>
</table>

                </div>
            </div>

            <div class="modal-footer">

                <button
                
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="limpiarEditar()">
                <i class="ti ti-x"></i>
                    Cancelar
                </button>

                <button
                class="btn btn-success"
                @click="editar()">
                <i class="ti ti-check"></i>
                    Guardar
                </button>

            </div>

        </div>
    </div>
</div>


</div>