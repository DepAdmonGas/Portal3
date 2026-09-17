<div id="container" class="mt-4 mb-5"
data-id="<?= (int)$id ?>"
data-fecha="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?>"
data-fecha-larga="<?= htmlspecialchars($fecha_larga, ENT_QUOTES, 'UTF-8') ?>"
data-estatus="<?= (int)$estatus ?>"
data-modulo="formato"
data-editable="<?= $editable ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-finalizar="<?= $puedeFinalizar ? 'true' : 'false' ?>"
data-puede-habilitar="<?= $puedeHabilitar ? 'true' : 'false' ?>"
data-puede-editar-fecha="<?= $puedeEditarFecha ? 'true' : 'false' ?>"
data-puede-eliminar-det="<?= $puedeEliminarDet ? 'true' : 'false' ?>"
data-filas='<?= htmlspecialchars(json_encode($filas), ENT_QUOTES, 'UTF-8') ?>'
x-data="{ ...actions(), ...cuentaLitrosFormatoComponent() }">

<style>
@media (min-width: 992px) {
#container .table-responsive { overflow-x: auto; }
}
</style>

<div class="d-flex align-items-center justify-content-end flex-wrap gap-2 mb-4">

    <!-- Botón Finalizar (Primero a la derecha) -->
    <div>
        <button type="button" class="btn btn-success" @click="confirmarFinalizar()" x-show="puedeFinalizar">
            <i class="ti ti-check me-1"></i> Finalizar
        </button>
    </div>

    <!-- Dropdown con las demás acciones (Después del botón Finalizar) -->
    <div class="dropdown" x-show="puedeCrear || puedeEditarFecha">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical fs-5"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item pointer" @click="abrirAgregar()" x-show="puedeCrear">
                    <i class="ti ti-plus me-1"></i> Nueva descarga
                </a>
            </li>
            <li>
                <a class="dropdown-item pointer" @click="abrirFecha()" x-show="puedeEditarFecha">
                    <i class="ti ti-calendar me-1"></i> Editar fecha
                </a>
            </li>
        </ul>
    </div>

</div>


<?php if (!empty($filas)): ?>
<div class="row">
<?php $fecha_tarjeta = $fecha_larga; ?>
<?php foreach ($filas as $fila): ?>
<?php
$tarjetaEditable = $editable;
$tarjetaMostrarAcciones = $estatus === 0;
include __DIR__ . '/_tarjeta.php';
?>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-secondary border-0 text-center text-muted p-5 mt-4 rounded-3" role="alert">
    <h5 class="fw-semibold text-dark mb-2">Sin descargas registradas</h5>
    <p class="fs-5 mb-0">No se encontraron descargas registradas para este formato.</p>
</div>
<?php endif; ?>

<!-- Modal Agregar Descarga -->
<div class="modal fade" id="modalAgregar" tabindex="-1" x-ref="modalAgregar">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-plus me-2"></i>Nueva descarga</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-6 col-md-3">
<label class="form-label">* Hora:</label>
<input type="time" class="form-control" x-model="form.hora">
</div>
<div class="col-6 col-md-3">
<label class="form-label">* Embarque:</label>
<select class="form-select" x-model="form.embarque">
<option value=""></option>
<?php foreach ($embarques as $emb): ?>
<option value="<?= htmlspecialchars($emb, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emb, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-6 col-md-3">
<label class="form-label">* Tanque:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.tanque">
</div>
<div class="col-6 col-md-3">
<label class="form-label">* TAD:</label>
<select class="form-select" x-model="form.tad">
<option value=""></option>
<?php foreach ($tads as $tad): ?>
<option value="<?= htmlspecialchars($tad, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tad, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12" x-show="form.embarque === 'Delivery' || form.embarque === 'Pick Up'">
<label class="form-label">* Nombre del transporte:</label>
<div class="select2-modal-field is-select2-pending" x-ref="wrapTransporteAgregar">
<select class="form-select" x-ref="selTransporteAgregar">
<option value=""></option>
<?php foreach ($transportes as $t): ?>
<option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="col-12 col-md-6">
<label class="form-label">* Producto:</label>
<select class="form-select" x-model="form.producto">
<option value=""></option>
<?php foreach ($productos as $p): ?>
<option value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12 col-md-6">
<label class="form-label">* Unidad:</label>
<div class="select2-modal-field is-select2-pending" x-ref="wrapUnidadAgregar">
<select class="form-select" x-ref="selUnidadAgregar">
<option value=""></option>
<?php foreach ($unidades as $u): ?>
<option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="col-6 col-md-4">
<label class="form-label">* Factura (litros):</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.litros">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Tirilla de descarga neto:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.descarga_neto">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Tirilla de descarga bruto:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.descarga_bruto">
</div>

<div class="col-6 col-md-4">
<label class="form-label">* Cuenta litros a 20° C:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.litros_c">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Venta al momento:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.venta_momento">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Folio de merma:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.folio_merma">
</div>

<div class="col-12">
<label class="form-label">* Imagen:</label>
<input type="file" accept="image/*" class="form-control" x-ref="archivoAgregar">
</div>

<div class="col-12">
<label class="form-label">Comentarios:</label>
<textarea class="form-control" rows="4" x-model="form.comentario"></textarea>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
<button type="button" class="btn btn-success" @click="guardarDescarga()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
<template x-if="guardando">
<span class="spinner-border spinner-border-sm me-1"></span>
</template>
Guardar
</button>
</div>
</div>
</div>
</div>

<!-- Modal Editar Descarga -->
<div class="modal fade" id="modalEditar" tabindex="-1" x-ref="modalEditar">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-pencil me-2"></i>Editar descarga</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-6 col-md-3">
<label class="form-label">* Hora:</label>
<input type="time" class="form-control" x-model="form.hora">
</div>
<div class="col-6 col-md-3">
<label class="form-label">* Embarque:</label>
<select class="form-select" x-model="form.embarque">
<option value=""></option>
<?php foreach ($embarques as $emb): ?>
<option value="<?= htmlspecialchars($emb, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emb, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-6 col-md-3">
<label class="form-label">* Tanque:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.tanque">
</div>
<div class="col-6 col-md-3">
<label class="form-label">* TAD:</label>
<select class="form-select" x-model="form.tad">
<option value=""></option>
<?php foreach ($tads_editar as $tad): ?>
<option value="<?= htmlspecialchars($tad, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tad, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12" x-show="form.embarque === 'Delivery' || form.embarque === 'Pick Up'">
<label class="form-label">* Nombre del transporte:</label>
<div class="select2-modal-field is-select2-pending" x-ref="wrapTransporteEditar">
<select class="form-select" x-ref="selTransporteEditar">
<option value=""></option>
<?php foreach ($transportes as $t): ?>
<option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="col-12 col-md-6">
<label class="form-label">* Producto:</label>
<select class="form-select" x-model="form.producto">
<option value=""></option>
<?php foreach ($productos_editar as $p): ?>
<option value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12 col-md-6">
<label class="form-label">* Unidad:</label>
<div class="select2-modal-field is-select2-pending" x-ref="wrapUnidadEditar">
<select class="form-select" x-ref="selUnidadEditar">
<option value=""></option>
<?php foreach ($unidades as $u): ?>
<option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>
</div>

<div class="col-6 col-md-4">
<label class="form-label">* Factura (litros):</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.litros">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Tirilla de descarga neto:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.descarga_neto">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Tirilla de descarga bruto:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.descarga_bruto">
</div>

<div class="col-6 col-md-4">
<label class="form-label">* Cuenta litros a 20° C:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.litros_c">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Venta al momento:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.venta_momento">
</div>
<div class="col-6 col-md-4">
<label class="form-label">* Folio de merma:</label>
<input type="number" min="0" step="any" class="form-control" x-model="form.folio_merma">
</div>

<div class="col-12" x-show="form.archivo_actual">
<label class="form-label">Imagen actual:</label>
<div class="border rounded p-2 text-center">
<img :src="form.archivo_url" alt="Imagen actual" class="img-fluid" style="max-height: 180px;">
</div>
</div>

<div class="col-12">
<label class="form-label">Imagen <small>(dejar vacío para conservar la actual)</small>:</label>
<input type="file" accept="image/*" class="form-control" x-ref="archivoEditar">
</div>

<div class="col-12">
<label class="form-label">Comentarios:</label>
<textarea class="form-control" rows="4" x-model="form.comentario"></textarea>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
<button type="button" class="btn btn-success" @click="guardarDescarga()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
<template x-if="guardando">
<span class="spinner-border spinner-border-sm me-1"></span>
</template>
Guardar
</button>
</div>
</div>
</div>
</div>

<!-- Modal Editar Fecha -->
<div class="modal fade" id="modalFecha" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-calendar me-2"></i>Editar fecha</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<label class="form-label">Fecha:</label>
<input type="date" class="form-control" x-model="fechaForm.fecha">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
<button type="button" class="btn btn-success" @click="guardarFecha()" :disabled="guardando">
<template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
<template x-if="guardando">
<span class="spinner-border spinner-border-sm me-1"></span>
</template>
Guardar
</button>
</div>
</div>
</div>
</div>

<!-- Modal Visor de Imagen -->
<div class="modal fade" id="modalVisorImagen" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-photo me-2"></i>Imagen de descarga</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body text-center">
<img id="imgVisor" src="" alt="Imagen de descarga" class="img-fluid" style="max-height: 70vh;">
</div>
</div>
</div>
</div>

</div>