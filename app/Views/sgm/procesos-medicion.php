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
  
   <div class="col-md-3">
    <div class="card bg-primary mt-2">
      <div class="card-body text-white fs-5">1. Programa anual de calibración de patrones e instrumentos de medida</div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card bg-info mt-2">
      <div class="card-body text-white fs-5">2. Bitácora la para la calibración de equipos</div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card bg-primary mt-2">
      <div class="card-body text-white fs-5">3. Programa anual de verificación de equipos</div>
    </div>
  </div>

   <div class="col-md-3">
    <div class="card bg-info mt-2">
      <div class="card-body text-white fs-5">4. Bitácora para la verificación de equipos de medicion</div>
    </div>
  </div>

</div>
