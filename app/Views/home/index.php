<div id="container" class="mt-4">

<div class="row">
<?php foreach ($modulousuario as $clave => $mimodulo): ?>

<?php if ($mimodulo['leer']): ?>

<div class="col-lg-4 col-md-6 col-12">
<a href="<?= $mimodulo['ruta'] ?>" class="text-decoration-none">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">

<div class="d-flex flex-row align-items-center">

<div class="icon-container-do">
<h3 class="text-white mb-0">
<i class="ti <?= $mimodulo['icono'] ?> fs-9"></i>
</h3>
</div>
 
<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">
<?= $mimodulo['nombre'] ?>
</h5>
</div>

<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0">
<i class="ti ti-arrow-right fs-8"></i>
</h4>
</div>

</div>
</div>
</a>
</div>

<?php endif; ?>
<?php endforeach; ?>

</div>
</div>

