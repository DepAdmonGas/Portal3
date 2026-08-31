<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...bitacoraVerificacion() }">

    <div class="datatables mt-4">
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="table-bitacora-verificacion-equipos">
                <thead>
                    <tr class="bg-primary text-white">
                        <th>Equipo a calibrar</th>
                        <th>Periodicidad</th>
                        <th>Fechas programadas</th>
                        <th>Estatus</th>
                        <th class="text-center align-middle" width="35px">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <div
        class="modal fade"
        id="modalDetalleBitacora"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

            <div class="modal-content">

                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                        Detalle de bitácora de verificación
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <template x-if="programa.equipo?.nombre == 'Sensor de nivel y temperatura'">
                        <div>

                            <h5 class="mb-3 mt-3">
                                Sensor de nivel y temperatura
                            </h5>

                            <table class="table table-bordered table-sm fs-4">

                                <tbody>

                                    <tr>
                                        <td width="700" class="align-middle">
                                            <b>Fecha:</b>
                                        </td>

                                        <td x-text="bitacora.fecha"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>Hora:</b>
                                        </td>

                                        <td x-text="bitacora.hora"></td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="bg-muted text-white">
                                            <b>Verificación de sensores de nivel y temperatura</b>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>No. de tanque</b>
                                        </td>

                                        <td x-text="bitacora.no_tanque"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>Marca</b>
                                        </td>

                                        <td x-text="bitacora.marca"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>Capacidad</b>
                                        </td>

                                        <td x-text="bitacora.capacidad"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>Producto que almacena</b>
                                        </td>

                                        <td x-text="bitacora.producto"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>
                                                La verificación es realizada por personal interno o
                                                externo (en caso de ser externo indicar la empresa).
                                            </b>
                                        </td>

                                        <td x-text="bitacora.interno_externo"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>
                                                Al iniciar la calibración se asegura que el producto se
                                                encuentre sin movimiento
                                            </b>
                                        </td>

                                        <td x-text="bitacora.verificacion_movimiento"></td>
                                    </tr>

                                    <tr>
                                        <td class="align-middle">
                                            <b>
                                                Método para determinar el nivel líquido dentro del tanque
                                                (Inmersión o medida seca)
                                            </b>
                                        </td>

                                        <td x-text="bitacora.metodo_nivel"></td>
                                    </tr>

                                </tbody>

                            </table>

                            <template
                                x-for="categoria in resultados"
                                :key="categoria.titulo">

                                <div class="mb-4">

                                    <table class="table table-bordered table-sm fs-4">

                                        <thead>

                                            <tr>

                                                <th class="bg-muted text-white" x-text="categoria.titulo"></th>

                                                <th class="bg-muted text-white" width="250">
                                                    Resultado
                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody>

                                            <template
                                                x-for="item in categoria.preguntas"
                                                :key="item.id">

                                                <tr>

                                                    <td x-text="item.lista.pregunta"></td>

                                                    <td x-text="item.resultado"></td>

                                                </tr>

                                            </template>

                                        </tbody>

                                    </table>

                                </div>

                            </template>

                            <div class="alert alert-light text-black mt-4">

                                <b>Nota 1:</b>

                                Referente al nivel puede existir una variación de ±3 mm; para
                                aplicaciones fiscales o transferencia de custodia los equipos deben
                                cumplir con un EMP de ±4 mm en todo el intervalo de medición.

                                <hr>

                                <b>Nota 2:</b>

                                Referente a la temperatura puede existir una variación igual o menor
                                a 0.5 °C.

                            </div>

                        </div>
                    </template>

                    <!-- Dispensarios -->
                    <template x-if="programa.equipo?.nombre == 'Dispensarios'">
                        <div>
                            <h5 class="mb-3 mt-3">Dispensarios</h5>

                            <table class="table table-sm table-bordered align-middle mb-4 fs-4">
                                <tbody>

                                    <tr>
                                        <td width="45%">
                                            <strong>Fecha</strong>
                                        </td>
                                        <td x-text="bitacora.fecha"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            <strong>Hora</strong>
                                        </td>
                                        <td x-text="bitacora.hora"></td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" class="bg-muted text-white">
                                            <strong>Verificacion de dispensarios</strong>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="table-light">
                                            1. Aspecto a verificar en los patrones de referencia
                                        </td>
                                        <td class="table-light">Resultado</td>
                                    </tr>

                                    <tr>
                                        <td>
                                            Marca y modelo de la jarra patrón
                                        </td>
                                        <td x-text="bitacora.marca_modelo_jarra_patron"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            Capacidad
                                        </td>
                                        <td x-text="bitacora.capacidad"></td>
                                    </tr>

                                    <tr>
                                        <td>
                                            La jarra patrón se encuentra calibrada
                                        </td>
                                        <td x-text="bitacora.jarra_patron_calibrada"></td>
                                    </tr>

                                    <tr>
                                        <td class="bg-light">
                                            2. Aspecto a verificar
                                        </td>
                                        <td class="table-light">Resultado</td>
                                    </tr>

                                    <tr>
                                        <td>
                                            No. de dispensario
                                        </td>
                                        <td x-text="bitacora.no_dispensario"></td>
                                    </tr>

                                </tbody>
                            </table>

                            <table class="table table-sm table-bordered table-hover align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th>Lado</th>

                                        <th>Producto</th>

                                        <th>Medida a comparar (ml)</th>

                                        <th>Medición jarra patrón (ml)</th>

                                        <th>Diferencia</th>

                                        <th>Resultado</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <template
                                        x-if="detalles.length===0">

                                        <tr>

                                            <td
                                                colspan="7"
                                                class="text-center text-muted">

                                                No hay registros

                                            </td>

                                        </tr>

                                    </template>

                                    <template
                                        x-for="detalle in detalles"
                                        :key="detalle.id">

                                        <tr>

                                            <td x-text="detalle.lado"></td>

                                            <td x-text="detalle.producto"></td>

                                            <td x-text="detalle.medida_comparar + ' ml'"></td>

                                            <td x-text="detalle.medicion_jarra_patron + ' ml'"></td>

                                            <td
                                                x-text="detalle.diferencia + ' ml'">
                                            </td>

                                            <td>

                                                <span
                                                    class="badge"
                                                    :class="detalle.resultado=='Favorable'
                                    ? 'bg-success'
                                    : 'bg-danger'"
                                                    x-text="detalle.resultado">

                                                </span>

                                            </td>

                                        </tr>

                                    </template>

                                </tbody>

                            </table>

                        </div>
                    </template>

                </div>

            </div>

        </div>
    </div>

</div>

<?php endif; ?>

</div>