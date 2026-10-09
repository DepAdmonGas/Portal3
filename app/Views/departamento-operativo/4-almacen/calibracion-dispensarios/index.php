<?php
/**
 * Calibración de Dispensarios — Almacén
 *
 * La vista no contiene lógica de negocio ni ciclos: los periodos viajan como JSON
 * en un data-attribute y se pintan con Alpine, igual que los permisos y el estado
 * de la estación. La estación NO se pide en los modales: se toma del selector de
 * contexto del módulo.
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var array  $periodos
 * @var bool   $puedeCrear
 * @var bool   $puedeEditar
 * @var bool   $puedeEliminar
 * @var bool   $puedeDescargar
 */

$baseUrl = '/departamento-operativo/almacen/calibracion-dispensarios';
?>
<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-fija="<?= $estacionFija ? 'true' : 'false' ?>"
data-periodos="<?= htmlspecialchars(json_encode($periodos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...calibracionDispensariosComponent() }">

<div class="row mt-3">

<!-- ENCABEZADO -->
<div class="col-12 mb-3">
<template x-if="puedeCrear && estacionFija">
<button type="button" class="btn bg-primary-subtle text-primary float-end" @click="abrirNuevo()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</template>
</div>

<!-- TABLA -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-calibracion-dispensarios" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

<!-- ================= MODAL NUEVO ================= -->
<div class="modal fade" id="modalNuevo" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-certificate text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Nueva Calibración</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<label class="form-label mb-1">* Año:</label>
<input type="number"
class="form-control"
step="1"
x-model="form.year"
:style="errores.year ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.year = false">
</div>

<div class="col-12">
<label class="form-label mb-1">* Periodo:</label>
<select class="form-select"
x-model="form.periodo"
:style="errores.periodo ? 'border: 2px solid #A52525 !important;' : ''"
@change="errores.periodo = false">
<option value="">Seleccione una opción...</option>
<template x-for="p in periodos" :key="p">
<option :value="p" x-text="p"></option>
</template>
</select>
</div>

<div class="col-12">
<label class="form-label mb-1">* Archivo (PDF):</label>
<input type="file"
class="form-control"
id="inputArchivoNuevo"
accept=".pdf,application/pdf"
@change="form.archivo = $event.target.files[0] || null; errores.archivo = false"
:style="errores.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button" class="btn btn-success" @click="guardarNuevo()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL EDITAR ================= -->
<div class="modal fade" id="modalEditar" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-pencil text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Editar Calibración</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoDetalle">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información del registro...</span>
</div>
</template>

<template x-if="!cargandoDetalle && detalle">
<div>
<div class="row g-3">
<div class="col-12">
<label class="form-label mb-1">Fecha de captura:</label>
<div class="" x-text="detalle.fecha || 'S/I'"></div>
</div>

<div class="col-12">
<label class="form-label mb-1">* Año:</label>
<input type="number"
class="form-control"
step="1"
x-model="form.year"
:style="errores.year ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.year = false">
</div>

<div class="col-12">
<label class="form-label mb-1">* Periodo:</label>
<select class="form-select"
x-model="form.periodo"
:style="errores.periodo ? 'border: 2px solid #A52525 !important;' : ''"
@change="errores.periodo = false">
<option value="">Seleccione una opción...</option>
<template x-for="p in periodos" :key="p">
<option :value="p" x-text="p"></option>
</template>
</select>
</div>

<div class="col-12">
<label class="form-label mb-1">Archivo actual:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalle.archivo_existe">
    <button type="button" 
            class="btn btn-primary d-inline-flex align-items-center gap-2" 
            @click="descargar(detalle.archivo)">
        <i class="ti ti-file-type-pdf fs-5"></i>
        <span>Descargar PDF</span>
    </button>
</template>
<template x-if="!detalle.archivo_existe">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
No hay archivo almacenado para este registro.
</div>
</template>
</div>
</div>

<div class="col-12">
<label class="form-label mb-1">Archivo (PDF):</label>
<input type="file"
class="form-control"
id="inputArchivoEditar"
accept=".pdf,application/pdf"
@change="form.archivo = $event.target.files[0] || null; errores.archivo = false"
:style="errores.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>
</div>
</div>
</template>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button"
class="btn btn-success"
@click="guardarEdicion()"
:disabled="guardando || cargandoDetalle">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Actualizar'"></span>
</button>
</div>
</div>
</div>
</div>

</div>
