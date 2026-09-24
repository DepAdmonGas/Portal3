<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-module-station-key="pedido-pinturas"
x-data="{ ...actions(), ...pedidoPinturasReporteComponent() }">

<div class="row">

<div class="col-12 mb-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">

<div class="d-flex align-items-center">
<a class="btn bg-danger-subtle text-danger" href="/departamento-operativo/comercializadora/pedido-pinturas">
<i class="ti ti-arrow-left me-1"></i> Regresar
</a>
</div>

<div class="d-flex align-items-center">
<div class="ms-auto">
<button type="button" class="btn bg-primary-subtle text-primary" @click="nuevoReporte()" x-show="hayContexto && puedeCrear">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>

</div>
</div>

<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-reportes" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<!---------- MODAL DETALLE REPORTE ---------->
<div class="modal fade" id="modalDetalleReporte" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-eye "></i> Detalle del reporte de pinturas (<span x-text="reporteActual.id ? '#00' + reporteActual.id : ''"></span>)</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">

<div class="row g-3">
    <div class="col-12">
        <div class="form-label mb-1">Fecha y hora:</div>
        <div x-text="reporteActual.fecha_hora"></div>
    </div>

    <div class="col-12">
<div class="mb-1 form-label">Detalle:</div>
        <div x-text="reporteActual.detalle || 'Sin detalle disponible'"></div>

    </div>

<div class="col-12">
<div class="form-label mb-1">Pintura o complementos:</div>

<div class="table-responsive">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Piezas</th>
<th class="text-center align-middle">Observaciones</th>
</tr>
</thead>
<tbody>
<template x-for="(it, i) in reporteActual.detalle_items" :key="it.id">
<tr>
<td class="text-center" x-text="i + 1"></td>
<td x-text="it.producto"></td>
<td class="text-center" x-text="it.piezas"></td>
<td x-text="it.observaciones || '—'"></td>
</tr>
</template>
<tr x-show="!reporteActual.detalle_items || !reporteActual.detalle_items.length">
<td colspan="4" class="text-center text-primary">No se encontro informacion</td>
</tr>
</tbody>
</table>
</div>
</div>

</div>

</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button>
</div>
</div>
</div>
</div>

</div>