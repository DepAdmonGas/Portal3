<?php
/** @var \App\Models\Sgm\CalibracionEquipo $calibracion */
?>
<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= $estacionId ?? '' ?>"
class="pb-4"
x-data="{ ...actions(), ...calibracionJarraPatron() }">
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

<div class="bg-white mt-3 p-3">

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
                <label class="form-label">Temperatura ambiente:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.temperatura_ambiente"
                @blur="editarCampo(
                    3,
                    calibracion.temperatura_ambiente,
                    'temperatura_ambiente'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Presión atmosférica:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.presion_atmosferica"
                @blur="editarCampo(
                    4,
                    calibracion.presion_atmosferica,
                    'presion_atmosferica'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Humedad:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.humedad"
                @blur="editarCampo(
                    5,
                    calibracion.humedad,
                    'humedad'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Liquido usado en la calibración:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.liquido_calibracion"
                @blur="editarCampo(
                    6,
                    calibracion.liquido_calibracion,
                    'liquido_calibracion'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Temperatura del líquido:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.temperatura_liquido"
                @blur="editarCampo(
                    7,
                    calibracion.temperatura_liquido,
                    'temperatura_liquido'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Laboratorio de calibración:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.laboratorio_calibracion"
                @blur="editarCampo(
                    8,
                    calibracion.laboratorio_calibracion,
                    'laboratorio_calibracion'
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
                    9,
                    calibracion.numero_acreditacion,
                    'numero_acreditacion'
                )">
        </div>

        <div class="col-6 col-sm-3 mt-2">
            <div class="text-secondary">
                <label class="form-label">Método de calibración:</label>
            </div>

            <input
                type="text"
                class="form-control"
                x-model="calibracion.metodo_calibracion"
                @blur="editarCampo(
                    10,
                    calibracion.metodo_calibracion,
                    'metodo_calibracion'
                )">
        </div>

    </div>

    <table class="table table-sm table-bordered mt-4">

        <thead>
            <tr>
                <th>Marca</th>
                <th>No. Serie</th>
                <th>Capacidad</th>
                <th>Incertidumbre de calibración</th>
            </tr>
        </thead>

        <tbody>

    <template
        x-for="item in calibracion.jarras"
        :key="item.id">

        <tr>
            <td x-text="item.jarra?.marca"></td>

            <td x-text="item.jarra?.no_serie"></td>

            <td x-text="item.jarra?.capacidad"></td>

            <td class="p-0 m-0">
                <input
                    type="text"
                    class="form-control border-0 rounded-0"
                    x-model="item.resultado1"
                    @blur="editarJarra(
                        item,
                        13,
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
                    11,
                    calibracion.observaciones,
                    'observaciones'
                )"></textarea>

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
                    12,
                    calibracion.responsable_verificacion,
                    'responsable_verificacion'
                )">

        </div>

    </div>

    <div class="text-end mt-3">

        <template x-if="calibracion.estado == 0">

            <button
                type="button"
                class="btn btn-success"
                @click="finalizar('Jarra patron')">

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