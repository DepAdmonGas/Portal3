<?php
/**
 * Calendario Mantenimiento Preventivo — Almacén
 *
 * Replica el patrón de /sasisopa/calendario y /sgm/calendario, pero es de sólo
 * lectura y utiliza los datos de op_mantenimiento_preventivo (fecha y fecha2 de
 * cada registro). Requiere una estación del selector de contexto del módulo.
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var int    $estacionActual
 */

$baseUrl = '/departamento-operativo/almacen/mantenimiento-preventivo';
?>
<div id="container"
class="pb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-actual="<?= (int)($estacionActual ?? 0) ?>"
x-data="{ ...actions(), ...mantenimientoPreventivoCalendario() }">

<?php if (empty($estacionFija)): ?>

<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar los mantenimientos preventivos.
</div>

<?php else: ?>

<div class="calender-sidebar app-calendar mt-3">

<div class="d-flex justify-content-between align-items-center my-3">

<!-- Totales -->
<div class="d-flex align-items-center">
<div class="border-end pe-3">
<h6 class="text-muted fw-normal mb-1">Pendientes</h6>
<b class="text-danger fs-5" x-text="totales.pendientes"></b>
</div>
<div class="ms-5 border-end pe-3">
<h6 class="text-muted fw-normal mb-1">Finalizados</h6>
<b class="text-success fs-5" x-text="totales.finalizados"></b>
</div>
<div class="ms-5">
<h6 class="text-muted fw-normal mb-1">Total</h6>
<b class="fs-5" x-text="totales.total"></b>
</div>
</div>
</div>

<div class="text-capitalize" id="calendar"></div>

</div>

<!-- ================= MODAL: MANTENIMIENTOS DEL DÍA ================= -->
<div class="modal fade" id="modalDia" tabindex="-1" aria-labelledby="modalDiaLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-calendar-month fs-6 me-1"></i>
<h4 class="modal-title text-white" id="modalDiaLabel" x-text="fechaSeleccionada"></h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="table-responsive">
<table class="table table-striped p-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">#</th>
<th class="text-center align-middle">Folio</th>
<th class="text-center align-middle">Tipo</th>
<th class="text-center align-middle">Encargado</th>
<th class="text-center align-middle">Estatus</th>
</tr>
</thead>
<tbody>

<!-- Registros -->
<template x-for="(item, index) in actividadesDia" :key="item.id">
<tr class="pointer" @click="abrirDetalle(item)">
<td class="text-center align-middle" x-text="index + 1"></td>
<td class="text-center align-middle">
<strong x-text="item.folio_label"></strong>
<template x-if="item.es_proximo">
<div class="small text-muted mt-1">
<i class="ti ti-calendar-time me-1"></i>
<span x-text="item.tipo_fecha"></span>
</div>
</template>
</td>
<td class="text-center align-middle">
<span class="badge" :class="item.tipo_badge" x-text="item.es_proximo ? 'Próximo' : 'Mantenimiento'"></span>
</td>
<td class="text-center align-middle" x-text="item.encargado"></td>
<td class="text-center align-middle">
<span class="badge" :class="item.status_badge" x-text="item.status_label"></span>
</td>
</tr>
</template>

<!-- Sin registros -->
<template x-if="actividadesDia.length === 0">
<tr>
<td colspan="5" class="text-center text-muted py-5">
No existen mantenimientos preventivos programados para este día.
</td>
</tr>
</template>

</tbody>
</table>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL: DETALLE ================= -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-eye fs-6 me-1"></i>
<h4 class="modal-title text-white" id="modalDetalleLabel">Detalle del mantenimiento</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Folio:</label>
<div class="mb-1" x-text="detalle.folio_label ?? 'Sin información'"></div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Estatus:</label>
<div class="mb-1">
<span class="badge" :class="detalle.status_badge" x-text="detalle.status_label ?? 'Sin información'"></span>
</div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Tipo de mantenimiento:</label>
<div class="mb-1" x-text="detalle.tipo_titulo ?? 'Sin información'"></div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Encargado:</label>
<div class="mb-1" x-text="detalle.encargado ?? 'Sin información'"></div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Fecha de mantenimiento:</label>
<div class="mb-1" x-text="detalle.fecha ?? 'Sin información'"></div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Próxima fecha:</label>
<div class="mb-1" x-text="detalle.fecha2 ?? 'Sin información'"></div>
</div>

<div class="col-md-6">
<label class="form-label fw-semibold mb-1">Costo:</label>
<div class="mb-1" x-text="detalle.costo_label ?? 'Sin información'"></div>
</div>

<div class="col-12">
<label class="form-label fw-semibold mb-1">Observaciones:</label>
<div x-text="detalle.observaciones ?? 'Sin información'"></div>
</div>

</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
</div>
</div>
</div>
</div>

<?php endif; ?>

</div>