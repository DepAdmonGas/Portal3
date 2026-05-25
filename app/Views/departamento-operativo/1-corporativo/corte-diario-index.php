<div id="container" class="mt-4 mb-4" data-mes="<?= $idMes ?>" data-year="<?= $idYear ?>" data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>">

<div class="row">
<div class="col-12">
<div class="d-flex align-items-center ">
<div class="ms-auto">

<?php if (!$multiestacion || $estacionId != 8): ?>
<div class="dropdown">
<button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-tools"></i> Herramientas
</button>

<?php if ($multiestacion): ?>
<!-- Admin -->
<ul class="dropdown-menu dropdown-menu-end">

<li>
<a class="dropdown-item" href="/departamento-operativo/control-volumetrico/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-bottle"></i> Control Volumétrico
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/resumen-monedero/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-wallet"></i> Resumen Monedero
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/clientes-mes/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-users"></i> Resumen Clientes
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/concentrado-ventas/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-cash-register"></i> Concentrado de Ventas
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/aceites-mes/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-droplet-filled"></i> Resumen Aceites
</a>
</li>

<?php if ($esDireccionOperaciones): ?>

<li><hr class="dropdown-divider"></li>

<li>
<a class="dropdown-item" href="/departamento-operativo/corte-diario-evaluacion/<?= $idYear ?>/<?= $idMes ?>/<?= $estacionId ?>">
<i class="ti ti-chart-bar"></i> Apertura de Cortes Diarios (KPI's)
</a>
</li>

<?php endif; ?>
</ul>

<?php else: ?>
<!-- ENCARGADOS -->
<ul class="dropdown-menu dropdown-menu-end">

<li>
<a class="dropdown-item" href="/departamento-operativo/control-volumetrico/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-bottle"></i> Control Volumétrico
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/concentrado-ventas/<?= $estacionId ?>/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-cash-register"></i> Concentrado de Ventas
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/resumen-impuestos/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-receipt-tax"></i> Resumen Impuestos
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/resumen-monedero/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-wallet"></i> Resumen Monedero
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/aceites-mes/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-droplet-filled"></i> Resumen Aceites
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/clientes-mes/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-users"></i> Resumen Clientes
</a>
</li>

<li>
<a class="dropdown-item" href="/departamento-operativo/embarques/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-truck"></i> Resumen Embarques
</a>
</li>

</ul>

<?php endif; ?>

</div>
</div>
<?php endif; ?>
</div>
</div>

<div class="col-12">
<div class="datatables">
<div class="table-responsive">
<table id="table-corte-diario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

<?php if ($multiestacion): ?>
<div class="modal fade" id="modalEditarCorte" tabindex="-1" x-data="editarCorteComponent()">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Activar Corte Diario (<span x-text="fecha" class="fw-normal"></span>)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<!-- Step: loading -->
<template x-if="loading">
<div class="modal-body">
<div class="text-center py-4">
<div class="spinner-border" role="status"></div>
<p class="mt-2">Cargando...</p>
</div>
</div>
</template>

<!-- Step: history (shows history table + activar button) -->
<template x-if="!loading && step === 'history'">
<div>
<div class="modal-body">
<div class="text-end mb-3">
<button type="button" class="btn btn-primary" @click="step = 'activate'">
<i class="ti ti-lock-open"></i> Activar corte diario
</button>
</div>
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">#</th>
<th class="text-center align-middle">Fecha</th>
<th class="text-center align-middle">Usuario</th>
<th class="text-center align-middle">Motivo</th>
</tr>
</thead>
<tbody>
<template x-if="historial.length === 0">
<tr>
<td colspan="4" class="text-center text-muted py-3">
<small>No se encontraron registros de activación</small>
</td>
</tr>
</template>
<template x-for="(item, index) in historial" :key="item.id">
<tr>
<th class="text-center align-middle" x-text="index + 1"></th>
<td class="text-center align-middle" x-text="item.fecha + ', ' + item.hora"></td>
<td class="text-center align-middle" x-text="item.usuario"></td>
<td class="text-center align-middle" x-text="item.detalle"></td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</template>

<!-- Step: activate (show motivo form) -->
<template x-if="!loading && step === 'activate'">
<div>
<div class="modal-body">
<div class="mb-3">
<label class="text-secondary mb-1">* Motivo:</label>
<textarea class="form-control" x-model="motivo" rows="3"></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" @click="step = 'history'">Cancelar</button>
<button type="button" class="btn btn-success" @click="submitActivacion" :disabled="saving">
<span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
Activar
</button>
</div>
</div>
</template>

</div>
</div>
</div>
<?php endif; ?>
