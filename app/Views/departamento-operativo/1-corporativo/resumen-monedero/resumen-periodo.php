<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información.
</div>
<?php else: ?>
<div id="resumenPeriodoContainer"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>">
</div>
<?php endif; ?>
