<div id="container" class="mt-4 mb-4"
data-puede-crear="<?= !empty($permisos['crear']) ? 'true' : 'false' ?>"
data-puede-editar="<?= !empty($permisos['editar']) ? 'true' : 'false' ?>"
data-puede-eliminar="<?= !empty($permisos['eliminar']) ? 'true' : 'false' ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? 'biometricos', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int)($estacionId ?? 0) ?>">

<?php if (!$estacionId): ?>
<div id="retardos-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información.
</div>
<div id="retardos-content" style="display:none">
<?php else: ?>
<div id="retardos-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información.
</div>
<div id="retardos-content">
<?php endif; ?>

<!-- TARJETAS RETARDO / INCIDENCIA -->
<div class="row mb-4" x-data="{ ...actions(), ...retardoConfigForm() }" x-init="init()">

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<!-- Contenedor principal sin bordes ni cabecera de tarjeta -->
<div class="p-3 bg-white border rounded-3 h-100 d-flex align-items-center">
<div class="row g-2 align-items-center w-100 m-0">

<!-- Icono + Etiqueta -->
<div class="col-12 col-sm-6 d-flex align-items-center gap-2 ps-0">
<div class="bg-primary-subtle text-primary rounded-2 p-2 d-flex align-items-center justify-content-center">
<i class="ti ti-clock fs-6"></i>
</div>
<div>
<label for="inputRetardo" class="form-label mb-0 fw-bold text-dark fs-6">
Retardo
</label>
<div class="text-muted small" style="font-size: 0.75rem;">Agregar retardo en minuros</div>
</div>
</div>

<!-- Entrada + Botón integrado -->
<div class="col-12 col-sm-6 pe-0">
<div class="input-group">
<input type="number" 
id="inputRetardo"
class="form-control text-center fw-bold shadow-none" 
x-model="retardo" 
min="0" 
placeholder="0" 
:disabled="!puedeEditar">
<span class="input-group-text bg-light text-muted small">min</span>

<template x-if="puedeEditar">
<button type="button" 
class="btn btn-primary d-flex align-items-center gap-1" 
@click="guardarRetardoIncidencia()" 
:disabled="loadingRi"
title="Guardar cambios">

<span x-show="loadingRi" class="spinner-border spinner-border-sm" role="status"></span>
<i x-show="!loadingRi" class="ti ti-check fs-5"></i>
<span x-show="!loadingRi" class="d-none d-lg-inline">Guardar</span>
</button>
</template>
</div>
</div>

</div>
</div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<!-- Contenedor principal sin cabecera vacía -->
<div class="p-3 bg-white border rounded-3 h-100 d-flex align-items-center">
<div class="row g-2 align-items-center w-100 m-0">

<!-- Icono Tabler + Etiqueta -->
<div class="col-12 col-sm-6 d-flex align-items-center gap-2 ps-0">
<div class="bg-primary-subtle text-primary rounded-2 p-2 d-flex align-items-center justify-content-center">
<i class="ti ti-calendar-x fs-6"></i>
</div>
<div>
<label for="inputIncidencia" class="form-label mb-0 fw-bold text-dark fs-6">
Incidencias
</label>
<div class="text-muted small" style="font-size: 0.75rem;">Agregar dias para la resolucion de Incidencias</div>
</div>
</div>

<!-- Entrada + Botón integrado -->
<div class="col-12 col-sm-6 pe-0">
<div class="input-group">
<input type="number" 
id="inputIncidencia"
class="form-control text-center fw-bold shadow-none" 
x-model="incidencia" 
min="0" 
placeholder="0" 
:disabled="!puedeEditar">
<span class="input-group-text bg-light text-muted small">días</span>

<template x-if="puedeEditar">
<button type="button" 
class="btn btn-primary d-flex align-items-center gap-1" 
@click="guardarRetardoIncidencia()" 
:disabled="loadingRi"
title="Guardar cambios">

<span x-show="loadingRi" class="spinner-border spinner-border-sm" role="status"></span>
<i x-show="!loadingRi" class="ti ti-check fs-5"></i>
<span x-show="!loadingRi" class="d-none d-lg-inline">Guardar</span>
</button>
</template>
</div>
</div>

</div>
</div>
</div>

</div>

<!-- TABLA HORARIOS -->
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="mb-0"><i class="ti ti-clock"></i> Horarios</h5>
<?php if (!empty($permisos['crear'])): ?>
<button type="button" class="btn btn-primary"
id="btn-agregar-horario"
@click="$dispatch('open-horario-create')">
<i class="ti ti-plus"></i> Nuevo
</button>
<?php endif; ?>
</div>
<div class="card-body mb-0">
<div class="datatables">
<div class="table-responsive">
<table id="table-horarios" class="table table-striped table-bordered pb-4 align-middle" style="overflow-y: hidden; overflow-x: auto;">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

</div>

<!-- MODAL AGREGAR / EDITAR HORARIO -->
<div class="modal fade" id="modalHorario" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
x-data="{ ...actions(), ...horarioForm() }"
@open-horario-edit.window="openEdit($event.detail)"
@open-horario-create.window="openCreateModal()">

<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-clock"></i>
<span x-text="mode === 'create' ? 'Agregar Horario' : 'Editar Horario'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<!-- TITULO -->
<label class="form-label">* Titulo del horario:</label>
<input type="text" class="form-control rounded-0" x-model="titulo"
@input="errors.titulo = false"
:class="errors.titulo ? 'is-invalid' : ''"
placeholder="Ej. Horario Matutino"
autocomplete="off">

<!-- HORA ENTRADA -->
<label class="form-label mt-3">* Hora de entrada:</label>
<input type="time" class="form-control rounded-0" x-model="horaEntrada"
@input="errors.horaEntrada = false"
:class="errors.horaEntrada ? 'is-invalid' : ''">

<!-- HORA SALIDA -->
<label class="form-label mt-3">* Hora de salida:</label>
<input type="time" class="form-control rounded-0" x-model="horaSalida"
@input="errors.horaSalida = false"
:class="errors.horaSalida ? 'is-invalid' : ''">

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
