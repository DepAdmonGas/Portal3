<div id="revision-container" class="mt-3 mb-3"
data-id-year="<?= $idYear ?>"
data-id-estacion="<?= $idEstacion ?>"
data-mes="<?= $mes ?>"
data-module-station-key="<?= $moduleStationKey ?? '' ?>"
x-data="recibosNominaRevisionComponent()">

<!-- MENSAJE SI NO HAY ESTACIÓN SELECCIONADA -->
<template x-if="!revision.hay_estacion">
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Selecciona una estación o departamento del menú superior.
</div>
</template>

<!-- EXCEL DE DESPACHADORES -->
<template x-if="revision.mostrar_excel">
<div class="mb-3 clearfix">
<a class="btn btn-success float-end d-inline-flex align-items-center gap-2 shadow-sm" :href="revision.excel_url">
<i class="ti ti-file-spreadsheet fs-4"></i>
<span>Descargar Excel (Despachadores)</span>
</a>
</div>
</template>

<!-- BLOQUES POR PERIODO -->
<template x-for="bloque in revision.bloques" :key="bloque.periodo">
<div class="card mb-4">
<div class="card-header bg-primary d-flex justify-content-between align-items-center">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-calendar-event text-white fs-6"></i>
<h5 class="mb-0 text-white d-flex flex-column">
<span x-text="bloque.titulo"></span>
<small class="text-white mt-1" x-text="bloque.rango_fechas"></small>
</h5>
</div>
<div class="d-flex align-items-center gap-2">
<template x-if="bloque.puede_finalizar">
<button type="button" class="btn btn-success d-flex align-items-center gap-1" @click="finalizarOperativo(bloque)">
<i class="ti ti-check fs-5"></i> Finalizar actividad
</button>
</template>
<template x-if="!bloque.puede_finalizar">
<span class="badge rounded-pill" :class="bloque.estado_badge.clase" x-text="bloque.estado_badge.texto"></span>
</template>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-center align-middle" style="width:48px;">#</th>
<th class="text-center align-middle" style="width:110px;">No. Colaborador</th>
<th>Nombre del personal</th>
<th class="text-end align-middle">Importe</th>
<th class="text-center align-middle" style="width:140px;">Prima Vacacional</th>
<th class="text-center align-middle"><i class="ti ti-file-text text-primary fs-7" title="Recibo Acuse"></i></th>
<th class="text-center align-middle"><i class="ti ti-signature text-success fs-8" title="Recibo Firmado"></i></th>
<th class="text-center align-middle"><i class="ti ti-gift text-warning fs-7" title="Aguinaldo"></i></th>
<th class="text-center align-middle">Estatus</th>
</tr>
</thead>
<tbody>
<template x-for="fila in bloque.rows" :key="fila.num">
<tr :style="'background-color: ' + fila.bg_color + ';'">
<td class="text-center align-middle" x-text="fila.num"></td>
<td class="text-center align-middle" x-text="fila.no_colaborador"></td>
<td x-text="fila.nombre"></td>
<td class="text-end align-middle"><span x-text="'$' + fila.importe"></span></td>
<td class="text-center align-middle">
<span class="badge rounded-pill" :class="fila.prima.clase" x-text="fila.prima.texto"></span>
</td>
<td class="text-center align-middle">
<a x-show="fila.doc_nomina.tiene_documento" :href="fila.doc_nomina.url" target="_blank"><i :class="fila.doc_nomina.icono"></i></a>
<i x-show="!fila.doc_nomina.tiene_documento" :class="fila.doc_nomina.icono"></i>
</td>
<td class="text-center align-middle">
<a x-show="fila.doc_firma.tiene_documento" :href="fila.doc_firma.url" target="_blank"><i :class="fila.doc_firma.icono"></i></a>
<i x-show="!fila.doc_firma.tiene_documento" :class="fila.doc_firma.icono"></i>
</td>
<td class="text-center align-middle">
<a x-show="fila.doc_aguinaldo.tiene_documento" :href="fila.doc_aguinaldo.url" target="_blank"><i :class="fila.doc_aguinaldo.icono"></i></a>
<i x-show="!fila.doc_aguinaldo.tiene_documento" :class="fila.doc_aguinaldo.icono"></i>
</td>
<td class="text-center align-middle">
<span class="badge rounded-pill" :class="fila.estatus.clase" x-text="fila.estatus.texto"></span>
</td>
</tr>
</template>
<template x-if="!bloque.hay_rows">
<tr><td colspan="9" class="text-center text-muted py-4">Sin registros para el periodo.</td></tr>
</template>
</tbody>
<tfoot class="table-dark">
<tr>
<th colspan="4" class="text-end text-white">Importe Total:</th>
<th class="text-end text-white"><span x-text="'$' + bloque.total_general"></span></th>
<th colspan="5"></th>
</tr>
</tfoot>
</table>
</div>
</div>
</div>
</template>

<!-- El service resuelve badges, iconos, URLs e importes: la vista solo los imprime. -->
<script type="application/json" id="revision-data"><?= json_encode($revisionData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
