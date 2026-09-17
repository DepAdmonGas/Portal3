<?php 
/** @var \Illuminate\Database\Eloquent\Collection $programas */
?>
<div id="container" class="pb-4" x-data="{ ...actions(), ...programaMantenimiento() }"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

  <div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn bg-primary-subtle text-primary" @click="nuevo()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

<div class="row mt-3">
<?php
foreach($programas as $program):?>

<div class="col-md-3 d-flex align-items-stretch mb-4">
    <a href="/sasisopa/control-actividades-procesos/programa-anual-mantenimiento/<?= htmlspecialchars($program->id) ?>" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <!-- Icono circular -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px;">
                    <i class="ti ti-calendar-cog text-white display-6"></i> 
                </div>

                <!-- Título a la derecha -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <h4 class="fw-bold text-dark mb-0 lh-sm">
                        <?= htmlspecialchars($program->year) ?>
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver programa</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
            </div>
        </div>
    </a>
</div>

<?php endforeach; ?>

</div>
<?php endif; ?>
</div>

<!-- ------------------------- -->
<!-- inicio offcanvas -------- -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHelp" aria-labelledby="offcanvasHelpLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasExampleLabel">
            Configuración inicial del Programa anual de mantenimiento
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body fs-4">

    
          <p class="text-justify">
            Al dar clic en Aceptar para acceder a tu programa anual de mantenimiento, da clic en el icono de <i class="ti ti-plus fs-7 text-success"></i> y selecciona de la lista desplegable el equipo o instalación (Periodicidad se dará por default). Selecciona la última fecha en la cual diste mantenimiento al equipo o instalación, da clic en aceptar.</br>
            En caso de cometer error ubica el equipo o instalación y da clic en el botón editar o en su defecto eliminar.
          </p>
          <p class="text-secondary">
            Nota: De la lista desplegable selecciona solo aquellas actividades que correspondan a tu estación.
          </p>
     
    </div>
  </div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->