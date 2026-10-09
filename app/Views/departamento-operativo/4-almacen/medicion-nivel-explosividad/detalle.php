<?php
/**
 * Medición Nivel de Explosividad — Almacén
 *
 * Detalle de sólo lectura de un registro finalizado (estado 1). Mismo diseño
 * por cards, ahora sin lógica PHP expuesta: el payload y las etiquetas viajan
 * en data-attributes y `medicionDetalleComponent` (actions.init.js) los
 * consume con Alpine (x-for / x-text).
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var array  $detalle
 * @var bool   $estacionFija
 * @var array  $etiquetas  etiquetas de Elemento1..18
 */
?>
<div id="container" class="pb-4"
     x-data="medicionDetalleComponent()"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-detalle='<?= htmlspecialchars(json_encode((array)$detalle, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
     data-etiquetas='<?= htmlspecialchars(json_encode((array)$etiquetas, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>

<!-- ---------------- DATOS GENERALES ---------------- -->
<div class="row mt-3">

<div class="col-md-9">
<div class="row">

<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DATOS GENERALES</h4>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-md-2">
<span class="form-label mb-1">Folio</span>
<div x-text="detalle.folio_texto || ''"></div>
</div>
<div class="col-md-5">
<span class="form-label mb-1">Estación de Servicio</span>
<div x-text="(detalle.estacion_razonsocial || '') + ', ' + (detalle.estacion_cre || '')"></div>
</div>
<div class="col-md-5">
<span class="form-label mb-1">Referencia</span>
<div>NOM 005 ASEA 2016</div>
</div>

<div class="col-md-3">
<span class="form-label mb-1">Fecha</span>
<div x-text="detalle.fecha || ''"></div>
</div>

<template x-for="g in generales" :key="g.n">
<div class="col-12 col-md-3">
<label class="form-label mb-1" x-text="g.etiqueta + ':'"></label>
<div x-text="g.texto"></div>
</div>
</template>
</div>
</div>
</div>
</div>

<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-gauge"></i> MEDICIONES (PPM)</h4>
</div>
<div class="card-body">
<div class="row g-3">
<template x-for="m in mediciones" :key="m.n">
<div class="col-12 col-md-4 col-xl-3">
<label class="form-label mb-1" x-text="m.etiqueta + ':'"></label>
<div x-text="m.texto + ' PPM'"></div>
</div>
</template>
</div>

</div>
</div>
</div>

</div>
</div>

<div class="col-md-3 d-flex flex-column">
    <div class="card h-100 d-flex flex-column">
        <div class="card-header bg-primary">
            <h4 class="mb-0 text-white card-title">
                <i class="ti ti-info-circle"></i> PARTICULAS POR MILLON PPM
            </h4>
        </div>
        <div class="card-body p-3 d-flex align-items-center justify-content-center flex-grow-1">
            <img src="/assets/img/SPD202ex.PNG" 
                 alt="SPD202/Ex" 
                 class="img-fluid w-100 h-100" 
                 style="object-fit: contain; max-height: 320px;">
        </div>
        <div class="card-footer bg-light text-center">
            <span class="">
                LAS MEDICIONES SON CON EQUIPO <br> "COMBUSTIBLE GAS ALARM DETECTOR SPD202/Ex"
            </span>
        </div>
    </div>
</div>

<!-- ---------------- POZOS Y MOTOBOMBAS ---------------- -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-car-crash"></i> POZOS Y MOTOBOMBAS</h4>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-center align-middle">Detalle</th>
<th class="text-center align-middle">PPM</th>
<th class="text-center align-middle">Ubicación de Pozos</th>
</tr>
</thead>
<tbody>
<template x-if="(detalle.pozos || []).length === 0">
<tr><td colspan="3" class="text-center text-secondary">No se encontró información</td></tr>
</template>
<template x-for="p in detalle.pozos || []" :key="p.id ?? p.pozo">
<tr>
<td class="text-center align-middle" x-text="p.pozo || ''"></td>
<td class="text-center align-middle" x-text="p.ppm != null ? p.ppm : ''"></td>
<td class="text-center align-middle" x-text="p.ubicacion || ''"></td>
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
<div class="card-body">
    <div style="white-space: pre-line;" x-text="detalle.observaciones || 'Sin observaciones'"></div>
</div>
</div>
</div>

<!-- ---------------- FIRMAS ---------------- -->
<div class="col-12">
    <h5 class="mb-2">Firmas:</h5>
    <div class="row g-3">

    <template x-for="f in detalle.firmas || []" :key="f.tipo">
        <div class="col-12 col-md-6">
            <div class="card h-100 border d-flex flex-column">

                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px; height:50px;">
                            <template x-if="f.url"><i class="ti ti-circle-check fs-6 text-success"></i></template>
                            <template x-if="!f.url"><i class="ti ti-clock-hour-4 fs-6 text-warning"></i></template>
                        </div>
                        <div class="ms-3 overflow-hidden">
                            <h6 class="mb-0 text-white" x-text="f.tipo || 'FIRMA'"></h6>
                        </div>
                    </div>
                </div>

                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4 flex-grow-1" x-show="f.url">
                    <img :src="f.url" alt="Firma" class="img-fluid mb-3" style="max-height: 120px; object-fit: contain;">
                    <template x-if="f.fecha">
                        <h6 class="text-dark mb-0"><strong x-text="f.fecha"></strong></h6>
                    </template>
                </div>
                <div class="card-footer bg-light text-center mt-auto border-top" x-show="f.url">
                    <h6 class="mb-0 fw-semibold text-truncate" x-text="f.usuario || ''"></h6>
                </div>

                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4 flex-grow-1" x-show="!f.url">
                    <i class="ti ti-signature-off text-muted mb-3" style="font-size: 80px;"></i>
                    <h6 class="text-muted mb-0" x-text="'¡Falta la firma de ' + (f.tipo || 'Vo.Bo.') + '!'"></h6>
                </div>
                <div class="card-footer bg-light text-center mt-auto border-top" x-show="!f.url">
                    <small class="text-muted">Pendiente de firma electrónica</small>
                </div>

            </div>
        </div>
    </template>

    <template x-if="(detalle.firmas || []).length === 0">
        <div class="col-12 text-secondary"><small>No se encontró información</small></div>
    </template>

    </div>
</div>

</div>
</div>

</div>