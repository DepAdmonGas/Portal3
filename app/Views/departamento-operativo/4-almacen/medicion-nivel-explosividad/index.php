<?php
/**
 * Medición Nivel de Explosividad — Almacén
 *
 * Un único listado para todos los puestos: la vista sólo recibe los permisos ya
 * resueltos por el Service (C1) y los pinta. No hay lógica de negocio ni ciclos
 * de datos: la estación, los permisos y la estación fija viajan en data-attributes.
 *
 * Semántica de estados heredada del legacy (inversa a lo habitual):
 *   estado 0 = borrador rosa #ffb6af  → editable / eliminable, sin detalle
 *   estado 1 = finalizado verde #b0f2c2 → sólo detalle, ya no se modifica
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var bool   $puedeCrear
 * @var bool   $puedeEditar
 * @var bool   $puedeEliminar
 */

$baseUrl = '/departamento-operativo/almacen/medicion-nivel-explosividad';
?>
<div id="container"
     class="pb-4"
     data-base-url="<?= $baseUrl ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-estacion-fija="<?= $estacionFija ? 'true' : 'false' ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...medicionExplosividadComponent() }">

<div class="row mt-3">

<!-- ENCABEZADO -->
<div class="col-12 mb-3">
<template x-if="puedeCrear && estacionFija">
<button type="button" class="btn bg-primary-subtle text-primary float-end" @click="nuevo()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</template>
</div>

<!-- TABLA -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-medicion-nivel-explosividad" class="table table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>
</div>
