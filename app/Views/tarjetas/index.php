<div id="container" class="mb-4">

<?php
if ($utilitiesUser['idPuestoUser'] == "6") {
echo !empty($permisos['crear']) ? '
<div class="row d-flex justify-content-end">
<div class="col-12 col-md-auto d-grid  mt-4 mb-3">
<button class="btn bg-primary-subtle text-primary float-end" data-bs-toggle="modal" data-bs-target="#nuevo">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>
' : '';
}
?>

<div class="datatables">
 <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
<table id="table-tarjetas" class="table table-striped table-bordered  text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div> 

</div>


<!---------- MODAL AGREGAR TARJETAS ---------->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...tarjetasForm() }" @open-edit.window="openEdit($event.detail)">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header bg-primary">
<h4 class="modal-title text-white">
    <i class="ti ti-clipboard-text"></i>
Nuevo registro</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- ARCHIVO -->
<label class="form-label mb-1">Cargar archivo:</label>
<input type="file" class="form-control mb-3" x-ref="archivo" @change="handleFile($event)" :class="errors.archivo ? 'is-invalid' : ''">


<!-- RAZON SOCIAL -->
<label class="form-label mb-1">* Razon social:</label>
<input type="text" class="form-control mb-3" x-model="razon_social" @input="errors.razon_social = false" :class="errors.razon_social ? 'is-invalid' : ''">

<!-- NOMBRE USUARIO -->
<label class="form-label mb-1">* Usuario:</label>
<input type="text" class="form-control mb-3" x-model="nombre_usuario" @input="errors.nombre_usuario = false" :class="errors.nombre_usuario ? 'is-invalid' : ''">

<!-- VEHICULO -->
<label class="form-label mb-1">* Vehiculo:</label>
<input type="text" class="form-control mb-3" x-model="vehiculo" @input="errors.vehiculo = false" :class="errors.vehiculo ? 'is-invalid' : ''">

<!-- PLACAS -->
<label class="form-label mb-1">* Placas:</label>
<input type="text" class="form-control mb-3" x-model="placas" @input="errors.placas = false" :class="errors.placas ? 'is-invalid' : ''">

<!-- NO. UNIDAD -->
<label class="form-label mb-1">* No. Unidad:</label>
<input type="text" class="form-control mb-3" x-model="no_unidad" @input="errors.no_unidad = false" :class="errors.no_unidad ? 'is-invalid' : ''">

<!-- TARJETA -->
<label class="form-label mb-1">* Tarjeta:</label>
<input type="text" class="form-control mb-3" x-model="tarjeta" @input="errors.tarjeta = false" :class="errors.tarjeta ? 'is-invalid' : ''">

<!-- TIPO DE TARJETA -->
<label class="form-label mb-1">* Tipo de tarjeta:</label>
<select class="form-control mb-3" x-model="tipo_tarjeta" @change="errors.tipo_tarjeta = false":class="errors.tipo_tarjeta ? 'is-invalid' : ''">
<option value="">Selecciona una opción...</option>
<option value="Cliente Nuevo">Cliente Nuevo</option>
<option value="Tarjeta Adicional">Tarjeta Adicional</option>
<option value="Desgaste">Desgaste</option>
<option value="Reposición por extravio $50.00">Reposición por extravio $50.00</option>
</select>

</div>

<!-- FOOTER -->
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" @click="resetForm()">
<i class="ti ti-x"></i>    
Cancelar</button>
<button type="button" class="btn btn-success" @click="submit()" :disabled="loading">
    <i class="ti ti-check"></i>
<span x-show="!loading">Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
</div>

</div>
</div>
</div>


