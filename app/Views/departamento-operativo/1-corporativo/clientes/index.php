<div id="container" class="mt-4 mb-4"
data-id-dia="<?= $idDia ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
x-data="clientesComponent()">

<div class="text-center py-5" x-show="loading">
<div class="spinner-border text-primary" role="status"></div>
<p class="text-muted">Cargando clientes...</p>
</div>

<div class="row" x-show="!loading">

<div class="col-12 mb-4">
<button x-show="puedeAgregar" type="button" class="btn btn-primary float-end" @click="abrirModalAgregar()">
<i class="ti ti-plus"></i> Agregar
</button>
<button type="button" class="btn btn-primary float-end me-3" @click="abrirListaClientes()">
<i class="ti ti-users"></i> Lista de Clientes
</button>
</div>

<div class="col-12">
<div class="datatables">
<div class="table-responsive" style="overflow-x: auto; overflow-y: hidden;">
<table id="tablaClientes" class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%">
<tbody></tbody>
</table>
</div>
</div>
</div>

<div class="row justify-content-end">
<div class="col-lg-6 col-md-12 col-sm-12 mt-4">

<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-calculator me-2"></i>TOTALES</h5>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Tipo</th>
<th class="text-center align-middle">Consumo</th>
<th class="text-center align-middle">Pago</th>
</tr>
</thead>
<tbody>
<tr>
<th class="text-center text-success">Débito</th>
<td class="text-end" x-text="'$ ' + formatNum(resumen.dc)"></td>
<td class="text-end" x-text="'$ ' + formatNum(resumen.dp)"></td>
</tr>
<tr>
<th class="text-center text-primary">Crédito</th>
<td class="text-end" x-text="'$ ' + formatNum(resumen.cc)"></td>
<td class="text-end" x-text="'$ ' + formatNum(resumen.cp)"></td>
</tr>
<tr>
<th class="text-center">Total</th>
<td class="text-end"><strong x-text="'$ ' + formatNum(resumen.total_consumo)"></strong></td>
<td class="text-end"><strong x-text="'$ ' + formatNum(resumen.total_pago)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>

</div>
</div>




</div>

<div class="modal fade" id="modalAgregar" tabindex="-1" x-ref="modalAgregar">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Consumos y Pagos</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-1">
<h6>* Nombre del cliente:</h6> 
<div class="select2-modal-field is-select2-pending" x-ref="clienteWrapper">
<select id="selectCliente" x-ref="selectCliente" data-width="100%">
<option value="">Selecciona un cliente...</option>
<template x-for="cliente in clientes" :key="cliente.id">
<option :value="cliente.id" x-text="cliente.cliente + ' (' + cliente.cuenta + ')'"></option>
</template>
</select>
</div>
</div>

<div class="mt-4 mb-1">
<h6>* Total:</h6>
<input type="number" class="form-control" min="0" step="0.01" x-ref="totalInput" x-model="modalTotal">
</div>

<div class="mb-1 mt-4">
<h6>* Consumo o Pago:</h6>
<select class="form-select" x-ref="tipoSelect" x-model="modalTipo">
<option value="">Selecciona una opción:</option>
<option value="Consumo">Consumo</option>
<option value="Pago">Pago</option>
</select>
</div>

<div x-cloak x-show="modalTipo === 'Pago'">
<hr>
<div class="mb-1">
* Forma de pago
</div>
<select class="form-select" x-ref="formaPagoSelect" x-model="modalFormaPago">
<option value="">Forma de pago</option>
<option value="Efectivo">Efectivo</option>
<option value="Tarjeta">Tarjeta</option>
<option value="Transferencia">Transferencia</option>
<option value="Cheque">Cheque</option>
<option value="Monederos">Monederos</option>
</select>
<div x-cloak x-show="modalFormaPago === 'Tarjeta' || modalFormaPago === 'Transferencia'">
<div class="mb-1 mt-4">* Voucher</div>
<input class="form-control" type="file" id="Comprobante" @change="modalComprobante = $event.target.files[0] || null">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-labeled2 btn-success" @click="guardar()" :disabled="guardando">
<span x-show="!guardando">Guardar</span>
<span x-show="guardando"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalListaClientes" tabindex="-1" x-ref="modalListaClientes">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Lista de Clientes</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<template x-if="listaLoading">
<div class="text-center py-3">
<div class="spinner-border text-primary spinner-border-sm" role="status"></div>
<p class="mt-1 text-muted small">Cargando clientes...</p>
</div>
</template>
<template x-if="!listaLoading">
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" colspan="3">Crédito</th>
</tr>
<tr>
<th class="text-start align-middle">Cuenta</th>
<th class="text-start align-middle">Cliente</th>
<th class="text-center align-middle">RFC</th>
</tr>
</thead>
<tbody>
<template x-for="c in listaCreditos" :key="c.id">
<tr>
<th class="align-middle fw-normal" x-text="c.cuenta"></th>
<td class="align-middle" x-text="c.cliente"></td>
<td class="align-middle text-center" x-text="c.rfc || 'N/A'"></td>
</tr>
</template>
<template x-if="listaCreditos.length === 0">
<tr>
<td colspan="3" class="text-center text-muted py-2">Sin clientes de crédito</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" colspan="2">Débito</th>
</tr>
<tr>
<th class="text-start align-middle">Cuenta</th>
<th class="text-start align-middle">Cliente</th>
</tr>
</thead>
<tbody>
<template x-for="c in listaDebitos" :key="c.id">
<tr>
<th class="align-middle fw-normal" x-text="c.cuenta"></th>
<td class="align-middle" x-text="c.cliente"></td>
</tr>
</template>
<template x-if="listaDebitos.length === 0">
<tr>
<td colspan="2" class="text-center text-muted py-2">Sin clientes de débito</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</template>
</div>
</div>
</div>
</div>

</div>
