<?php
/** @var \App\Models\Sgm\CalibracionEquipo $calibracion */
?>
<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= $estacionId ?? '' ?>"
class="pb-4"
x-data="{ ...actions(), ...calibracionSondas() }">
<script type="application/json" id="calibracion-data">
<?= json_encode(
$calibracion,
JSON_UNESCAPED_UNICODE |
JSON_UNESCAPED_SLASHES |
JSON_HEX_TAG |
JSON_HEX_AMP |
JSON_HEX_APOS |
JSON_HEX_QUOT
) ?>
</script>



<template x-if="calibracion.estado == 0">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3 mt-3">
                      
<button
type="button"
class="btn btn-success"
@click="finalizar(
'Sondas de medición'
)">

<i class="ti ti-check"></i> Finalizar

</button>
                    </div>
                  </div>


</template>

<template x-if="calibracion.estado == 1">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3 mt-3">
                      <button
type="button"
class="btn btn-success"
@click="window.history.back()">

<i class="ti ti-check"></i> Finalizar

</button>
                    </div>
                  </div>



</template>




<div class="card">
<div class="card-body">
<div class="row">

<div class="col-12 col-sm-3 mt-2 ">
<label class="form-label mb-2">Folio:</label>
<div class="mb-3">
<h5 x-text="'00' + calibracion.folio"></h5>
</div>
</div>

<div class="col-12 col-sm-3">
<div class="text-secondary mb-0">
<label class="form-label">Fecha:</label>
</div>

<input
type="date"
class="form-control mb-3"
x-model="calibracion.fecha_formateada"
@change="editarCampo(
1,
calibracion.fecha_formateada,
'fecha_formateada'
)">
</div>

<div class="col-12 col-sm-3 ">
<div class="text-secondary mb-0">
<label class="form-label">Hora:</label>
</div>

<input
type="time"
class="form-control mb-3"
x-model="calibracion.hora"
@change="editarCampo(
2,
calibracion.hora,
'hora'
)">
</div>

<div class="col-12 col-sm-3 ">
<div class="text-secondary mb-0">
<label class="form-label">Unidad de verificación:</label>
</div>

<input
type="text"
class="form-control mb-3"
x-model="calibracion.unidad_verificacion"
@blur="editarCampo(
3,
calibracion.unidad_verificacion,
'unidad_verificacion'
)">
</div>

<div class="col-12 col-sm-3 ">
<div class="text-secondary mb-0">
<label class="form-label">No. de acreditación:</label>
</div>

<input
type="text"
class="form-control mb-3"
x-model="calibracion.numero_acreditacion"
@blur="editarCampo(
4,
calibracion.numero_acreditacion,
'numero_acreditacion'
)">
</div>

<div class="col-12 col-sm-3">
<div class="text-secondary mb-0">
<label class="form-label">Método usado para la calibración:</label>
</div>

<input
type="text"
class="form-control mb-3"
x-model="calibracion.metodo_calibracion"
@blur="editarCampo(
5,
calibracion.metodo_calibracion,
'metodo_calibracion'
)">
</div>

</div>

<?php if (!empty($permisos['crear'])): ?>
<template x-if="Number(calibracion.estado) === 0">
    <div class="row g-0 ms-auto w-100 justify-content-end mt-3">
        <div class="col-12 col-md-auto d-grid">
            <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModalSondas()"><i class="ti ti-plus"></i> Nuevo</button>
        </div>
    </div>
</template>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-striped table-bordered  mt-3 text-nowrap align-middle">

<thead>
<tr>
<th class="align-middle text-center">No. Sonda</th>
<th class="align-middle text-center">Marca</th>
<th class="align-middle text-center">Modelo</th>
<th class="align-middle text-center">Incertidumbre de calibración</th>
<?php if (!empty($permisos['eliminar'])): ?>
<th class="align-middle text-center" width="48px"><i class="ti ti-trash fs-6 text-muted"></i></th>
<?php endif; ?>
</tr>
</thead>

<tbody>

<template
x-for="item in calibracion.sondas"
:key="item.id">

<tr>

<td class="align-middle text-center"
x-text="item.sonda?.no_sonda">
</td>

<td class="align-middle text-center"
x-text="item.sonda?.marca">
</td>

<td class="align-middle text-center"
x-text="item.sonda?.modelo">
</td>

<td class="p-0 m-0">

<input
type="text"
class="text-center form-control border-0 rounded-0"
x-model="item.resultado1"
@blur="editarSonda(
item,
8,
'resultado1'
)">

</td>
<?php if (!empty($permisos['eliminar'])): ?>
<td class="text-center align-middle">
<template x-if="Number(calibracion.estado) === 0">
    <a class="pointer" @click="eliminarSonda(item.id)" aria-label="Eliminar sonda"><i class="ti ti-trash fs-6 text-danger"></i></a>
</template>
</td>
<?php endif; ?>

</tr>

</template>

</tbody>

</table>
</div>

<?php if (!empty($permisos['crear'])): ?>
<div class="modal fade" id="modalSonda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h5 class="modal-title text-white"><i class="ti ti-ruler-measure me-1"></i> Nueva sonda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">* Sonda:</label>
                <select class="form-select" x-model="nuevaSonda">
                    <option value="">Seleccione una sonda...</option>
                    <template x-for="item in sondasDisponibles" :key="item.id">
                        <option :value="item.id" x-text="'Sonda ' + item.no_sonda + ' - ' + item.marca + ' - ' + item.modelo"></option>
                    </template>
                </select>
                <p class="text-muted mt-2 mb-0" x-show="sondasDisponibles.length === 0">No hay sondas disponibles.</p>
            </div>
            <div class="modal-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button></div>
                    <div class="col-12 col-md-auto d-grid"><button type="button" class="btn btn-success" @click="agregarSonda()"><i class="ti ti-check"></i> Guardar</button></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="row mt-3">

<div class="col-12">

<div class="text-secondary mb-0">
<label class="form-label">Responsable de la verificación:</label>
</div>

<input
type="text"
class="form-control mb-3"
x-model="calibracion.responsable_verificacion"
@blur="editarCampo(
7,
calibracion.responsable_verificacion,
'responsable_verificacion'
)">

</div>

<div class="col-12">

<div class="text-secondary mb-0">
<label class="form-label">Observaciones:</label>
</div>

<textarea
class="form-control mb-3" rows="4"
x-model="calibracion.observaciones"
@blur="editarCampo(
6,
calibracion.observaciones,
'observaciones'
)">
</textarea>

</div>



</div> 
</div>

</div>

</div>
