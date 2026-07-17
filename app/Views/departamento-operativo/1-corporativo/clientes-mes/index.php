<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Resumen Clientes.
</div>
<?php else: ?>
<div id="container" class="mt-4 mb-4"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-reporte="<?= $idReporte ?>"
data-finalizado="<?= $finalizado ? 'true' : 'false' ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-module-station-key="corte-diario"
x-data="clientesMesComponent()">

<div class="row mb-3">
<div class="col-12">
<div class="float-end">
<div class="d-flex gap-2">

<template x-if="finalizado">
<span class="badge bg-success d-flex align-items-center px-3">
<i class="ti ti-check-circle me-1"></i> Resumen Finalizado
</span>
</template>

<?php if (!$puestoExcluido): ?>
<div class="dropdown mb-2">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">

<li>
<a class="dropdown-item pointer" @click.prevent="listaClientes()">
<i class="ti ti-list me-2"></i> Lista clientes
</a>
</li>

<?php if ($puedeFinalizar): ?>
<li>
<a class="dropdown-item pointer" @click.prevent="finalizarResumen()" :class="finalizando ? 'disabled' : ''">
<i class="ti ti-circle-check me-2"></i> Finalizar Resumen <?= nombremes($idMes) ?> <?= $idYear ?>
</a>
</li>
<?php endif; ?>

<?php if ($puedeDescargar): ?>
<li>
<a class="dropdown-item pointer" @click.prevent="descargar()">
<i class="ti ti-file-spreadsheet me-2"></i> Descargar Resumen <?= nombremes($idMes) ?> <?= $idYear ?>
</a>
</li>
<?php endif; ?>

</ul>
</div>
<?php endif; ?>

</div>
</div>
</div>
</div>

<div class="col-12">

<div class="card mb-4">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white"><i class="ti ti-credit-card me-2"></i>Crédito</h5>
</div>
<div class="card-body">
<div class="datatables">
<div class="table-responsive">
<table id="tablaCredito" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="card mb-4">
<div class="card-header text-bg-success">
<h5 class="mb-0 text-white"><i class="ti ti-currency-dollar me-2"></i>Débito</h5>
</div>
<div class="card-body">
<div class="datatables">
<div class="table-responsive">
<table id="tablaDebito" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div id="granTotalContainer"></div>

</div>

</div>
<?php endif; ?>
