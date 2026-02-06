<div class="mb-2">
  <div class="action-btn layout-top-spacing d-flex align-items-center justify-content-between flex-wrap">
    <h1 class="mb-0 fs-7"><?= $title ?></h1>

    <div class="d-flex flex-wrap gap-6">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo">Nuevo</a></li>
                <li>
                    <a class="dropdown-item">Buscar</a>
                </li>
            </ul>
        </div>
    </div>

     </div>
</div>

<div class="datatables">

    <div class="table-responsive">
      <table id="table-tarjetas" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>

          <tr>
            <th>#</th>
            <th>No. Solicitud</th>
            <th>Fecha</th>
            <th>Solicita</th>
            <th>Estación</th>
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