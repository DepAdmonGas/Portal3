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

<div class="row mt-3">
  <div class="col-md-9"></div>
  <div class="col-md-3 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-center">Responsabilidades</h4>

        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Representante Técnico</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Gerente</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Jefe de Piso</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Facturista</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Despachador</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Auxiliar administrativo</button>
        <button type="button" class="btn bg-info-subtle text-info w-100 mt-2">Mantenimiento</button>
         
      </div>
    </div>
  </div>
</div>

<div class="row">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>
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
          <th>#</th>
          <th>Fecha</th>
          <th>Hora</th>
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

<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Formato de asignación de representante técnico</h4>
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
           <th>#</th>
            <th>Fecha</th>
            <th>Hora</th>
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
