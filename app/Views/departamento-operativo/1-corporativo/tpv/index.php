<div id="container" class="mt-4 mb-4"
data-id-dia="<?= $idDia ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-estado="<?= $estado ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-id-estacion="<?= $idEstacion ?>"
x-data="tpvComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando cierre de lote...</p>
</div>
</template>

<template x-if="!loading">
<div class="row">
<template x-for="empresa in empresasList" :key="empresa">
<template x-if="cierres[empresa]">
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-building me-2"></i><span x-text="empresa"></span></h5>
<template x-if="!multiestacion && puedeEditar && !finalizado">
<button type="button" class="btn btn-success btn-sm" @click="agregarCierre(empresa)" :disabled="saving">
<i class="ti ti-plus me-1"></i>Agregar
</button>
</template>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">No. Cierre de lote</th>
<th class="text-center align-middle">Importe</th>
<th class="text-center align-middle">No. De tickets</th>
<th class="text-center align-middle"></th>
</tr>
</thead>
<tbody>
<template x-for="(item, idx) in cierres[empresa]" :key="item.id">
<tr>
<td class="text-center align-middle p-0">
<template x-if="!multiestacion && puedeEditar && !finalizado">
                    <input type="text"
                        class="border-0 p-3 text-center w-100 bg-transparent"
                        style="min-width: 60px;"
                        x-model="item.no_cierre_lote"
                        @change="editarCierre(item.id, 'no_cierre_lote', $event.target.value)">
                        </template>

<template x-if="!puedeEditar || multiestacion || finalizado">
<span x-text="item.no_cierre_lote || ''"></span>
</template>
</td>
<td class="align-middle text-end p-0">
<template x-if="!multiestacion && puedeEditar && !finalizado">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
                    <input type="number"
                        min="0"
                        step="any"
                        class="border-0 p-3 text-end w-100 bg-transparent"
                        style="padding-left: 20px !important; min-width: 80px;"
                        x-model="item.importe"
                        @change="editarCierre(item.id, 'importe', $event.target.value)">
</div>
</template>

<template x-if="!puedeEditar || multiestacion || finalizado">
<span x-text="'$ ' + formatNum(item.importe)"></span>
</template>
</td>
<td class="align-middle text-center p-0">
<template x-if="!multiestacion && puedeEditar && !finalizado">
                    <input type="number"
                        min="0"
                        class="border-0 p-3 text-center w-100 bg-transparent"
                        style="min-width: 60px;"
                        x-model="item.ticktes"
                        @change="editarCierre(item.id, 'ticktes', $event.target.value)">
</template>

<template x-if="!puedeEditar || multiestacion || finalizado">
<span x-text="formatNum(item.ticktes)"></span>
</template>
</td>
<td class="align-middle text-center" style="width: 40px;">
<i x-show="!multiestacion && !finalizado && item.estado == 0"
class="ti ti-circle-check text-success pointer fs-6"
@click="togglePendiente(item.id, empresa, 1)"
title="Marcar como pendiente"></i>
<i x-show="!multiestacion && !finalizado && item.estado == 1"
class="ti ti-circle-x text-danger pointer fs-6"
@click="togglePendiente(item.id, empresa, 0)"
title="Activar"></i>
<i x-show="(multiestacion || finalizado) && item.estado == 0"
class="ti ti-circle-check text-success fs-6" title="Activo"></i>
<i x-show="(multiestacion || finalizado) && item.estado == 1"
class="ti ti-circle-x text-danger fs-6" title="Pendiente"></i>
</td>
</tr>
</template>
<tr>
<th class="align-middle text-center">TOTAL</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesEmpresa(empresa).total_importe)"></strong></td>
<td class="align-middle text-center"><strong x-text="totalesEmpresa(empresa).total_ticket"></strong></td>
<td></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</template>
</template>
</div>
</template>

</div>
