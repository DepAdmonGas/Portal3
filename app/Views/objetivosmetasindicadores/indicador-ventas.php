<div id="container" class="mb-3" x-data="{ ...actions(), ...indicadorVentas}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">


<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div x-data="indicadorVentas">

    <!-- SELECT AÑO -->

     <div class="row mt-3 mb-3">
        <div class="col-md-9 col-xl-9"> <h4 x-text="year"></h4> </div>
        <div class="col-md-3 col-xl-3">
            <select class="form-select" x-model="year" @change="getVentas()">
                <template x-for="y in [2026,2025,2024,2023,2022,2021,2020]" :key="y">
                    <option :value="y" x-text="y"></option>
                </template>
            </select>
        </div>
    </div>

 
    <!-- LOADING -->
    <div x-show="loading" class="text-center">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <!-- CONTENIDO -->
    <div x-show="!loading">

    <div id="chart"></div>

    <div class="row">

        <template x-for="item in ventas" :key="item.mes">
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mt-2 mb-2">

                    <div class="bg-light p-2 text-white text-center">
                        <h5 x-text="item.nombre_mes"></h5>
                    </div>

                    <table class="table table-sm table-bordered text-center">
                        <thead>
                            <tr>
                                <th x-text="productos.p1" class="text-white" style="background: #78bd24"></th>
                                <th x-text="productos.p2" class="text-white" style="background: #e01483"></th>
                                <th x-show="productos.p3" class="text-white" style="background: #5e0f8e" x-text="productos.p3"></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td x-text="format(item.producto1)"></td>
                                <td x-text="format(item.producto2)"></td>
                                <td x-show="productos.p3" x-text="format(item.producto3)"></td>
                            </tr>
                        </tbody>
                    </table>
            </div>
        </template>

    </div>

    <!-- TOTALES -->
    <div class="row mt-3">
        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">

                <div class="bg-light text-white text-center p-2">
                    <h5>Total Neto</h5>
                </div>

                <table class="table table-sm table-bordered text-center">
                    <thead>
                        <tr>
                            <th x-text="productos.p1" class="text-white" style="background: #78bd24"></th>
                            <th x-text="productos.p2" class="text-white" style="background: #e01483"></th>
                            <th x-show="productos.p3" class="text-white" style="background: #5e0f8e" x-text="productos.p3"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td x-text="format(totales.producto1)"></td>
                            <td x-text="format(totales.producto2)"></td>
                            <td x-show="productos.p3" x-text="format(totales.producto3)"></td>
                        </tr>
                    </tbody>
                </table>

          </div>
    </div>

    </div>

</div>
    <?php endif; ?>

</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            INDICADORES DE VENTAS
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado puedes consultar tus ventas de cada uno de tus productos ya sea por mes o por año. 
            Identifica los meses de mayor y menor venta. Esto nos ayudara a crear estrategias para aumentar los indicadores más bajos.</b>
          </p>
          <p>
            Las gráficas que veras a continuación son el resultado del reporte estadístico diario que ingresas ante este portal. 
          </p>

          <hr>

          <small>Recuerda que los datos aquí reflejados son el resultado de lo que reportas diariamente, 
            con la finalidad de obtener datos estadísticos verídicos es importante no omitir el reporte estadístico diario. </small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->