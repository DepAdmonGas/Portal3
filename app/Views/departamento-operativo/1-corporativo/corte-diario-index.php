<div id="container" class="mt-4 mb-4" data-mes="<?= $idMes ?>" data-year="<?= $idYear ?>" data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>" data-module-station-key="corte-diario" data-puede-editar-corte="<?= ($puedeEditarCorte ?? false) ? 'true' : 'false' ?>">

<?php if (!$estacionId): ?>
<div id="corte-diario-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Corte Diario.
</div>
<div id="corte-diario-content" style="display:none">
<?php else: ?>
<div id="corte-diario-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Corte Diario.
</div>
<div id="corte-diario-content">
<?php endif; ?>

<div class="row">
<div class="col-12">
<div class="d-flex align-items-center">
<div class="ms-auto">

<div id="corte-diario-actions-wrapper"<?php if (!$estacionId): ?> style="display:none"<?php endif; ?>>
<div class="dropdown mb-4">

<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/control-volumetrico/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-bottle"></i> Control Volumétrico
</a>
</li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/concentrado-ventas/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-cash-register"></i> Concentrado de Ventas
</a>
</li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/resumen-monedero/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-wallet"></i> Resumen Monedero
</a>
</li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/aceites-mes/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-droplet"></i> Resumen Aceites
</a>
</li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/clientes-mes/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-users"></i> Resumen Clientes
</a>
</li>

<?php if ($estacionId && in_array((int) ($puestoId ?? 0), [6, 7], true)): ?>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/resumen-impuestos/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-receipt-tax"></i> Resumen Impuestos
</a>
</li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/embarques/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-truck"></i> Resumen Embarques
</a>
</li>

<?php endif; ?>


<?php if ($esDireccionOperaciones): ?>

<li><hr class="dropdown-divider"></li>

<li>
<a class="dropdown-item pointer" href="/departamento-operativo/corte-diario-evaluacion/<?= $idYear ?>/<?= $idMes ?>">
<i class="ti ti-chart-bar"></i> Apertura de Cortes Diarios (KPI's)
</a>
</li>

<?php endif; ?>

</ul>
</div>
</div>

</div>
</div>
</div>
</div>

<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-hidden">
<table id="table-corte-diario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>

<?php if ($puedeEditarCorte ?? false): ?>
<div class="modal fade" id="modalEditarCorte" tabindex="-1" x-data="editarCorteComponent()">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Activar Corte Diario (<span x-text="fecha" class="fw-normal"></span>)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<template x-if="loading">
<div class="modal-body">
<div class="text-center py-4">
<div class="spinner-border" role="status"></div>
<p class="mt-2">Cargando...</p>
</div>
</div>
</template>

<template x-if="!loading && step === 'history'">
<div>
<div class="modal-body">
<div class="text-end mb-3">
<button type="button" class="btn btn-primary" @click="step = 'activate'">
<i class="ti ti-lock-open"></i> Activar corte diario
</button>
</div>
<div class="table-responsive overflow-x-auto overflow-hidden">

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

</div>
