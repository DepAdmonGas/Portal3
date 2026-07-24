<?php

/** @var int $pendientesCalibracion */
/** @var int $pendientesVerificacion */
?>

<div class="row mt-4">

    <div class="col-md-3">
        <a href="/sgm/procesos-medicion/programa-anual-calibracion-patrones-instrumentos-medida">
            <div class="card bg-primary mt-2">
                <div class="card-body text-white fs-5">1. Programa anual de calibración de patrones e instrumentos de medida</div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/sgm/procesos-medicion/bitacora-calibracion-equipos">
            <div class="card bg-info mt-2">
                <div class="card-body">
                    <?php if ($pendientesCalibracion > 0): ?>
                        <span class="badge rounded-pill text-bg-light float-end fs-1">
                            <?= $pendientesCalibracion ?>
                        </span>
                    <?php endif; ?>

                    <div class="fs-5 text-white">2. Bitácora la para la calibración de equipos</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/sgm/procesos-medicion/programa-anual-verificacion-equipos">
            <div class="card bg-primary mt-2">
                <div class="card-body text-white fs-5">3. Programa anual de verificación de equipos</div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion">
            <div class="card bg-info mt-2">
                <div class="card-body">
                    <?php if ($pendientesVerificacion > 0): ?>
                        <span class="badge rounded-pill text-bg-light float-end fs-1">
                            <?= $pendientesVerificacion ?>
                        </span>
                    <?php endif; ?>
                    <div class="fs-5 text-white">4. Bitácora para la verificación de equipos de medicion</div>
                </div>
            </div>
        </a>
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

        <p>Bienvenido al elemento <b>7. PROCESOS DE MEDICIÓN.</b> A continuación, encontraras los programas de calibración y verificación de equipos de medición y patrones de medida, asi como sus respectivas bitácoras para el registro. </p>

    </div>
</div>
<!-- ------------------------- -->
<!-- fin offcanvas -------- -->