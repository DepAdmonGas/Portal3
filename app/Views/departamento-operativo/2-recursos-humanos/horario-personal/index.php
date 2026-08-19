<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-nombre-puesto="<?= $nombrePuesto ?>"
data-module-station-key="horario-personal"
x-data="{ ...actions(), ...horarioPersonalComponent() }">

<div class="row">

<template x-if="puedeDescargar">
<div class="col-12 mb-4">
<a href="/departamento-operativo/recursos-humanos/horario-personal/pdf" target="_blank" class="btn bg-primary-subtle text-primary float-end">
<i class="ti ti-file-text me-1"></i> Descargar PDF
</a>
</div>
</template>

<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-horario-personal" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>
</div>
