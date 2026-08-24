<div id="container" class="mt-3 mb-3"
data-year-mes-template="/departamento-operativo/corporativo/aclaracion-voucher/{year}/{mes}"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-es-comercializadora="<?= $esComercializadora ? 'true' : 'false' ?>"
data-es-mes-actual="<?= $esMesActual ? 'true' : 'false' ?>"
data-module-station-key="aclaracion-voucher"
x-data="{ ...actions(), ...aclaracionVoucherComponent() }">

<div id="av-tools-header" class="d-flex align-items-center justify-content-between mb-2 gap-2 mb-3">
<div id="av-pending-wrapper" class="d-flex align-items-center gap-1">
<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle"></i>
<span>Pendientes: <span id="av-pending-count">0</span></span>
</span>
</div>
<div id="av-tools-anchor" class="d-flex"></div>
</div>

<span id="av-pendientes-data" style="display:none"><?= json_encode($pendientesData) ?></span>

<template id="av-tools-tmpl">
<?php if ($puedeCrear): ?><div id="av-tools-wrapper" class="d-flex justify-content-end">
<button type="button" class="btn btn-primary" @click="abrirModalAgregar()"> <i class="ti ti-plus me-1"></i> Nuevo</button>
</div><?php endif; ?>
</template>

<div class="table-responsive pb-5" style="overflow-y: hidden; overflow-x: auto;">
<table id="tabla-aclaracion-voucher" class="table table-striped table-bordered"></table>
</div>

<!-- Modal Aclaración (Agregar / Editar) -->
<div class="modal fade" id="modalAclaracion" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" x-text="editando ? 'Editar aclaración' : 'Nueva aclaración'"></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row">
<div class="col-12">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle mb-4">
<thead>
<tr>
<td class="text-start">
<h6 class="fw-semibold mb-0">
Información para llevar a cabo la Aclaración de Voucher
</h6>
</td>
</tr>
</thead>
<tbody>
<tbody>
<tr>
<td class="align-middle">
<div class="d-flex align-items-start align-items-center">
<span class="badge bg-primary flex-shrink-0 me-3">1</span>
<div>Mexdesa solicita el documento de aclaración.</div>
</div>
</td>
</tr>

<tr>
<td class="align-middle">
<div class="d-flex align-items-start align-items-center">
<span class="badge bg-primary flex-shrink-0 me-3">2</span>
<div>
Las aclaraciones deberán enviarse de manera inmediata una vez que la
<strong>Valera</strong> las requiera, con el fin de dar respuesta
oportuna y, en su caso, realizar el depósito correspondiente a la
cuenta de la estación de servicio.
</div>
</div>
</td>
</tr>

<tr>
<td class="align-middle">
<div class="d-flex align-items-start align-items-center">
<span class="badge bg-primary flex-shrink-0 me-3">3</span>
<div>
Recibida la solicitud, la estación dispondrá de
<strong>48 horas</strong> para emitir la respuesta correspondiente.
</div>
</div>
</td>
</tr>

<tr>
<td class="align-middle">
<div class="d-flex align-items-start align-items-center">
<span class="badge bg-primary flex-shrink-0 me-3">4</span>
<div>
Se anexa un cuadro de <strong>"Comentarios"</strong> para
observaciones adicionales.
</div>
</div>
</td>
</tr>
</tbody>
</tbody>
</table>
</div>
</div>
<template x-if="editando">
<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">Nombre del solicitante:</label>
<p class="mt-0" x-text="form.solicitante_nombre"></p>
</div>
</template>
<template x-if="editando">
<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">Fecha de solicitud:</label>
<p class="mt-0" x-text="form.fecha_creacion"></p>
</div>
</template>
<div class="col-12 mb-3">
<label class="form-label pb-0">* Ticket a solicitar:</label>
<input type="text" class="form-control" x-model="form.nombre_ticket" :class="{'is-invalid': errors.nombre_ticket}">
</div>
<div class="col-xl-9 col-lg-9 mb-3">
<label class="form-label pb-0">* Fecha:</label>
<input type="date" class="form-control" x-model="form.fecha" :class="{'is-invalid': errors.fecha}">
</div>
<div class="col-xl-3 col-lg-3 mb-3">
<label class="form-label pb-0">* Hora:</label>
<input type="time" class="form-control" x-model="form.hora" :class="{'is-invalid': errors.hora}">
</div>
<div class="col-xl-9 col-lg-9 mb-3">
<label class="form-label pb-0">* Valera:</label>
<input type="text" class="form-control" x-model="form.valera" :class="{'is-invalid': errors.valera}">
</div>
<div class="col-xl-3 col-lg-3 mb-3">
<label class="form-label pb-0">* Importe:</label>
<input type="number" step="0.01" class="form-control" x-model="form.importe" :class="{'is-invalid': errors.importe}">
</div>
<div class="col-12 mb-3">
<label class="form-label pb-0">* Número de aclaración:</label>
<input type="text" class="form-control" x-model="form.numero_aclaracion" :class="{'is-invalid': errors.numero_aclaracion}">
</div>
<template x-if="editando">
<div class="col-12 mb-3">
<label class="form-label pb-0">* Estado del ticket:</label>
<div class="form-check">
<input class="form-check-input" type="radio" id="TicketNoPagado" value="0" x-model="form.pagado">
<label class="form-check-label" for="TicketNoPagado">El ticket no ha sido pagado</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" id="TicketPagado" value="1" x-model="form.pagado">
<label class="form-check-label" for="TicketPagado">El ticket ha sido pagado</label>
</div>
</div>
</template>
<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">* Ticket:</label>
<input type="file" class="form-control" id="fileTicket" accept=".pdf,.jpg,.jpeg,.png">
</div>
<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">* Voucher:</label>
<input type="file" class="form-control" id="fileVoucher" accept=".pdf,.jpg,.jpeg,.png">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-labeled2 btn-success" @click="guardar()" :disabled="guardando">
<span x-text="editando ? 'Editar' : 'Guardar'"></span>
</button>
<template x-if="editando">
<button type="button" class="btn btn-labeled2 btn-primary" @click="finalizarSolicitud()">Finalizar</button>
</template>
</div>
</div>
</div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Detalle de aclaración</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<template x-if="detalle">
<div class="row"> 

<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">Nombre del solicitante:</label>
<p class="mt-0" x-text="detalle.solicitante_nombre"></p>
</div>

<div class="col-xl-6 col-lg-6 mb-3">
<label class="form-label pb-0">Fecha de solicitud:</label>
<p class="mt-0" x-text="detalle.fecha_creacion"></p>
</div>

<div class="col-12 mb-3">
<label class="form-label pb-0">Ticket a solicitar:</label>
<p class="mt-0" x-text="detalle.nombre_ticket"></p>
</div>

<div class="col-xl-9 col-lg-9 mb-3">
<label class="form-label pb-0">Fecha:</label>
<p class="mt-0" x-text="detalle.fecha"></p>
</div>

<div class="col-xl-3 col-lg-3 mb-3">
<label class="form-label pb-0">Hora:</label>
<p class="mt-0" x-text="detalle.hora"></p>
</div>

<div class="col-xl-9 col-lg-9 mb-3">
<label class="form-label pb-0">Valera:</label>
<p class="mt-0" x-text="detalle.valera"></p>
</div>

<div class="col-xl-3 col-lg-3 mb-3">
<label class="form-label pb-0">Importe:</label>
<p class="mt-0" x-text="'$ ' + detalle.importe"></p>
</div>

<div class="col-12 mb-3">
<label class="form-label pb-0">Número de aclaración:</label>
<p class="mt-0" x-text="detalle.numero_aclaracion"></p>
</div>

<div class="col-12 mb-3">
<label class="form-label pb-0">Estado del ticket:</label>
<p class="mt-0" x-text="detalle.pagado == 1 ? 'Pagado' : 'No pagado'"></p>
</div>

<div class="col-12">
<label class="form-label mb-2">Documentación:</label>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr><th class="text-center">Ticket</th><th class="text-center">Voucher</th></tr>
</thead>
<tbody>
<tr>
<td class="text-center align-middle">
<template x-if="detalle.doc_ticket">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('aclaracion-voucher', detalle.doc_ticket)"></i>
</template>
<template x-if="!detalle.doc_ticket">
<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>
</template>
</td>
<td class="text-center align-middle">
<template x-if="detalle.doc_voucher">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('aclaracion-voucher', detalle.doc_voucher)"></i>
</template>
<template x-if="!detalle.doc_voucher">
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
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
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
Aclaración (#<span x-text="comentarioSolicitudId"></span>)
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

<!-- Modal Anexos -->
<div class="modal fade" id="modalAnexos" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Anexos de la aclaración</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<!-- <template x-if="puedeEditar"> -->
<div class="row mb-3">
<div class="col-12 mb-2">
<label class="form-label pb-0">* Descripción:</label>
<input type="text" class="form-control" x-model="anexoForm.descripcion">
</div>
<div class="col-10 mt-2">
<label class="form-label pb-0">* Archivo:</label>
<input type="file" class="form-control" id="fileAnexo">
</div>
<div class="col-2 mt-2 d-flex align-items-end">
<button type="button" class="btn btn-labeled2 btn-success w-100" @click="agregarAnexo()" :disabled="subiendoAnexo">
<span class="btn-label2"><i class="ti ti-plus"></i></span> Agregar
</button>
</div>
</div>
<!-- </template> -->

    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-striped table-bordered mt-3">
<thead>
<tr>
<th class="text-center align-middle">No.</th>
<th class="text-center align-middle">Fecha</th>
<th class="text-center align-middle">Responsable</th>
<th class="text-center align-middle">Descripción</th>
<th class="text-center align-middle"><i class="ti ti-file text-primary fs-5"></i></th>
<th class="text-center align-middle"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="(a, idx) in anexos" :key="a.id">
<tr>
<td class="text-center" x-text="idx + 1"></td>
<td class="text-center" x-text="a.fecha_creacion"></td>
<td x-text="a.usuario_nombre"></td>
<td x-text="a.descripcion"></td>
<td class="text-center">
<template x-if="a.documento">
<i class="ti ti-download pointer text-primary fs-5" @click="downloadFile('aclaracion-voucher', a.documento)"></i>
</template>
<template x-if="!a.documento">
<span class="text-muted"><i class="ti ti-file-off fs-5"></i></span>
</template>
</td>
<td class="text-center">
<!--<template x-if="puedeEditar">-->
<i class="ti ti-trash pointer text-danger fs-5" @click="eliminarAnexo(a.id)"></i>
</template>
</td>
</tr>
</template>
<template x-if="anexos.length === 0">
<tr><td colspan="6" class="text-center text-muted">No se encontraron anexos</td></tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

</div>
