<style>
    .nav-card {
        transition: all 0.25s ease-in-out;
        border: 1px solid transparent;
    }
    .nav-card:hover {
        transform: translateX(5px);
        background-color: #ffffff !important;
        border-color: rgba(13, 110, 253, 0.25) !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    }
    .nav-card:hover .ti-chevron-right {
        opacity: 1 !important;
        color: var(--bs-primary) !important;
        transform: translateX(2px);
        transition: transform 0.2s ease;
    }
    .pointer {
        cursor: pointer;
    }
/* Por defecto en móviles y pantallas pequeñas: ocupa pantalla completa */
#offcanvasEstacion {
    --bs-offcanvas-width: 100vw;
}

/* En tablets y laptops (desde 576px en adelante): se ajusta a 450px */
@media (min-width: 576px) {
    #offcanvasEstacion {
        --bs-offcanvas-width: 500px;
    }
}
</style>

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
<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="offcanvasEstacion" x-ref="offcanvas">
<div class="offcanvas-header bg-primary text-white py-3">
                <h5 class="offcanvas-title text-white d-flex align-items-center">
                <i class="ti ti-gas-station fs-6 me-3"></i> 
                <div>
                    <span class="d-block fs-5">Opciones de estación</span>
                    <small class="text-white-50 fw-normal" x-text="estacionNombre"></small>
                </div>
            </h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
 <div class="offcanvas-body p-3 bg-light">
    
    <div class="d-flex flex-column gap-2">

        <!-- SECCIÓN: SASISOPA & NORMATIVA -->
        <div class="text-uppercase text-muted fw-bold px-1 pb-1" style="font-size: 0.725rem; letter-spacing: 0.05em;">
            SASISOPA
        </div>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irSasisopa()">
            <div class="d-flex align-items-center">
                <i class="ti ti-shield-check fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">SASISOPA</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irConsultaSasisopa()">
            <div class="d-flex align-items-center">
                <i class="ti ti-list-search fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Consulta tu SASISOPA</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irConfiguracionBitacora()">
            <div class="d-flex align-items-center">
                <i class="ti ti-settings fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Configuración Bitácoras</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark"
            :href="'/gestoria/analisis-riesgo/' + estacionId">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Análisis de Riesgo</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irRequisitosLegales()">
            <div class="d-flex align-items-center">
                <i class="ti ti-certificate fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Requisitos Legales</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark"
            :href="'/gestoria/reporte-cre/' + estacionId">
            <div class="d-flex align-items-center">
                <i class="ti ti-report-analytics fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Reporte Estadístico de la CRE</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark"
            :href="'/gestoria/cambio-precio/' + estacionId">
            <div class="d-flex align-items-center">
                <i class="ti ti-report-money fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Cambio de Precio</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irProgramaAnual()">
            <div class="d-flex align-items-center">
                <i class="ti ti-calendar-event fs-4 text-primary me-3"></i>
                <span class="fw-medium fs-6">Programa Anual de Mantenimiento</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>


        <!-- SECCIÓN: SISTEMA SGM -->
        <div class="text-uppercase text-muted fw-bold px-1 pt-3 pb-1 mt-2" style="font-size: 0.725rem; letter-spacing: 0.05em;">
            Sistema de Gestión de Medición (SGM)
        </div>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irSGM()">
            <div class="d-flex align-items-center">
                <i class="ti ti-gauge fs-4 text-success me-3"></i>
                <span class="fw-medium fs-6">SGM</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark"
            :href="'/gestoria/calibracion-tanques/' + estacionId">
            <div class="d-flex align-items-center">
                <i class="ti ti-cylinder fs-4 text-success me-3"></i>
                <span class="fw-medium fs-6">Calibración de Tanques</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark"
            :href="'/gestoria/control-documental-sgm/' + estacionId">
            <div class="d-flex align-items-center">
                <i class="ti ti-files fs-4 text-success me-3"></i>
                <span class="fw-medium fs-6">Control documental del SGM</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>


        <!-- SECCIÓN: REGISTRO Y OPERACIÓN -->
        <div class="text-uppercase text-muted fw-bold px-1 pt-3 pb-1 mt-2" style="font-size: 0.725rem; letter-spacing: 0.05em;">
            PROFECO
        </div>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irProfeco()">
            <div class="d-flex align-items-center">
                <i class="ti ti-notebook fs-4 text-warning me-3"></i>
                <span class="fw-medium fs-6">Bitácora de registro de eventos (PROFECO)</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>


        <!-- SECCIÓN: PERSONAL -->
        <div class="text-uppercase text-muted fw-bold px-1 pt-3 pb-1 mt-2" style="font-size: 0.725rem; letter-spacing: 0.05em;">
            Lista del personal
        </div>

        <a class="nav-card d-flex align-items-center justify-content-between p-3 bg-white rounded-3 shadow-sm text-decoration-none text-dark pointer"
            @click.prevent="irPersonal()">
            <div class="d-flex align-items-center">
                <i class="ti ti-users fs-4 text-info me-3"></i>
                <span class="fw-medium fs-6">Personal</span>
            </div>
            <i class="ti ti-chevron-right text-muted opacity-50"></i>
        </a>

    </div>
</div>
    </div>

    <!-- ------------------------- -->
    <!-- fin offcanvas -------- -->

</div>