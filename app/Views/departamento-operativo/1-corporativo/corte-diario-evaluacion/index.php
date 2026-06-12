<?php if (!$idEstacion): ?>
<div class="row mt-4">
<div class="col-12">
<div class="alert alert-secondary border-0 text-center text-muted py-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de los KPI's de Apertura de Cortes Diarios.
</div>
</div>
</div>
<?php else: ?>
<div id="kpi-corte-diario-container"
data-id-estacion="<?= $idEstacion ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
style="display: none;"></div>

<div class="row" x-data="kpiCorteDiarioComponent()">
<div class="col-12">

<div x-show="cargando" class="text-center py-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando evaluación...</p>
</div>

<div x-show="!cargando" x-cloak>
<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
<div></div>
<button type="button" class="btn btn-primary" @click="abrirInfoEvaluacion()">
<i class="ti ti-info-circle me-1"></i>Forma de evaluación
</button>
</div>

<div class="row">

<div class="col-12">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">

<div>
<h5 class="mb-1 text-white">
<i class="ti ti-chart-line me-2"></i>
<span x-text="'Apertura de Cortes (' + (data?.estacion_nombre ?? '') + ')'"></span>
</h5>
</div>

<span class="badge bg-light text-primary px-3 py-2"><i class="ti ti-calendar-month me-1"></i>Mensual</span>

</div>
</div>

<div class="card-body">
<div id="chartMensual" style="width: 100%; height: 400px;"></div>
</div>

</div>
</div>

<div class="col-12">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">

<h5 class="mb-0 text-white">
<i class="ti ti-chart-bar me-2"></i>
<span x-text="'Apertura de Cortes (Estaciones)'"></span>
</h5>

<span class="badge bg-light text-primary px-3 py-2"><i class="ti ti-calendar-month me-1"></i>Anual</span>
</div>
</div>

<div class="card-body">
<div id="chartAnual" class="mb-3" style="width: 100%; height: 400px;"></div>
<template x-if="data?.peor_estacion">
<span class="badge bg-dark text-white fw-semibold float-end" x-text="'Menor: ' + data.peor_estacion"></span>
</template>
</div>

</div>
</div>

</div>
</div>

<template x-if="data">
<div class="modal fade" id="modalInfoEvaluacion" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Forma de Evaluación (Apertura de Cortes Diarios)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" x-html="data.info"></div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>
</template>
<?php endif; ?>
</div>
