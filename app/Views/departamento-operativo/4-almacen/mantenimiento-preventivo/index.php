<?php
/**
 * Mantenimiento Preventivo — Almacén
 *
 * La vista no contiene lógica de negocio ni ciclos: el catálogo de tipos viaja como
 * JSON en un data-attribute y se pinta con Alpine. La estación NO se pide en los
 * modales: se toma del selector de contexto del módulo (ModuleStationService).
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var int    $estacionActual
 * @var bool   $esUsuarioEstacion
 * @var int    $year
 * @var string $yearMesTemplate
 * @var array  $tipos
 * @var bool   $puedeCrear
 * @var bool   $puedeEditar
 * @var bool   $puedeEliminar
 * @var bool   $puedeDescargar
 */

$baseUrl = '/departamento-operativo/almacen/mantenimiento-preventivo';
?>

<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-fija="<?= $estacionFija ? 'true' : 'false' ?>"
data-estacion-actual="<?= (int)($estacionActual ?? 0) ?>"
data-es-usuario-estacion="<?= !empty($esUsuarioEstacion) ? 'true' : 'false' ?>"
data-id-year="<?= (int)$year ?>"
data-tipos="<?= htmlspecialchars(json_encode($tipos ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-pendientes-actual="<?= (int)($pendientesActual ?? 0) ?>"
data-pendientes-json="<?= htmlspecialchars($pendientesJson ?? '{}', ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...mantenimientoPreventivoComponent() }">

<div class="row mt-3">

<!-- ENCABEZADO -->
<div class="col-12 mb-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
<div class="d-flex align-items-center gap-1">
<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle fs-4"></i>
<span>Pendientes: <span id="mp-pending-count" x-text="pendientesActual">0</span></span>
</span>
</div>
<div class="d-flex align-items-center gap-2">
<template x-if="estacionFija">
<div class="btn-group float-end">

<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">
<template x-if="puedeCrear">
<li>
<a class="dropdown-item pointer" @click="abrirNuevo()">
<i class="ti ti-plus me-1"></i> Nuevo
</a>
</li>
</template>

<li>
<a class="dropdown-item pointer" href="<?= $baseUrl ?>/calendario">
<i class="ti ti-calendar me-1"></i> Calendario
</a>
</li>
<template x-if="puedeEditar && !esUsuarioEstacion">
<li>
<a class="dropdown-item pointer" @click="abrirArchivos()">
<i class="ti ti-vaccine-bottle me-1"></i> Prueba de Eficiencia
</a>
</li>
</template>
</ul>
</div>
</template>
</div>
</div>
</div>

<!-- TABLA -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-mantenimiento-preventivo" class="table table-striped table-bordered mb-0 align-middle w-100">
<thead></thead>
<tbody></tbody>
<tfoot class="table-dark">
<tr>
</tr>
</tfoot>
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
<i class="ti ti-settings-plus text-white fs-5"></i>
<h5 class="modal-title text-white mb-0"> Nuevo Mantenimiento Preventivo</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<label class="form-label mb-1">* Encargado:</label>
<div class="select2-modal-field is-select2-pending" :class="errores.id_encargado ? 'is-invalid' : ''">
<select id="selectEncargadoNuevo" x-ref="selectEncargadoNuevo" data-width="100%">
<option value=""></option>
<template x-for="e in encargados" :key="e.id">
<option :value="e.id" x-text="e.nombre" :selected="String(e.id) === String(form.id_encargado)"></option>
</template>
</select>
</div>
</div>

<div class="col-12">
<label class="form-label mb-1">* Tipo de mantenimiento:</label>
<div class="select2-modal-field is-select2-pending" :class="errores.tipo ? 'is-invalid' : ''">
<select id="selectTipoNuevo" x-ref="selectTipoNuevo" data-width="100%">
<option value=""></option>
<template x-for="t in tipos" :key="t.id">
<option :value="t.descripcion" x-text="t.descripcion" :selected="String(t.descripcion) === String(form.tipo)"></option>
</template>
</select>
</div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de mantenimiento:</label>
<input type="date"
class="form-control"
x-model="form.fecha"
:style="errores.fecha ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Próxima fecha de mantenimiento:</label>
<input type="date"
class="form-control"
x-model="form.fecha2"
:style="errores.fecha2 ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha2 = false">
</div>

<div class="col-12">
<label class="form-label mb-1">* Costo:</label>
<input type="number"
class="form-control"
step="0.01"
min="0"
x-model="form.costo"
:style="errores.costo ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.costo = false">
</div>

<div class="col-12">
<label class="form-label mb-1">* Orden de servicio:</label>
<input type="file"
class="form-control"
id="inputArchivoNuevo"
@change="form.archivo = $event.target.files[0] || null; errores.archivo = false"
:style="errores.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>

<div class="col-12">
<label class="form-label mb-1">Observaciones:</label>
<textarea class="form-control"
rows="4"
x-model="form.observaciones"></textarea>
</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button" class="btn btn-success" @click="guardarNuevo()" :disabled="guardando || cargandoEncargados">
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
<h5 class="modal-title text-white mb-0">Editar Mantenimiento Preventivo (#<span class="fw-semibold" x-text="detalle.folio_label"></span>)</h5>
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
<label class="form-label mb-1">* Encargado:</label>
<div class="select2-modal-field is-select2-pending" :class="errores.id_encargado ? 'is-invalid' : ''">
<select id="selectEncargadoEditar" x-ref="selectEncargadoEditar" data-width="100%">
<option value=""></option>
<template x-for="e in encargados" :key="e.id">
<option :value="e.id" x-text="e.nombre" :selected="String(e.id) === String(form.id_encargado)"></option>
</template>
</select>
</div>
</div>

<div class="col-12">
<label class="form-label mb-1">* Tipo de mantenimiento:</label>
<div class="select2-modal-field is-select2-pending" :class="errores.tipo ? 'is-invalid' : ''">
<select id="selectTipoEditar" x-ref="selectTipoEditar" data-width="100%">
<option value=""></option>
<template x-for="t in tipos" :key="t.id">
<option :value="t.descripcion" x-text="t.descripcion" :selected="String(t.descripcion) === String(form.tipo)"></option>
</template>
</select>
</div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de mantenimiento:</label>
<input type="date"
class="form-control"
x-model="form.fecha"
:style="errores.fecha ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Próxima fecha de mantenimiento:</label>
<input type="date"
class="form-control"
x-model="form.fecha2"
:style="errores.fecha2 ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha2 = false">
</div>

<div class="col-12">
<label class="form-label mb-1">* Costo:</label>
<input type="number"
class="form-control"
step="0.01"
min="0"
x-model="form.costo"
:style="errores.costo ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.costo = false">
</div>

<div class="col-12">
<label class="form-label mb-1">Orden de servicio:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalle.orden_servicio_existe">
<button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" @click="descargarOrden(detalle.orden_servicio)">
<i class="ti ti-download fs-5"></i>
<span>Descargar actual</span>
</button>
</template>
<template x-if="!detalle.orden_servicio_existe && detalle.orden_servicio">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
El archivo no está disponible en el servidor.
</div>
</template>
<template x-if="!detalle.orden_servicio">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
Sin orden de servicio registrada.
</div>
</template>
</div>
<input type="file"
class="form-control mt-2"
id="inputArchivoEditar"
@change="form.archivo = $event.target.files[0] || null; errores.archivo = false"
:style="errores.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>

<div class="col-12">
<label class="form-label mb-1">Observaciones:</label>
<textarea class="form-control"
rows="4"
x-model="form.observaciones"></textarea>
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

<!-- ================= MODAL PRUEBA DE EFICIENCIA ================= -->
<div class="modal fade" id="modalArchivos" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-vial text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Prueba de Eficiencia</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* Fecha:</label>
<input type="date"
class="form-control"
x-model="formDoc.fecha"
:style="erroresDoc.fecha ? 'border: 2px solid #A52525 !important;' : ''"
@input="erroresDoc.fecha = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Archivo (PDF):</label>
<input type="file"
class="form-control"
id="inputArchivoPrueba"
accept=".pdf,application/pdf"
@change="formDoc.archivo = $event.target.files[0] || null; erroresDoc.archivo = false"
:style="erroresDoc.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>
</div>

<div class="text-end mt-3">
<button type="button" class="btn btn-success mb-3" @click="guardarArchivoPrueba()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-plus me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Agregar'"></span>
</button>
</div>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100" style="font-size:.9em;">
<thead>
<tr>
<th class="text-center align-middle">Fecha</th>
<th class="text-center align-middle" width="60"><i class="ti ti-file-type-pdf"></i></th>
<th class="text-center align-middle" width="60"><i class="ti ti-trash"></i></th>
</tr>
</thead>
<tbody>
<template x-if="cargandoDoc">
<tr>
<td colspan="3" class="text-center text-secondary py-3">
<span class="spinner-border spinner-border-sm me-2"></span>Cargando documentos...
</td>
</tr>
</template>
<template x-if="!cargandoDoc && documentos.length === 0">
<tr>
<td colspan="3" class="text-center text-secondary py-3">No se encontró información para mostrar</td>
</tr>
</template>
<template x-for="doc in documentos" :key="doc.id">
<tr>
<td class="text-center align-middle" x-text="doc.fecha"></td>
<td class="text-center align-middle">
<template x-if="doc.archivo_existe">
<a href="javascript:void(0)" class="pointer" @click="descargar(doc.archivo)" title="Descargar PDF">
<i class="ti ti-file-type-pdf fs-5 text-danger"></i>
</a>
</template>
<template x-if="!doc.archivo_existe">
<i class="ti ti-file-off text-muted"></i>
</template>
</td>
<td class="text-center align-middle">
<template x-if="puedeEliminar">
<a href="javascript:void(0)" class="pointer" @click="eliminarDocumento(doc.id)" title="Eliminar">
<i class="ti ti-trash fs-5 text-danger"></i>
</a>
</template>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

</div>