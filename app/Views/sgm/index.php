<div id="container"
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
<?php foreach($elementos as $elemento): ?>

<div class="col-md-4 d-flex align-items-stretch mb-4">
    <a href="sgm/<?= $elemento->url ?>" 
       class="card shadow-sm h-100 w-100 border text-decoration-none card-hover overflow-hidden position-relative">
        
        <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
            <!-- Bloque Superior: Icono + Título alineados -->
            <div class="d-flex align-items-center gap-3">
                <!-- Icono de Categoría -->
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 60px; height: 60px; font-size: 1.1rem;">
                    <i class="ti ti-layout-grid text-white display-6"></i> 
                </div>

                <!-- Título y Número alineados a la derecha y centrados verticalmente -->
                <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                    <span class="badge bg-primary-subtle text-primary fw-bold mb-2">
                        Elemento <?= $elemento->no ?>
                    </span>
<h4 class="fw-bold text-dark mb-0 lh-sm text-uppercase">
                            <?= $elemento->criterio ?>
                    </h4>
                </div>
            </div>
        </div>

        <!-- Pie de la tarjeta: Acción -->
        <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
            <span class="small">Ver elemento</span>
            <div class="icon-transition">
                <i class="ti ti-arrow-right fs-5"></i>
            </div>
        </div>

    </a>
</div>


<?php endforeach; ?>
</div>  

</div>
<?php endif; ?>

</div>