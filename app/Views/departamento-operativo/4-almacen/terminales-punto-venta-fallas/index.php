<?php
/**
 * Fallas de Terminales Punto de Venta — Almacén
 *
 * Réplica de `4-almacen/tpv` del legacy. Sin lógica de negocio ni ciclos: los
 * catálogos y los permisos viajan como data-attributes y se pintan con Alpine.
 *
 * @var string $title
 * @var string $moduleStationKey
 * @var array|null $terminal   Ficha de la terminal (vista con id) o null (vista por estación)
 * @var int|null  $estacionId
 * @var bool      $estacionFija
 * @var int       $estacionActual
 * @var bool      $puedeCrear
 * @var bool      $puedeEditar
 * @var bool      $puedeEliminar
 * @var bool      $puedeDescargar
 * @var bool      $puedeOperar
 */

$baseUrl = '/departamento-operativo/almacen/terminales-punto-venta-fallas';

// Las opciones del catálogo se pintan desde el servidor para que existan en el
// DOM cuando Alpine inicializa el select (igual que en el módulo de TPV).
$catalogos = ['fallas' => [], 'controlux' => []];

if (!empty($catalogosJson)) {
    $decodificado = json_decode((string)$catalogosJson, true);
    if (is_array($decodificado)) {
        $catalogos = array_merge($catalogos, $decodificado);
    }
}
?>

<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-id-tpv="<?= (int)($terminal['id'] ?? 0) ?>"
data-estacion-id="<?= (int)($estacionId ?? 0) ?>"
data-estacion-fija="<?= !empty($estacionFija) ? 'true' : 'false' ?>"
data-estacion-actual="<?= (int)($estacionActual ?? 0) ?>"
data-puede-crear="<?= !empty($puedeCrear) ? 'true' : 'false' ?>"
data-puede-editar="<?= !empty($puedeEditar) ? 'true' : 'false' ?>"
data-puede-eliminar="<?= !empty($puedeEliminar) ? 'true' : 'false' ?>"
data-puede-descargar="<?= !empty($puedeDescargar) ? 'true' : 'false' ?>"
data-puede-operar="<?= !empty($puedeOperar) ? 'true' : 'false' ?>"
data-puede-reportar="<?= !empty($puedeReportar) ? 'true' : 'false' ?>"
data-terminal="<?= !empty($terminal) ? htmlspecialchars(json_encode($terminal, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '' ?>"
data-catalogos="<?= htmlspecialchars(json_encode($catalogos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...terminalesPuntoVentaFallasComponent() }">

<div class="row mt-3">

<!-- ENCABEZADO Y RESUMEN DE LA TERMINAL -->
<div class="col-12 mb-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

<!-- Tarjeta de la terminal (Lado izquierdo, adaptada al contenido) -->
<template x-if="idTpv > 0 && terminal">
<div class="mb-0 text-start">
<div class="card border-0 shadow-sm bg-primary text-white rounded-2 mb-0">
<div class="card-body py-2 px-3">
<div class="d-flex flex-wrap align-items-center justify-content-start gap-2 text-start" style="font-size: 0.85rem;">

<div class="d-flex flex-wrap align-items-center gap-2 text-start">
<span><strong>TPV:</strong> <span x-text="terminal.tpv"></span></span>
<span class="text-white text-opacity-50">|</span>
<span><strong>Serie:</strong> <span x-text="terminal.no_serie"></span></span>
<span class="text-white text-opacity-50">|</span>
<span><strong>Modelo:</strong> <span x-text="terminal.modelo"></span></span>
<span class="text-white text-opacity-50">|</span>
<span><strong>Conexión:</strong> <span x-text="terminal.tipo_conexion"></span></span>
<span class="text-white text-opacity-50">|</span>
<span><strong>Afiliación:</strong> <span x-text="terminal.no_afiliacion"></span></span>
</div>

<span class="text-white text-opacity-50 d-none d-md-inline">|</span>

<div class="d-flex align-items-center gap-1">
<span><strong>Tipo:</strong> <span  x-text="terminal.estatus_tpv || 'S/I'"></span></span>
</div>

</div>
</div>
</div>
</div>
</template>

<!-- Botón Nuevo (Lado derecho) -->
<template x-if="puedeReportar">
<div class="ms-auto">
<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirNuevaFalla()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</template>

</div>
</div>

<!-- TABLA -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-fallas-tpv" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

<!-- ================= MODAL NUEVA FALLA ================= -->
<div class="modal fade" id="modalNuevaFalla" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-alert-triangle text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Falla TPV: <span x-text="terminal ? terminal.tpv : ''"></span>, No. de serie: <span x-text="terminal ? terminal.no_serie : ''"></span></h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<label class="form-label mb-1">* Falla:</label>
<div class="select2-modal-field is-select2-pending" x-ref="fallaWrapperNueva" :class="errores.falla ? 'is-invalid' : ''">
<select id="selectFallaNueva" x-ref="selectFallaNueva" data-width="100%">
<option value=""></option>
<template x-for="o in catalogos.fallas" :key="o">
<option :value="o" x-text="o"></option>
</template>
</select>
</div>
</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button" class="btn btn-success" @click="guardarNuevaFalla()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL EDITAR / FINALIZAR ================= -->
<div class="modal fade" id="modalEditarFalla" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-pencil text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Falla TPV: <span x-text="detalle ? detalle.tpv : ''"></span>, No. de serie: <span x-text="detalle ? detalle.no_serie : ''"></span></h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoDetalle">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información de la falla...</span>
</div>
</template>

<template x-if="!cargandoDetalle && detalle">
<div>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">Cotización:</label>
<input type="file"
class="form-control"
id="inputCotizacionEditar"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.cotizacion_file = $event.target.files[0] || null">
</div>

<div class="col-md-6">
<label class="form-label mb-1">Factura:</label>
<input type="file"
class="form-control"
id="inputFacturaEditar"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="form.factura_file = $event.target.files[0] || null">
</div>

<div class="col-12">
<label class="form-label mb-1">Día de reporte:</label>
<div class="form-control-plaintext fw-semibold" x-text="detalle.fecha_label"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Falla:</label>
<div class="select2-modal-field is-select2-pending" x-ref="fallaWrapperEditar" :class="errores.falla ? 'is-invalid' : ''">
<select id="selectFallaEditar" x-ref="selectFallaEditar" data-width="100%">
<option value=""></option>
<template x-for="o in catalogos.fallas" :key="o">
<option :value="o" x-text="o"></option>
</template>
</select>
</div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Vida útil / garantía:</label>
<input type="text"
class="form-control"
x-model="form.vida_util"
:style="errores.vida_util ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.vida_util = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Persona que atiende:</label>
<select class="form-select"
x-model="form.atiende_controlux"
:disabled="atiendeEscrito()"
:disabled="atiendoEscrito()"
:style="errores.atiende ? 'border: 2px solid #A52525 !important;' : ''"
@change="onCambiaAtiende()">
<option value="">Seleccione una opción...</option>
<template x-for="p in catalogos.controlux" :key="p.id">
<option :value="p.id" x-text="p.nombre"></option>
</template>
</select>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Persona que atiende (escrita libre):</label>
<input type="text"
class="form-control"
x-model="form.atiende"
:disabled="Number(form.atiende_controlux) > 0"
:style="errores.atiende ? 'border: 2px solid #A52525 !important;' : ''"
@input="onEscribeAtiende()">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* No. de reporte:</label>
<input type="text"
class="form-control"
x-model="form.no_reporte"
:style="errores.no_reporte ? 'border: 2px solid #A52525 !important;' : ''"
@input="errores.no_reporte = false">
</div>

<div class="col-12">
<label class="form-label mb-1">Observaciones:</label>
<textarea class="form-control" rows="3" x-model="form.observaciones"></textarea>
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
@click="guardarFalla('editar')"
:disabled="guardando || cargandoDetalle || !detalle || !detalle.puede_actualizar"
title="Guardar los cambios de la falla">
<template x-if="!guardando"><i class="ti ti-check me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : 'Editar'"></span>
</button>
<button type="button"
class="btn btn-primary"
@click="guardarFalla('finalizar')"
:disabled="guardando || cargandoDetalle || !detalle || !detalle.puede_actualizar"
:title="detalle && detalle.puede_actualizar ? 'Avanza el estatus de la falla' : 'No tienes permiso para actualizar el estatus de esta falla'">
<template x-if="!guardando"><i class="ti ti-refresh me-1"></i></template>
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="etiquetaSiguienteStatus"></span>
</button>
</div>
</div>
</div>
</div>

<!-- ================= MODAL DETALLE FALLA ================= -->
<div class="modal fade" id="modalDetalleFalla" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-eye text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Falla TPV: <span x-text="detalleRegistro ? detalleRegistro.tpv : ''"></span>, No. de serie: <span x-text="detalleRegistro ? detalleRegistro.no_serie : ''"></span></h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="cargandoRegistro">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando la información de la falla...</span>
</div>
</template>

<template x-if="!cargandoRegistro && detalleRegistro">
<div>
<div class="row g-3">

<div class="col-md-6">
<label class="form-label mb-1">Estado:</label>
<div><span class="badge rounded-pill" :class="detalleRegistro.status_badge" x-text="detalleRegistro.status_label"></span></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Estación:</label>
<div x-text="detalleRegistro.estacion_nombre || 'S/I'"></div>
</div>

<!-- Documentación -->
<div class="col-md-6">
<label class="form-label mb-1">Cotización:</label>
<div>
<template x-if="detalleRegistro.cotizacion">
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" @click="descargarDocumento(detalleRegistro.cotizacion)" title="Descargar cotización">
        <i class="ti ti-file-download fs-5"></i>
        <span>Descargar documento</span>
    </button>
</template>
<template x-if="!detalleRegistro.cotizacion">
<span class="text-muted">Documento no disponible</span>
</template>
</div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">Factura:</label>
<div>
<template x-if="detalleRegistro.factura">
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" @click="descargarDocumento(detalleRegistro.factura)" title="Descargar factura">
        <i class="ti ti-file-download fs-5"></i>
        <span>Descargar documento</span>
    </button>
</template>
<template x-if="!detalleRegistro.factura">
<span class="text-muted">Documento no disponible</span>
</template>
</div>
</div>

<!-- Fechas -->
<div class="col-md-4">
<label class="form-label mb-1">Día de reporte:</label>
<div x-text="detalleRegistro.dia_reporte_label || 'Sin información'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Día solución:</label>
<div x-text="detalleRegistro.dia_solucion || 'Sin información'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Fecha de falla:</label>
<div x-text="detalleRegistro.fecha_label || 'Sin información'"></div>
</div>

<!-- Personas -->
<div class="col-md-4">
<label class="form-label mb-1">Personal que reportó la falla:</label>
<div x-text="detalleRegistro.reporta || 'S/I'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Persona que atendió:</label>
<div x-text="detalleRegistro.atiende_label || 'S/I'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">No. reporte:</label>
<div x-text="detalleRegistro.no_reporte || 'Sin información'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Vida útil / garantía:</label>
<div x-text="detalleRegistro.vida_util || 'Sin información'"></div>
</div>

<div class="col-md-4">
<label class="form-label mb-1">Falla:</label>
<div x-text="detalleRegistro.falla || 'Sin información'"></div>
</div>

<div class="col-12">
<label class="form-label mb-1">Observaciones:</label>
<div x-text="detalleRegistro.observaciones || 'Sin información'"></div>
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

<!-- ================= MODAL ANEXOS ================= -->
<div class="modal fade" id="modalAnexos" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-paperclip text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">Anexos Terminal Punto de Venta</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="row g-3">
<div class="col-12"><h5 class="text-primary fw-semibold mb-1">Información TPV</h5></div>
<div class="col-md-6">
<label class="form-label mb-1">TPV:</label>
<div x-text="detalleAnexo ? detalleAnexo.tpv : ''"></div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">No. de serie:</label>
<div x-text="detalleAnexo ? detalleAnexo.no_serie : ''"></div>
</div>


<div class="col-12"><h5 class="text-primary fw-semibold mb-1">Documentación</h5></div>


<div class="col-md-5">
<label class="form-label mb-1">Cotización:</label>
<div class="input-group">
<template x-if="detalleAnexo && detalleAnexo.cotizacion">
<span class="input-group-text bg-success text-white">
<a class="pointer text-white" @click="descargarDocumento(detalleAnexo.cotizacion)" title="Descargar">
<i class="ti ti-file-download"></i>
</a>
</span>
</template>
<input type="file" class="form-control" id="inputCotizacionAnexo"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="docs.cotizacion = $event.target.files[0] || null">
</div>
</div>

<div class="col-md-5">
<label class="form-label mb-1">Factura:</label>
<div class="input-group">
<template x-if="detalleAnexo && detalleAnexo.factura">
<span class="input-group-text bg-success text-white">
<a class="pointer text-white" @click="descargarDocumento(detalleAnexo.factura)" title="Descargar">
<i class="ti ti-file-download"></i>
</a>
</span>
</template>
<input type="file" class="form-control" id="inputFacturaAnexo"
accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.doc,.docx"
@change="docs.factura = $event.target.files[0] || null">
</div>
</div>

<div class="col-md-2 d-flex align-items-end">
    <button type="button" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-1" @click="actualizarDocumentacion()" :disabled="subiendoDocs">
        <template x-if="!subiendoDocs">
            <i class="ti ti-check"></i>
        </template>
        <span x-text="subiendoDocs ? 'Subiendo...' : 'Actualizar'"></span>
    </button>
</div>


<div class="col-12"><h5 class="text-primary fw-semibold mb-1">Anexos</h5></div>

<div class="col-12">
<label class="form-label mb-1">Descripción:</label>
<input type="text" class="form-control" x-model="formAnexo.descripcion"
:style="erroresAnexo.descripcion ? 'border: 2px solid #A52525 !important;' : ''"
@input="erroresAnexo.descripcion = false">
</div>

<div class="col-12">
    <label class="form-label mb-1">Anexo:</label>
    <div class="input-group">
        <input type="file" class="form-control" id="inputArchivoAnexo"
        :style="erroresAnexo.archivo ? 'border: 2px solid #A52525 !important;' : ''"
        @change="formAnexo.archivo = $event.target.files[0] || null">
        
        <button type="button" class="btn bg-primary-subtle text-primary d-inline-flex align-items-center gap-1" @click="guardarAnexo()" :disabled="guardandoAnexo">
            <template x-if="!guardandoAnexo">
                <i class="ti ti-plus fs-5"></i>
            </template>
            <span x-text="guardandoAnexo ? 'Guardando...' : 'Nuevo'"></span>
        </button>
    </div>
</div>

<div class="col-12">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" width="48px">No.</th>
<th class="text-start align-middle">Descripción</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-download fs-6 text-primary"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></th>
</tr>
</thead>
<tbody>
<template x-for="a in anexos" :key="a.id">
<tr>
<th class="align-middle text-center" x-text="a.id"></th>
<th class="align-middle text-start" x-text="a.descripcion"></th>
<th class="align-middle text-center">
<a class="pointer" @click="descargarDocumento(a.documento, true)" title="Descargar anexo">
<i class="ti ti-file-download fs-6 text-primary"></i>
</a>
</th>
<th class="align-middle text-center">
<a class="pointer text-danger" @click="eliminarAnexo(a.id)" title="Eliminar anexo">
<i class="ti ti-trash fs-6"></i>
</a>
</th>
</tr>
</template>
<template x-if="!cargandoAnexos && anexos.length === 0">
<tr><td colspan="4" class="text-center text-primary">No se encontró información</td></tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
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
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">Falla #<span x-text="comentarioId"></span></p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
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
<h6 class="fw-semibold mb-1" x-text="c.nombre"></h6>
<div class="fs-3 text-muted mb-1"><span x-text="c.fecha_label"></span><span x-show="c.hora_label" x-text="', ' + c.hora_label"></span></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.es_propio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end"><span x-text="c.fecha_label"></span><span x-show="c.hora_label" x-text="', ' + c.hora_label"></span></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>
</div>
</template>
</div>
</template>

</div>
</div>

<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill"
rows="1" placeholder="Escribe un comentario..."
style="resize:none;"
x-model="formComentario"
@keydown.enter.prevent="guardarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;" type="button"
@click="guardarComentario()"
:disabled="guardandoComentario || !formComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>
</div></div>
