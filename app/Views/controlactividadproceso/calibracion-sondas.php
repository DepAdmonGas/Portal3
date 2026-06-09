<?php
/** @var \App\Models\Sgm\CalibracionEquipo $calibracion */
?>
<div id="container"
class="pb-4"
x-data="{ ...actions(), ...calibracionSondas() }">
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

<div class="mt-3 p-3">

    <div class="row">

        <div class="col-6 col-sm-3 mt-2">
            <label class="form-label">Folio:</label>
            <div>
                <h5 x-text="'00' + calibracion.folio"></h5>
            </div>
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Fecha:</label>
            </div>

            <input
                type="date"
                class="form-control"
                x-model="calibracion.fecha_formateada"
                @change="editarCampo(
                    1,
                    calibracion.fecha_formateada,
                    'fecha_formateada'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Hora:</label>
            </div>

            <input
                type="time"
                class="form-control"
                x-model="calibracion.hora"
                @change="editarCampo(
                    2,
                    calibracion.hora,
                    'hora'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Unidad de verificación:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.unidad_verificacion"
                @blur="editarCampo(
                    3,
                    calibracion.unidad_verificacion,
                    'unidad_verificacion'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">No. de acreditación:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.numero_acreditacion"
                @blur="editarCampo(
                    4,
                    calibracion.numero_acreditacion,
                    'numero_acreditacion'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Método usado para la calibración:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.metodo_calibracion"
                @blur="editarCampo(
                    5,
                    calibracion.metodo_calibracion,
                    'metodo_calibracion'
                )">
        </div>

    </div>

    <table class="table table-sm table-bordered mt-4">

        <thead>
            <tr>
                <th class="align-middle text-center">No. Sonda</th>
                <th class="align-middle">Marca</th>
                <th class="align-middle">Modelo</th>
                <th class="align-middle">Incertidumbre de calibración</th>
            </tr>
        </thead>

        <tbody>

            <template
                x-for="item in calibracion.sondas"
                :key="item.id">

                <tr>

                    <td class="align-middle text-center"
                        x-text="item.sonda?.no_sonda">
                    </td>

                    <td class="align-middle"
                        x-text="item.sonda?.marca">
                    </td>

                    <td class="align-middle"
                        x-text="item.sonda?.modelo">
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="form-control border-0 rounded-0"
                            x-model="item.resultado1"
                            @blur="editarSonda(
                                item,
                                8,
                                'resultado1'
                            )">

                    </td>

                </tr>

            </template>

        </tbody>

    </table>

    <div class="row">

        <div class="col-6">

            <div class="text-secondary mt-2">
                <label class="form-label">Observaciones:</label>
            </div>

            <textarea
                class="form-control"
                x-model="calibracion.observaciones"
                @blur="editarCampo(
                    6,
                    calibracion.observaciones,
                    'observaciones'
                )">
            </textarea>

        </div>

        <div class="col-6">

            <div class="text-secondary mt-2">
                <label class="form-label">Responsable de la verificación:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.responsable_verificacion"
                @blur="editarCampo(
                    7,
                    calibracion.responsable_verificacion,
                    'responsable_verificacion'
                )">

        </div>

    </div>

    <div class="text-end mt-2">

        <template x-if="calibracion.estado == 0">

            <button
                type="button"
                class="btn btn-success"
                @click="finalizar(
                    'Sondas de medición'
                )">

                <i class="ti ti-check"></i> Finalizar Bitácora

            </button>

        </template>

        <template x-if="calibracion.estado == 1">

            <button
                type="button"
                class="btn btn-success"
                @click="window.history.back()">

                <i class="ti ti-check"></i> Finalizar Bitácora

            </button>

        </template>

    </div>

</div>

</div>