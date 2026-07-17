<div id="container" class="mt-3 mb-3"
data-year-mes-template="<?= $yearMesTemplate ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-usuario="<?= $idUsuario ?>"
data-mostrar-cuenta="<?= $mostrarCuenta ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-get-data-url="/departamento-operativo/corporativo/solicitud-vales/get-data"
x-data="solicitudValesComponent()">

<div class="row mb-2">
<div class="col-12">
<a href="/departamento-operativo/corporativo/solicitud-vales-nuevo/<?= $idYear ?>/<?= $idMes ?>/<?= $idEstacion ?>" class="btn btn-primary float-end"> <i class="ti ti-plus me-1"></i> Nuevo</a>
</div>
</div>

<div class="table-responsive pb-5" style="overflow-y: hidden; overflow-x: auto;">
<table id="tabla-solicitud-vales" class="table table-striped table-bordered w-100"></table>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Detalle de Solicitud de Vale</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">

<div class="col-md-4">
<label class="form-label">Folio:</label>
<p x-text="'00' + detalle.folio"></p>
</div>

<div class="col-md-4">
<label class="form-label">Fecha y hora:</label>
<p x-text="detalle.fecha + ', ' + detalle.hora"></p>
</div>

<div class="col-md-4">
<label class="form-label">Monto y moneda:</label>
<p x-text="'$' + detalle.monto + ' ' + detalle.moneda"></p>
</div>

<div class="col-md-4">
<label class="form-label">Concepto:</label>
<p x-text="detalle.concepto"></p>
</div>

<div class="col-md-4">
<label class="form-label">Nombre del solicitante:</label>
<p x-text="detalle.solicitante"></p>
</div>

<div class="col-12 mt-3">

<h5 class="fw-semibold">Cargo a cuenta</h5>
</div>

<template x-if="detalle.id_estacion > 0">
<div class="col-md-4">
<label class="form-label">Estación:</label>
<p x-text="detalle.estacion_nombre"></p>
</div>
</template>

<template x-if="detalle.cuenta && detalle.cuenta !== ''">
<div class="col-md-4">
<label class="form-label">Cuenta:</label>
<p x-text="detalle.cuenta"></p>
</div>
</template>

<div class="col-md-4">
<label class="form-label">Autorizado por:</label>
<p x-text="detalle.autorizado_por"></p>
</div>

<div class="col-md-4">
<label class="form-label">Método de autorización:</label>
<p x-text="detalle.metodo_autorizacion"></p>
</div>

<template x-if="detalle.observaciones">
<div class="col-12">
<label class="form-label">Observaciones:</label>
<p class="mb-0" x-text="detalle.observaciones"></p>
</div>
</template>

<div class="col-12 mt-3">

<h6 class="fw-semibold">Documentación:</h6>
   
<div class="table-responsive">
<table class="table table-striped table-bordered mt-2 mb-0">
<thead>
<tr>
<th class="text-center">Descripción</th>
<th class="text-center" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="doc in documentos" :key="doc.id">
<tr>
<td class="text-center fw-normal" x-text="doc.nombre"></td>
<td class="text-center">
<a href="" @click.prevent="downloadFile(doc.documento)">
<i class="ti ti-download text-primary fs-5"></i>
</a>
</td>
</tr>
</template>
<template x-if="documentos.length === 0">
<tr>
<td colspan="2" class="text-center text-primary">No se encontro información</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

<!-- Offcanvas Comentarios -->
<div class="offcanvas offcanvas-end d-flex flex-column"
tabindex="-1"
id="offcanvasComentarios"
style="width: 480px; max-height: 100dvh; overflow: hidden;">

<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
<span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success">
<span class="visually-hidden">online</span>
</span>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">
Solicitud #00<span x-text="comentarioIdActual"></span>
</p>
</div>
</div>
<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="offcanvas"></button>
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

<div class="d-flex mb-4"
:class="c.esMio ? 'justify-content-end' : 'justify-content-start'">

<template x-if="!c.esMio">
<div class="d-flex gap-3 align-items-start">

<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center"
style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-6"></i>
</div>
</div>

<div>
<h6 class="fw-semibold mb-1"
x-text="c.usuario_nombre || 'Usuario'"></h6>

<div class="fs-3 text-muted mb-1"
x-text="c.fecha_formateada || ''"></div>

<div class="p-3 text-bg-success rounded-3 text-white mt-2"
style="max-width: 420px;"
x-text="c.comentario"></div>
</div>

</div>
</template>

<template x-if="c.esMio">
<div class="d-flex flex-column align-items-end">

<div class="fs-3 text-muted mb-1 text-end"
x-text="c.fecha_formateada || ''"></div>

<div class="p-3 bg-primary text-white rounded-3 mt-2"
style="max-width: 420px;"
x-text="c.comentario"></div>

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
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
rows="1"
placeholder="Escribe un comentario..."
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

<!-- Modal Documentos -->
<div class="modal fade" id="modalDocumentos" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Documentación</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<div class="row">

<div class="col-12 mb-3">
<select class="form-select" x-model="documentoForm.nombre">
<option value="">Selecciona una opción...</option>
<option value="VALE">VALE</option>
<option value="RECIBO">RECIBO</option>
<option value="FACTURA">FACTURA</option>
<option value="PDF">PDF</option>
<option value="XML">XML</option>
</select>
</div>

<div class="col-12 mb-3">
<div class="d-flex align-items-center gap-2 mb-3">
<input type="file" class="form-control flex-grow-1" id="fileDocumento">
<button type="button" class="btn btn-success text-nowrap" @click="agregarDocumento()" :disabled="subiendoDocumento">
<i class="ti ti-plus me-1"></i> Agregar
</button>
</div>
</div>

<div class="col-12">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0">
<thead>
<tr>
<th class="text-center">Nombre</th>
<th class="text-center" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<th class="text-center" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="doc in documentosModal" :key="doc.id">
<tr>
<th class="text-center fw-normal" x-text="doc.nombre"></th>
<td class="text-center"><a href="" @click.prevent="downloadFile(doc.documento)"><i class="ti ti-download text-primary fs-5"></i></a></td>
<td class="text-center"><a href="" @click.prevent="eliminarDocumento(doc.id)"><i class="ti ti-trash text-danger fs-5"></i></a></td>
</tr>
</template>
<template x-if="documentosModal.length === 0">
<tr>
<td colspan="3" class="text-center text-primary">No se encontro información</td>
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
</div>

</div>
