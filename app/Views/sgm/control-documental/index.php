<div id="container" data-elemento="102" data-herramienta="2" data-id="0">

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
  <div x-data="{ ...actions(), ...revision() }">
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

<div class="card">
  <div class="card-body">

  <div x-data="controlDocumental()">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo. SGM.003 Control documental del SGM</h4>
      <div class="ms-auto">
      <a type="button" class="btn" href="/sgm/control-documental-sistema-gestion-medicion/pdf">
        <i class="ti ti-download fs-7 text-primary"></i>
        </a>
      </div>
  </div>

  <table class="table table-bordered table-sm mt-3">

            <tbody>

                <template
                    x-for="doc in documentosPorSeccion(3)"
                    :key="doc.id">

                    <tr>

                        <td x-text="doc.nombre"></td>

                        <td width="40" class="text-center">

                            <template x-if="doc.url">

                                <a
                                    :href="doc.url"
                                    download>

                                    <i class="ti ti-file-download fs-7 text-primary"></i>

                                </a>

                            </template>

                            <template x-if="!doc.url">

                                <i class="ti ti-x text-danger fs-7"></i>

                            </template>

                        </td>

                    </tr>

                </template>

            </tbody>

  </table>

  <template x-for="seccion in [1,2]">

    <div class="mt-4">

      <h5 x-text="titulo(seccion)"></h5>

      <table class="table table-bordered table-hover">

        <thead>
          <tr>
          <th class="bg-primary text-white text-center">#</th>
          <th class="bg-primary text-white">Codificación</th>
          <th class="bg-primary text-white">Nombre</th>
          <th class="bg-primary text-white">Fecha aprobación</th>
          <th width="40"><i class="ti ti-file-download fs-7 text-muted"></i></th>
          </tr>
        </thead>
      <tbody>
        <template
        x-for="doc in documentosPorSeccion(seccion)"
        :key="doc.id">

        <tr>

        <td class="fw-bolder text-center" x-text="doc.id"></td>
        <td x-text="doc.codificacion"></td>
        <td x-text="doc.nombre"></td>
        <td x-text="doc.fecha_aprobacion"></td>
        <td class="text-center">
        <template x-if="doc.url">

        <a
        :href="doc.url"
        download>

        <i class="ti ti-file-download fs-7 text-primary"></i>

        </a>

        </template>

        <template x-if="!doc.url">

        <i class="ti ti-x text-danger fs-7"></i>

        </template>

        </td>

        </tr>

        </template>
      </tbody>

      </table>

    </div>
  </template>

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
            <b>Bienvenido al elemento 2 Control del documental del Sistema de Gestión de medición</b>, este elemento esta correlacionado con el elemento 1 por lo que adicional solo deberás revisar de manera anual los procedimientos y registros con el propósito de mantenerlos aprobados, actualizados y protegidos; considerando su distribución, acceso, control de cambios lo anterior dejando el registro en el formato 003. 
          </p>
        
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>