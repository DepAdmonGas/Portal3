<div class="row mt-4">

<?php 
$i = 1; 
foreach ($submenus as $submenu): 
$ruta = $submenu['ruta'];

if ($submenu['clave'] === 'corte-diario') {
$ruta .= "/{$idYear}/{$idMes}";
}else if ($submenu['clave'] === 'embarques') {
$ruta .= "/{$idYear}/{$idMes}";
}

?>

<div class="col-lg-4 col-md-6 col-12">
<a href="<?=$ruta?>" class="text-decoration-none">
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

<!--
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0">
<i class="ti ti-arrow-right fs-8"></i>
</h4>
</div>
-->

</div>
</div>
</a>
</div>

<?php 
$i++; 
endforeach; 
?>

</div>