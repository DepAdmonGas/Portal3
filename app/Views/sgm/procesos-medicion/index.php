<?php

/** @var int $pendientesCalibracion */
/** @var int $pendientesVerificacion */
?>

<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data>

<div class="row mt-4">

<!----------------card  programa anual de claibracion----->
<div class="col-md-6 d-flex align-items-stretch mb-4">
        <a href="/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                         <i class="ti ti-number-1 text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                          Programa anual de calibración de patrones e instrumentos de medida
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                    <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>


    <!------------card de bitracora para la calibracion de equipos-------->
<?php if ($pendientesCalibracion > 0): ?>

<div class="col-md-6 d-flex align-items-stretch mb-4">
    <a href="/sgm/procesos-medicion/bitacora-calibracion-equipos" 
       class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">



            <!-- Bloque Superior: Icono + Título alineados -->
            <div class="d-flex align-items-center gap-3">
                <!-- Icono de Categoría -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px; font-size: 1.1rem;">
                     <i class="ti ti-number-2 text-white display-6"></i>

                </div>



                <!-- Título y Número alineados a la derecha y centrados verticalmente -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <span class="badge bg-primary-subtle text-primary fw-bold mb-2">
                         Pendientes:
                    <?= $pendientesCalibracion ?>
                       
                    </span>
<h4 class="fw-bold text-dark mb-0 lh-sm">
                       Bitácora la para la calibración de equipos
                    </h4>
                </div>
            </div>
        </div>



        
        <!-- Pie de la tarjeta: Acción -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
            </div>
        </div>

    </a>
</div>
<?php endif; ?>
    





    <!-----------card de programa anual de verificacion de equipos----->
<div class="col-md-6 d-flex align-items-stretch mb-4">
        <a  href="/sgm/procesos-medicion/programa-anual-verificacion-equipos"
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="ti ti-number-3 text-white display-6"></i>
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                        Programa anual de verificación de equipos
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <div class="icon-transition">
                   <i class="ti ti-arrow-narrow-right fs-5"></i>
                </div>
            </div>
        </a>
    </div>



<!--------card de vitacora para la verificacion------->
 <?php if ($pendientesVerificacion > 0): ?>

<div class="col-md-6 d-flex align-items-stretch mb-4">
    <a href="/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion" 
      class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">



            <!-- Bloque Superior: Icono + Título alineados -->
            <div class="d-flex align-items-center gap-3">
                <!-- Icono de Categoría -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px; font-size: 1.1rem;">
                    <i class="ti ti-number-4 text-white display-6"></i>
                </div>



                <!-- Título y Número alineados a la derecha y centrados verticalmente -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <span class="badge bg-primary-subtle text-primary fw-bold mb-2">
                        Pendientes:
                         <?= $pendientesVerificacion ?>
                       
                    </span>
<h4 class="fw-bold text-dark mb-0 lh-sm">
                  Bitácora para la verificación de equipos de medicion
                    </h4>
                </div>
            </div>
        </div>



        
        <!-- Pie de la tarjeta: Acción -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver detalle</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
            </div>
        </div>

    </a>
</div>
<?php endif; ?>
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

        <p>Bienvenido al elemento <b>7. PROCESOS DE MEDICIÓN.</b> A continuación, encontraras los programas de calibración y verificación de equipos de medición y patrones de medida, asi como sus respectivas bitácoras para el registro. </p>

    </div>
</div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->

</div>

<?php endif; ?>

</div>