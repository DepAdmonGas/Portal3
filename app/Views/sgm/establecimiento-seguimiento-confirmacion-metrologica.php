<div id="container" data-elemento="109" data-herramienta="2" data-id="0"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">


<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>



<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

<table class="table table-sm table-bordered table-striped align-middle">
  <thead >
    <tr>
      <th class="text-center aling-middle bg-primary text-white" colspan="5">ESPECIFICACIONES METROLÓGICAS </th>
    </tr>
    <tr>
      <th class="text-center aling-middle bg-primary text-white">Equipo</th>
      <th class="text-center aling-middle bg-primary text-white">Resolución</th>
      <th class="text-center aling-middle bg-primary text-white">Repetibilidad</th>
      <th class="text-center aling-middle bg-primary text-white">EMP</th>
      <th class="text-center aling-middle bg-primary text-white">Incertidumbre</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Tablas de calibración de tanques</td>
      <td>1mm</td>
      <td>*</td>
      <td>±0.5%</td>
      <td>0.2%</td>
    </tr>
    <tr>
      <td>Sensor de nivel automático</td>
      <td>1 mm</td>
      <td>*</td>
      <td>± mm</td>
      <td>1.5 mm</td>
    </tr>
     <tr>
      <td>Sensores de temperatura</td>
      <td>0.1 °C</td>
      <td>0.05 °C </td>
      <td>± 0.5°C</td>
      <td>0.2 °C</td>
    </tr>
    <tr>
      <td>Medidor de densidad para el cálculo CTL o CPL</td>
      <td>0.5 kg/m3</td>
      <td>*</td>
      <td>± 3kg/m3</td>
      <td>1 kg/m3</td>
    </tr>
    <tr>
      <td>Volumen a condiciones base</td>
      <td>*</td>
      <td>*</td>
      <td>*</td>
      <td>0.5%</td>
    </tr>
     <tr>
      <td>Medida volumétrica mayor a 10 L </td>
      <td>10 Ml </td>
      <td>*</td>
      <td>*</td>
      <td>0.025%  </td>
    </tr>
    <tr>
      <td>Termómetro</td>
      <td>1 °C</td>
      <td>*</td>
      <td>*</td>
      <td>*</td>
    </tr>
    <tr>
      <td>Cronometro</td>
      <td>0.01 s</td>
      <td>*</td>
      <td>*</td>
      <td>*</td>
    </tr>
    <tr>
      <td>Cinta Metálica</td>
      <td>1 mm</td>
      <td>*</td>
      <td>±1.5 mm (nueva) o ±2 mm (en uso)</td>
      <td>*</td>
    </tr>
  </tbody>
</table>

  </div>
</div>

</div>

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

         <p>Bienvenido al elemento <b>8. GESTIÓN DE RIESGOS QUE IMPACTAN EN LA MEDICIÓN</b>. Lee atentamente el procedimiento del elemento 8 del Manual de procedimientos del SGM, una vez que analices los riesgos da a conocer las medidas de mitigación de cada riesgo y asienta el registro en el formato 001.
          </p>
          
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->
<?php endif; ?>
</div>