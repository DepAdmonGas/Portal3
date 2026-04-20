<div class="mb-4" x-data="{ ...actions(), ...seguimientoForm() }"
x-init="init()" id="container" data-estacion="<?= $idEstacion ?>" data-reporte="<?= $noReporte ?>" data-puesto="<?= $utilitiesUser['idPuestoUser'] ?>">
 
<div class="row mt-3 mb-3">
<div class="col-8"> <span class="badge rounded-pill bg-success">No. de Solicitud: <?=$noReporte?></span></div>

<div class="col-4 d-flex justify-content-end align-items-center gap-2">
<div class="text-end">
<div class="btn-group">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button> 
<ul class="dropdown-menu animated rubberBand pointer">
<?= !empty($permisos['crear']) ? '<li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"> <i class="ti ti-plus"></i> Nuevo </a></li>' : '' ?>
<div id="botonSeguimiento"></div>
</ul>
</div>
</div>
</div>

</div>

<div class="datatables">
<div class="table-responsive">
<table id="table-gafetes-formulario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
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

