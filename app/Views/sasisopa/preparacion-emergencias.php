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

  <div class="col-md-6 align-items-stretch">
    <div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0"> Protocolo de respuesta a emergencias </h4>
      <div class="ms-auto">
        <button type="button" class="btn">
        <i class="ti ti-plus fs-7 text-primary"></i>
        </button>
      
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-comprobacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
          <th>Fecha elaboración</th>
          <th>PDF</th>
          <th>Anexo</th>
          <th>Editar</th>
          <th>Eliminar</th>
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

    <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title"> Teléfonos de emergencias </h4>

         <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver telefonos 
          </button>
        </div>

      </div>
    </div>
  </div>

  </div>

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Programa anual de simulacros</h4>
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots fs-7"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <li>
                <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>
                <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-search"></i> Buscar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-comprobacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Nombre del simulacro</th>
            <th>Periodicidad</th>
            <th>Fecha</th>
            <th>Personal que asiste</th>
            <th>Resumen</th>
            <th>Evaluación (Fo.ADMONGAS.016a)</th>
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
