<?php
/**
 * Medición Nivel de Explosividad — Almacén
 *
 * Formulario de un borrador (estado 0). El registro finalizado no se puede
 * editar, así que ésta es la única pantalla de captura del módulo.
 *
 * La vista sólo maqueta: el catálogo de encargados, las opciones de pozo y los
 * pozos ya capturados viajan en data-attributes; la validación y el guardado
 * viven en medicion-nivel-explosividad.actions.init.js.
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var int    $idReporte
 * @var string $folio
 * @var int    $idEstacion
 * @var bool   $estacionFija
 * @var array  $etiquetas  etiquetas de Elemento1..18
 * @var array  $datos      valores precargados (Elemento1..18, Observaciones)
 * @var array  $pozos      pozos ya dados de alta en el borrador
 * @var array  $encargados [{id, nombre}] del puesto Encargado (6) en la estación
 * @var array  $opcionesPozo catálogo de pozos de la estación (vacío si no hay)
 */

$baseUrl = '/departamento-operativo/almacen/medicion-nivel-explosividad';
?>
<div id="container"
     class="pb-5"
     data-base-url="<?= $baseUrl ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-estacion-fija="<?= $estacionFija ? 'true' : 'false' ?>"
     data-id-reporte="<?= (int)$idReporte ?>"
     data-id-estacion="<?= (int)$idEstacion ?>"
     data-folio="<?= htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') ?>"
     data-datos="<?= htmlspecialchars(json_encode((array)$datos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
     data-pozos="<?= htmlspecialchars(json_encode((array)$pozos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
     data-etiquetas='<?= htmlspecialchars(json_encode((array)$etiquetas, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-encargados='<?= htmlspecialchars(json_encode((array)$encargados, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-opciones-pozo='<?= htmlspecialchars(json_encode((array)$opcionesPozo, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     x-data="{ ...actions(), ...medicionFormComponent() }">

<style>
.firma-invalida { border: 2px solid #A52525 !important; }
</style>

<div class="row mt-3">

<!-- ENCABEZADO -->
<div class="col-12 mb-3">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
<div>
</div>
<button type="button" class="btn btn-success" :disabled="guardando" @click="guardar()">
<i class="ti ti-check me-1"></i> Finalizar
</button>
</div>
</div>

<!-- ---------------- DATOS GENERALES ---------------- -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DATOS GENERALES</h4>
</div>
<div class="card-body">
<div class="row g-3">

<div class="col-12 col-md-3">
<label class="form-label mb-1">* Fecha:</label>
<input type="date" class="form-control" x-model="form.Fecha"
       :style="errores.Fecha ? 'border: 2px solid #A52525 !important;' : ''"
       @input="errores.Fecha = false">
</div>

<div class="col-12 col-md-3">
<label class="form-label mb-1">* <span x-text="etiquetas[1]"></span>:</label>
<select class="form-select"
        x-model="form.Elemento1"
        :style="errores.Elemento1 ? 'border: 2px solid #A52525 !important;' : ''"
        @change="errores.Elemento1 = false">
<option value=""></option>
<template x-for="opcion in opcionesElementos[1]" :key="opcion">
<option :value="opcion" x-text="opcion"></option>
</template>
</select>
</div>

<div class="col-12 col-md-3">
<label class="form-label mb-1">* <span x-text="etiquetas[2]"></span>:</label>
<select class="form-select"
        x-model="form.Elemento2"
        :style="errores.Elemento2 ? 'border: 2px solid #A52525 !important;' : ''"
        @change="errores.Elemento2 = false">
<option value=""></option>
<template x-for="opcion in opcionesElementos[2]" :key="opcion">
<option :value="opcion" x-text="opcion"></option>
</template>
</select>
</div>

<div class="col-12 col-md-3">
<label class="form-label mb-1">* <span x-text="etiquetas[3]"></span>:</label>
<select class="form-select"
        x-model="form.Elemento3"
        :style="errores.Elemento3 ? 'border: 2px solid #A52525 !important;' : ''"
        @change="errores.Elemento3 = false">
<option value=""></option>
<template x-for="opcion in opcionesElementos[3]" :key="opcion">
<option :value="opcion" x-text="opcion"></option>
</template>
</select>
</div>

</div>
</div>
</div>
</div>

<!-- ---------------- MEDICIONES PPM ---------------- -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-gauge"></i> MEDICIONES (PPM)</h4>
</div>
<div class="card-body">
<div class="row g-3">

<template x-for="n in ppm" :key="n">
<div class="col-12 col-md-4 col-xl-3">
<label class="form-label mb-1" x-text="etiquetas[n] + ':'"></label>
<input type="number" class="form-control" step="1" min="0" x-model="form['Elemento' + n]">
</div>
</template>

</div>
</div>
</div>
</div>

<!-- ---------------- POZOS Y MOTOBOMBAS ---------------- -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h4 class="mb-0 text-white card-title"><i class="ti ti-car-crash"></i> POZOS Y MOTOBOMBAS</h4>
<button type="button" class="btn btn-success" @click="abrirPozo()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-center align-middle">Detalle</th>
<th class="text-center align-middle">PPM</th>
<th class="text-center align-middle">Ubicación de Pozos</th>
<th class="text-center align-middle text-center" width="48"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-if="pozos.length === 0">
<tr><td colspan="4" class="text-center text-secondary">No se encontró información</td></tr>
</template>
<template x-for="p in pozos" :key="p.id">
<tr>
<td class="text-center align-middle" x-text="p.pozo"></td>
<td class="text-center align-middle" x-text="p.ppm"></td>
<td class="text-center align-middle" x-text="p.ubicacion"></td>
<td class="text-center align-middle">
<i class="ti ti-trash text-danger fs-6 pointer" @click="eliminarPozo(p)"></i>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- ---------------- OBSERVACIONES ---------------- -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-eye"></i> OBSERVACIONES</h4>
</div>
<div class="card-body p-0">
<textarea class="form-control border-0 p-3" id="Observaciones" rows="10"
x-model="form.Observaciones"></textarea>
</div>
</div>
</div>

<!-- ---------------- FIRMAS ---------------- -->
<div class="col-12">
    <h5 class="mb-2">Firmas:</h5>
<div class="row g-3">

<!-- Firma de quien toma medición -->
<div class="col-12 col-md-6">
<div class="card h-100 d-flex flex-column">
<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-signature fs-6"></i>
<h5 class="mb-0 text-white">FIRMA DE QUIEN TOMA MEDICIÓN</h5>
</div>
</div>
<div class="card-body p-3 d-flex flex-column">
<div class="signature-pad-wrapper flex-grow-1"
     style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair; min-height: 190px;"
     :class="{ 'firma-invalida': errores.firma1 }">
<canvas id="canvas1" class="w-100 h-100" style="display: block;"></canvas>
</div>
</div>
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0"
        style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;"
        @click="limpiarFirma(1)">
<i class="ti ti-eraser me-1"></i> Limpiar firma
</button>
</div>
</div>

<!-- Firma por la estación -->
<div class="col-12 col-md-6">
<div class="card h-100 d-flex flex-column">
<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-signature fs-6"></i>
<h5 class="mb-0 text-white">NOMBRE Y FIRMA POR LA ESTACIÓN</h5>
</div>
</div>
<div class="card-body p-3 d-flex flex-column">
<label class="form-label mb-1">* Encargado de la estación:</label>
<select class="form-select mb-3"
        x-model="form.Encargado"
        :style="errores.Encargado ? 'border: 2px solid #A52525 !important;' : ''"
        @change="errores.Encargado = false">
<option value="">Selecciona una opción...</option>
<template x-for="e in encargados" :key="e.id">
<option :value="e.id" x-text="e.nombre"></option>
</template>
</select>

<div class="signature-pad-wrapper flex-grow-1"
     style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair; min-height: 140px;"
     :class="{ 'firma-invalida': errores.firma2 }">
<canvas id="canvas2" class="w-100 h-100" style="display: block;"></canvas>
</div>
</div>
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0"
        style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;"
        @click="limpiarFirma(2)">
<i class="ti ti-eraser me-1"></i> Limpiar firma
</button>
</div>
</div>

</div>
</div>

</div>

<!-- ================= MODAL POZO ================= -->
<div class="modal fade" id="modalPozo" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-car-crash text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Nueva ubicación de pozo</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-12 col-md-8">
<label class="form-label mb-1">* Pozo o motobomba:</label>
<select class="form-select"
        x-model="pozo.pozo"
        :style="erroresPozo.pozo ? 'border: 2px solid #A52525 !important;' : ''"
        @change="erroresPozo.pozo = false">
<option value=""></option>
<template x-for="opcion in opcionesPozo" :key="opcion">
<option :value="opcion" x-text="opcion"></option>
</template>
</select>
<small class="text-muted d-block mt-1" x-show="opcionesPozo.length === 0">Esta estación no tiene pozos registrados.</small>
</div>

<div class="col-12 col-md-4">
<label class="form-label mb-1">* PPM:</label>
<input type="number" step="1" class="form-control" x-model="pozo.ppm"
       :style="erroresPozo.ppm ? 'border: 2px solid #A52525 !important;' : ''"
       @input="erroresPozo.ppm = false">
</div>

<div class="col-12">
<label class="form-label mb-1">Ubicación de pozo:</label>
<textarea class="form-control" rows="3" x-model="pozo.ubicacion"></textarea>
</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cancelar
</button>
<button type="button" class="btn btn-success" @click="guardarPozo()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

</div>
