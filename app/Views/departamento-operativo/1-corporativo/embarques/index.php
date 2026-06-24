<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de los Embarques.
</div>
<?php else: ?>

<?php if ($multiestacion && $idEstacion === 8): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de los Embarques.
</div>
<?php else: ?>

<div id="container" class="mt-3 mb-4"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-mes-db="<?= $idMesDb ?? '' ?>"
data-puede-agregar="<?= $puedeAgregar ? 'true' : 'false' ?>"
data-puede-agregar-comentarios="<?= $puedeAgregarComentarios ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-origen="<?= $origen ?>"
x-data="{ ...actions(), ...embarquesComponent() }">

<div class="row mb-3">
<div class="col-12">
<div class="float-end">
<div class="d-flex gap-2">

<?php if ($esEncargadoAsistente): ?>
<button type="button" class="btn btn-success" @click="abrirModalAgregar()">
<i class="ti ti-plus me-1"></i> Agregar
</button>
<?php endif; ?>

<?php if ($puedeAnalisisCompras && !$esEncargadoAsistente): ?>
<?php if ($esDireccionOperaciones): ?>
<div class="dropdown d-inline">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
<i class="ti ti-tools me-1"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<?php if ($puedeAgregar): ?>
<li>
<a class="dropdown-item pointer" @click.prevent="abrirModalAgregar()">
<i class="ti ti-plus me-2"></i> Agregar
</a>
</li>
<?php endif; ?>
<li>
<a class="dropdown-item pointer" @click.prevent="abrirAnalisisCompras()">
<i class="ti ti-chart-bar me-2"></i> Análisis de Compras
</a>
</li>
</ul>
</div>
<?php else: ?>
<button type="button" class="btn btn-info" @click.prevent="abrirAnalisisCompras()">
<i class="ti ti-chart-bar me-1"></i> Análisis de Compras
</button>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>
</div>

<div class="table-responsive pb-5" style="overflow-y: hidden; overflow-x: auto;">
<table id="tabla-embarques" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
</table>
</div>

<?php if ($puedeAgregar): ?>
<div class="modal fade" id="modalEmbarque" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalEmbarque">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title" x-text="editando ? 'Editar embarque' : 'Agregar embarque'"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<div class="alert bg-primary fw-semibold d-flex align-items-center mb-0" role="alert">
<i class="ti ti-file-description me-2 text-white"></i>
<h5 class="mb-0 text-white">
ANEXO IV: Expediente de Transporte para la Reclamación de Producto
</h5>
</div>

<table class="table table-striped table-bordered mb-0 text-nowrap align-middle mt-3">
<tbody>
<tr><td class="text-start"><h6 class="fw-semibold ">La estación de servicio debe recabar la siguiente documentación:</h6></td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">1</span>Hoja 1 <strong>"Acta de Balance (Estación)"</strong></td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">2</span>Factura final de producto.</td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">3</span>Nota de Embarque de Axfaltec.</td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">4</span>Check List: <strong>"Lista de Verificación de la Descarga"</strong></td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">5</span>Tirillas de inventarios (Veeder Root) inicial, final y de aumento.</td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">6</span>Reporte de ventas (de ser el caso, de acuerdo con el punto 10 del checklist).</td></tr>
<tr><td class="text-start"><span class="badge bg-primary me-2">7</span>Firmas autógrafas de ambas partes.</td></tr>
</tbody>
</table>

<hr>

<div class="row g-2">

<div class="col-md-4 mb-3">
<label class="form-label">* Fecha:</label>
<input type="date" class="form-control" x-model="form.fecha">
</div>

<div class="col-md-4 mb-3">
<label class="form-label">* Embarque:</label>
<select class="form-select" x-model="form.embarque" @change="onEmbarqueChange()">
<option value="">Selecciona una opción...</option>
<option value="Pemex">Pemex</option>
<option value="Delivery">Delivery</option>
<option value="Pick Up">Pick Up</option>
</select>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">* Producto:</label>
<select class="form-select" x-model="form.producto">
<option value="">Selecciona una opción...</option>
<option value="G SUPER">G SUPER</option>
<option value="G PREMIUM">G PREMIUM</option>
<option value="G DIESEL">G DIESEL</option>
</select>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">* Documento:</label>
<input type="file" class="form-control" x-ref="documento">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">No. Documento CV:</label>
<input type="text" class="form-control" x-model="form.documentocv">
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Litros Factura:</label>
<input type="number" step="any" class="form-control" x-model="form.importef">
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Precio por Litro:</label>
<input type="number" step="any" class="form-control" x-model="form.precio_litro">
</div>

<div class="col-md-4 mb-3">
<label class="form-label">TAD:</label>
<select class="form-select" x-model="form.tad">
<option value="">Selecciona una opción...</option>
<option value="906 Tizayuca">906 Tizayuca</option>
<option value="904 Tuxpan">904 Tuxpan</option>
<option value="Pemex">Pemex</option>
<option value="903 Atlacomulco">903 Atlacomulco</option>
<option value="901 Vopack">901 Vopack</option>
<option value="908 Monterra">908 Monterra</option>
<option value="907 Puebla">907 Puebla</option>
</select>
</div>

</div>

<hr>

<div x-show="form.embarque === 'Pick Up'">
<div class="table-responsive">
<table class="table table-sm table-bordered" style="font-size:12.5px;">
<thead class="table-secondary">
<tr>
<th class="align-middle text-center">DESCRIPCION</th>
<th class="align-middle text-center">PDF</th>
<th class="align-middle text-center">XML</th>
</tr>
</thead>
<tbody>
<tr>
<td class="align-middle text-center bg-light">Factura</td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="pdf"></td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="xml"></td>
</tr>
<tr>
<td class="align-middle text-center bg-light">Comprobante de pago</td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="comprobante_p"></td>
<td class="align-middle text-center bg-light">N/A</td>
</tr>
<tr>
<td class="align-middle text-center bg-light">Nota de credito</td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="nc_pdf"></td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="nc_xml"></td>
</tr>
<tr>
<td class="align-middle text-center bg-light">Complemento de pago</td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="comPDF"></td>
<td class="align-middle text-center bg-light"><input type="file" class="form-control" x-ref="comXML"></td>
</tr>
</tbody>
</table>
</div>
<hr>
</div>

<div class="row g-2">
<div class="col-md-6 mb-3">
<label class="form-label">* Chofer:</label>
<div class="select2-modal-field is-select2-pending" x-ref="choferWrapper">
<select class="form-select" x-ref="choferSelect" data-width="100%">
<option value="">Selecciona una opción...</option>
</select>
</div>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">* Unidad:</label>
<div class="select2-modal-field is-select2-pending" x-ref="unidadWrapper">
<select class="form-select" x-ref="unidadSelect" data-width="100%">
<option value="">Selecciona una opción...</option>
</select>
</div>
</div>
</div>

<div x-show="form.embarque === 'Pick Up' || form.embarque === 'Delivery'">
<hr>
<div class="row g-2">
<div class="col-md-6 mb-3">
<label class="form-label">Merma:</label>
<input type="number" step="any" class="form-control" x-model="form.merma">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Nombre del transporte:</label>
<div class="select2-modal-field is-select2-pending" x-ref="transporteWrapper">
<select class="form-select" x-ref="transporteSelect" data-width="100%">
<option value="">Selecciona una opción...</option>
</select>
</div>
</div>
</div>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardar()" :disabled="guardando">
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<div class="offcanvas offcanvas-end d-flex flex-column"
tabindex="-1"
id="modalComentarios"
style="width: 480px; max-height: 100dvh; overflow: hidden;">

<!-- HEADER -->
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
Embarque #<span x-text="comentarioEmbarqueId"></span>
</p>
</div>
</div>

<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="offcanvas"></button>
</div>

<!-- BODY -->
<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">

<!-- CHAT WRAPPER -->
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">

<!-- SCROLL AREA -->
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
style="min-height: 0; overscroll-behavior: contain;"
x-ref="chatContainer">

<!-- EMPTY STATE -->
<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center"
style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<!-- COMMENTS LIST -->
<div class="chat-list active-chat p-2">

<template x-for="c in comentarios" :key="c.id">

<div class="d-flex mb-4"
:class="c.esMio ? 'justify-content-end' : 'justify-content-start'">

<!-- OTROS -->
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

<!-- MÍO -->
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

<!-- FOOTER -->
<template x-if="puedeAgregarComentarios">
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
</template>

</div>
<?php endif; ?>
<?php endif; ?>
