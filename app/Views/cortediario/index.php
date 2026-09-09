<div
    id="container"
    x-data="{
        ...actions(),
        ...corteDiario()
    }">

    <!-- =====================================================
    CABECERA / FILTRO
    ====================================================== -->
    <div
        class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">

        <div
            class="w-100"
            style="max-width: 420px;">

            <label
                for="filtroEstacion"
                class="form-label fw-semibold">
                Estación
            </label>

            <select
                id="filtroEstacion"
                class="form-select"
                x-model="idEstacion"
                @change="cambiarEstacion()">

                <option value="">
                    Selecciona una estación
                </option>

                <?php foreach ($estaciones as $estacion): ?>

                    <option
                        value="<?= (int) $estacion->id ?>">
                        <?= htmlspecialchars(
                            $estacion->razonsocial
                                ?: $estacion->nombre,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


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
        class="card"
        x-show="!idEstacion"
        x-cloak>

        <div class="card-body">

            <div
                class="text-center py-5">

                <div
                    class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                    style="
                        width: 64px;
                        height: 64px;
                    ">

                    <i
                        class="ti ti-gas-station fs-7"></i>

                </div>

                <h5 class="fw-semibold">
                    Selecciona una estación
                </h5>

                <p class="text-muted mb-0">
                    Selecciona una estación para consultar sus cortes diarios.
                </p>

            </div>

        </div>

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