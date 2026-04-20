<div id="container" class="mt-4 mb-4">

<?php
echo !empty($permisos['crear']) ? '
<div class="row">
<div class="col-12 mb-4">
<button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#nuevo">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>
' : '';
?>

<div class="datatables">
<div class="table-responsive">
<table id="table-modulos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div> 

</div>


<!---------- MODAL AGREGAR / EDITAR MODULO ---------->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...moduloForm() }" @open-edit.window="openEdit($event.detail)">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title" x-text="mode === 'create' ? 'Crear registro' : 'Editar registro'"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- NOMBRE MODULO -->
<label class="form-label">* Nombre del módulo:</label>
<input type="text" class="form-control mb-3" x-model="nombre_modulo" @input="errors.nombre_modulo = false" :class="errors.nombre_modulo ? 'is-invalid' : ''">

<!-- CLAVE -->
<label class="form-label">* Clave:</label>
<input type="text" class="form-control mb-3" x-model="clave" @input="errors.clave = false" :class="errors.clave ? 'is-invalid' : ''">

<!-- RUTA -->
<label class="form-label">* Ruta:</label>
<input type="text" class="form-control mb-3" x-model="ruta" @input="errors.ruta = false" :class="errors.ruta ? 'is-invalid' : ''">

<!-- ICONO -->
<label class="form-label"> Icono:</label>
<input type="text" class="form-control mb-3" x-model="icono" @input="errors.icono = false" :class="errors.icono ? 'is-invalid' : ''">

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

