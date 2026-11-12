<!-- Soporte (Departamento de Sistemas) — Crear registro -->
<div id="soporte-formulario"
class="mt-3 mb-4"
x-data="{ ...actions(), ...soporteFormularioComponent() }"
data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
data-id-ticket="<?= (int) $ticket['id_ticket'] ?>"
data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-endpoint="<?= htmlspecialchars(isset($endpoint) ? $endpoint : '/sistemas/soporte', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-asignar="<?= !empty($permisos['puede_asignar']) ? 'true' : 'false' ?>"
     data-responsables="<?= htmlspecialchars(json_encode(isset($responsables) ? $responsables : [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

<div class="row g-3">

<div class="col-12">
    <div class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-3" role="alert">
        <div>
            <?= isset($textoAlerta) ? $textoAlerta : 'Crea tu solicitud de pendientes para el área de Sistemas, es importante que detalles la descripción o las actividades a realizar.' ?>
        </div>
        <button type="button" class="btn btn-success"
                @click="finalizarRegistro()" :disabled="guardando">
            <i class="ti ti-check me-1"></i> Finalizar ticket
        </button>
    </div>
</div>

<div class="col-12">
<div class="card">
        
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white">
<i class="ti ti-info-circle me-2"></i>
<span>INFORMACIÓN DEL TICKET</span>
</h5>
</div>

<div class="card-body">
<div class="row g-3">

<div class="col-12">
<div class="form-label mb-1">* Descripción:</div>
<textarea class="form-control rounded-0"
:class="{ 'is-invalid': errores.descripcion }"
rows="3" x-model="form.descripcion"
@blur="guardarDescripcion()"></textarea>
</div>

<div class="col-12">
<div class="form-label mb-1">* Prioridad:</div>
<select class="form-select rounded-0"
:class="{ 'is-invalid': errores.prioridad }"
x-model="form.prioridad"
@change="guardarPrioridad()">
<option value="">Selecciona una opción...</option>
<option value="Baja">Baja</option>
<option value="Media">Media</option>
<option value="Alta">Alta</option>
</select>
</div>

<!-- Responsable: sólo el responsable principal del área (id 496) puede asignarlo -->
<template x-if="puedeAsignar">
<div class="col-12">
<div class="form-label mb-1">Responsable:</div>
<select class="form-select rounded-0"
x-model="form.id_responsable"
@change="guardarResponsable()">
<option value="">Selecciona una opción...</option>
<template x-for="r in responsables" :key="r.id">
<option :value="r.id" x-text="r.nombre"></option>
</template>
</select>
</div>
</template>

</div>
</div>

</div>
</div>

<!---------- EVIDENCIAS ---------->

<div class="col-12">
<div class="card">
        
<div class="card-header text-bg-primary">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <!-- Lado Izquierdo: Icono + Título y Subtítulo -->
        <div class="d-flex align-items-center">
            <i class="ti ti-notes fs-6 me-2 text-white"></i>
            <div>
                <h5 class="mb-0 text-white fw-bold">ACTIVIDADES</h5>
                <small class="text-white-50 d-block">
                    Anexa las actividades en el formato de tu elección: PDF, Excel, Word, JPG o PNG.
                </small>
            </div>
        </div>

        <!-- Lado Derecho: Botón -->
        <button type="button" class="btn btn-success" @click="abrirNuevaActividad()">
            <i class="ti ti-plus me-1"></i> Nuevo
        </button>
    </div>
</div>

<div class="card-body p-0">


<div class="table-responsive">
<table class="table table-striped table-bordered align-middle mb-0" width="100%">
<thead>
<tr>
<th class="align-middle text-center" width="96px">#</th>
<th class="align-middle text-start">Descripción de la actividad</th>
<th class="align-middle text-center" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<th class="align-middle text-center" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="(actividad, indice) in actividades" :key="actividad.id">
<tr>
<td class="align-middle text-center" x-text="actividad.id"></td>
<td class="align-middle text-start" x-text="actividad.descripcion"></td>
<td class="align-middle text-center">
<template x-if="actividad.url && puedeDescargar">
<a :href="actividad.url" download><i class="ti ti-download text-primary fs-5"></i></a>
</template>
<template x-if="!actividad.url || !puedeDescargar">
<i class="ti ti-file-off text-muted fs-5"></i>
</template>
</td>
<td class="align-middle text-center">
<a class="pointer text-danger" @click="eliminarActividad(actividad.id)">
<i class="ti ti-trash fs-5"></i>
</a>
</td>
</tr>
</template>
<template x-if="actividades.length === 0">
<tr>
<td colspan="4" class="text-center text-secondary">
<small>No se encontró información</small>
</td>
</tr>
</template>
</tbody>
</table>
</div>

</div>

</div>
</div>

</div>

<!-- Modal: agregar actividad -->
<div class="modal fade" id="modalActividad" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-plus me-2"></i>
<h5 class="modal-title text-white">Nueva actividad</h5>
<button type="button" class="btn-close btn-close-white"
data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<label class="form-label">* Descripción de la actividad:</label>
<textarea class="form-control" rows="3"
:class="{ 'is-invalid': errores.actividad }"
x-model="nuevaActividad.descripcion"></textarea>

<label class="form-label mt-3">Anexo (opcional):</label>
<input type="file" class="form-control" x-ref="actividadArchivo">
</div>
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
<div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
</div>
<div class="col-12 col-md-auto d-grid">
<button type="button" class="btn btn-success" @click="guardarActividad()">
<i class="ti ti-check"></i> Guardar
</button>
</div>
</div>
</div>
</div>
</div>
</div>

</div>
