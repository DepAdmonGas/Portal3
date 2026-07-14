<div id="container" data-elemento="101" data-herramienta="2" data-id="0">

<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div x-data="{ ...actions(), ...listaasistenciaForm() }">
  <div class="card-body">

    <div class="float-end">
        <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>    
    </div>

    <h4 class="card-title mb-0">Fo.SGM.001 Lista de asistencia</h4>

  <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
          <th>#</th>
          <th>Fecha</th>
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
  </div>            
  </div>
</div>

</div>

<div class="col-md-6">

<div class="card">
  <div x-data="{ ...actions(), ...estructuraSm() }">
  <div class="card-body">

  <div class="float-end">
       <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="crearRevision()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>        
    </div>

<h4 class="card-title mb-0">Fo.SGM.002 Revisión del SGM, procedimientos y registros</h4>

  <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-revision-sgm" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Fecha</th>
            <th>Hora</th>
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
            Ayuda
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

          <p>
            <b>Bienvenido al elemento 1 Estructura del sistema de Medición</b>, en este elemento de manera anual deberás verificar que el SGM cumpla con la legislación vigente requisitando el formato 002 y dando a conocer al personal involucrado (con el formato 001) los cambios en caso de haberlos, de lo contrario solo informar que se realizó la revisión del SGM y cumple. 
          </p>
        
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->



</div>