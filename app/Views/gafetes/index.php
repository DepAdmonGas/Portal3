<div id="container" class="mt-4 mb-4">

<?php
if ($utilitiesUser['idPuestoUser'] == "6") {
echo !empty($permisos['crear']) ? '
<div class="row">
<div class="col-12 mb-4">
<button class="btn bg-primary-subtle text-primary float-end" data-bs-toggle="modal" data-bs-target="#nuevo">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>
' : '';
}
?>
  
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-hidden pb-2">
<table id="table-gafetes" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div> 

</div>

<!---------- MODAL AGREGAR GAFETES ---------->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...gafetesForm() }" @open-edit.window="openEdit($event.detail)">

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
<!-- CLAVE -->
<label class="form-label mb-1">
    * Clave:</label>
<input type="text" class="form-control mb-3" x-model="clave" @input="errors.clave = false" :class="errors.clave ? 'is-invalid' : ''">

<!-- NOMBRE -->
<label class="form-label mb-1">* Nombre Completo:</label>
<input type="text" class="form-control mb-3" x-model="nombre_g" @input="errors.nombre_g = false" :class="errors.nombre_g ? 'is-invalid' : ''">

<!-- FOTO -->
<label class="form-label mb-1">* Foto</label>
<input type="file" class="form-control" x-ref="foto" @change="handleFile($event)" :class="errors.foto ? 'is-invalid' : ''">
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