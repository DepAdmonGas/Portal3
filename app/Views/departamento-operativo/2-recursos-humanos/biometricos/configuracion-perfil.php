<div id="container" class="mt-4 mb-4"
data-puede-crear="<?= !empty($permisos['crear']) ? 'true' : 'false' ?>"
data-puede-editar="<?= !empty($permisos['editar']) ? 'true' : 'false' ?>"
data-puede-eliminar="<?= !empty($permisos['eliminar']) ? 'true' : 'false' ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? 'biometricos', ENT_QUOTES, 'UTF-8') ?>">

<?php if (!$estacionId): ?>
<div id="perfil-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Selecciona una estación del menú superior para consultar la información correspondiente.
</div>
<div id="perfil-content" style="display:none">
<?php else: ?>
<div id="perfil-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Selecciona una estación del menú superior para consultar la información correspondiente.
</div>
<div id="perfil-content">
<?php endif; ?>

<div class="row">
<div class="col-12 text-end" x-data="actions()">
<button type="button" class="btn btn-primary"
id="btn-agregar-perfil"
style="display:none"
@click="$dispatch('open-perfil-create')">
<i class="ti ti-plus"></i> Agregar
</button>
</div>
</div>

<div class="datatables">
<div class="table-responsive">
<table id="table-perfil" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

</div>

<!-- MODAL AGREGAR / EDITAR PERFIL -->
<div class="modal fade" id="modalPerfil" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...perfilForm() }" @open-perfil-edit.window="openEdit($event.detail)" @open-perfil-create.window="openCreateModal()">

<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header bg-primary ">
<h4 class="modal-title d-flex align-items-center gap-2 text-white">
<i class="ti ti-photo-sensor fs-6"></i>
<span x-text="mode === 'create' ? 'Agregar (Sensor de huella)' : 'Editar (Sensor de huella)'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" @click="resetForm()"></button>
</div>

<div class="modal-body">

<!-- USUARIO -->
<label class="form-label">* Usuario:</label>
<input type="text" class="form-control rounded-0" x-model="usuario"
@input="errors.usuario = false"
:class="errors.usuario ? 'is-invalid' : ''"
autocomplete="off">

<!-- CONTRASENA -->
<label class="form-label mt-3"><span x-text="mode === 'edit' ? 'Contraseña (dejar vacío para mantener la actual):' : '* Contraseña:'"></span></label>
<input type="text" class="form-control rounded-0 mb-2" x-model="password"
@input="onPasswordInput()"
:class="passwordBorderClass"
autocomplete="off"
id="txtPasswordPerfil">

<!-- REGLAS CONTRASENA -->
<div class="alert alert-secondary border-0 shadow-sm p-4 mt-3 mb-4">
<div class="d-flex align-items-center mb-3">
<!-- Icono opcional para dar énfasis -->
<i class="bi bi-info-circle-fill text-secondary me-3 fs-4"></i> 
<h6 class="alert-heading mb-0 fw-bold">Requisitos de la contraseña:</h6>
</div>

<ul class="list-unstyled mb-0 ms-4 text-muted">
<li class="mb-2"><i class="ti ti-point me-2"></i>Al menos una letra <strong>mayúscula</strong>.</li>
<li class="mb-2"><i class="ti ti-point me-2"></i>Al menos una letra <strong>minúscula</strong>.</li>
<li class="mb-2"><i class="ti ti-point me-2"></i>Al menos un <strong>dígito</strong> (número).</li>
<li><i class="ti ti-point me-2"></i>Un mínimo de <strong>8 caracteres</strong>.</li>
</ul>
</div>

<!-- VALIDA CONTRASENA -->
<label class="form-label"><span x-text="mode === 'edit' ? 'Repetir contraseña:' : '* Repetir contraseña:'"></span></label>
<input type="password" class="form-control rounded-0" x-model="validaPassword"
:class="errors.passwordsMatch === false ? 'is-invalid' : ''"
autocomplete="off">

</div>

<!-- FOOTER -->
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" @click="resetForm()"><i class="ti ti-x"></i>  Cancelar</button>
<button type="button" class="btn btn-success" @click="submit()" :disabled="loading">
<span x-show="!loading"><i class="ti ti-check"></i> Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
</div>

</div>
</div>
</div>
