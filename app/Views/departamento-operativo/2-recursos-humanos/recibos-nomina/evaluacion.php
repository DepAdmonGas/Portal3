<?php if (!$idEstacion): ?>

<div class="row mt-4">
<div class="col-12">
<div class="alert alert-secondary border-0 text-center text-muted py-4">
Debes de seleccionar una estación del menú superior para poder visualizar la evaluación de los Recibos de Nómina.
</div>
</div>
</div>

<?php else: ?>

<div id="kpi-recibos-nomina-container"
data-id-estacion="<?= $idEstacion ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
style="display: none;"></div>

<div class="row pb-4" x-data="kpiRecibosNominaComponent()">

<div class="col-12">

<div x-show="cargando" class="text-center py-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando evaluación...</p>
</div>

<div x-show="!cargando" x-cloak>

<div class="d-flex justify-content-end mt-3 mb-3">
<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirInfoEvaluacion()">
<i class="ti ti-info-circle me-1"></i>Forma de evaluación
</button>
</div>

<!---------- CARD DE RESUMEN ANUAL ---------->
<div class="col-12" x-show="data?.es_anual">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="ti ti-chart-bar me-2"></i>
<span>Resumen Anual</span>
</h5>
<span class="badge bg-light text-primary px-3 py-2"><i class="ti ti-calendar-month me-1"></i>Anual</span>
</div>
</div>
<div class="card-body">
<div id="chartAnual" class="w-100 h-100"></div>
</div>
</div>
</div>


<!---------- CARD DE RESUMEN MENSUAL ---------->
<template x-if="!cargando && data && !data.es_anual">
<div>

<div class="row g-3">
<template x-for="p in data.periodos" :key="p.periodo">
<div class="col-12 col-md-6">
<div class="card">
<div class="card-header text-bg-primary">

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="ti ti-chart-line me-2"></i>
<span x-text="p.etiqueta"></span>
</h5>
<span class="badge bg-light text-primary px-3 py-2">
<span x-text="p.rango"></span>
</span>
</div>


</div>
<div class="card-body">
<div :id="'chartPeriodo' + p.periodo" class="w-100 h-100"></div>
</div>
</div>
</div>
</template>

<!-- Resumen Mensual -->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="ti ti-chart-line me-2"></i>
<span>Resumen Mensual</span>
</h5>
<span class="badge bg-light text-primary px-3 py-2"><i class="ti ti-calendar-month me-1"></i>Mensual</span>
</div>
</div>
<div class="card-body">
<div id="chartMensual" class="w-100 h-100"></div>
</div>
</div>
</div>
</div>
</div>
</template>

<template x-if="data && !data.periodos.length && !data.es_anual">
<div class="alert alert-warning border-0 text-center text-muted">
No hay periodos de evaluación disponibles para el mes seleccionado.
</div>
</template>
</div>

<template x-if="data">
<div class="modal fade" id="modalInfoEvaluacion" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white"><i class="ti ti-info-circle"></i> Forma de Evaluación (Recibos de Nómina)</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body text-dark" x-html="DOMPurify.sanitize(data.info)"></div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cerrar</button>
</div>
</div>
</div>
</div>
</template>
</div>
</div>
<?php endif; ?>
