<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...bitacoraCalibracion() }">

    <div class="datatables mt-4">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table class="table table-striped table-bordered text-nowrap align-middle mb-0" id="table-bitacora-calibracion-equipos">
                <thead>
                    <tr class="bg-primary text-white">
                        <th class="text-center">Equipo a calibrar</th>
                        <th class="text-center">Periodicidad</th>
                        <th class="text-center">Fechas programadas</th>
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
                        <i class="ti ti-eye"></i>
                        Detalle de bitácora de calibración
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <table class="table table-striped table-bordered align-middle mb-5">
                        <tbody>
                            <tr>
                                <td class="align-middle" width="700">Fecha:</td>
                                <td class="text-center"><label x-text="bitacora.fecha"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Hora:</td>
                                <td class="text-center"><label x-text="bitacora.hora"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Nombre del equipo a calibrar:</td>
                                <td class="align-middle text-center"><label class="fw-bolder" x-text="bitacora.nombre_equipo"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Marca:</td>
                                <td class="text-center"><label x-text="bitacora.marca"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Capacidad:</td>
                                <td class="text-center"><label x-text="bitacora.capacidad"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Producto que almacena:</td>
                                <td class="text-center"><label x-text="bitacora.almacena"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Nombre del laboratorio o unidad de verificación encargada de la calibración:</td>
                                <td class="text-center"><label x-text="bitacora.nombre_laboratorio"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">No de acreditación o aprobación:</td>
                                <td class="text-center"><label x-text="bitacora.no_acreditacion"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Método utilizado para la calibración:</td>
                                <td class="text-center"><label x-text="bitacora.metodo_calibracion"></label></td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4 fw-semibold">Descripción de patrones utilizados</h5>

                    <table class="table table-striped table-bordered text-nowrap align-middle mb-5">
                        <tbody>
                            <tr>
                                <td class="align-middle" width="700">Nombre del patrón</td>
                                <td class="text-center"><label x-text="bitacora.nombre_patron"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Marca y modelo y serie</td>
                                <td class="text-center"><label x-text="bitacora.marca_modelo_serie"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Resolución</td>
                                <td class="text-center"><label x-text="bitacora.resolucion"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Incertidumbre</td>
                                <td class="text-center"><label x-text="bitacora.incertidumbre"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700">Vigencia de su certificado de calibración</td>
                                <td class="text-center"><label x-text="bitacora.vigencia_certificado"></label></td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="table table-striped table-bordered text-nowrap align-middle mb-0"
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
                                    <td
                                    class="text-center" >
                                        <label x-text="detalle.resultado"></label>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                </div>
                <div class="modal-footer">
 <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cerrar

                </button>
                </div>

            </div>

        </div>
    </div>

</div>

<?php endif; ?>

</div>