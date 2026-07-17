<div class="row mt-4">
<?php $i = 1; foreach ($submenus as $submenu): ?>
<div class="col-lg-4 col-md-6 col-12">
<a href="<?= $submenu['ruta'] ?>" class="text-decoration-none">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">
<div class="d-flex flex-row align-items-center">
<div class="icon-container-do">
<h3 class="text-white mb-0">
<i class="ti <?= $submenu['icono'] ?> fs-9"></i>
</h3>
</div>
<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">
<?= $i . '. ' . $submenu['nombre'] ?>
</h5>
</div>
</div>
</div>
</a>
</div>
<?php $i++; endforeach; ?>
</div>
