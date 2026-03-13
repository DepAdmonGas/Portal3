<div id="container" data-idsasisopa="1">

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
  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-primary">Politica:</h4>
          <p class="card-text fs-4 fw-normal">
          <?=$user->estacion->politica; ?>
          </p>
      </div>
    </div>
  </div>

  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-primary">Misión:</h4>
          <p class="card-text fs-4 fw-normal">
          <?=$user->estacion->mision; ?>
          </p>
      </div>
    </div>
  </div>

  <div class="col-md-4 d-flex align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title text-primary">Visión:</h4>
          <p class="card-text fs-4 fw-normal">
          <?=$user->estacion->vision; ?>
          </p>
      </div>
    </div>
  </div>

</div>


<div class="row">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.ADMONGAS.001 (Lista de comprobación)</h4>
      <div class="ms-auto">
        <button type="button" class="btn">
        <i class="ti ti-plus fs-7 text-primary"></i>
        </button>
      
      </div>
  </div>

  <div class="datatables">

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
    <h4 class="card-title mb-0">Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)</h4>
      <div class="ms-auto">
      <button type="button" class="btn">
        <i class="ti ti-plus fs-7 text-primary"></i>
        </button>
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 1. POLITICA, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            Aquí vas a encontrar la política de tu empresa acorde a los requisitos solicitados en las Disposiciones 
            Administrativas de Carácter General <b>(DACG)</b>, Sistemas de Administración de Seguridad Industrial, 
            Seguridad Operativa y Protección al Medio Ambiente <b>(SASISOPA)</b>.
          </p>
          <p>
            La política debe ser comunicada a todo el personal incluyendo clientes, prestadores de servicios y proveedores.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item disabled">Elegir un día a la semana para comunicar política en una plática de 5 minutos</li>
            <li class="list-group-item disabled">Imprimir y colocar en el tablón de anuncios de la estación</li>
            <li class="list-group-item disabled">Subirla a la página web (en caso de contar)</li>
            <li class="list-group-item disabled">Elaborar trípticos y distribuirlos entre los empleados</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> y <label class="text-danger fw-bold">Jefes de Piso</label>, comunicar la política a todas las partes interesadas.</p>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>