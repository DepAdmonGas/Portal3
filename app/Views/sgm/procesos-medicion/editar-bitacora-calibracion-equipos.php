<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...editarBitacoraCalibracion(<?= $id ?>) }">


    <div class="text-end">

        <button

            class="btn btn-success mt-3"

            @click="finalizar()">
            <i class="ti ti-check"></i>

            Finalizar bitácora

        </button>

    </div>


    <table class="table table-striped table-bordered text-nowrap align-middle mb-4 mt-3">
        <tbody>
            <tr>
                <td class="align-middle" width="700">Fecha:</td>
                <td class="p-0 m-0 "><input type="date" class="form-control border-0 text-center" x-model="bitacora.fecha" @change="guardar('fecha')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Hora:</td>
                <td class="p-0 m-0"><input type="time" class="form-control border-0 text-center" x-model="bitacora.hora" @change="guardar('hora')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Nombre del equipo a calibrar:</td>
                <td class="align-middle p-2 text-center"><label class="fw-bolder" x-text="bitacora.nombre_equipo"></label></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Marca:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.marca" @change="guardar('marca')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Capacidad:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.capacidad" @change="guardar('capacidad')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Producto que almacena:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.almacena" @change="guardar('almacena')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Nombre del laboratorio o unidad de verificación encargada de la calibración:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.nombre_laboratorio" @change="guardar('nombre_laboratorio')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">No de acreditación o aprobación:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.no_acreditacion" @change="guardar('no_acreditacion')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Método utilizado para la calibración:</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.metodo_calibracion" @change="guardar('metodo_calibracion')"></td>
            </tr>
        </tbody>
    </table>

    <h5 class="fw-semibold mt-5">Descripción de patrones utilizados</h5>

    <table class="table table-striped table-bordered text-nowrap align-middle mb-3">
        <tbody>
            <tr>
                <td class="align-middle" width="700">Nombre del patrón</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.nombre_patron" @change="guardar('nombre_patron')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Marca y modelo y serie</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.marca_modelo_serie" @change="guardar('marca_modelo_serie')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Resolución</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.resolucion" @change="guardar('resolucion')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Incertidumbre</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.incertidumbre" @change="guardar('incertidumbre')"></td>
            </tr>
            <tr>
                <td class="align-middle" width="700">Vigencia de su certificado de calibración</td>
                <td class="p-0 m-0"><input type="text" class="form-control border-0 text-center" x-model="bitacora.vigencia_certificado" @change="guardar('vigencia_certificado')"></td>
            </tr>
        </tbody>
    </table>

    <table class="table table-striped table-bordered text-nowrap align-middle mb-4 mt-4"
        x-show="detalles.length > 0">

        <thead>
            <tr>
                <th class="text-center">Equipo</th>
                <th class="text-center">Identificación</th>
                <th class="text-center">Resultado</th>
            </tr>
        </thead>
        <tbody>
            <template
                x-for="detalle in detalles"
                :key="detalle.id">
                <tr>
                    <td
                    class="text-center"
                        x-text="detalle.equipo.nombre">
                    </td>
                    <td
                    class="text-center"
                        x-text="detalle.equipo.identificacion">
                    </td>
                    <td class="p-0 m-0">
                        <input
                            class="form-control border-0 text-center"
                            x-model="detalle.resultado"
                            @change="guardarResultado(detalle)">
                    </td>
                </tr>
            </template>
        </tbody>
    </table>


</div>

<?php endif; ?>

</div>