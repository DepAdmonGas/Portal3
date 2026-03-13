<div class="text-end mt-2">
  <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Agregar</a></li>
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-download"></i> Descargar</a></li>
            </ul>
        </div>
</div>
<div class="card mt-2">
  <div class="card-body">

  <div class="d-flex align-items-center">
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
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Puesto</th>
            <th>Descripción evento</th>
            <th>Tipo evento</th>
            <th>Muertes</th>
            <th>Grupo interdiciplinario</th>
            <th>Fo.ADMONGAS.026</th>
            <th>Tercer Autorizado</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
                    
  </div>
</div>

<div class="card">
  <div class="card-body">
  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Sin accidentes a la fecha</h4>
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
            <th>Nombre completo</th>
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
            Bienvenido al elemento 16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES, del Sistema de Administración
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            En este apartado podrás registrar los accidentes ocurridos dentro de la estación de servicio.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul>
            <li>Da clic en el botón <i class="ti ti-plus fs-6 text-primary"></i> para agregar un nuevo registro sobre algún incidente o accidente ocurrido.</small></li>
            <li>La investigación e informe de los eventos tipo 1 y 2 (Excepto cuando exista muerte de una o mas personas dentro de las instalaciones) puede realizarse por personal interno especializado utilizando un procedimiento para identificar la causa raíz de los accidentes, sin embargo también se podrá contratar un tercer autorizado ante la ASEA.</li>
            <li>Cuando el evento es tipo 2 (Existe muerte de una o mas personas dentro de las instalaciones) y tipo 3 se deberá contratar aun tercer autorizado para realizar la investigación causa raíz.</li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label>, <label class="text-danger fw-bold">Representante Legal</label> y departamento de mantenimiento realizar la investigación causa raíz así como el informe detallado.</p>

          <small>
            <div>Nota:</div>
            <p>No olvides los siguientes conceptos:</p>
            <b>Accidente:</b> Evento que ocasiona afectaciones al personal, a la Población, a los bienes propiedad de la Nación, a los equipos e instalaciones, a los sistemas y/o procesos operativos y al medio ambiente.<br>
            <b>Incidente:</b> Evento o combinación de eventos inesperados no deseados que alteran el funcionamiento normal de las Instalaciones, del proceso o de la industria; acompañado o no de afectación al Ambiente, a las Instalaciones, a la Población y/o al personal del Regulado, así como al personal de contratistas, subcontratistas, proveedores y prestadores de servicios.

          </small>

    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->