<div class="pb-4" x-data="{ ...actions(), ...bitacoraCalibracion() }">

    <div class="datatables mt-4">
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="table-bitacora-calibracion-equipos">
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
                        Detalle de bitácora de calibración
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <table class="table table-bordered table-sm align-middle">
                        <tbody>
                            <tr>
                                <td class="align-middle" width="700"><b>Fecha:</b></td>
                                <td><label x-text="bitacora.fecha"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Hora:</b></td>
                                <td><label x-text="bitacora.hora"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Nombre del equipo a calibrar:</b></td>
                                <td class="align-middle"><label class="fw-bolder" x-text="bitacora.nombre_equipo"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Marca:</b></td>
                                <td><label x-text="bitacora.marca"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Capacidad:</b></td>
                                <td><label x-text="bitacora.capacidad"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Producto que almacena:</b></td>
                                <td><label x-text="bitacora.almacena"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Nombre del laboratorio o unidad de verificación encargada de la calibración:</b></td>
                                <td><label x-text="bitacora.nombre_laboratorio"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>No de acreditación o aprobación:</b></td>
                                <td><label x-text="bitacora.no_acreditacion"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Método utilizado para la calibración:</b></td>
                                <td><label x-text="bitacora.metodo_calibracion"></label></td>
                            </tr>
                        </tbody>
                    </table>

                    <h5>Descripción de patrones utilizados</h5>

                    <table class="table table-bordered table-sm mt-3">
                        <tbody>
                            <tr>
                                <td class="align-middle" width="700"><b>Nombre del patrón</b></td>
                                <td><label x-text="bitacora.nombre_patron"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Marca y modelo y serie</b></td>
                                <td><label x-text="bitacora.marca_modelo_serie"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Resolución</b></td>
                                <td><label x-text="bitacora.resolucion"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Incertidumbre</b></td>
                                <td><label x-text="bitacora.incertidumbre"></label></td>
                            </tr>
                            <tr>
                                <td class="align-middle" width="700"><b>Vigencia de su certificado de calibración</b></td>
                                <td><label x-text="bitacora.vigencia_certificado"></label></td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="table table-bordered table-sm align-middle"
                        x-show="detalles.length > 0">

                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Identificación</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                x-for="detalle in detalles"
                                :key="detalle.id">
                                <tr>
                                    <td
                                        x-text="detalle.equipo.nombre">
                                    </td>
                                    <td
                                        x-text="detalle.equipo.identificacion">
                                    </td>
                                    <td>
                                        <label x-text="detalle.resultado"></label>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                </div>

            </div>

        </div>
    </div>

</div>