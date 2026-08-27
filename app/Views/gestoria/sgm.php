<div
    x-data="sgm()"
    class="container-fluid">
    <script>
        window.estacionesSgm = <?= json_encode($estaciones) ?>;
    </script>

    <div class="row">

        <!-- AÑO -->
        <div class="col-md-3">

            <label class="fw-bolder mb-1">
                Año
            </label>

            <select
                class="form-select"
                x-model="anio">

                <template
                    x-for="year in years"
                    :key="year">

                    <option
                        :value="year"
                        x-text="year">
                    </option>

                </template>

            </select>

        </div>

    </div>


    <div class="mt-4">

        <h5>
            Reporte
            <span x-text="anio"></span>
        </h5>


        <table class="table table-sm table-bordered table-hover align-middle">

            <tbody>

                <template
                    x-for="estacion in estaciones"
                    :key="estacion.id">

                    <tr>

                        <td
                            width="50px"
                            class="text-center">

                            <b x-text="estacion.numlista"></b>

                        </td>


                        <td>

                            <b x-text="estacion.razonsocial"></b>

                        </td>


                        <td
                            width="30px"
                            class="text-center">

                            <a
                                class="pointer"
                                @click="descargarReporte(estacion.id)">

                                <i class="ti ti-file-type-pdf fs-7"></i>

                            </a>

                        </td>

                    </tr>

                </template>


                <template x-if="estaciones.length === 0">

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No se encontraron estaciones.

                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

    </div>

</div>