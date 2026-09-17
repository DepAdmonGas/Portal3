<div id="container"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="row mt-4">
    <!-- Control y documentos de Requisitos Legales -->
    <div class="col-md-6 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-documentos-registros/requisitos-legales" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-scale text-white display-6"></i> 
                    </div>

                    <!-- Título a la derecha (sin text-uppercase) -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Control y documentos de Requisitos Legales
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Control y documentos del Sistema de Administración -->
    <div class="col-md-6 d-flex align-items-stretch mb-4">
        <a href="/sasisopa/control-documentos-registros/sistema-administracion" 
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-folder text-white display-6"></i> 
                    </div>

                    <!-- Título a la derecha (sin text-uppercase) -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Control y documentos del Sistema de Administración
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 8. CONTROL DE DOCUMENTOS Y REGISTROS, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

        <p>Aquí vas a poder visualizar los documentos y registros del Sistema de Administración.</p>
          <p>La política debe ser comunicada a todo el personal incluyendo clientes, prestadores de servicios y proveedores.</p>

          <hr>

          <label class="fw-bold" >Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en recuadro Control de requisitos legales para consultar los permisos de tu estación de servicio </li>
            <li class="list-group-item">Da clic en el recuadro Control de documentos del Sistemas de Administración para consultar y descargar los formatos de registro del SA (Esto es únicamente de manera informativa ya que los registros se llenan con ayuda del sistema)</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> conocer y realizar los registros correspondientes de cada elemento del SA.</p>

          <small>Nota:<br>
          Portal AdmonGas es una herramienta que te ayuda a realizar los registros requeridos por el SA, es decir simplifica el llenado de los formatos.
          </small>
      
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->
