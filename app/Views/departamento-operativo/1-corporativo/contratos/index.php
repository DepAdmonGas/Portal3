<div id="container" data-module-station-key="contratos" data-categoria="<?= $categoria ?>" data-contexto="<?= $contexto ?>">

<?php if (!$idEstacion): ?>
<div id="contratos-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar los contratos.
</div>
<div id="contratos-content" style="display:none">
<?php else: ?>
<div id="contratos-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar los contratos.
</div>
<div id="contratos-content">
<?php endif; ?>

<div class="d-flex justify-content-end mb-2">
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-contrato">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>

<div class="table-responsive pb-5" style="overflow-y: hidden; overflow-x: auto;">
<table id="tabla-contratos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>

</div>
</div>

<div class="modal fade"
id="modal-contrato"
tabindex="-1"
data-bs-backdrop="static"
x-data="{ ...actions(), ...contratosComponent() }"
@contrato:detalle.window="abrirModalDetalle($event.detail.id)"
@contrato:editar.window="abrirModalEditar($event.detail.id)"
@contrato:eliminar.window="eliminarContrato($event.detail.id, $event.detail.descripcion)"
@contrato:descargar.window="download($event.detail.tipo, $event.detail.archivo)">

<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title" x-text="modalTitle"></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="cerrarModal"></button>
</div>

<div class="modal-body">

<template x-if="modo === 'detalle'">
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
<label class="form-label">Fecha:</label>
<p class="mb-0" x-text="detalle.fecha_formateada"></p>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
<label class="form-label">Descripcion del contrato:</label>
<p class="mb-0" x-text="detalle.descripcion"></p>
</div>
<div class="col-12 mb-3">
<label class="form-label">PDF:</label>
<template x-if="detalle.archivo">
<iframe class="border-0 mt-1" :src="'/download?tipo=contratos&file=' + encodeURIComponent(detalle.archivo) + '&view=1'" width="100%" height="400px"></iframe>
</template>
<template x-if="!detalle.archivo">
<p class="text-muted">No se cuenta con un archivo adjunto.</p>
</template>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-3">
<label class="form-label">Objeto:</label>
<p class="mb-0" x-text="detalle.objeto"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-3">
  <label class="form-label">Proveedor:</label>
<p class="mb-0" x-text="detalle.proveedor"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-3">
<label class="form-label">Vencimiento:</label>
<p class="mb-0" x-text="detalle.vencimiento"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-3">
<label class="form-label">Personas que firman:</label>
<p class="mb-0" x-text="detalle.firmas"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 mb-3">
<label class="form-label">Comentario:</label>
<p class="mb-0" x-text="detalle.comentario"></p>
</div>
</div>
</template>

<template x-if="modo === 'agregar' || modo === 'editar'">
<form @submit.prevent="guardarContrato">
<div class="row">
<div class="col-12 mb-3">
<label class="form-label">* Fecha:</label>
<input type="date" class="form-control" x-model="form.fecha" required>
</div>
<div class="col-12 mb-3">
<label class="form-label">* Descripcion del contrato:</label>
<textarea class="form-control" x-model="form.descripcion" required></textarea>
</div>
<div class="col-12 mb-3">
<label class="form-label">* PDF:</label>
<input type="file" class="form-control" x-ref="archivoInput" accept=".pdf,.doc,.docx">
</div>
<div class="col-12 mb-3">
<label class="form-label">Objeto:</label>
<textarea class="form-control" x-model="form.objeto"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
  <label class="form-label">Proveedor:</label>
<textarea class="form-control" x-model="form.proveedor"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
<label class="form-label">Vencimiento:</label>
<input type="date" class="form-control" x-model="form.vencimiento">
</div>
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
<label class="form-label">Personas que firman:</label>
<textarea class="form-control" x-model="form.firmas"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 mb-3">
<label class="form-label">Comentario:</label>
<textarea class="form-control" x-model="form.comentario"></textarea>
</div>
</div>
</form>
</template>

</div>

<div class="modal-footer" x-show="modo === 'detalle'">
<button type="button" class="btn btn-danger" @click="cerrarModal">Cerrar</button>
</div>

<div class="modal-footer" x-show="modo === 'agregar' || modo === 'editar'">
<button type="button" class="btn btn-danger" @click="cerrarModal" :disabled="loading">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarContrato" :disabled="loading">Guardar</button>
</div>

</div>
</div>
</div>
