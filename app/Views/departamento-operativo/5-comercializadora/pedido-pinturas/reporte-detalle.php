<div id="container" class="mt-4 mb-5"
data-reporte='<?= htmlspecialchars(json_encode($reporte, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-pinturas"
x-data="{ ...actions(), ...pedidoPinturasReporteDetalleComponent() }">

<div class="row">

<template x-if="puedeEditar && reporteActual.status === 0 && reporteActual.detalle_items && reporteActual.detalle_items.length" x-cloak>
<div class="col-12 mb-3">
<div class="d-flex justify-content-end">
<button type="button" class="btn btn-success" @click="aprobarReporte()" :disabled="guardandoReporte">
<i class="ti ti-check me-1"></i> Finalizar
</button>
</div>
</div>
</template>

<!---------- FORM DETALLE (solo status 0 con permiso) ---------->
<div class="col-12" x-show="puedeEditar && reporteActual.status === 0" x-cloak>
<div class="card mb-3">
<div class="card-body">
<div class="row g-3">
<div class="col-md-6">
<div class="mb-1 form-label">* Fecha:</div>
<input type="date" class="form-control" x-model="reporteForm.fecha" @input="programarAutosave()">
</div>
<div class="col-md-6">
<div class="mb-1 form-label">* Hora:</div>
<input type="time" class="form-control" x-model="reporteForm.hora" @input="programarAutosave()">
</div>
<div class="col-12">
<div class="mb-1 form-label">Detalle:</div>
<textarea class="form-control" rows="4" x-model="reporteForm.detalle" @input="programarAutosave()"></textarea>
</div>
</div>
</div>
</div>
</div>

<!---------- FORM DETALLE SOLO LECTURA (finalizado o sin permiso) ---------->
<div class="col-12" x-show="!puedeEditar || reporteActual.status !== 0" x-cloak>
<div class="card mb-3">
<div class="card-body">
<div class="row align-items-center mb-3">
<div class="col-md-4"><div class="text-secondary small">FECHA</div><div class="fw-semibold" x-text="reporteActual.fecha || '—'"></div></div>
<div class="col-md-4"><div class="text-secondary small">HORA</div><div class="fw-semibold" x-text="reporteActual.hora || '—'"></div></div>
<div class="col-md-4"><div class="text-secondary small">DETALLE</div><div class="fw-semibold" x-text="reporteActual.detalle || '—'"></div></div>
</div>
<div class="row" x-show="reporteActual.status === 1">
<div class="col-12">
<span class="badge" :class="reporteStatusBadgeClass(1)"><i class="ti ti-check me-1"></i><span x-text="reporteActual.status_label || 'Finalizado'"></span></span>
<span class="text-secondary small ms-2">Reporte finalizado, no es posible modificarlo.</span>
</div>
</div>
</div>
</div>
</div>

<!---------- TABLA ITEMS ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-paint me-1"></i> Pintura o complementos</h5>

<template x-if="puedeEditar && reporteActual.status === 0">
<button type="button" class="btn bg-success text-white" @click="modalAgregarProducto()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</template>
</div>

<div class="card-body p-0">
<div class="table-responsive mb-0">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Piezas</th>
<th class="text-center align-middle">Observaciones</th>
<th class="text-center align-middle" width="48px" x-show="reporteActual.status === 0">
    <i class="ti ti-trash text-danger fs-6"></i>
</th>
</tr>
</thead>
<tbody>
<template x-for="(it, i) in reporteActual.detalle_items" :key="it.id">
<tr>
<td class="text-center align-middle" x-text="i + 1"></td>
<td class="text-center align-middle" x-text="it.producto"></td>
<td class="text-center align-middle" x-text="it.piezas"></td>
<td class="text-center align-middle" x-text="it.observaciones || 'Sin observaciones'"></td>
<td class="text-center align-middle" x-show="reporteActual.status === 0">
<i class="ti ti-trash text-danger pointer fs-6" @click="eliminarProductoReporte(it)"></i>
</td>
</tr>
</template>
<tr x-show="!reporteActual.detalle_items || !reporteActual.detalle_items.length">
<td colspan="5" class="text-center text-primary">No se encontró información</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!---------- MODAL AGREGAR PRODUCTO (solo status 0) ---------->
<div class="modal fade" id="modalAgregarProductoReporte" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-paint me-2"></i> Nueva pintura o complemento</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-12">
<div class="mb-1 form-label">* Pintura o complemento:</div>
<div class="select2-modal-field" x-ref="productoWrapper">
<select class="form-select" x-ref="productoSelect" data-width="100%">
<option value="0">Selecciona una opción...</option>
</select>
</div>
</div>
<div class="col-12">
<div class="mb-1 form-label">* Piezas utilizadas:</div>
<input type="number" min="1" class="form-control" x-model.number="reporteItem.unidad">
</div>
<div class="col-12">
<div class="mb-1 form-label">Observaciones:</div>
<textarea class="form-control" rows="4" x-model="reporteItem.observaciones"></textarea>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="agregarProductoReporte()" :disabled="guardandoReporteItem"><i class="ti ti-check me-1"></i> Guardar</button>
</div>
</div>
</div>
</div>

</div>