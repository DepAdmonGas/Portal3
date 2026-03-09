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
            <a class="link-info text-decoration-none" href="">SGM</a>
        </li>
        <li class="breadcrumb-item" aria-current="page"><?=$title;?></li>
    </ol>
</nav>

<div class="mt-4 fs-6">
  1. Gestión de personal, funciones y roles
</div>
<div class="row mt-3">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.SGM.007 Designación de responsable SGM</h4>
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
    <h4 class="card-title mb-0">Fo.SGM.008 Lista de personal</h4>
  </div>

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
<div class="mt-2 fs-6">
  2. Capacitación del personal
</div>

<div class="row mt-2">
  <div class="col-md-3">
    <div class="card bg-primary">
      <div class="card-body text-white fs-5">Programa Capacitacion Interna</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-primary">
      <div class="card-body text-white fs-5">Programa Capacitacion Externa</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-primary">
      <div class="card-body text-white fs-5">Capacitación de inducción</div>
    </div>
  </div>
</div>

<div class="row">
  

   <div class="col-md-3">
    <div class="fs-6">
    3. Gestión de equipos
    </div>
    <div class="card bg-primary mt-2">
      <div class="card-body text-white fs-5">Inventario de equipo</div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="fs-6">
    4. Evaluación de proveedores y servicios
  </div>
    <div class="card bg-primary mt-2">
      <div class="card-body text-white fs-5">Orden de servicio y Evaluación de proveedores</div>
    </div>
  </div>
</div>




