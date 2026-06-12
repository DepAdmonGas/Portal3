<?php if (!$idEstacion): ?>
<div class="row mt-4 mb-5">
<div class="col-12">
<div class="alert alert-info text-center">
<i class="ti ti-info-circle fs-4"></i>
Debes de seleccionar una estación del menú superior para poder visualizar la información del Resumen Impuestos.
</div>
</div>
</div>
<?php else: ?>
<div id="resumenImpuestosContainer"
     data-id-year="<?= $idYear ?>"
     data-id-mes="<?= $idMes ?>"
     data-id-estacion="<?= $idEstacion ?>">
</div>
<?php endif; ?>