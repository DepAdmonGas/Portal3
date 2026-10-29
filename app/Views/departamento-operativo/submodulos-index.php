<div class="row mt-4">

<?php if (!empty($submenus)): ?>

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
<?php if ($submenu['clave'] === 'solicitud-cheque'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaSolicitudCheque ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaSolicitudCheque ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'aclaracion-voucher'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesAclaracionVoucher ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesAclaracionVoucher ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'formatos'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaFormatos ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaFormatos ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'bitacora-rrhh'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesBitacoraRrhh ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesBitacoraRrhh ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'pedido-pinturas'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaPedidoPinturas ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoPinturas ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'pedido-papeleria'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaPedidoPapeleria ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoPapeleria ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'pedido-articulos-limpieza'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaPedidoArticulosLimpieza ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoArticulosLimpieza ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'pedido-aditivo'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaPedidoAditivo ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaPedidoAditivo ?></span>
<?php endif; ?>
</h4>
</div>
<?php elseif ($submenu['clave'] === 'orden-mantenimiento'): ?>
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0 d-flex justify-content-end">
<?php if ((int)($pendientesFirmaOrdenMantenimiento ?? 0) > 0): ?>
<span class="badge-historico badge-solicitud-home"><?= (int)$pendientesFirmaOrdenMantenimiento ?></span>
<?php endif; ?>
</h4>
</div>
<?php endif; ?>
</div>
</div>
</a>
</div>
<?php $i++; endforeach; ?>

<?php else: ?>

<div class="col-12">
<div class="alert alert-warning text-center py-4" role="alert">
No cuentas con apartados configurados para este módulo.
</div>
</div>

<?php endif; ?>

</div>
