<div class="mt-3">
  <div class="action-btn layout-top-spacing d-flex align-items-center justify-content-between flex-wrap">
    <h4 class="fw-semibold"><?=$title;?></h4>

    <div class="d-flex flex-wrap gap-6">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-help"></i> Ayuda</a></li>
                <li>
                    <a class="dropdown-item" href=""><i class="ti ti-pencil"></i> Editar Politica</a>
                </li>
                <li>
                    <a class="dropdown-item" href=""><i class="ti ti-file-download"></i> Descargar Politica</a>
                </li>
            </ul>
        </div>
    </div>


  </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a class="link-info text-decoration-none" href="">SASISOPA</a>
        </li>
        <li class="breadcrumb-item" aria-current="page"><?=$title;?></li>
    </ol>
</nav>

<div class="row mt-4">

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Monitoreo de aspectos ambientales y riesgos</h4>

         <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle 
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Calibración, Verificación y mantenimiento de equipos</h4>
          
          <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Evaluación y cumplimiento de requisitos legales</h4>
          
          <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-3 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Administración de hallazgos derivados del monitoreo del sistema de administración</h4>
          
          <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </button>
        </div>

      </div>
    </div>
  </div>

</div>
