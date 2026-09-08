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
      <template x-if="calibracion.estado == 1">
<div class="d-flex justify-content-end mb-3 mt-3">
      <button
                type="button"
                class="btn btn-success"
                @click="window.history.back()">

                <i class="ti ti-check"></i> Finalizar

            </button>
</div>
      </template>

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
                )">
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
                <label class="form-label">Temperatura ambiente:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.temperatura_ambiente"
                @blur="editarCampo(
                    3,
                    calibracion.temperatura_ambiente,
                    'temperatura_ambiente'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Presión atmosférica:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.presion_atmosferica"
                @blur="editarCampo(
                    4,
                    calibracion.presion_atmosferica,
                    'presion_atmosferica'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Humedad:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.humedad"
                @blur="editarCampo(
                    5,
                    calibracion.humedad,
                    'humedad'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Liquido usado en la calibración:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.liquido_calibracion"
                @blur="editarCampo(
                    6,
                    calibracion.liquido_calibracion,
                    'liquido_calibracion'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Temperatura del líquido:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.temperatura_liquido"
                @blur="editarCampo(
                    7,
                    calibracion.temperatura_liquido,
                    'temperatura_liquido'
                )">
        </div>

        <div class="col-12 col-sm-3">
            <div class="text-secondary mb-0">
                <label class="form-label">Laboratorio de calibración:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.laboratorio_calibracion"
                @blur="editarCampo(
                    8,
                    calibracion.laboratorio_calibracion,
                    'laboratorio_calibracion'
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
                    9,
                    calibracion.numero_acreditacion,
                    'numero_acreditacion'
                )">
        </div>

        <div class="col-12 col-sm-3 mt-1 mb-2">
            <div class="text-secondary mb-0">
                <label class="form-label">Método de calibración:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.metodo_calibracion"
                @blur="editarCampo(
                    10,
                    calibracion.metodo_calibracion,
                    'metodo_calibracion'
                )">
        </div>

    </div>

    <div class="table-responsive mt-4">
  <table class="table table-striped table-bordered  text-nowrap align-middle">

        <thead>
            <tr>
                <th class="text-center">Marca</th>
                <th class="text-center">No. Serie</th>
                <th class="text-center">Capacidad</th>
                <th class="text-center">Incertidumbre de calibración</th>
            </tr>
        </thead>

        <tbody>

    <template
        x-for="item in calibracion.jarras"
        :key="item.id">

        <tr>
            <td class="text-center" x-text="item.jarra?.marca"></td>

            <td class="text-center" x-text="item.jarra?.no_serie"></td>

            <td class="text-center" x-text="item.jarra?.capacidad"></td>

            <td class="p-0 m-0">
                <input
                    type="text"
                    class="text-center form-control border-0 rounded-0"
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

    </div>
  

    <div class="row mt-3">

        <div class="col-12 col-md-6">

            <div class="text-secondary mb-0">
                <label class="form-label">Observaciones:</label>
            </div>

            <textarea
                class="form-control mb-3"
                x-model="calibracion.observaciones"
                @blur="editarCampo(
                    11,
                    calibracion.observaciones,
                    'observaciones'
                )"></textarea>

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
                    12,
                    calibracion.responsable_verificacion,
                    'responsable_verificacion'
                )">

        </div>

    </div>


</div>




</div>
         </div>
