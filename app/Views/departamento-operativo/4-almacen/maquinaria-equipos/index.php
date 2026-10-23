<?php
/**
 * Maquinaria y Equipos — Almacén
 *
 * La vista no contiene lógica de negocio ni ciclos: el catálogo de maquinaria y los
 * permisos viajan como data-attributes y se pintan con Alpine. La estación NO se pide
 * en los modales: se toma del contexto del módulo (ModuleStationService).
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var bool   $estacionFija
 * @var int    $estacionActual
 * @var bool   $esUsuarioEstacion
 * @var array  $maquinariaOpciones
 * @var string $maquinariaOpcionesJson
 * @var bool   $puedeCrear
 * @var bool   $puedeEditar
 * @var bool   $puedeEliminar
 * @var bool   $puedeDescargar
 */

$baseUrl = '/departamento-operativo/almacen/maquinaria-equipos';
?>
<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-fija="<?= $estacionFija ? 'true' : 'false' ?>"
data-estacion-actual="<?= (int)($estacionActual ?? 0) ?>"
data-es-usuario-estacion="<?= !empty($esUsuarioEstacion) ? 'true' : 'false' ?>"
data-maquinaria-opciones="<?= htmlspecialchars($maquinariaOpcionesJson ?? '[]', ENT_QUOTES, 'UTF-8') ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...maquinariaEquiposComponent() }">

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
<table id="tabla-maquinaria-equipos" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
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
<i class="ti ti-settings-plus text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Nueva Maquinaria</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* Tipo de maquinaria:</label>
<select class="form-select"
x-model="form.maquinaria"
:style="errores.maquinaria ? 'border: 2px solid #A52525 !important;' : ''"
@change="errores.maquinaria = false">
<option value="">Seleccione una opción...</option>
<template x-for="m in maquinariaOpciones" :key="m">
<option :value="m" x-text="m"></option>
</template>
</select>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Descripción:</label>
<input type="text"
class="form-control"
x-model="form.descripcion"
:style="errores.descripcion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.descripcion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Marca:</label>
<input type="text" class="form-control" x-model="form.marca">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Modelo:</label>
<input type="text" class="form-control" x-model="form.modelo">
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de serie:</label>
<input type="text" class="form-control" x-model="form.no_serie">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de compra:</label>
<input type="date"
class="form-control"
x-model="form.fecha_compra"
:style="errores.fecha_compra ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha_compra = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de instalación:</label>
<input type="date"
class="form-control"
x-model="form.fecha_instalacion"
:style="errores.fecha_instalacion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha_instalacion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Proveedor:</label>
<input type="text" class="form-control" x-model="form.proveedor">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Costo de compra:</label>
<input type="number"
class="form-control"
step="0.01"
min="0"
x-model="form.costo_compra"
:style="errores.costo_compra ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.costo_compra = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Garantía:</label>
<input type="text" class="form-control" x-model="form.garantia">
</div>

<div class="col-12">
<label class="form-label mb-1">Factura (PDF, imagen u office):</label>
<input type="file"
class="form-control"
id="inputFacturaNuevo"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.factura_file = $event.target.files[0] || null; errores.factura_file = false"
:style="errores.factura_file ? 'border: 2px solid #A52525 !important;' : ''">
</div>

<div class="col-12">
<label class="form-label mb-1">Manual (PDF, imagen u office):</label>
<input type="file"
class="form-control"
id="inputManualNuevo"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.manual_file = $event.target.files[0] || null; errores.manual_file = false"
:style="errores.manual_file ? 'border: 2px solid #A52525 !important;' : ''">
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
<h5 class="modal-title text-white mb-0">Editar Maquinaria (#<span class="fw-semibold" x-text="detalle ? detalle.id : ''"></span>)</h5>
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
<div class="col-md-6">
<label class="form-label mb-1">* Tipo de maquinaria:</label>
<select class="form-select"
x-model="form.maquinaria"
:style="errores.maquinaria ? 'border: 2px solid #A52525 !important;' : ''"
@change="errores.maquinaria = false">
<option value="">Seleccione una opción...</option>
<template x-for="m in maquinariaOpciones" :key="m">
<option :value="m" x-text="m"></option>
</template>
</select>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Descripción:</label>
<input type="text"
class="form-control"
x-model="form.descripcion"
:style="errores.descripcion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.descripcion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Marca:</label>
<input type="text" class="form-control" x-model="form.marca">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Modelo:</label>
<input type="text" class="form-control" x-model="form.modelo">
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de serie:</label>
<input type="text" class="form-control" x-model="form.no_serie">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de compra:</label>
<input type="date"
class="form-control"
x-model="form.fecha_compra"
:style="errores.fecha_compra ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha_compra = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de instalación:</label>
<input type="date"
class="form-control"
x-model="form.fecha_instalacion"
:style="errores.fecha_instalacion ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.fecha_instalacion = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Proveedor:</label>
<input type="text" class="form-control" x-model="form.proveedor">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Costo de compra:</label>
<input type="number"
class="form-control"
step="0.01"
min="0"
x-model="form.costo_compra"
:style="errores.costo_compra ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.costo_compra = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Garantía:</label>
<input type="text" class="form-control" x-model="form.garantia">
</div>

<div class="col-12">
<label class="form-label mb-1">Factura actual:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalle.factura_existe">
<button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" @click="descargarFactura(detalle.factura)">
<i class="ti ti-file-download fs-5"></i>
<span>Descargar factura</span>
</button>
</template>
<template x-if="!detalle.factura_existe && detalle.factura">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
El archivo no está disponible en el servidor.
</div>
</template>
<template x-if="!detalle.factura">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
Sin factura registrada.
</div>
</template>
</div>
<input type="file"
class="form-control mt-2"
id="inputFacturaEditar"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.factura_file = $event.target.files[0] || null; errores.factura_file = false"
:style="errores.factura_file ? 'border: 2px solid #A52525 !important;' : ''">
</div>

<div class="col-12">
<label class="form-label mb-1">Manual actual:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalle.manual_existe">
<button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" @click="descargarManual(detalle.manual)">
<i class="ti ti-file-download fs-5"></i>
<span>Descargar manual</span>
</button>
</template>
<template x-if="!detalle.manual_existe && detalle.manual">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
El archivo no está disponible en el servidor.
</div>
</template>
<template x-if="!detalle.manual">
<div class="text-muted">
<i class="ti ti-file-off me-1"></i>
Sin manual registrado.
</div>
</template>
</div>
<input type="file"
class="form-control mt-2"
id="inputManualEditar"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.manual_file = $event.target.files[0] || null; errores.manual_file = false"
:style="errores.manual_file ? 'border: 2px solid #A52525 !important;' : ''">
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

<!-- ================= MODAL DETALLE ================= -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-eye text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Detalle de Maquinaria (#<span class="fw-semibold" x-text="detalleRegistro ? detalleRegistro.id : ''"></span>)</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoRegistro">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información del registro...</span>
</div>
</template>

<template x-if="!cargandoRegistro && detalleRegistro">
<div>
<div class="row g-3">
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Estación / Departamento:</label>
<div x-text="detalleRegistro.contexto_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Tipo de maquinaria:</label>
<div x-text="detalleRegistro.maquinaria_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Estatus:</label>
<div><span class="badge rounded-pill" :class="detalleRegistro.estatus_badge" x-text="detalleRegistro.estatus_label"></span></div>
</div>

<div class="col-12">
<label class="form-label text-muted mb-1 fw-semibold">Descripción:</label>
<div x-text="detalleRegistro.descripcion_label"></div>
</div>

<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Marca:</label>
<div x-text="detalleRegistro.marca_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Modelo:</label>
<div x-text="detalleRegistro.modelo_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">No. de serie:</label>
<div x-text="detalleRegistro.no_serie_label"></div>
</div>

<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Fecha de compra:</label>
<div x-text="detalleRegistro.fecha_compra_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Fecha de instalación:</label>
<div x-text="detalleRegistro.fecha_instalacion_label"></div>
</div>
<div class="col-md-4">
<label class="form-label text-muted mb-1 fw-semibold">Costo de compra:</label>
<div x-text="detalleRegistro.costo_label"></div>
</div>

<div class="col-md-6">
<label class="form-label text-muted mb-1 fw-semibold">Proveedor:</label>
<div x-text="detalleRegistro.proveedor_label"></div>
</div>
<div class="col-md-6">
<label class="form-label text-muted mb-1 fw-semibold">Garantía:</label>
<div x-text="detalleRegistro.garantia_label"></div>
</div>

<div class="col-md-6">
<label class="form-label text-muted mb-1 fw-semibold">Factura:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalleRegistro.factura_existe">
<a class="pointer" @click="descargarFactura(detalleRegistro.factura)" title="Descargar factura">
<i class="ti ti-file-download fs-5 text-primary"></i>
</a>
</template>
<template x-if="!detalleRegistro.factura_existe">
<i class="ti ti-file-off fs-5 text-muted"></i>
</template>
</div>
</div>

<div class="col-md-6">
<label class="form-label text-muted mb-1 fw-semibold">Manual:</label>
<div class="d-flex align-items-center gap-2 flex-wrap">
<template x-if="detalleRegistro.manual_existe">
<a class="pointer" @click="descargarManual(detalleRegistro.manual)" title="Descargar manual">
<i class="ti ti-file-download fs-5 text-primary"></i>
</a>
</template>
<template x-if="!detalleRegistro.manual_existe">
<i class="ti ti-file-off fs-5 text-muted"></i>
</template>
</div>
</div>
</div>
</div>
</template>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
</div>
</div>
</div>
</div>

<!-- ================= OFF-CANVAS COMENTARIOS ================= -->
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">Maquinaria #<span x-text="comentarioId"></span></p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>

<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;" x-ref="chatContainer">

<template x-if="cargandoComentarios">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<div class="spinner-border text-primary mb-3" role="status"></div>
<p class="text-muted mb-0">Cargando comentarios...</p>
</div>
</template>

<template x-if="!cargandoComentarios && comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<template x-if="!cargandoComentarios">
<div class="chat-list active-chat p-2">
<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-3" :class="c.es_propio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.es_propio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-5"></i>
</div>
</div>
<div>
<h6 class="fw-semibold mb-1" x-text="c.nombre_usuario || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_label || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.es_propio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_label || ''"></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>
</div>
</template>
</div>
</template>

</div>
</div>
</div>

<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill"
rows="1" placeholder="Escribe un comentario..."
style="resize:none;"
x-model="nuevoComentario"
@keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;" type="button"
@click="agregarComentario()"
:disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>

</div>