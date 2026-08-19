<div class="row mb-5" x-data="listaAceitesComponent()">
<div class="col-12">
<button type="button" class="btn btn-primary float-end " @click="nuevoAceite()" :disabled="creando">
<i class="ti ti-plus me-1"></i>Nuevo
</button>
</div>

<div class="col-12 mt-3">
<div class="table-responsive overflow-x-auto overflow-hidden">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" style="width: 80px;">ID</th>
<th class="align-middle">CONCEPTO</th>
<th class="text-center align-middle" style="width: 120px;">PZAS CAJAS</th>
<th class="text-center align-middle" style="width: 160px;">PRECIO UNITARIO</th>
<?php if ($esDireccionOperaciones): ?>
<th class="text-center align-middle" style="width: 60px;"><i class="ti ti-trash text-danger fs-6"></i></th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<template x-for="(item, index) in items" :key="item.id">
<tr>
<td class="align-middle p-1 text-center" x-text="item.id_aceite"></td>
<td class="align-middle p-1">
<template x-if="editandoConcepto !== item.id">
<span @dblclick="editarConcepto(item.id)"
class="d-block px-2 py-1 rounded cursor-pointer"
:class="{ 'text-muted fst-italic': !item.concepto }"
x-text="item.concepto || 'Ingresa aquí el concepto...'">
</span>
</template>
<template x-if="editandoConcepto === item.id">
<input type="text"
x-model="tempValues[item.id].concepto"
placeholder="Ingresa aquí el concepto..."
@keyup.enter="guardarConcepto(item.id)"
@blur="guardarConcepto(item.id)"
@keyup.escape="cancelarEdicionConcepto(item.id)"
x-ref="conceptoInput"
class="form-control form-control-sm border-primary">
</template>
</td>
<td class="align-middle p-1">
<input type="number" min="0" step="1"
x-model="tempValues[item.id].piezas"
@change="guardar(item.id, 'piezas', tempValues[item.id].piezas)"
class="form-control form-control-sm text-center border-0">
</td>
<td class="align-middle p-1">
<input type="number" min="0" step="0.01"
x-model="tempValues[item.id].precio"
@change="guardar(item.id, 'precio', tempValues[item.id].precio)"
class="form-control form-control-sm text-end border-0">
</td>
<?php if ($esDireccionOperaciones): ?>
<td class="align-middle p-1 text-center">
<span x-data="actions()">
<i class="ti ti-trash text-danger fs-6 pointer"
@click="deleteAction({url: '/departamento-operativo/corporativo/lista-aceites/eliminar', id: item.id, name: item.concepto || 'ID ' + item.id_aceite, table: null}).then(r => r?.success && eliminarFila(item.id))">
</i>
</span>
</td>
<?php endif; ?>
</tr>
</template>
<tr x-show="items.length === 0">
<td colspan="<?= $esDireccionOperaciones ? 5 : 4 ?>" class="text-center text-primary">
No se encontro información
</td>
</tr>
</tbody>
</table>
</div>

<div x-show="creando" class="text-center py-3">
<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
<span class="text-muted">Agregando nuevo aceite...</span>
</div>

<div id="finalDePagina"></div>
</div>
</div>
</div>
