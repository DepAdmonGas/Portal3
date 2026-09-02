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

<div class="col-md-4 d-flex align-items-stretch">
<a href="sgm/<?= $elemento->url ?>" class="card w-100 card-hover">
<div class="card-body">
<div class="d-flex align-items-center">
<i class="ti ti-layout-grid text-primary display-6"></i>
<div class="ms-auto">
<i class="ti ti-arrow-right text-primary fs-7"></i>
</div>
</div>
<div class="mt-4">
<h4 class="card-title mb-1 opacity-80"><?= $elemento->no . ' ' . $elemento->criterio ?></h4>
</div>
</div>
</a>
</div>

<?php endforeach; ?>
</div>  

</div>
<?php endif; ?>

</div>