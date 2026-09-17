

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>
<div class="row mt-3 align-items-start">

    <!-- Contenedor para las cards navegables (Se adaptan solo entre ellas) -->
    <div class="col-md-8">
        <div class="row">

            <!-- Procedimientos de Operación, Seguridad y Mantenimiento -->
            <div class="col-md-6 d-flex align-items-stretch mb-4">
                <a href="/uploads/archivos/procedimientos/DLES.ADMONGAS.001.pdf" target="_blank"
                   class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
                    
                    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <!-- Icono circular -->
                            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width: 60px; height: 60px;">
                                <i class="ti ti-settings-automation text-white display-6"></i> 
                            </div>

                            <!-- Título a la derecha -->
                            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                                <h4 class="fw-bold text-dark mb-0 lh-sm">
                                    Procedimientos de Operación, Seguridad y Mantenimiento
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Pie de la tarjeta -->
                    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                        <span class="small">Ver procedimientos</span>
                        <div class="icon-transition">
                            <i class="ti ti-arrow-right fs-5"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Programa anual de mantenimiento -->
            <div class="col-md-6 d-flex align-items-stretch mb-4">
                <a href="/sasisopa/control-actividades-procesos/programa-anual-mantenimiento" 
                   class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
                    
                    <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <!-- Icono circular -->
                            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width: 60px; height: 60px;">
                                <i class="ti ti-calendar-event text-white display-6"></i> 
                            </div>

                            <!-- Título a la derecha -->
                            <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                                <h4 class="fw-bold text-dark mb-0 lh-sm">
                                    Programa anual de mantenimiento
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Pie de la tarjeta -->
                    <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                        <span class="small">Ver programa</span>
                        <div class="icon-transition">
                            <i class="ti ti-arrow-right fs-5"></i>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>

<!-- Columna independiente para Bitácoras -->
    <div class="col-md-4 mb-4">
        <div class="card w-100 border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white d-flex align-items-center py-3">
                <i class="ti ti-settings-automation fs-4 me-2"></i>
                <h4 class="card-title text-white mb-0 fs-5">Bitácoras</h4>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-2">
                    
                    <!-- Configuración Bitácora -->
                    <a href="/sasisopa/control-actividades-procesos/configuracion-bitacora" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-user-cog fs-5 me-2"></i>Configuración Bitácora
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Recepción y Descarga del Producto -->
                    <a href="/sasisopa/control-actividades-procesos/recepcion-descarga-producto" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-gas-station fs-5 me-2"></i>Recepción y Descarga del Producto
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Mantenimiento Preventivo -->
                    <a href="/sasisopa/control-actividades-procesos/mantenimiento-preventivo" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-tool fs-5 me-2"></i>Mantenimiento Preventivo
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Mantenimiento Correctivo -->
                    <a href="/sasisopa/control-actividades-procesos/mantenimiento-correctivo" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-tool fs-5 me-2"></i>Mantenimiento Correctivo
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Calibración de equipos -->
                    <a href="/sasisopa/control-actividades-procesos/calibracion-equipos" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-gauge fs-5 me-2"></i>Calibración de equipos
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>
                    
                    <!-- Bitácora PROFECO -->
                    <a href="/sasisopa/control-actividades-procesos/bitacora-dispensario" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-clipboard-text fs-5 me-2"></i>Bitácora PROFECO
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Mantenimiento V.1.2 -->
                    <a href="/sasisopa/control-actividades-procesos/bitacora-mantenimiento" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-file-description fs-5 me-2"></i>Mantenimiento V.1.2
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                    <!-- Bitácora de Residuos Peligrosos -->
                    <a href="/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos" 
                       class="btn bg-info-subtle text-info w-100 text-start d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none waves-effect waves-light">
                        <span class="d-flex align-items-center fw-medium">
                            <i class="ti ti-biohazard fs-5 me-2"></i>Bitácora de Residuos Peligrosos
                        </span>
                        <i class="ti ti-chevron-right fs-6"></i>
                    </a>

                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 10. CONTROL DE ACTIVIDADES Y PROCESOS, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

       <p>
            Aquí vas a encontrar tu programa anual de mantenimiento y tus procedimientos de Operación, Seguridad y Mantenimiento.
          </p>
          <p>
           Recuerda que el programa anual de mantenimiento debe de empatar con los registros de la bitácora de mantenimiento preventivo y correctivo. 
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el botón de ver procedimientos para visualizar y descargar los procedimientos de Operación, Seguridad y Mantenimiento.</li>
            <li class="list-group-item">Mediante el tablón de noticias de la estación invita a todos los involucrados a consultar los procedimientos.</li>    
            <li class="list-group-item">Da clic en el botón de programa para poder visualizar tu Programa de Mantenimiento.</li>   
            <li class="list-group-item">Las fechas de las actividades del programa deben de empatar con las fechas de las bitácoras de mantenimiento preventivo y correctivo.</li>   
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>
          Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y <label class="text-danger fw-bold">Jefes de Piso</label>, <label class="text-danger fw-bold">Departamento de Mantenimiento</label> o en su caso prestadores de servicio llenar y firmar los checklist de las verificaciones de las bitácoras.</p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->