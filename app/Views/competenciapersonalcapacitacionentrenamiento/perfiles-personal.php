<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

    <div class="text-end mt-2">
          <?= 
          !empty($permisos['crear']) ? 
          '<a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf" type="button" class="btn bg-primary-subtle text-primary">
          <i class="ti ti-download"></i> Descargar
          </a>' 
          : '' 
          ?>   
    </div>

      <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
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