<div class="row mt-4">

<?php 
$i = 1; 
foreach ($submenus as $submenu): ?>

<div class="col-md-4 d-flex align-items-stretch">
<a href="<?= $submenu['ruta'] ?>" class="card w-100 card-hover">
<div class="card-body">

<div class="d-flex align-items-center">
<i class="<?= $submenu['icono'] ?> text-primary display-6"></i>

<div class="ms-auto">
<i class="ti ti-arrow-right text-primary fs-7"></i>
</div>
</div>

<div class="mt-4">
<h4 class="card-title mb-1 opacity-80">
<?= $i . '. ' . $submenu['nombre'] ?>
</h4>
</div>

</div>
</a>
</div>

<?php 
$i++; 
endforeach; 
?>

</div>