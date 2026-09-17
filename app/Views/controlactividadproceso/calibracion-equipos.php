<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= $estacionId ?? '' ?>"
class="mb-4">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="row mt-4">

    <!-- 1. Configuración de Tanques de almacenamiento -->
    <div class="col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-tanques" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            1. Configuración de Tanques de almacenamiento
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- 2. Configuración de Dispensarios -->
    <div class="col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-dispensario" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            2. Configuración de Dispensarios
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Configuración de Sondas de medición -->
    <div class="col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-sondas-medicion" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            3. Configuración de Sondas de medición
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- 4. Configuración de Jarra patron -->
    <div class="col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            4. Configuración de Jarra patron
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- 5. Bitácora calibración de equipos -->
    <div class="col-md-4 col-sm-12 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-adjustments-horizontal text-white display-6"></i> 
                    </div>
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            5. Bitácora calibración de equipos
                        </h4>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

</div>
<?php endif; ?>

</div>