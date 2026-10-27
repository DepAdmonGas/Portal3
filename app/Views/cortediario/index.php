<div
    id="container"
    x-data="{
        ...actions(),
        ...corteDiario(<?= json_encode($idEstacion ? (int) $idEstacion : null) ?>)
    }">

    <!-- =====================================================
    CABECERA / FILTRO
    ====================================================== -->
    <div class="d-flex justify-content-end mb-4">
        <div
            x-show="idEstacion"
            x-cloak>

            <div
                class="d-flex align-items-center gap-2 text-muted">

                <i
                    class="ti ti-calendar-stats fs-5"></i>

                <span>
                    Últimos dos meses
                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
    SIN ESTACIÓN
    ====================================================== -->
    <div
        id="corte-diario-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4"
        x-show="!idEstacion"
        x-cloak>
        Debes de seleccionar una estación del menú superior para poder visualizar la información del Corte Diario.
    </div>


    <!-- =====================================================
    DATATABLE
    ====================================================== -->
    <div
        class="datatables"
        x-show="idEstacion"
        x-cloak>

        <div class="table-responsive">

            <table
                id="table-corte-diario"
                class="table table-striped table-bordered mb-0 align-middle w-100">

                <thead>

                    <tr>

                        <th class="text-center">
                            #
                        </th>

                        <th>
                            Fecha
                        </th>

                        <th class="text-center">
                            Estatus
                        </th>

                        <th class="text-center">

                            <i
                                class="ti ti-dots-vertical fs-6"></i>

                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
    LOADING
    ====================================================== -->
    <div
        class="position-fixed bottom-0 end-0 m-4"
        style="z-index: 1080;"
        x-show="loading"
        x-cloak>

        <div
            class="bg-white border rounded-3 shadow-sm px-3 py-2 d-flex align-items-center gap-2">

            <span
                class="spinner-border spinner-border-sm text-primary"></span>

            <small class="fw-semibold">
                Procesando...
            </small>

        </div>

    </div>

</div>
