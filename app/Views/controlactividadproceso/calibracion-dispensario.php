<?php
/** @var \App\Models\Sgm\CalibracionEquipo $calibracion */
?>
<div id="container"
     data-module-station-key="sasisopa"
     data-estacion-id="<?= $estacionId ?? '' ?>"
     class="pb-4"
     x-data="{ ...actions(), ...calibracionDispensario() }">

    <script type="application/json" id="calibracion-data">
        <?= json_encode(
            $calibracion,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>
    </script>

    <template x-if="calibracion.estado == 0">
<div class="d-md-flex justify-content-md-end mb-3 mt-3">
        <button
            type="button"
            class="btn btn-success"
            @click="finalizar('Dispensario')">

            <i class="ti ti-check"></i> Finalizar

        </button>
</div>
    </template>

    <div class="text-end mt-3">

    <template x-if="calibracion.estado == 1">

        <button
            type="button"
            class="btn btn-success"
            @click="window.location.href='/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos'">

            <i class="ti ti-check"></i> Finalizar 

        </button>

    </template>

</div>

    <div class="card">
        <div class="card-body p-3">

<div class="row">

        <div class="col-12 col-sm-3">
            <label class="form-label mb-2">Folio:</label>
            <div class="mb-3">
            <h5 x-text="'00' + calibracion.folio"></h5>
            </div>
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Fecha:</label>
            </div>

            <input
                type="date"
                class="form-control mb-3"
                x-model="calibracion.fecha_formateada"
                @change="editarCampo(
                    1,
                    calibracion.fecha_formateada,
                    'fecha_formateada'
                )"
                id="Fecha">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Hora:</label>
            </div>

            <input
                type="time"
                class="form-control mb-3"
                x-model="calibracion.hora"
                @change="editarCampo(
                    2,
                    calibracion.hora,
                    'hora'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Fecha término:</label>
            </div>

            <input
                type="date"
                class="form-control mb-3"
                x-model="calibracion.fecha_termino_formateada"
                @change="editarCampo(
                    12,
                    calibracion.fecha_termino_formateada,
                    'fecha_termino_formateada'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Hora término:</label>
            </div>

            <input
                type="time"
                class="form-control mb-3"
                x-model="calibracion.hora_termino"
                @change="editarCampo(
                    13,
                    calibracion.hora_termino,
                    'hora_termino'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Unidad de verificación:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.unidad_verificacion"
                @blur="editarCampo(
                    3,
                    calibracion.unidad_verificacion,
                    'unidad_verificacion'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">No. de acreditación:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.numero_acreditacion"
                @blur="editarCampo(
                    4,
                    calibracion.numero_acreditacion,
                    'numero_acreditacion'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Tipo calibración:</label>
            </div>

            <select
                class="form-control mb-3"
                x-model.number="calibracion.categoria"
                @change="editarCampo(
                    11,
                    calibracion.categoria,
                    'categoria'
                )">

                <option value="1">Ordinaria</option>
                <option value="2">Extraordinaria</option>

            </select>

        </div>

    </div>

    <div class="text-end mb-3 mt-3">

<button
    type="button"
    class="btn bg-primary-subtle text-primary"
    @click="
        cargarDispensariosDisponibles();
        new bootstrap.Modal(
            document.getElementById(
                'modalDispensario'
            )
        ).show();
    ">

    <i class="ti ti-plus"></i> Nuevo

</button>

</div>

<div class="table-responsive">
    <table class="table table-striped table-bordered mb-3 text-nowrap align-middle">

    <thead>
        <tr>
            <th class="align-middle text-center">No Dispensario</th>
            <th class="align-middle text-center">Marca</th>
            <th class="align-middle text-center">Modelo</th>
            <th class="align-middle text-center">Serie</th>
            <th class="align-middle text-center">¿Cumple con el error máximo tolerado?</th>
            <th class="align-middle text-center">¿Cumple con la repetibilidad?</th>
            <th class="align-middle text-center">Folio del holograma</th>
            <th class="align-middle text-center">Distintivo empresarial</th>
            <th class="text-center p-2"><i class="ti ti-trash fs-6 text-muted"></i></th>
        </tr>
    </thead>

    <tbody>

        <template
            x-for="item in calibracion.dispensarios"
            :key="item.id">

            <tr>

                <td class="align-middle text-center p-2"
                    x-text="item.dispensario.no_dispensario">
                </td>

                <td class="align-middle text-center p-2"
                    x-text="item.dispensario.marca">
                </td>

                <td class="align-middle text-center p-2"
                    x-text="item.dispensario.modelo">
                </td>

                <td class="align-middle text-center p-2"
                    x-text="item.dispensario.serie">
                </td>

                <td class="p-0 m-0">
                    <input
                    type="text"
                    class="text-center form-control border-0 rounded-0"
                    x-model="item.resultado1"
                    @blur="editarDispensario(item, 7, 'resultado1')">
                </td>

                <td class="p-0 m-0">
                   <input
                type="text"
                class="text-center form-control border-0 rounded-0"
                x-model="item.resultado2"
                @blur="editarDispensario(item, 8, 'resultado2')">
                </td>

                <td class="p-0 m-0">
                <input
                type="text"
                class="text-center form-control border-0 rounded-0"
                x-model="item.resultado3"
                @blur="editarDispensario(item, 9, 'resultado3')">
                </td>

                <td class="p-0 m-0">
                <input
                type="text"
                class="text-center form-control border-0 rounded-0"
                x-model="item.resultado4"
                @blur="editarDispensario(item, 10, 'resultado4')">
                </td>

                <td class="text-center align-middle p-2">
                <a href="javascript:void(0)" @click="eliminarDispensario(item.id)"><i class="ti ti-trash fs-6 text-danger"></i></a>
                </td>

            </tr>

        </template>

    </tbody>

</table>
</div>

<div class="row mt-3">

    <div class="col-12 col-md-6">

        <div class="text-secondary mb-0">
            <label class="form-label">Observaciones:</label>
        </div>

        <textarea
            class="form-control mb-3"
            rows="4"
            x-model="calibracion.observaciones"
            @blur="editarCampo(
                5,
                calibracion.observaciones,
                'observaciones'
            )">
        </textarea>

    </div>

    <div class="col-12 col-md-6">

        <div class="text-secondary mb-0">
            <label class="form-label">Responsable de la verificación:</label>
        </div>
         
        <input
            type="text"
            class="form-control mb-3"
            x-model="calibracion.responsable_verificacion"
            @blur="editarCampo(
                6,
                calibracion.responsable_verificacion,
                'responsable_verificacion'
            )">
            
    </div>

</div>



<!---- Modal Agregar -->

<div
    class="modal fade"
    id="modalDispensario"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                <i class="ti ti-gas-station"></i>    
                Agregar dispensario</h4>
                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <label class="form-label">* Dispensario:</label>

                <select
                class="form-select"
                x-model="nuevoDispensario">

                <option value="">
                    Seleccione una opcion...
                </option>

                <template
                    x-for="item in dispensariosDisponibles"
                    :key="item.id">

                    <option
                        :value="item.id"
                        x-text="
                            item.no_dispensario +
                            ' - ' +
                            item.marca +
                            ' - ' +
                            item.modelo +
                            ' - ' +
                            item.serie
                        ">
                    </option>

                </template>

            </select>

            </div>


        <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="agregarDispensario()">
            <i class="ti ti-check"></i> 
            Guardar
        </button>
        </div>

        </div>

    </div>

</div>
    </div>

        </div>

    

</div>