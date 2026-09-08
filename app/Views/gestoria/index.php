<div class="pb-4"
    x-data="index()"
    @click="handleClick($event)"
    data-modelo="Gestoria">


    <div class="datatables mt-4">
        <div class="table-responsive">
            <table id="table-estaciones" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Permiso cre</th>
                        <th>Razón Social</th>
                        <th>RFC</th>
                        <th>Estatus</th>
                        <th class="text-center">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- ------------------------- -->
    <!-- inicio offcanvas -------- -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEstacion" x-ref="offcanvas">
        <div class="offcanvas-header bg-primary text-white">
            <h5 class="offcanvas-title text-white">
                Opciones de estación
            </h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div>

            <div class="p-3">
                <span class="fs-5" x-text="estacionNombre"></span>
            </div>

            <div class="list-group border-0">
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irSasisopa()">
                    <i class="ti ti-currency-solana fs-4 me-2"></i>
                    SASISOPA
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irConsultaSasisopa()">
                    <i class="ti ti-list-search fs-4 me-2"></i>
                    Consulta tu SASISOPA
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irConfiguracionBitacora()">
                    <i class="ti ti-currency-solana fs-4 me-2"></i>
                    Configuración Bitácoras
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    :href="'/gestoria/analisis-riesgo/' + estacionId">
                    <i class="ti ti-prison fs-4 me-2"></i>
                    Análisis de Riesgo
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irRequisitosLegales()">
                    <i class="ti ti-certificate fs-4 me-2"></i>
                    Requisitos Legales
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    :href="'/gestoria/reporte-cre/' + estacionId">
                    <i class="ti ti-report-analytics fs-4 me-2"></i>
                    Reporte de la CRE
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3"
                    :href="'/gestoria/cambio-precio/' + estacionId">
                    <i class="ti ti-report-money fs-4 me-2"></i>
                    Cambio de Precio
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irProgramaAnual()">
                    <i class="ti ti-calendar-week fs-4 me-2"></i>
                    Programa de Mantenimiento
                </a>

                <hr>

                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irSGM()">
                    <i class="ti ti-currency-solana fs-4 me-2"></i>
                    SGM
                </a>

                <a class="list-group-item list-group-item-action border-0 fs-4 p-3"
                    :href="'/gestoria/calibracion-tanques/' + estacionId">
                    <i class="ti ti-cylinder fs-4 me-2"></i>
                    Calibración de Tanques
                </a>
                <a class="list-group-item list-group-item-action border-0 fs-4 p-3"
                    :href="'/gestoria/control-documental-sgm/' + estacionId">
                    <i class="ti ti-file-zip fs-4 me-2"></i>
                    SGM (Documentos)
                </a>

                <hr>

                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irProfeco()">
                    <i class="ti ti-list fs-4 me-2"></i>
                    Bitacora PROFECO
                </a>

                <hr>

                <a class="list-group-item list-group-item-action border-0 fs-4 p-3 pointer"
                    @click.prevent="irPersonal()">
                    <i class="ti ti-users fs-4 me-2"></i>
                    Personal
                </a>

            </div>
        </div>
    </div>
    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>