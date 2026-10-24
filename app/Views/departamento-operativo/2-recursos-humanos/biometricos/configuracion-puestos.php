<div id="container" class="mt-4 mb-4"
data-puede-crear="<?= !empty($permisos['crear']) ? 'true' : 'false' ?>"
data-puede-editar="<?= !empty($permisos['editar']) ? 'true' : 'false' ?>"
data-puede-eliminar="<?= !empty($permisos['eliminar']) ? 'true' : 'false' ?>">

<?php if (!empty($permisos['crear'])): ?>
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3">
                    <button class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#nuevo">
<i class="ti ti-plus"></i> Nuevo
</button>
  
                    </div>
                  </div>


<?php endif; ?>

<div class="datatables">
<div class="table-responsive">
<table id="table-puestos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>

<!-- MODAL AGREGAR / EDITAR PUESTO -->
<div class="modal fade" id="nuevo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...puestoForm() }" @open-edit.window="openEdit($event.detail)">

<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-briefcase-2"></i>
<span x-text="mode === 'create' ? 'Nuevo Puesto' : 'Editar Puesto'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" @click="resetForm()"></button>
</div>

<div class="modal-body">
<!-- NOMBRE PUESTO -->
<label class="form-label">* Nombre del puesto:</label>
<input type="text" class="form-control" x-model="nombre"
@input="errors.nombre = false"
:class="errors.nombre ? 'is-invalid' : ''"
placeholder="Ej. Encargado, Despachador, etc.">
</div>

<!-- FOOTER -->
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" 
class="btn bg-danger-subtle text-danger" 
data-bs-dismiss="modal" 
@click="resetForm()"><i class="ti ti-x"></i> 
Cancelar
</button>

                    </div>


                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" 
class="btn btn-success"
 @click="submit()" 
 :disabled="loading">
<span x-show="!loading">
    <i class="ti ti-check"></i>
<span x-text="mode === 'create' ?'Guardar':'Actualizar'"></span>
</span>
<span x-show="loading">Guardando...</span>
</button>
                    </div>
                  </div>




</div>

</div>
</div>
</div>
