<div id="container" class="mt-4" data-estacion="<?= $idEstacion ?>" data-reporte="<?= $noReporte ?>">

<?= !empty($permisos['crear']) ? 
'<div class="row mb-3">
<div class="col-12">
<button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Agregar </button>
</div>
</div>' : '' 
?>

<div class="datatables">
<div class="table-responsive">
<table id="table-gafetes-formulario" class="table  table-bordered mb-0 text-nowrap align-middle">
<thead>

<tr>
<th class="text-center align-middle" width="96px">#</th>
<th class="text-center align-middle">Clave</th>
<th class="text-center align-middle">Nombre Completo</th>
<th class="text-center align-middle">Estación</th>
<th class="text-center align-middle" width="48px"><a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a></th>
</tr>

</thead>
<tbody></tbody>
</table>
</div>
</div> 

</div>


<!---------- MODAL AGREGAR GAFETES ---------->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...gafetesForm() }" @open-edit.window="openEdit($event.detail)" data-estacion="<?= $idEstacion ?>" data-reporte="<?= $noReporte ?>">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title">Agregar registro (No. Reporte: <?=$noReporte?>)</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
</div>

<div class="modal-body">
<!-- CLAVE -->
<label class="form-label">* Clave:</label>
<input type="text" class="form-control mb-3" x-model="clave" @input="errors.clave = false" :class="errors.clave ? 'is-invalid' : ''">

<!-- NOMBRE -->
<label class="form-label">* Nombre Completo:</label>
<input type="text" class="form-control mb-3" x-model="nombre_g" @input="errors.nombre_g = false" :class="errors.nombre_g ? 'is-invalid' : ''">

<!-- FOTO -->
<label class="form-label">* Foto</label>
<input type="file" class="form-control" x-ref="foto" @change="handleFile($event)" :class="errors.foto ? 'is-invalid' : ''">
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

