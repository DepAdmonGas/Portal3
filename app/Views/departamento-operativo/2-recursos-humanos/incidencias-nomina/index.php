<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $estacionId ?>"
data-id-year="<?= $idYear ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
data-year-mes-template="<?= htmlspecialchars($yearMesTemplate ?? '', ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...incidenciasNominaComponent() }">

<!-- SELECTOR DE SEMANA + DROPDOWN DE REPORTES -->
<div class="row mb-3 align-items-center">
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-2">
<div class="d-flex align-items-center">
<!-- <label class="text-muted me-2 fw-semibold">Semana:</label> -->
<select class="form-select form-select-sm" id="incidencias-semana-selector"
style="max-width: 220px;">
<option value="">Selecciona una semana...</option>
<?php foreach ($weeks as $w): ?>
<option value="<?= $w['numero'] ?>" <?= $w['numero'] == $currentWeek ? 'selected' : '' ?>>
Semana <?= $w['numero'] ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-2 text-end">
<?php /* if ($puedeDescargar): */ ?>

<div class="dropdown">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">
<li><h6 class="dropdown-header">Reportes</h6></li>

<li>
<a class="dropdown-item" href="javascript:void(0)" @click="descargarReporteEstaciones()">
<i class="ti ti-gas-station me-2"></i>Reporte de Estaciones
</a>
</li>
<li x-show="estacionEspecifica" x-cloak>
<a class="dropdown-item" href="javascript:void(0)" @click="descargarReporteIndividual()">
<i class="ti ti-file-analytics me-2"></i>Reporte Individual
</a>
</li>
<li><hr class="dropdown-divider"></li>
<li><h6 class="dropdown-header">Módulos</h6></li>

<li>
<a class="dropdown-item" href="/departamento-operativo/recursos-humanos/rol-comodines">
<i class="ti ti-users-group me-2"></i>  Rol de Comodines
</a>
</li>
<li>
<a class="dropdown-item" href="/departamento-operativo/recursos-humanos/dia-doble/<?= date('Y') ?>">
<i class="ti ti-calendar-event me-2"></i> Día Doble
</a>
</li>
</ul>
</div>
<?php /* endif; */ ?>
</div>
</div>

<div class="row">
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center gap-2">
<i class="ti ti-calendar-event text-white fs-6"></i>
<h5 class="card-title text-white mb-0">
<div id="incidencias-week-title" class="text-start">
<?= htmlspecialchars($weekTitle, ENT_QUOTES, 'UTF-8') ?>
</div>
</h5>
</div>

<div class="card-body">
<div id="incidencias-content">
<div class="datatables">
<div class="table-responsive overflow-x-auto pb-4">
<table id="tabla-incidencias-nomina" class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%">
<tbody></tbody>
</table>
</div>
</div>

<div class="text-center py-5" id="incidencias-loading" style="display:none;">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando incidencias...</p>
</div>
</div>
</div>



</div>
</div>
</div>




</div>
