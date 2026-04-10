<div id="container" data-elemento="3" data-herramienta="1">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item" href="requisitos-legales/configuracion"><i class="ti ti-list-check"></i> Requisitos</a>
                </li>
                 <li>
                    <a class="dropdown-item" href="/sasisopa/requisitos-legales/calendario-pdf"><i class="ti ti-calendar"></i> Calendario</a>
                </li>
            </ul>
        </div>
    </div>

<div class="row mt-2">
  <div class="col-md-3 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="/sasisopa/requisitos-legales/Municipal">
      <div class="card-body">
        <h4 class="card-title text-center">Municipal</h4>
        
        <div class="text-center fs-8 mt-4 mb-4 text-primary"><?= $requisitos['Municipal']['Cumplimiento'].'%' ?></div>
        <div class="text-end"><small><?= $requisitos['Municipal']['ToReFin'].' de '.$requisitos['Municipal']['ToRe'].' Requisitos' ?></small></div>


      </div>
      </a>
    </div>
  </div>

  <div class="col-md-3 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="/sasisopa/requisitos-legales/Estatal">
      <div class="card-body">
        <h4 class="card-title text-center">Estatal</h4>

        <div class="text-center fs-8 mt-4 mb-4 text-primary"><?= $requisitos['Estatal']['Cumplimiento'].'%' ?></div>
        <div class="text-end"><small><?= $requisitos['Estatal']['ToReFin'].' de '.$requisitos['Estatal']['ToRe'].' Requisitos' ?></small></div>
          
      </div>
      </a>
    </div>
  </div>

  <div class="col-md-3 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="/sasisopa/requisitos-legales/Federal">
      <div class="card-body">
        <h4 class="card-title text-center">Federal</h4>

        <div class="text-center fs-8 mt-4 mb-4 text-primary"><?= $requisitos['Federal']['Cumplimiento'].'%' ?></div>
        <div class="text-end"><small><?= $requisitos['Federal']['ToReFin'].' de '.$requisitos['Federal']['ToRe'].' Requisitos' ?></small></div>

      </div>
      </a>
    </div>
  </div>

  <div class="col-md-3 d-flex align-items-stretch">
    <div class="card w-100">
      <a href="/sasisopa/requisitos-legales/Varios">
      <div class="card-body">
        <h4 class="card-title text-center">Varios</h4>

        <div class="text-center fs-8 mt-4 mb-4 text-primary"><?= $requisitos['Varios']['Cumplimiento'].'%' ?></div>
        <div class="text-end"><small><?= $requisitos['Varios']['ToReFin'].' de '.$requisitos['Varios']['ToRe'].' Requisitos' ?></small></div>

      </div>
      </a>
    </div>
  </div>

</div>

<div class="mb-2"><small>Porcentaje de cumplimiento general</small></div>

  <?php 
  $totalRequisitos = 
    $requisitos['Municipal']['ToRe'] +
    $requisitos['Estatal']['ToRe'] +
    $requisitos['Federal']['ToRe'] +
    $requisitos['Varios']['ToRe'];

    $totalCumplimiento = 
    ($requisitos['Municipal']['Cumplimiento'] * $requisitos['Municipal']['ToRe']) +
    ($requisitos['Estatal']['Cumplimiento'] * $requisitos['Estatal']['ToRe']) +
    ($requisitos['Federal']['Cumplimiento'] * $requisitos['Federal']['ToRe']) +
    ($requisitos['Varios']['Cumplimiento'] * $requisitos['Varios']['ToRe']);

$cumplimiento = $totalRequisitos > 0 
    ? round($totalCumplimiento / $totalRequisitos, 0) 
    : 0;
  ?>
<div class="progress" style="height: 20px;">
    <div 
        class="progress-bar progress-bar-striped progress-bar-animated 
        <?= $cumplimiento == 100 ? 'text-bg-success' : ($cumplimiento >= 50 ? 'text-bg-warning' : 'text-bg-danger') ?>"
        role="progressbar"
        aria-valuenow="<?= $cumplimiento ?>"
        aria-valuemin="0"
        aria-valuemax="100"
        style="width: <?= $cumplimiento ?>%;">
        
        Cumple <?= $cumplimiento ?>%

    </div>
</div>

<div class="row mt-4">
<div class="col-md-7">

<div class="card">
  <div class="card-body">

  <div class="float-end">
      <div x-data="{ ...actions(), ...listaasistenciaForm() }">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>   
      </div>  
    </div>

  <h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>

  <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-asistencia" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Fecha</th>
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
                    
  </div>
</div>

</div>


</div>
</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 3 REQUISITOS LEGALES, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            Aquí vas a poder consultar, descargar e imprimir los requisitos legales aplicables a tu estación de servicio, así como también identificar el porcentaje de cumplimiento en los diferentes niveles de gobierno regulatorio.
          </p>
     
          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en el botón de Requisitos Legales</li>
            <li class="list-group-item">Selecciona el nivel de gobierno para visualizar los requisitos aplicables</li>
            <li class="list-group-item">Da clic en el icono PDF para visualizar o descargar</li>
          </ul>

          <p><small class="text-danger">* La barra indica el porcentaje de cumplimiento general de tus requisitos legales</small></p>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y <label class="text-danger fw-bold">Departamento de Gestión</label> el actualizar aquellos requisitos legales que cuentes con vigencia.</p>
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->