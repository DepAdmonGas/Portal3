<div id="container"
    data-elemento="102" data-herramienta="2" data-id="0"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content">

<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div x-data="{ ...actions(), ...listaasistenciaForm() }">
<div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="card-title mb-0">Fo.SGM.001 Lista de asistencia</h4>
  <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn bg-primary-subtle text-primary" @click="crearAsistencia()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?>  
</div>

  <div class="card-body">

  <div class="datatables mt-4">
    <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
      <table id="table-lista-asistencia" class="table table-striped table-bordered  text-nowrap align-middle">
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
    <div class="card-header d-flex justify-content-between align-items-center">
<h4 class="card-title mb-0">Fo.SGM.002 Revisión del SGM, procedimientos y registros</h4>

       <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn bg-primary-subtle text-primary text-nowrap" @click="crearRevision()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
        ?> 
    </div>
  <div class="card-body">

  <div class="datatables mt-4">
    <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
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
<div class="card-header">


   <div class="d-flex justify-content-between align-items-center">
    <h4 class="card-title mb-0">Fo. SGM.003 Control documental del SGM</h4>
    
      <div class="ms-auto">
      <a type="button" class="btn bg-primary-subtle text-primary" href="/sgm/control-documental-sistema-gestion-medicion/pdf">
        Descargar
        <i class="ti ti-download"></i>
        </a>
      </div>
  </div>
</div>

  <div class="card-body">

  <div  x-data="controlDocumental()">



  <table class="table table-striped table-bordered text-nowrap align-middle mb-4">

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
<div class="card">
  <div class="card-header card-colored-header bg-primary">
    <h5 class="card-title text-white" x-text="titulo(seccion)"></h5>
  </div>

    <div class="card-body p-0">

  

      <table class="table table-bordered table-striped mb-0">

        <thead>
          <tr>
          <th class="text-center">#</th>
          <th class="text-center">Codificación</th>
          <th class="text-center">Nombre</th>
          <th class="text-center">Fecha aprobación</th>
          <th width="40"><i class="ti ti-file-download fs-7 text-muted"></i></th>
          </tr>
        </thead>
      <tbody>
        <template
        x-for="doc in documentosPorSeccion(seccion)"
        :key="doc.id">

        <tr>

        <td class="fw-bolder text-center" x-text="doc.id"></td>
        <td  class="text-cenetr" x-text="doc.codificacion"></td>
        <td class="text-center" x-text="doc.nombre"></td>
        <td  class="text-center" x-text="doc.fecha_aprobacion"></td>
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

<?php endif; ?>

</div>