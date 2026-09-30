<div id="container" class="pb-4" 
     data-year-mes-template="<?= htmlspecialchars($yearMesTemplate ?? '/departamento-operativo/almacen/orden-compra/{year}/{mes}') ?>"
     x-data="{ ...actions(), ...ordenCompraComponent(<?= $idYear ?>, <?= $idMes ?>) }">

<div class="row mt-3">

<!-- HEADER Y ACCIONES -->
<div class="col-12 mb-3">
<div class="float-end">
<button type="button" class="btn bg-primary-subtle text-primary" @click="nuevaOrden()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>

<!-- TABLA CARD HEADER AZUL -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-orden-compra" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>
</div>