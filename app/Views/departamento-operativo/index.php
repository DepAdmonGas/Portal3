<div id="container" class="mt-4">
<div class="row">

<?php if (!empty($accesos)): ?>

<?php
$i = 1;
foreach ($accesos as $acceso):
?>

<?php if ($acceso['tipo'] === 'modulo'): ?>

<div class="col-lg-4 col-md-6 col-12">
<a href="<?= $acceso['ruta'] ?>" class="text-decoration-none">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">

<div class="d-flex flex-row align-items-center">

<div class="icon-container-do">
<h3 class="text-white mb-0">
<i class="ti <?= $acceso['icono'] ?> fs-9"></i>
</h3>
</div>

<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">
<?= $i . '. ' . $acceso['nombre'] ?>
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

<?php else: ?>

<div class="col-lg-4 col-md-6 col-12">
<a href="<?= $acceso['ruta'] ?>" class="text-decoration-none">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">

<div class="d-flex flex-row align-items-center">

<div class="icon-container-do">
<h3 class="text-white mb-0">
<i class="ti <?= $acceso['icono'] ?> fs-9"></i>
</h3>
</div>

<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">
<?= $i . '. ' . $acceso['nombre'] ?>
</h5>
<?php if (!empty($acceso['padre'])): ?>
<div class="text-white-50 small mt-1">
<?= $acceso['padre'] ?>
</div>
<?php endif; ?>
</div>

<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if (($acceso['clave'] ?? '') === 'solicitud-cheque' && (int)($pendientesFirmaSolicitudCheque ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaSolicitudCheque ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'aclaracion-voucher' && (int)($pendientesAclaracionVoucher ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesAclaracionVoucher ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'formatos' && (int)($pendientesFirmaFormatos ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaFormatos ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'bitacora-rrhh' && (int)($pendientesBitacoraRrhh ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesBitacoraRrhh ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'pedido-pinturas' && (int)($pendientesFirmaPedidoPinturas ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoPinturas ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'pedido-papeleria' && (int)($pendientesFirmaPedidoPapeleria ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoPapeleria ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'pedido-articulos-limpieza' && (int)($pendientesFirmaPedidoArticulosLimpieza ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoArticulosLimpieza ?></span>
<?php elseif (($acceso['clave'] ?? '') === 'pedido-aditivo' && (int)($pendientesFirmaPedidoAditivo ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoAditivo ?></span>
<?php else: ?>
<i class="ti ti-arrow-right fs-8"></i>
<?php endif; ?>
</h4>
</div>

</div>
</div>
</a>
</div>

<?php endif; ?>

<?php
$i++;
endforeach;
?>

<?php else: ?>

<div class="col-12">
<div class="alert alert-warning text-center py-4" role="alert">
No cuentas con módulos configurados para este apartado.
</div>
</div>

<?php endif; ?>

</div>
</div>
