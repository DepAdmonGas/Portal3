<div id="container" x-data="{ ...actions(), ...mejoresPracticasEstandares()}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div class="card-header">

 <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Diseño y construcción</h4>
      <div class="ms-auto">

        <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" arial-explaned="false">
              <i class="ti ti-dots-vertical fs-4"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <?= 
              !empty($permisos['crear']) ? 
              '<li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModalDC()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>' 
              : '' 
            ?>   

              <?= 
              !empty($permisos['descargar']) ? 
              '<li>
                <a class="dropdown-item pointer" href="/sasisopa/mejores-practicas-estandares/pdf-diseno-construccion"><i class="ti ti-download"></i> Descargar</a>
              </li>
              <li>' 
              : '' 
            ?>   
              
                
              </li>
            </ul>
          </div>   
      
      </div>
  </div>
  </div>
  <div class="card-body">

 

  <div class="datatables ">
    <div class="table-responsive pb-4 overflow-x-auto overflow-hidden">
      <table id="table-diseno-construccion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
          <th style="max-width:48%; white-space:normal; word-break:break-word;">Código, estándar, normatividad o práctica de ingeniería.</th>
          <th style="max-width:48%; white-space:normal; word-break:break-word;">Área, maquinaria, equipo o instalación a la que aplica. 	</th>
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

</div>

<div class="col-md-6">

<div class="card">
  <div class="card-header">
  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Operación y Mantenimiento</h4>
      <div class="ms-auto">
      <div class="dropdown dropstart">
            <a href="javascript:void(0)" class="link btn btn-light" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
 
            <i class="ti ti-dots-vertical fs-4"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <?= 
              !empty($permisos['crear']) ? 
              '<li>
                <a class="dropdown-item pointer" href="javascript:void(0)" @click="openModalOM()"><i class="ti ti-plus"></i> Agregar</a>
              </li>
              <li>' 
              : '' 
            ?>   

              <?= 
              !empty($permisos['descargar']) ? 
              '<li>
                <a class="dropdown-item pointer" href="/sasisopa/mejores-practicas-estandares/pdf-operacion-mantenimiento"><i class="ti ti-download"></i> Descargar</a>
              </li>
              <li>' 
              : '' 
            ?>   
            </ul>
          </div>   
      </div>
  </div>

  </div>
  <div class="card-body">


  <div class="datatables">
    <div class="table-responsive pb-4 overflow-x-auto overflow-hidden">
      <table id="table-operacion-mantenimiento" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Fecha</th>
            <th>Norma</th>
            <th>Nombre</th>
            <th>Link</th>
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
 

</div>
</div>


    <div class="modal fade" id="modalDC" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
        <i class="ti ti-home-plus"></i></i>  
        Diseño y construcción</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModalDC()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* Código, estándar, normatividad o práctica de ingeniería:</label>
        <textarea class="form-control mb-2" x-model="dc.codigo"
                :class="errorsDC.codigo ? 'is-invalid' : ''"
                @input="errorsDC.codigo = false"></textarea>

        <label class="form-label">* Área, maquinaria, equipo o instalación a la que aplica:</label>
        <textarea class="form-control mb-2" x-model="dc.area"
                :class="errorsDC.area ? 'is-invalid' : ''"
                @input="errorsDC.area = false"></textarea>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModalDC()">
          <i class="ti ti-x"></i> 
        Cancelar
      </button>
        <button class="btn btn-success" @click="guardarDC()">
          <i class="ti ti-check">

        </i> Guardar
        </button>
    </div>

    </div>
    </div>
    </div>

    <!--- Modal OM -->

    <div class="modal fade" id="modalOM" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
       <i class="ti ti-settings-plus"></i></i>
        Operación y Mantenimiento</h4>
        <button type="button"class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModalOM()"></button>
    </div>

    <div class="modal-body">

        <label class="form-label">* Fecha::</label>
        <input type="date" class="form-control mb-2" x-model="om.fecha"
                :class="errorsOM.fecha ? 'is-invalid' : ''"
                @input="errorsOM.fecha = false"/>

        <label class="form-label">* Norma:</label>
        <textarea class="form-control mb-2" x-model="om.norma"
                :class="errorsOM.norma ? 'is-invalid' : ''"
                @input="errorsOM.norma = false"></textarea>

        <label class="form-label">* Nombre:</label>
        <textarea class="form-control mb-2" x-model="om.nombre"
                :class="errorsOM.nombre ? 'is-invalid' : ''"
                @input="errorsOM.nombre = false"></textarea>

        <label class="form-label">* Link:</label>
        <textarea class="form-control mb-2" x-model="om.link"
                :class="errorsOM.link ? 'is-invalid' : ''"
                @input="errorsOM.link = false"></textarea>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModalOM()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardarOM()"><i class="ti ti-check"></i> Guardar
        </button>
    </div>

    </div>
    </div>
    </div>
<?php endif; ?>
</div>
<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Bienvenido al elemento 9 MEJORES PRÁCTICAS Y ESTÁNDARES
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

        <p>
            Aquí vas a poder consultar la <b>NOM-005 ASEA 2016</b> para la etapa actual de tu estación de servicio
          </p>
          <p>
            La política debe ser comunicada a todo el personal incluyendo clientes, prestadores de servicios y proveedores.
          </p>

          <hr>

          <label class="fw-bold">Como hacerlo:</label>
          <ul class="list-group list-group-flush">
            <li class="list-group-item">En extracto encontraras los artículos aplicables a la etapa actual de la estación</li>
            <li class="list-group-item">Podrás descargar la Norma oficial mexicana completa dando clic en el enlace </li>
          </ul>

          <hr>

          <label class="fw-bold">Responsables:</label>
          <p>Recuerda que es responsabilidad del <label class="text-danger fw-bold">Representante Técnico</label> (RT), <label class="text-danger fw-bold">Gerente de la Estación</label> el conocer y poner en práctica lo establecido en la <b>NOM-005 ASEA 2016</b>, esto con la finalidad de llevar acabo las mejores prácticas.</p>
      
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->
