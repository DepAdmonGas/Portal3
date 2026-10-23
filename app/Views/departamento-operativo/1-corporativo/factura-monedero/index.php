<div id="container" class="mt-3 mb-3"
data-year-mes-template="<?= $yearMesTemplate ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-module-station-key="factura-monedero"
x-data="{ ...actions(), ...facturaMonederoComponent() }">

<div id="fm-tools-header" class="d-flex align-items-center justify-content-between mb-2 gap-2 mb-3">
<div id="fm-pending-wrapper" class="d-flex align-items-center gap-1">
<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle fs-4"></i>
<span>Pendientes: <span id="fm-pending-count">0</span></span>
</span>
</div>
<div id="fm-tools-anchor" class="d-flex"></div>
</div>

<template id="fm-tools-tmpl">
<div class="row">
<div class="col-12 text-end">
<div id="fm-tools-wrapper" class="d-inline-block">
<div class="dropdown">
<button type="button" class="btn btn-light dropdown-toggle text-dark"
data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">
<li>
<a class="dropdown-item pointer" @click="abrirModalNuevo()">
<i class="ti ti-plus me-2"></i>Nueva factura
</a>
</li>
<li>
<a class="dropdown-item pointer" @click="descargarExcel()">
<i class="ti ti-file-spreadsheet me-2"></i>Descargar Excel
</a>
</li>
<li>
<a class="dropdown-item pointer" @click="descargarPdf()">
<i class="ti ti-file-type-pdf me-2"></i>Descargar PDF
</a>
</li>
</ul>
</div>
</div>
</div>
</div>
</template>

<div class="table-responsive pb-5 overflow-x-auto overflow-y-hidden">
<table id="tabla-factura-monedero" class="table table-striped table-bordered w-100"></table>
</div>

<!-- Modal Nuevo/Editar -->
<div class="modal fade" id="modalFacturaMonedero" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
    <i class="ti":class="editando ? 'ti-edit' : 'ti-receipt-2'"></i>
    <span x-text="editando ? 'Editar factura' : 'Nueva factura'"></span>
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row">

<template x-if="editando">
<div class="col-12 mb-3">
<label class="form-label mb-0">Folio:</label>
<p class="mt-0 fw-bold" x-text="'00' + form.folio"></p>
</div>
</template>

<template x-if="editando">
<div class="col-12 mb-3">
<label class="form-label mb-1">Fecha de creación:</label>
<p class="mt-0" x-text="form.fecha_creacion"></p>
</div>
</template>

<div class="col-md-6 mb-3">
<label class="form-label mb-1">* No. Factura:</label>
<input type="text" class="form-control" x-model="form.no_factura" :class="{'is-invalid': errors.no_factura}">
</div>

<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Monto:</label>
<input type="number" min="0" step="0.01" class="form-control" x-model="form.monto" :class="{'is-invalid': errors.monto}">
</div>

<div class="col-12">
<h5 class="mt-2 mb-3 form-label">Documentación:</h5>
</div>

<div class="col-md-6 mb-3">
<label class="form-label mb-1">Factura (PDF):</label>

<div class="input-group">
<input
type="file"
class="form-control"
x-ref="fileFactura"
accept=".pdf,.jpg,.jpeg,.png">

<template x-if="editando && form.archivo_factura">
<button
type="button"
class="btn btn-outline-primary"
@click="downloadFile('factura-monedero', form.archivo_factura)"
title="Descargar factura">
<i class="ti ti-download"></i>
</button>
</template>
</div>
</div>

<div class="col-md-6 mb-3">
<label class="form-label mb-1">Factura (XML):</label>

<div class="input-group">
<input
type="file"
class="form-control"
x-ref="fileXml"
accept=".xml">

<template x-if="editando && form.archivo_factura_xml">
<button
type="button"
class="btn btn-outline-primary"
@click="downloadFile('factura-monedero', form.archivo_factura_xml)"
title="Descargar XML">
<i class="ti ti-download"></i>
</button>
</template>
</div>
</div>

<template x-if="editando">
<div class="col-12 mb-3">
<label class="form-label">Comprobante de pago:</label>

<div class="input-group">
<input
type="file"
class="form-control"
x-ref="fileComprobante"
accept=".pdf,.jpg,.jpeg,.png">

<template x-if="form.archivo_comprobante_pago">
<button
type="button"
class="btn btn-outline-info"
@click="downloadFile('factura-monedero', form.archivo_comprobante_pago)"
title="Descargar comprobante">
<i class="ti ti-download"></i>
</button>
</template>
</div>
</div>
</template>
</div>
</div>
<div class="modal-footer">
    <div class="row g-0 ms-auto w-100 justify-content-end">
        <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i>    
Cancelar</button>
        </div>
        <div class="col-12 col-md-auto d-grid ms-0 ms-md-1">
<button type="button" class="btn btn-success" @click="guardar()" :disabled="guardando">
    <i class="ti ti-check"></i>
<span x-text="editando ? 'Finalizar' : 'Guardar'"></span>
</button>
        </div>
    </div>


</div>
</div>
</div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
<i class="ti ti-eye"></i>    
Detalle de factura monedero</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<template x-if="detalle">
<div class="row">

<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
<label class="form-label mb-1">No. de folio:</label>
<p x-text="'00' + detalle.folio"></p>
</div>

<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">
<label class="form-label mb-1">Monto:</label>
<p x-text="'$' + Number(detalle.monto).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
</div>

<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 ">
<label class="form-label mb-1">No. de factura:</label>
<p x-text="detalle.no_factura || '-'"></p>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<label class="form-label mb-1">Fecha de solicitud:</label>
<p x-text="detalle.fecha_creacion_format || detalle.fecha_creacion || '-'"></p>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<label class="form-label mb-1">Estado del factura:</label>
<p>
<template x-if="detalle.estado == 1"><span class="badge bg-success">Finalizado</span></template>
<template x-if="detalle.estado != 1"><span class="badge bg-warning text-dark">Pendiente</span></template>
</p>
</div>

<div class="col-12">
<label class="form-label mb-2">Documentos:</label>
    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Factura (PDF)</th>
<th class="text-center align-middle">Factura (XML)</th>
<th class="text-center align-middle">Comprobante de pago</th>
</tr>
</thead>
<tbody>
<tr>
<td class="text-center align-middle">
<template x-if="detalle.archivo_factura">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('factura-monedero', detalle.archivo_factura)"></i>
</template>
<template x-if="!detalle.archivo_factura">
<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>
</template>
</td>
<td class="text-center align-middle">
<template x-if="detalle.archivo_factura_xml">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('factura-monedero', detalle.archivo_factura_xml)"></i>
</template>
<template x-if="!detalle.archivo_factura_xml">
<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>
</template>
</td>
<td class="text-center align-middle">
<template x-if="detalle.archivo_comprobante_pago">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('factura-monedero', detalle.archivo_comprobante_pago)"></i>
</template>
<template x-if="!detalle.archivo_comprobante_pago">
<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>
</template>
</td>
</tr>
</tbody>
</table>
</div>
</div>

</div>
</template>
</div>
<div class="modal-footer">
    <div class="row ms-auto w-100 justify-content-end">
        <div class="col-12 col-md-auto d-grid">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
    <i class="ti ti-x"></i>
Cerrar</button>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- Offcanvas Comentarios -->
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
<p class="mb-0 text-white opacity-75">
Folio #00<span x-text="comentarioSolicitudId"></span>
</p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>

<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
style="min-height: 0; overscroll-behavior: contain;"
x-ref="chatContainer">

<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<div class="chat-list active-chat p-2">
<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-3"
:class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.esPropio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-5"></i>
</div>
</div>
<div>
<h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.esPropio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_hora || ''"></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>
</div>
</template>
</div>
</div>
</div>
</div>

<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1"
style="resize:none;"
x-model="nuevoComentario"
@keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;"
type="button"
@click="agregarComentario()"
:disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario">
<i class="ti ti-send fs-5"></i>
</template>
<template x-if="guardandoComentario">
<span class="spinner-border spinner-border-sm"></span>
</template>
</button>
</div>
</div>
</div>
</div>

</div>
