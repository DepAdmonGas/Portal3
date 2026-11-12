<div id="container" data-module-station-key="contratos" data-categoria="<?= $categoria ?>" data-contexto="<?= $contexto ?>">

<?php if (!$idEstacion && !$global): ?>
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

<div class="row ms-auto w-100 justify-content-end">
  <div class="col-12 col-md-auto d-grid mb-2 mt-2">
<button type="button" id="contratos-nuevo-btn" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modal-contrato" style="display:<?= $global ? 'none' : '' ?>">
<i class="ti ti-plus"></i> Nuevo
</button>
  </div>
</div>

<div class="table-responsive pb-5 overflow-x-auto overflow-y-hidden">
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
data-bs-keyboard="false"
x-data="{ ...actions(), ...contratosComponent() }"
@contrato:detalle.window="abrirModalDetalle($event.detail.id)"
@contrato:editar.window="abrirModalEditar($event.detail.id)"
@contrato:eliminar.window="eliminarContrato($event.detail.id, $event.detail.descripcion)"
@contrato:descargar.window="download($event.detail.tipo, $event.detail.archivo)">

<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
<i class="ti ti-contract"></i>
<span
x-text="modalTitle"></span>
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="cerrarModal"></button>
</div>

<div class="modal-body">

<template x-if="modo === 'detalle'">
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-12">
<label class="form-label mb-1">Fecha:</label>
<p class="mb-3" x-text="detalle.fecha_formateada"></p>
</div>
<div class="col-xl-6 col-lg-6 col-md-12">
<label class="form-label mb-1">Descripcion del contrato:</label>
<p class="mb-3" x-text="detalle.descripcion"></p>
</div>
<div class="col-12">
<label class="form-label mb-3">PDF:</label>
<template x-if="detalle.archivo">
<iframe class="border-0 mb-3" :src="'/download?tipo=contratos&file=' + encodeURIComponent(detalle.archivo) + '&view=1'" width="100%" height="400px"></iframe>
</template>
<template x-if="!detalle.archivo">
<p class="text-muted">No se cuenta con un archivo adjunto.</p>
</template>
</div>
<div class="col-xl-4 col-lg-4 col-md-6">
<label class="form-label mb-1">Objeto:</label>
<p class="mb-3" x-text="detalle.objeto"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6">
  <label class="form-label mb-1">Proveedor:</label>
<p class="mb-3" x-text="detalle.proveedor"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6">
<label class="form-label mb-1">Vencimiento:</label>
<p class="mb-3" x-text="detalle.vencimiento"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6">
<label class="form-label mb-1">Personas que firman:</label>
<p class="mb-3" x-text="detalle.firmas"></p>
</div>
<div class="col-xl-4 col-lg-4 col-md-6">
<label class="form-label mb-1">Comentario:</label>
<p class="mb-3" x-text="detalle.comentario"></p>
</div>
</div>
</template>

<template x-if="modo === 'agregar' || modo === 'editar'">
<form @submit.prevent="guardarContrato">
<div class="row">
<div class="col-12">
<label class="form-label mb-1">* Fecha:</label>
<input type="date" class="form-control mb-3" x-model="form.fecha" required>
</div>
<div class="col-12">
<label class="form-label mb-1">* Descripcion del contrato:</label>
<textarea class="form-control mb-3" x-model="form.descripcion" required></textarea>
</div>
<div class="col-12">
<label class="form-label mb-1">* PDF:</label>
<input type="file" class="form-control mb-3" x-ref="archivoInput" accept=".pdf,.doc,.docx">
</div>
<div class="col-12">
<label class="form-label mb-1">Objeto:</label>
<textarea class="form-control mb-3" x-model="form.objeto"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12">
  <label class="form-label mb-1">Proveedor:</label>
<textarea class="form-control mb-3" x-model="form.proveedor"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12">
<label class="form-label mb-1">Vencimiento:</label>
<input type="date" class="form-control mb-3" x-model="form.vencimiento">
</div>
<div class="col-xl-6 col-lg-6 col-md-12">
<label class="form-label mb-1">Personas que firman:</label>
<textarea class="form-control mb-3" x-model="form.firmas"></textarea>
</div>
<div class="col-xl-6 col-lg-6 col-md-12">
<label class="form-label mb-1">Comentario:</label>
<textarea class="form-control mb-3" x-model="form.comentario"></textarea>
</div>
</div>
</form>
</template>

</div>

<div class="modal-footer" x-show="modo === 'detalle'">
  <div class="row g-0 ms-auto w-100 justify-content-end">
    <div class="col-12 col-md-auto d-grid">
      <button type="button" class="btn bg-danger-subtle text-danger" @click="cerrarModal">
        <i class="ti ti-x"></i>
      Cerrar</button>
    </div>
  </div>

</div>

<div class="modal-footer" x-show="modo === 'agregar' || modo === 'editar'">
  <div class="row g-0 ms-auto w-100 justify-content-end">
    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
<button type="button" class="btn bg-danger-subtle text-danger" @click="cerrarModal" :disabled="loading">
  <i class="ti ti-x"></i>
Cancelar</button>
    </div>
    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
<button type="button" class="btn btn-success" @click="guardarContrato" :disabled="loading">
  <i class="ti ti-check"></i>
Guardar</button>
    </div>
  </div>
</div>

</div>
</div>
</div>
