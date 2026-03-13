
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