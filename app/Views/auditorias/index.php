<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

  <div class="row mt-4">

  <!-- Tarjeta 1: Formato Programa de auditorias -->
  <div class="col-12 col-md-4 d-flex align-items-stretch mb-4">
    <a href="/sasisopa/auditorias/programa" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-info text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-file-text text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Formato Programa de auditorias (Internas y externas)
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-info fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                                            <i class="ti ti-arrow-right fs-5"></i>

            </div>
        </div>
    </a>
  </div>

  <!-- Tarjeta 2: Auditoria interna -->
  <div class="col-12 col-md-4 d-flex align-items-stretch mb-4">
    <a href="/sasisopa/auditorias/interna" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-info text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-clipboard-check text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Auditoria interna
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-info fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                                            <i class="ti ti-arrow-right fs-5"></i>

            </div>
        </div>
    </a>
  </div>

  <!-- Tarjeta 3: Auditoria externa -->
  <div class="col-12 col-md-4 d-flex align-items-stretch mb-4">
    <a href="/sasisopa/auditorias/externa" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-info text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-file-certificate text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Auditoria externa
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-info fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                                            <i class="ti ti-arrow-right fs-5"></i>

            </div>
        </div>
    </a>
  </div>

</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 15. AUDITORÍAS, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado podrás consultar el programa de auditorias internas y externas así como también las competencias que deben de tener los auditores.</b>.
          </p>

          <hr>
 
          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en recuadro programa anual de auditorias <small>(Aquí podrás verificar y planear las auditorias internas y externas para el seguimiento del Sistema de Administración)</small></li>
            <li class="list-group-item">Da clic en el recuadro Auditoria interna para hacer el registro de las auditorias realizadas por internos de la empresa.</li>
            <li class="list-group-item">Da clic en el recuadro Auditoria externa para realizar el registro de las auditorias realizadas por el tercer autorizado ante la ASEA <small>(Recuerda que esta auditoria debe ser de manera bianual)</small>.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y en su caso departamento de <label class="text-danger fw-bold">Gestión</label>, coordinar las auditorias externas con un tercer acreditado ante la <b>ASEA</b>, es responsabilidad del Gerente Técnico del envió del Informe de auditoria y plan de atención de hallazgos a la ASEA.</p>

          <small>
          Nota: 
          El auditor interno deberá ser designado por la alta dirección o por quien corresponda, mismo que deberá conocer a profundidad la operación de la estación, así como también el Sistema de Administración que se esta implementando.
          </small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

<?php endif; ?>
</div>