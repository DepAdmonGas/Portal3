<div id="container" class="pb-4">

    <div class="text-end mt-2">
          <?= 
          !empty($permisos['crear']) ? 
          '<a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf" type="button" class="btn btn-light">
          <i class="ti ti-download"></i> Descargar
          </a>' 
          : '' 
          ?>   
    </div>

      <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-personal-estacion" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Nombre Usuario</th>
            <th>Puesto</th>
            <th>Telefono</th>
            <th>Email</th>
            <th>Cumplimiento</th>
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