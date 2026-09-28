<div id="container" class="mt-4 mb-5"
data-pedido='<?= htmlspecialchars(json_encode($pedido, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-es-encargado="<?= $esEncargado ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-articulos-limpieza"
x-data="{ ...actions(), ...pedidoLimpiezaPedidoComponent() }">

<div class="row">

<!---------- BOTONES FINALIZAR / ENTREGAR ---------->
<div class="col-12 mb-3">
<div class="d-flex justify-content-end gap-2">
<template x-if="pedidoActual.status === 0" x-cloak>
<button type="button" class="btn btn-success" @click="finalizarPedido()" :disabled="finalizando">
<i class="ti ti-check me-1"></i> Finalizar
</button>
</template>
<template x-if="puedeEditar && pedidoActual.status === 2" x-cloak>
<button type="button" class="btn btn-primary" @click="entregarPedido()" :disabled="entregando">
<i class="ti ti-package me-1"></i> Entregar
</button>
</template>
</div>
</div>

<!---------- DATOS ---------->
<div class="col-12 mb-4">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-spray me-1"></i> DETALLE DEL PEDIDO</h5>
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

<!---------- ESTATUS ---------->
<div class="col-12 mb-4" x-show="pedidoActual.status > 0" x-cloak>
<div class="card">
<div class="card-body d-flex align-items-center gap-2 flex-wrap">
<span class="form-label mb-0">Estatus:</span>
<span class="badge fs-6" :class="statusBadgeClass(pedidoActual.status)" x-text="pedidoActual.status_label"></span>
<span class="text-secondary small ms-2" x-show="pedidoActual.status === 1">Pedido finalizado, en espera de Vo.Bo.</span>
<span class="text-secondary small ms-2" x-show="pedidoActual.status === 2">Pedido firmado, pendiente de entrega.</span>
<span class="text-secondary small ms-2" x-show="pedidoActual.status === 3">Pedido entregado e inventario actualizado.</span>
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
<th class="text-center align-middle" width="48px" x-show="pedidoActual.status === 0"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="it in pedidoActual.detalle" :key="it.id">
<template x-if="pedidoActual.status === 0">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.unidad"></td>
<td class="text-center align-middle" x-text="it.producto"></td>
<td class="p-0">
<input type="number" min="1" class="form-control form-control border-0 p-3 text-center align-middle" :value="it.piezas" @change="editarPiezas(it.id, $event.target.value)">
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
</tr>
</template>
</template>
<tr x-show="!pedidoActual.detalle.length">
<td colspan="5" class="text-center text-primary">No se encontro informacion</td>
</tr>
</tbody>
<tfoot x-show="pedidoActual.detalle.length">
<tr class="table-dark">
<td colspan="3" class="text-center align-middle fw-semibold text-white">Total de piezas</td>
<td class="text-center align-middle fw-semibold text-white" x-text="pedidoActual.total_piezas"></td>
<td x-show="pedidoActual.status === 0"></td>
</tr>
</tfoot>
</table>
</div>
</div>
</div>
</div>

<!---------- FIRMAS REGISTRADAS (status > 0) ---------->
<div class="col-12 mt-4" x-show="pedidoActual.status > 0" x-cloak>
<div class="row g-3">

<div class="col-12">
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
<h4 class="modal-title text-white"><i class="ti ti-spray me-2"></i> Nuevo producto al pedido</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="row g-3">

<div class="col-12">
<div class="alert alert-info border-0 mb-0 d-flex align-items-start gap-2">
<i class="ti ti-info-circle fs-4 mt-1"></i>
<div class="small">
<strong>Nota:</strong> En caso de que no se encuentre el producto en el listado, marque la opción <strong>"Otro producto"</strong> para poder agregar su nombre y su unidad.
</div>
</div>
</div>

<div class="col-12 mt-4">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="checkOtroProducto" x-model="usarOtroProducto" @change="syncUsarOtroProducto()">
<label class="form-label" for="checkOtroProducto">Otro producto</label>
</div>
</div>

<div class="col-12" x-show="!usarOtroProducto">
<div class="mb-1 form-label">* Producto:</div>
<div class="select2-modal-field" x-ref="productoPedidoWrapper">
<select class="form-select" x-ref="productoPedidoSelect" data-width="100%">
<option value="">Selecciona un producto...</option>
</select>
</div>
</div>

<template x-if="usarOtroProducto" x-cloak>
<div class="col-12">
<div class="row g-3">
<div class="col-12">
<div class="mb-1 form-label">* Nombre del producto:</div>
<input type="text" class="form-control" x-model="nuevoItem.otro_producto" placeholder="Escribe el nombre del producto">
</div>
<div class="col-12">
<div class="mb-1 form-label">* Unidad:</div>
<select class="form-select" x-model="nuevoItem.unidad">
<option value="">Selecciona una opción...</option>
<option value="1 KG">1 KG</option>
<option value="BULTO">BULTO</option>
<option value="CAJA">CAJA</option>
<option value="CUB">CUB</option>
<option value="PZA">PZA</option>
<option value="ROLLO">ROLLO</option>
</select>
</div>
</div>
</div>
</template>

<div class="col-12">
<div class="mb-1 form-label">* Piezas:</div>
<input type="number" min="1" class="form-control" x-model.number="nuevoItem.piezas">
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