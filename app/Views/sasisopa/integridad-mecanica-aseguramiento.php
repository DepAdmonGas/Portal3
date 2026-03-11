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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

       <p>
          En este apartado podrás consultar el programa anual de mantenimiento, los procedimientos de operación, seguridad y mantenimiento y las características de las bitácoras conforme a la NOM-005-ASEA-2016 así como también deveras de hacer el registro de los equipos críticos con los que cuentes en la estación de servicio.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">Da clic en recuadro Programa anual de mantenimiento para visualizar</li>
            <li class="list-group-item">Da clic en el recuadro Procedimientos de operación, seguridad y mantenimiento para visualizar </li>
            <li class="list-group-item">Da clic en el recuadro de bitácoras para consultar las características </li>
            <li class="list-group-item">Da clic en el botón agregar para crear el listado de equipos críticos  </li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y <label class="text-danger fw-bold">Departamento de mantenimiento </label> (En caso de contar con el), el mantenimiento adecuado y el registro de los equipos críticos con los que cuenta la estación de servicio </p>

          <small>Nota:<br>
          Recuerda que un equipo critico hace referencia a aquellos que son capaces de generar una explosión o daño al personal por el mal funcionamiento, pero también se pueden definir como aquellos que son indispensables para el correcto funcionamiento de la estación de servicio y si fallan representan perdidas notables en las ventas
          </small>
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->