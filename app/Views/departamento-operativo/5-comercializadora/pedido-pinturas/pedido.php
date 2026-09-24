<div id="container" class="mt-4 mb-5"
data-pedido='<?= htmlspecialchars(json_encode($pedido, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-es-encargado="<?= $esEncargado ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-pinturas"
x-data="{ ...actions(), ...pedidoPinturasPedidoComponent() }">

<div class="row">

<!---------- BOTÓN FINALIZAR (solo status 0) ---------->
<div class="col-12 mb-3" x-show="pedidoActual.status === 0" x-cloak>
<div class="d-flex justify-content-end">
<button type="button" class="btn btn-success" @click="finalizarPedido()" :disabled="finalizando">
<i class="ti ti-check me-1"></i> Finalizar
</button>
</div>
</div>

<!---------- DATOS ---------->
<div class="col-12 mb-4">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-paint me-1"></i> DETALLE DEL PEDIDO</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-3"><div class="form-label mb-1">Folio:</div><div class="" x-text="pedidoActual.id ? '# 00' + pedidoActual.id : '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Fecha y hora:</div><div class="" x-text="pedidoActual.fecha_hora || '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Nombre del personal:</div><div class="" x-text="pedidoActual.personal || '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Puesto:</div><div class="" x-text="pedidoActual.puesto || '—'"></div></div>
</div>
</div>
</div>
</div>

<!---------- TABLA ITEMS ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-shopping-cart me-1"></i> PRODUCTOS</h5>

<div class="d-flex justify-content-end">
<template x-if="puedeEditar && pedidoActual.status === 0" x-cloak>
<button type="button" class="btn bg-success text-white" @click="modalAgregarProducto()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</template>
</div>
</div>

<div class="card-body p-0">
<div class="table-responsive mb-0">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="96px">#</th>
<th class="text-center align-middle">Unidad</th>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Piezas</th>
<th class="text-center align-middle">¿Para qué?</th>
<th class="text-center align-middle" width="48px" x-show="pedidoActual.status === 0"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="it in pedidoActual.detalle" :key="it.id">
<template x-if="pedidoActual.status === 0">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.unidad"></td>
<td class="text-center align-middle"x-text="it.producto"></td>
<td class="p-0">
<input type="number" min="1" class="form-control form-control border-0 p-3 text-center align-middle" :value="it.piezas" @change="editarPiezas(it.id, $event.target.value)">
</td>
<td class="p-0">
<input type="text" class="form-control form-control border-0 p-3 text-center align-middle" :value="it.para_que" @change="editarDetalle(it.id, $event.target.value)" placeholder="¿Para qué?">
</td>
<td class="text-center">
<i class="ti ti-trash text-danger pointer fs-6" @click="eliminarItem(it.id)"></i>
</td>
</tr>
</template>
<template x-if="pedidoActual.status > 0">
<tr>
<td class="text-center" x-text="it.num"></td>
<td x-text="it.unidad"></td>
<td x-text="it.producto"></td>
<td class="text-center" x-text="it.piezas"></td>
<td x-text="it.para_que || '—'"></td>
</tr>
</template>
</template>
<tr x-show="!pedidoActual.detalle.length">
<td colspan="6" class="text-center text-primary">No se encontro informacion</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!---------- OBSERVACIONES (solo status 0) ---------->
<div class="col-12" x-show="pedidoActual.status === 0" x-cloak>
<div class="card">
<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i> OBSERVACIONES</h5>
</div>
<div class="card-body p-0">
<textarea class="form-control border-0 p-3" rows="5" x-model="observaciones" placeholder="Ingresa aqui las observaciones del pedido..."></textarea>
</div>
</div>
</div>

<!---------- FIRMA ENCARGADO (solo status 0) ---------->
<div class="col-md-6" x-show="pedidoActual.status === 0" x-cloak>
<div class="card border-0 bg-white">
<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
<i class="ti ti-signature fs-6"></i>
</div>
<div class="ms-3">
<h5 class="mb-0 text-white">FIRMA DEL ENCARGADO</h5>
</div>
</div>
</div>
<div class="card-body p-3 ">
<div id="signature-pad" class="signature-pad-wrapper" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
<div class="signature-pad--body">
<canvas id="signature-pad-pedido" style="width:100%; height:250px; display: block;"></canvas>
</div>
</div>
</div>
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;" @click="limpiarFirma()">
<i class="ti ti-eraser me-1"></i> Limpiar firma
</button>
</div>
</div>

<!---------- FIRMAS REGISTRADAS (status > 0) ---------->
<div class="col-12 mt-4" x-show="pedidoActual.status > 0" x-cloak>
<div class="row g-3">

<div class="col-md-6">
<template x-if="pedidoActual.firmas && pedidoActual.firmas.A">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="pedidoActual.firmas.A.tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<template x-if="pedidoActual.firmas.A.firma_img_url">
<img :src="pedidoActual.firmas.A.firma_img_url" class="img-fluid" style="max-height:90px;object-fit:contain;">
</template>
<template x-if="!pedidoActual.firmas.A.firma_img_url">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark" x-text="pedidoActual.firmas.A.firma_texto || ''"></small>
</template>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="pedidoActual.firmas.A.usuario_nombre"></h6>
<small class="text-muted" x-text="pedidoActual.firmas.A.fecha || ''"></small>
</div>
</div>
</template>
<template x-if="!pedidoActual.firmas || !pedidoActual.firmas.A">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">ELABORÓ / ENCARGADO</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">Sin firma registrada</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>
</div>

<div class="col-md-6">
<template x-if="pedidoActual.firmas && pedidoActual.firmas.B">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="pedidoActual.firmas.B.tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark" x-text="pedidoActual.firmas.B.firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="pedidoActual.firmas.B.usuario_nombre"></h6>
<small class="text-muted" x-text="pedidoActual.firmas.B.fecha || ''"></small>
</div>
</div>
</template>
<template x-if="!pedidoActual.firmas || !pedidoActual.firmas.B">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">VO.BO.</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Vo.Bo.!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>
</div>

</div>
</div>

</div>

<!---------- MODAL AGREGAR PRODUCTO (solo status 0) ---------->
<div class="modal fade" id="modalAgregarProductoPedido" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-paint me-2"></i> Nuevo producto al pedido</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="row g-3">

<div class="col-md-6">
<div class="mb-1 form-label">* Producto:</div>
<div class="select2-modal-field" x-ref="productoPedidoWrapper">
<select class="form-select" x-ref="productoPedidoSelect" data-width="100%">
<option value="">Selecciona un producto</option>
</select>
</div>
</div>

<div class="col-md-6">
<div class="mb-1 form-label">* Otro:</div>
<input type="text" class="form-control" x-model="nuevoItem.otro_producto" @input="syncOtroProducto()" x-ref="otroProductoInput" :disabled="productoSeleccionado" placeholder="Producto libre">
</div>

<div class="col-12">
<div class="mb-1 form-label">* Piezas:</div>
<input type="number" min="1" class="form-control" x-model.number="nuevoItem.piezas">
</div>

<div class="col-12">
<div class="mb-1 form-label">¿Para que?</div>
<textarea class="form-control" rows="2" x-model="nuevoItem.para_que" placeholder="Para qué se utilizará"></textarea>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="agregarProducto()" :disabled="guardandoProducto"><i class="ti ti-check me-1"></i> Guardar</button>
</div>
</div>
</div>
</div>

</div>