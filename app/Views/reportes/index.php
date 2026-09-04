<div
    id="container"
    class="pb-4"
    x-data="reportesIndex()"
    x-init="init()">
    <style>
        [x-cloak] {
            display: none !important;
        }

        .report-card {
            height: 100%;
            transition:
                transform .15s ease,
                box-shadow .15s ease;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 .5rem 1rem rgba(0, 0, 0, .08);
        }

        .report-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary);
        }
    </style>


    <div
        class="
            d-flex
            flex-column
            flex-md-row
            align-items-md-center
            justify-content-between
            gap-3
            mb-4
            mt-4
        ">
        <div>

            <h4
                class="mb-1"
                x-text="tituloReporte">

            </h4>

            <div
                class="text-muted"
                x-show="reporte.estacion"
                x-cloak>
                <i class="ti ti-map-pin me-1"></i>

                <span
                    x-text="reporte.estacion"></span>
            </div>

        </div>

        <button
            type="button"
            class="
                btn
                btn-primary
                d-inline-flex
                align-items-center
                gap-2
            "
            @click="abrirBuscador()">
            <i class="ti ti-search fs-5"></i>

            Buscar reporte
        </button>
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

                <div
                    class="
                        col-12
                        col-md-6
                        col-xl-4
                        col-xxl-3
                    ">
                    <div
                        class="
                            card
                            border
                            report-card
                        ">
                        <div class="card-body">

                            <div
                                class="
                                    d-flex
                                    align-items-start
                                    justify-content-between
                                    gap-3
                                    mb-3
                                ">

                                <div
                                    class="report-icon">
                                    <i
                                        class="ti fs-7"
                                        :class="reporteItem.icono"></i>
                                </div>

                                <span
                                    class="
                                        badge
                                        text-bg-light
                                        border
                                    "
                                    x-text="
                                        reporte.tipo === 'diario'
                                            ? 'Diario'
                                            : 'Anual'
                                    "></span>

                            </div>

                            <h5
                                class="card-title mb-1"
                                x-text="reporteItem.nombre"></h5>

                            <p
                                class="
                                    text-muted
                                    small
                                    mb-4
                                "
                                x-text="reporte.nombre"></p>


                            <!-- Acciones -->

                            <div
                                class="
                                    d-flex
                                    flex-wrap
                                    gap-2
                                ">
                                <template
                                    x-for="accion in reporteItem.acciones"
                                    :key="
                                        reporteItem.id
                                        + '-'
                                        + accion.tipo
                                    ">
                                    <button
                                        type="button"
                                        class="
                                            btn
                                            bg-secondary-subtle 
                                            text-secondary
                                            btn-sm
                                            d-inline-flex
                                            align-items-center
                                            gap-3
                                        "
                                        @click="
                                            ejecutarAccion(
                                                reporteItem,
                                                accion
                                            )
                                        ">
                                        <i
                                            class="ti fs-5"
                                            :class="accion.icono"></i>

                                        <span
                                            x-text="accion.nombre"></span>
                                    </button>
                                </template>
                            </div>

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

                <p class="text-muted mb-0">
                    Intenta realizar una nueva búsqueda.
                </p>

            </div>
        </div>

    </div>


    <!-- -- -- -- -->
    <div
        class="modal fade"
        id="modalBuscarReporte"
        tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <div>

                        <h5 class="modal-title text-white">
                            Buscar reporte
                        </h5>

                        <div class="text-white small">
                            Selecciona estación y periodo.
                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">

                    <!-- Estación -->

                    <div class="mb-3">

                        <label
                            class="form-label">
                            Estación
                        </label>

                        <select
                            class="form-select"
                            x-model="busqueda.idEstacion"
                            @change="cambioEstacion()"
                            :class="{
                                'is-invalid':
                                    errors.idEstacion
                            }">
                            <option value="">
                                Selecciona una estación...
                            </option>

                            <template
                                x-for="estacion in estaciones"
                                :key="estacion.id">
                                <option
                                    :value="String(estacion.id)"
                                    x-text="estacion.nombre"></option>
                            </template>

                            <option value="0">
                                Todas las estaciones
                            </option>
                        </select>

                        <div
                            class="invalid-feedback"
                            x-text="errors.idEstacion"></div>

                    </div>


                    <!-- Año -->

                    <div class="mb-3">

                        <label
                            class="form-label">
                            Año
                        </label>

                        <select
                            class="form-select"
                            x-model="busqueda.year"
                            @change="cambioYear()"
                            :disabled="yearDeshabilitado"
                            :class="{
                                'is-invalid':
                                    errors.periodo
                            }">
                            <option value="">
                                Selecciona un año...
                            </option>

                            <template
                                x-for="year in years"
                                :key="year">
                                <option
                                    :value="String(year)"
                                    x-text="year"></option>
                            </template>
                        </select>

                    </div>


                    <!-- Mes -->

                    <div
                        class="mb-3"
                        x-show="busqueda.year">
                        <label
                            class="form-label">
                            Mes
                        </label>

                        <select
                            class="form-select"
                            x-model="busqueda.mes">
                            <option value="0">
                                Todos
                            </option>

                            <option value="1">
                                Enero
                            </option>

                            <option value="2">
                                Febrero
                            </option>

                            <option value="3">
                                Marzo
                            </option>

                            <option value="4">
                                Abril
                            </option>

                            <option value="5">
                                Mayo
                            </option>

                            <option value="6">
                                Junio
                            </option>

                            <option value="7">
                                Julio
                            </option>

                            <option value="8">
                                Agosto
                            </option>

                            <option value="9">
                                Septiembre
                            </option>

                            <option value="10">
                                Octubre
                            </option>

                            <option value="11">
                                Noviembre
                            </option>

                            <option value="12">
                                Diciembre
                            </option>
                        </select>

                    </div>


                    <!-- Día -->

                    <template
                        x-if="mostrarDia">
                        <div>

                            <div
                                class="
                                    d-flex
                                    align-items-center
                                    gap-2
                                    my-3
                                ">
                                <hr class="flex-grow-1">

                                <span
                                    class="
                                        text-muted
                                        small
                                    ">
                                    O
                                </span>

                                <hr class="flex-grow-1">
                            </div>

                            <div class="mb-2">

                                <label
                                    class="form-label">
                                    Día
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    x-model="busqueda.dia"
                                    @change="cambioDia()"
                                    :disabled="diaDeshabilitado"
                                    :class="{
                                        'is-invalid':
                                            errors.periodo
                                    }">

                            </div>

                        </div>
                    </template>


                    <div
                        class="
                            invalid-feedback
                            d-block
                        "
                        x-show="errors.periodo"
                        x-text="errors.periodo"></div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="
                            btn
                            btn-success
                            d-inline-flex
                            align-items-center
                            gap-1
                        "
                        @click="buscar()"
                        :disabled="buscando">

                        <template
                            x-if="!buscando">
                            <span
                                class="
                                    d-flex
                                    align-items-center
                                    gap-1
                                ">
                                <i class="ti ti-search"></i>
                                Buscar
                            </span>
                        </template>

                        <template
                            x-if="buscando">
                            <span
                                class="
                                    d-flex
                                    align-items-center
                                    gap-2
                                ">
                                <span
                                    class="
                                        spinner-border
                                        spinner-border-sm
                                    "></span>

                                Buscando...
                            </span>
                        </template>

                    </button>

                </div>

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


                        <!-- Diario -->

                        <template
                            x-if="
                                detalleAutolavado.tipo
                                === 'diario'
                            ">
                            <div
                                class="
                                    text-center
                                    py-4
                                ">

                                <div
                                    class="
                                        report-icon
                                        mx-auto
                                        mb-3
                                    ">
                                    <i
                                        class="
                                            ti
                                            ti-car-wash
                                            fs-3
                                        "></i>
                                </div>

                                <div
                                    class="text-muted mb-1">
                                    Concentrado de ventas
                                </div>

                                <div
                                    class="fw-semibold mb-3"
                                    x-text="
                                        detalleAutolavado.dia_formateado
                                    "></div>

                                <h2
                                    class="mb-0"
                                    x-text="
                                        moneda(
                                            detalleAutolavado.total
                                        )
                                    "></h2>

                            </div>
                        </template>

                    </div>

                </div>

            </div>
        </div>
    </div>

</div>