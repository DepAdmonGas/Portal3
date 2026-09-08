<?php
/** @var \App\Models\Sgm\CalibracionEquipo $calibracion */
?>
<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= $estacionId ?? '' ?>"
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

    <div class="text-end mt-3 mb-3">

        <template x-if="calibracion.estado == 0">

            <button
                type="button"
                class="btn btn-success"
                @click="finalizar(
                    'Sondas de medición'
                )">

                <i class="ti ti-check"></i> Finalizar

            </button>

        </template>

        <template x-if="calibracion.estado == 1">

            <button
                type="button"
                class="btn btn-success"
                @click="window.history.back()">

                <i class="ti ti-check"></i> Finalizar

            </button>

        </template>

    </div>


<div class="card">
    <div class="card-body p-3">
           <div class="row">

        <div class="col-12 col-sm-3 mt-2 ">
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

        <div class="col-12 col-sm-3 ">
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

        <div class="col-12 col-sm-3 ">
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

        <div class="col-12 col-sm-3 ">
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
                <label class="form-label">Método usado para la calibración:</label>
            </div>

            <input
                type="text"
                class="form-control mb-3"
                x-model="calibracion.metodo_calibracion"
                @blur="editarCampo(
                    5,
                    calibracion.metodo_calibracion,
                    'metodo_calibracion'
                )">
        </div>

    </div>

    <div class="table-responsive">
<table class="table table-striped table-bordered  mt-3 text-nowrap align-middle">

        <thead>
            <tr>
                <th class="align-middle text-center">No. Sonda</th>
                <th class="align-middle text-center">Marca</th>
                <th class="align-middle text-center">Modelo</th>
                <th class="align-middle text-center">Incertidumbre de calibración</th>
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

                    <td class="align-middle text-center"
                        x-text="item.sonda?.marca">
                    </td>

                    <td class="align-middle text-center"
                        x-text="item.sonda?.modelo">
                    </td>

                    <td class="p-0 m-0">

                        <input
                            type="text"
                            class="text-center form-control border-0 rounded-0"
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
                    6,
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
                    7,
                    calibracion.responsable_verificacion,
                    'responsable_verificacion'
                )">

        </div>

    </div> 
    </div>

</div>

</div>