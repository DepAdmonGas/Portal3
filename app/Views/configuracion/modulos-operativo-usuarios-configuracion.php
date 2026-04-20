<div id="container" class="mt-4 mb-4" x-data="{ ...actions(), ...modulosUsuariosOperativoForm()}" data-id="<?=$idUsuario?>" >

<?php
echo !empty($permisos['crear']) ? '
<div class="row">
<div class="col-12 mb-4">
<button class="btn btn-primary float-end" @click="openNuevo()">
<i class="ti ti-plus"></i> Nuevo
</button>
</div>
</div>
' : '';
?>
 
<div class="datatables">
<div class="table-responsive">
<table id="table-modulos-operativo-usuarios-configuracion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>

<!---------- MODAL CREAR / EDITAR ---------->
<div class="modal fade" id="nuevo" x-ref="modalNuevo" tabindex="-1">

<div class="modal-dialog modal-dialog-scrollable modal-lg">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header">
<h4 class="modal-title" x-text="mode === 'edit' ? 'Editar módulo' : 'Agregar módulo'"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetModal()"></button>
</div>

<!-- BODY -->
<div class="modal-body">

<!-- MÓDULO -->
<label class="form-label">* Nombre del módulo:</label>
<div class="select2-modal-field is-select2-pending mb-3" x-ref="moduloWrapper" :class="errors.modulo ? 'is-invalid' : ''">

<select id="selectModulo" x-ref="selectModulo" x-model="modulo_id" data-width="100%">
<option value="">Selecciona una opción...</option>
<template x-for="m in modulos" :key="m.id">
<option :value="m.id" x-text="m.nombre"></option>
</template>
</select>
</div>

<!-- PERMISOS -->
<label class="form-label mt-1 mb-3">Permisos:</label>

<div class="row">
<div class="col-6 mb-3">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="leer" x-ref="check_leer">
<label class="form-check-label" for="leer">Leer</label>
</div>
</div>

<div class="col-6 mb-3">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="crear" x-ref="check_crear">
<label class="form-check-label" for="crear">Crear</label>
</div>
</div>

<div class="col-6 mb-3">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="editar" x-ref="check_editar">
<label class="form-check-label" for="editar">Editar</label>
</div>
</div>

<div class="col-6 mb-3">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="eliminar" x-ref="check_eliminar">
<label class="form-check-label" for="eliminar">Eliminar</label>
</div>
</div>

<div class="col-6 mb-3">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="descargar" x-ref="check_descargar">
<label class="form-check-label" for="descargar">Descargar</label>
</div>
</div>
</div>

<!-- SUBMODULOS -->
<div x-show="modulo_id" x-transition>
<label class="form-label mt-1 mb-3">Submódulos:</label>

<!-- LISTA DE SUBMODULOS-->
<div class="row" x-show="submodulos.length">
<template x-for="sub in submodulos" :key="sub.id">
<div class="col-6 mb-3">
<div class="form-check">
<input type="checkbox" class="form-check-input" :id="'sub_' + sub.id" :value="sub.id" x-model="submodulosSeleccionados">
<label class="form-check-label" :for="'sub_' + sub.id" x-text="sub.nombre"></label>
</div>
</div>
</template>
</div>

<!-- SIN SUBMODULOS -->
<div class="text-muted" x-show="modulo_id && !submodulos.length">El módulo no cuenta con submódulos disponibles</div>
</div>

</div>

<!-- FOOTER -->
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" @click="resetModal()">Cancelar</button>
<button type="button" class="btn btn-success" @click="submit()" :disabled="loading">
<span x-show="!loading">Guardar</span>
<span x-show="loading">Guardando...</span>
</button>
</div>

</div>
</div>
</div>

</div>
