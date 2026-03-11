<div class="text-end mt-2">
  <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item" href=""><i class="ti ti-pencil"></i> Editar Politica</a>
                </li>
                <li>
                    <a class="dropdown-item" href=""><i class="ti ti-file-download"></i> Descargar Politica</a>
                </li>
            </ul>
        </div>
</div>

<div class="row mt-2">

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Programa anual de mantenimiento</h4>

         <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver programa 
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Procedimientos de Operación, Seguridad y Mantenimiento</h4>
          
          <div class="text-end mt-4">
          <button type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver procedimientos
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Bitácoras</h4>
          
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

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Operación y Mantenimiento</h4>
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
                <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-download"></i> Descargar</a>
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
           <th>#</th>
            <th>Nombre equipo</th>
            <th>Marca y Modelo</th>
            <th>Función</th>
            <th>Fecha de instalación</th>
            <th>Tiempo de vida</th>
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
