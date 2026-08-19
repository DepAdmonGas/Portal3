<div id="container" x-data="{ ...actions(), ...equipoCritico()}">

<div class="row mt-2">

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Programa anual de mantenimiento</h4>

         <div class="text-end mt-4">
          <a type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info"
          href="/sasisopa/control-actividades-procesos/programa-anual-mantenimiento">
            <i class="ti ti-eye"></i>
            Ver programa 
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Procedimientos de Operación, Seguridad y Mantenimiento</h4>
          
          <div class="text-end mt-4">
          <a type="button" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info"
          target="_blabk" href="/uploads/archivos/procedimientos/DLES.ADMONGAS.001.pdf">
            <i class="ti ti-eye"></i>
            Ver procedimientos
          </a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-md-4 align-items-stretch">
    <div class="card w-100">
      <div class="card-body">
        <h4 class="card-title">Bitácoras</h4>
          
          <div class="text-end mt-4">
          <a type="button" href="/sasisopa/integridad-mecanica-aseguramiento/bitacoras" class="btn waves-effect waves-light btn-rounded bg-info-subtle text-info">
            <i class="ti ti-eye"></i>
            Ver detalle
          </a>
        </div>

      </div>
    </div>
  </div>

</div>

<div class="card">
  <div class="card-header">
  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Lista de equipos críticos</h4>
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ti ti-dots-vertical fs-6"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
             <?= 
              !empty($permisos['crear']) ? 
              '<li>
                    <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModal()"><i class="ti ti-plus"></i> Agregar</a>
                </li>' 
              : '' 
              ?>   
              <li>
                <a class="dropdown-item" href="/sasisopa/integridad-mecanica-aseguramiento/pdf-equipo-critico"><i class="ti ti-download"></i> Descargar</a>
              </li>
            </ul>
          </div>   
      </div>
  </div>
  </div>
  <div class="card-body">



  <div class="datatables">
    <div class="table-responsive pb-4 overflow-x-auto overflow-hidden">
      <table id="table-equipo-critico" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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


    <div class="modal fade" id="modalEquipo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
         <i class="ti ti-device-desktop-plus"></i>
        Agregar equipo critico
      </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* Nombre:</label>
        <textarea class="form-control mb-2" x-model="equipo.nombre"
                :class="errors.nombre ? 'is-invalid' : ''"
                @input="errors.nombre = false"></textarea>

        <label class="form-label">* Marca y Modelo:</label>
        <textarea class="form-control mb-2" x-model="equipo.marca_modelo"
                :class="errors.marca_modelo ? 'is-invalid' : ''"
                @input="errors.marca_modelo = false"></textarea>

        <label class="form-label">* Función:</label>
        <textarea class="form-control mb-2" x-model="equipo.funciones"
                :class="errors.funciones ? 'is-invalid' : ''"
                @input="errors.funciones = false"></textarea>

        <label class="form-label">* Fecha Instalación <small>(Aproximado)</small>:</label>
        <input class="form-control mb-2" type="date" x-model="equipo.fecha_instalacion"
                :class="errors.fecha_instalacion ? 'is-invalid' : ''"
                @input="errors.fecha_instalacion = false">  

        <label class="form-label">* Tiempo de vida <small>(Años)</small>:</label>
        <input class="form-control mb-2" type="number" x-model="equipo.tiempo_vida"
                :class="errors.tiempo_vida ? 'is-invalid' : ''"
                @input="errors.tiempo_vida = false">  

        <label class="form-label">* Manual:</label>
        <input class="form-control" type="file" x-model="equipo.manual"
                x-ref="fileManual"
                :class="errors.manual ? 'is-invalid' : ''"
                @input="errors.manual = false">  


    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardar()"><i class="ti ti-check"></i> Guardar
        </button>
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