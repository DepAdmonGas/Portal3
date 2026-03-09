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

<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

 <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-comprobacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
            <td colspan="5" class="bg-primary text-white">ESPECIFICACIONES METROLÓGICAS </td>
          </tr>
          <tr>
          <th class="bg-primary text-white">Equipo</th>
          <th class="bg-primary text-white">Resolución</th>
          <th class="bg-primary text-white">Repetibilidad</th>
          <th class="bg-primary text-white">EMP</th>
          <th class="bg-primary text-white">Incertidumbre</th>
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
    <h4 class="card-title mb-0">Fo.SGM.002 Revisión del SGM, procedimientos y registros</h4>
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
