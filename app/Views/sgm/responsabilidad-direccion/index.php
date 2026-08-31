<div id="container" data-elemento="103" data-herramienta="2" data-id="0"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content">


<div class="card mt-4">
  <div class="card-body">

  <div x-data="{ ...actions(), ...politicaForm() }">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">POLITICA</h4>
      <div class="ms-auto">
      <a type="button" class="btn" href="/sgm/responsabilidades-direccion/politica-sgm">
        <i class="ti ti-edit fs-7 text-primary"></i>
        </a>
      </div>
  </div>

  <div x-data="{...actions(),...politicaForm()}">

<div class="row">

<div class="col-md-4">

  <table class="table table-sm table-bordered table-hover">
    <thead>
    <tr>
    <th class="text-center bg-primary text-white">#</th>
    <th class="text-center bg-primary text-white">Fecha</th>
    <th class="text-center bg-primary text-white"><i class="ti ti-trash  fs-7"></i></th>
    </tr>
    </thead>
    <tbody>
    <template
    x-for="(item,index) in politicas"
    :key="item.id"
    >
    <tr
    @click="seleccionar(item)"
    :class="{
    'table-primary':politica?.id==item.id
    }"
    style="cursor:pointer"
    >
    <td class="text-center align-middle"
    x-text="index+1"
    ></td>
    <td class="text-center align-middle"
    x-text="item.fecha"
    ></td>
    <td class="text-center align-middle" width="40">
    <a
    class="text-danger"
    @click.stop="eliminar(item)"
    >
    <i class="ti ti-trash fs-7"></i>
    </a>
    </td>
    </tr>
    </template>

    <tr
    x-show="politicas.length==0"
    >

    <td
    colspan="3"
    class="text-center text-muted"
    >

    No hay políticas registradas

    </td>

    </tr>

    </tbody>

  </table>

</div>

<div class="col-md-8">

<template x-if="politica">

<div>

<div>

<b>Fecha:</b>

<span
x-text="politica.fecha"
></span>

</div>

<div
class="mt-3"
x-html="politica.contenido"
></div>

<div class="row mt-5">

<div class="col-3">

<img
src="<?= $_ENV['APP_URL'] . '/uploads/firma-personal/' . $estacion->firma ?>"
class="img-fluid"
>

<hr>

<div class="text-center">

<b><?= $estacion->apoderado_legal ?></b>

</div>

<div class="text-center">

Representante Legal

</div>

</div>

</div>

</div>

</template>

</div>

</div>

</div>

  </div>

  </div>
</div>

<div class="row">
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
            <b>Bienvenido al elemento 3 Responsabilidades de la dirección</b>, este elemento esta correlacionado con el elemento 1 y 2 por lo que adicional solo deberás revisar de manera mensual deberás dar a conocer la política a los colaboradores, clientes, proveedores y de manera anual verificar que la política cumpla con los requerimientos de la estación de servicio, ambas actividades deberán registrarse bajo el formato 001.
          </p>
        
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>

<?php endif; ?>

</div>