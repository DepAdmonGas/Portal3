<div class="row mb-5" x-data="listaAceitesComponent()">
<div class="col-12 col-md-auto ms-md-auto d-grid mt-3 mb-3">
<button type="button" class="btn bg-primary-subtle text-primary" @click="nuevoAceite()" :disabled="creando">
<i class="ti ti-plus me-1"></i>Nuevo
</button>
</div>

<div class="col-12 mt-2">
<div class="table-responsive overflow-x-auto overflow-hidden">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle lista-aceites-seleccionable portal-table portal-table--selectable">
<thead>
<tr>
<th class="text-center align-middle" style="width: 96px;">#</th>
<th class="align-middle">Concepto</th>
<th class="text-center align-middle" style="width: 96px;">Pzas / cajas</th>
<th class="text-center align-middle" >Precio unitario</th>
<?php if ($esDireccionOperaciones): ?>
<th class="text-center align-middle" style="width: 48px;"><i class="ti ti-trash text-danger fs-6"></i></th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<template x-for="(item, index) in items" :key="item.id">
<tr class="pointer" :class="{ selected: selectedRowId === item.id }" @click="seleccionarFila(item.id)">
<td class="p-0 align-middle text-center" x-text="item.id_aceite"></td>
<td class="p-0 align-middle">
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
class="form-control form-control p-3 border-primary">
</template>
</td>
<td class="p-0 align-middle">
<input type="number" min="0" step="1"
x-model="tempValues[item.id].piezas"
@change="guardar(item.id, 'piezas', tempValues[item.id].piezas)"
class="form-control form-control p-3 text-center border-0">
</td>
<td class="p-0 align-middle">
<input type="number" min="0" step="0.01"
x-model="tempValues[item.id].precio"
@change="guardar(item.id, 'precio', tempValues[item.id].precio)"
class="form-control form-control p-3 text-end border-0">
</td>
<?php if ($esDireccionOperaciones): ?>
<td class="p-0 align-middle text-center">
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
    <td :colspan="<?= $esDireccionOperaciones ? 5 : 4 ?>" class="text-center text-primary align-middle py-4">
        No se encontró información
    </td>
</tr>
</tbody>
</table>
</div>

<div x-show="creando" class="text-center py-3">
<div class="spinner-border spinner-border text-primary me-2" role="status"></div>
<span class="text-muted">Agregando nuevo aceite...</span>
</div>

<div id="finalDePagina"></div>
</div>
</div>
</div>
