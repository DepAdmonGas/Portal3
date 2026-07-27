<div id="kpi-personal-container"
data-id-year="<?= $idYear ?>"
data-opciones="<?= htmlspecialchars(json_encode($opciones), ENT_QUOTES, 'UTF-8') ?>"
style="display: none;"></div>

<div id="container" data-year="<?= $idYear ?>"></div>

<div class="row" x-data="kpiPersonalComponent()">
<div class="col-12">

<div x-show="!tipoCargado">

<div class="row mt-3">
<template x-for="opcion in opciones" :key="opcion.id">
<div class="col-xl-4 col-lg-4 col-md-6 col-12 mb-3">
<div class="card body-container-do overflow-hidden card-hover shadow-sm h-100 pointer" @click="cargarTipo(opcion.id)">
<div class="d-flex flex-row align-items-center h-100">

<div class="icon-container-do">
<h3 class="text-white mb-0"><i :class="opcion.icono"></i></h3>
</div>

<div class="p-4 flex-grow-1">
<h5 class="text-white mb-1" x-text="opcion.titulo"></h5>
</div>

<div class="align-self-center me-4">
<h4 class="text-white mb-0"><i class="ti ti-eye"></i></h4>
</div>

</div>
</div>
</div>
</template>
</div>

</div>

<template x-if="tipoCargado">
<div>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3">

<button class="btn btn-danger" @click="volverOpciones()">
<i class="ti ti-arrow-left me-1"></i>Cambiar evaluación
</button>

</div>

<div x-show="cargando" class="text-center py-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando evaluación...</p>
</div>

<div x-show="!cargando" x-cloak>
<div class="row">

<div class="col-12">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">

<div>
<h5 class="mb-1 text-white">
<i class="ti ti-chart-line me-2"></i>
<span x-text="(data?.nombre_tipo ?? '') + ' (' + (data?.estacion_nombre ?? '') + ')'"></span>
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
<span x-text="(data?.nombre_tipo ?? '') + ' (Todas las Estaciones / Departamentos)'"></span>
</h5>

<span class="badge bg-light text-primary px-3 py-2"><i class="ti ti-calendar-month me-1"></i>Anual</span>
</div>
</div>

<div class="card-body">
<div id="chartAnual" class="mb-3" style="width: 100%; height: 400px;"></div>
<template x-if="data?.mejor_estacion">
<span class="badge bg-dark text-white fw-semibold float-end" x-text="'Mejor: ' + data.mejor_estacion"></span>
</template>

</div>

</div>
</div>

</div>
</div>
</div>
</template>

</div>
</div>
