<div id="container" class="mb-4">

<?php
if ($utilitiesUser['idPuestoUser'] == "6") {
echo !empty($permisos['crear']) ? '
<div class="row">
<div class="col-12 mt-4">
<button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#nuevo">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>
' : '';
}
?>

<div class="datatables mt-4">
 <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table id="table-tarjetas" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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
<div class="modal-header">
<h4 class="modal-title">Crear registro</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- ARCHIVO -->
<label class="form-label">Cargar archivo:</label>
<input type="file" class="form-control" x-ref="archivo" @change="handleFile($event)" :class="errors.archivo ? 'is-invalid' : ''">

<hr>

<!-- RAZON SOCIAL -->
<label class="form-label">* Razon social:</label>
<input type="text" class="form-control mb-3" x-model="razon_social" @input="errors.razon_social = false" :class="errors.razon_social ? 'is-invalid' : ''">

<!-- NOMBRE USUARIO -->
<label class="form-label">* Usuario:</label>
<input type="text" class="form-control mb-3" x-model="nombre_usuario" @input="errors.nombre_usuario = false" :class="errors.nombre_usuario ? 'is-invalid' : ''">

<!-- VEHICULO -->
<label class="form-label">* Vehiculo:</label>
<input type="text" class="form-control mb-3" x-model="vehiculo" @input="errors.vehiculo = false" :class="errors.vehiculo ? 'is-invalid' : ''">

<!-- PLACAS -->
<label class="form-label">* Placas:</label>
<input type="text" class="form-control mb-3" x-model="placas" @input="errors.placas = false" :class="errors.placas ? 'is-invalid' : ''">

<!-- NO. UNIDAD -->
<label class="form-label">* No. Unidad:</label>
<input type="text" class="form-control mb-3" x-model="no_unidad" @input="errors.no_unidad = false" :class="errors.no_unidad ? 'is-invalid' : ''">

<!-- TARJETA -->
<label class="form-label">* Tarjeta:</label>
<input type="text" class="form-control mb-3" x-model="tarjeta" @input="errors.tarjeta = false" :class="errors.tarjeta ? 'is-invalid' : ''">

<!-- TIPO DE TARJETA -->
<label class="form-label">* Tipo de tarjeta:</label>
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
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" @click="resetForm()">Cancelar</button>
<button type="button" class="btn btn-success" @click="submit()" :disabled="loading">
<span x-show="!loading">Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
</div>

</div>
</div>
</div>


