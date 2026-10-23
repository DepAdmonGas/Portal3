<div
    id="container"
    class="pb-4"
    x-data="reportesIndex()"
    @reportes-periodo.window="cambiarPeriodo($event.detail)">
    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>


    <div class="row g-3 align-items-end mt-2 mb-3">
        <div class="col-12">
            <div class="text-muted d-flex align-items-center" x-show="Number(filtros.idEstacion) === 0 && Number(filtros.idDepartamento) === 0" x-cloak>
                <i class="ti ti-map-pin me-1 fs-6"></i>
                <h5 class="mb-0 text-muted" x-text="reporte.estacion"></h5>
            </div>
        </div>
    </div>

    <div
        class="text-center py-5"
        x-show="cargando">
        <div
            class="spinner-border text-primary"
            role="status"></div>

        <div class="text-muted mt-2">
            Cargando reportes...
        </div>
    </div>

    <div
        x-show="!cargando"
        x-cloak>
        <div
            class="row g-3"
            x-show="reportes.length > 0">

            <template
                x-for="reporteItem in reportes"
                :key="reporteItem.id">

                <div class="col-12 col-md-6 col-xl-4 d-flex align-items-stretch">
                    <div class="card shadow-sm h-100 w-100 border card-hover overflow-hidden position-relative">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:60px;height:60px;">
                                    <i class="ti text-white display-6" :class="reporteItem.icono"></i>
                                </div>
                                <div class="flex-grow-1 text-end">
                                    <span class="badge bg-primary-subtle text-primary fw-bold mb-2"
                                          x-text="etiquetaReporte"></span>
                                    <h4 class="fw-bold text-dark mt-1 mb-0 lh-sm" x-text="reporteItem.nombre"></h4>
                                </div>
                            </div>
                           
                            <div class="row g-3 mt-2">
                                <template x-for="accion in reporteItem.acciones" :key="reporteItem.id + '-' + accion.tipo">
                                    <div :class="columnaAccion(reporteItem.acciones.length)">
                                        <button type="button"
                                                class="btn bg-primary-subtle text-primary w-100 d-inline-flex justify-content-center align-items-center gap-2"
                                                @click="ejecutarAccion(reporteItem, accion)">
                                            <i class="ti fs-5" :class="accion.icono"></i>
                                            <span x-text="accion.nombre"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                            <span class="small">Opciones del reporte</span>
                            <i class="ti ti-report-search fs-5"></i>

                        </div>
                    </div>
                </div>

            </template>

        </div>


        <!-- Empty -->

        <div
            class="
                card
                border
                text-center
                py-5
            "
            x-show="reportes.length === 0">
            <div class="card-body">

                <i
                    class="
                        ti
                        ti-report-off
                        fs-1
                        text-muted
                    "></i>

                <h5 class="mt-3">
                    No hay reportes disponibles
                </h5>

                <p class="text-muted mb-0">No hay reportes para los filtros seleccionados.</p>

            </div>
        </div>

    </div>


    <!-- -- -- -- -->
    <div
        class="modal fade"
        id="modalAutolavado"
        tabindex="-1"
        aria-hidden="true">
        <div
            class="
                modal-dialog
                modal-lg
                modal-dialog-centered
            ">
            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <div>

                        <h5 class="modal-title text-white">
                            Reporte de Autolavado
                        </h5>

                        <div
                            class="text-white small"
                            x-text="tituloAutolavado"></div>

                    </div>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <!-- Loading -->

                    <div
                        class="text-center py-5"
                        x-show="cargandoAutolavado">
                        <div
                            class="
                                spinner-border
                                text-primary
                            "></div>

                        <div class="text-muted mt-2">
                            Cargando información...
                        </div>
                    </div>


                    <div
                        x-show="!cargandoAutolavado"
                        x-cloak>

                        <!-- Anual -->

                        <template
                            x-if="
                                detalleAutolavado.tipo
                                === 'anual'
                            ">
                            <div>

                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-12 col-sm">
                                        <label class="form-label mb-1" for="autolavado-dia">Consultar ventas por día:</label>
                                        <input
                                            id="autolavado-dia"
                                            type="date"
                                            class="form-control"
                                            min="2021-01-01"
                                            :max="fechaHoy"
                                            x-model="consultaDia.fecha">
                                    </div>
                                    <div class="col-12 col-sm-auto">
                                        <button
                                            type="button"
                                            class="btn btn-primary w-100"
                                            @click="buscarDiaAutolavado()"
                                            :disabled="!consultaDia.fecha || cargandoDiaAutolavado">
                                            <span
                                                class="spinner-border spinner-border-sm me-1"
                                                x-show="cargandoDiaAutolavado"
                                                aria-hidden="true"></span>
                                            <i class="ti ti-search me-1" x-show="!cargandoDiaAutolavado"></i>
                                            Consultar día
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"
                                    x-show="resultadoDiaAutolavado"
                                    x-cloak>
                                    <div>
                                        <div class="small">Concentrado diario</div>
                                        <strong x-text="resultadoDiaAutolavado?.dia_formateado"></strong>
                                    </div>
                                    <div class="text-sm-end">
                                        <div class="small">Total de ventas</div>
                                        <strong x-text="moneda(resultadoDiaAutolavado?.total)"></strong>
                                    </div>
                                </div>

                                <div
                                    class="
                                        table-responsive
                                        border
                                        rounded
                                    ">
                                    <table
                                        class="
                                            table
                                            table-hover
                                            align-middle
                                            mb-0
                                        ">
                                        <thead
                                            class="table-light">
                                            <tr>

                                                <th>
                                                    Mes
                                                </th>

                                                <th
                                                    class="text-end">
                                                    Monto
                                                </th>

                                            </tr>
                                        </thead>

                                        <tbody>

                                            <template
                                                x-for="
                                                    mes
                                                    in detalleAutolavado.meses
                                                "
                                                :key="mes.numero">
                                                <tr>

                                                    <td
                                                        x-text="mes.nombre"></td>

                                                    <td
                                                        class="text-end"
                                                        x-text="
                                                            moneda(
                                                                mes.monto
                                                            )
                                                        "></td>

                                                </tr>
                                            </template>

                                        </tbody>

                                        <tfoot
                                            class="
                                                table-light
                                                fw-bold
                                            ">
                                            <tr>

                                                <td>
                                                    Total
                                                </td>

                                                <td
                                                    class="text-end"
                                                    x-text="
                                                        moneda(
                                                            detalleAutolavado.total
                                                        )
                                                    "></td>

                                            </tr>
                                        </tfoot>

                                    </table>
                                </div>

                            </div>
                        </template>
                    </div>

                </div>

            </div>
        </div>
    </div>

</div>
