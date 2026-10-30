<div id="asistencia-container"
class="mt-4 mb-5"
data-id-personal="<?= (int)$personal['id'] ?>"
data-mes-actual="<?= (int)date('m') ?>"
data-anio-actual="<?= (int)date('Y') ?>"
x-data="{ ...actions(), ...asistenciaComponent() }"
x-cloak>

<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-asistencia" class="table table-striped table-bordered mb-0 text-nowrap align-middle control-documentos-personal-seleccionable portal-table portal-table--selectable">
<tbody></tbody>
</table>
</div>
</div>

<!-- MODAL DETALLE INCIDENCIA (solo lectura) -->
<div class="modal fade" id="modalDetalleIncidencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white"> 
<i class="ti ti-eye"></i>
<span x-text="'Incidencia (' + modalIncidencia.fecha + ')'">
</span> 
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<template x-if="modalIncidencia.cargando">
<div class="text-center py-4">
<div class="spinner-border text-primary" role="status"></div>
<p class="text-muted mt-2 mb-0">Cargando información...</p>
</div>
</template>

<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
<div class="text-center py-3">
<p>No se encontró información de incidencias en el sistema</p>
</div>
</template>

<template x-if="!modalIncidencia.cargando && modalIncidencia.existe">
<div>
<div class="mb-3">
<div class="form-label mb-1">Fecha:</div>
<div x-text="modalIncidencia.fecha || '—'"></div>
</div>
<div class="mb-3">
<div class="form-label mb-1">Incidencia:</div>
<div  x-text="modalIncidencia.incidencia || '—'"></div>
</div>
<div class="mb-3">
<div class="form-label mb-1">Comentario:</div>
<div x-text="modalIncidencia.comentario || '—'"></div>
</div>

<div class="form-label mb-1">Sueldo día:</div>
<div x-text="modalIncidencia.puntos ?? '—'"></div>

<div x-show="modalIncidencia.requiereDocumento && !modalIncidencia.documento">

<div class="form-label mb-1 mt-2">Documento:</div>

<div class="input-group mt-1 mb-3">
    <input
        type="file"
        class="form-control"
        accept=".pdf"
        id="cdFileDetalleIncidencia"
    >

    <button
        type="button"
        class="btn btn-success"
        @click="guardarDocumentoIncidencia()"
        :disabled="modalIncidencia.guardando">

        <template x-if="modalIncidencia.guardando">
            <span class="spinner-border spinner-border-sm me-1"></span>
        </template>

        <span x-text="modalIncidencia.guardando ? 'Guardando...' : 'Guardar'"></span>
    </button>
</div>


</div>

<div x-show="modalIncidencia.requiereDocumento && modalIncidencia.documento">

<div class="form-label mb-1 mt-3">Documento:</div>

<div class="row g-0 ms-auto w-100 justify-content-start">
                    <div class="col-12 col-md-auto d-grid mb-0 mt-2">
                      <a href="" @click.prevent="download('docs-personal-incidencias', modalIncidencia.documento)"
class="btn  bg-danger-subtle text-danger">
<i class="ti ti-file-text me-1"></i>Descargar PDF
</a>
                    </div>
                  </div>



</div>
</div>
</template>
</div>

<div class="modal-footer">
    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid">
                      <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i>Cerrar
</button>
                    </div>
                  </div>
</div>

</div>
</div>
</div>

<!-- MODAL AGREGAR INCIDENCIA -->
<div class="modal fade" id="modalAgregarIncidencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
    <i class="ti ti-clipboard-list"></i>
<span x-text="'Incidencia (' + modalIncidencia.fecha + ')'"> 
</span>
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<template x-if="modalIncidencia.cargando">
<div class="text-center py-4">
<div class="spinner-border text-primary" role="status"></div>
<p class="text-muted mb-0">Cargando información...</p>
</div>
</template>

<template x-if="!modalIncidencia.cargando && modalIncidencia.existe">
<div>
<div class="mb-3">
<label class="form-label mb-1">Fecha:</label>
<div x-text="modalIncidencia.fecha || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label mb-1">Incidencia:</label>
<div  x-text="modalIncidencia.incidencia || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label mb-1">Comentario:</label>
<div x-text="modalIncidencia.comentario || '—'"></div>
</div>
<div x-show="modalIncidencia.requiereDocumento && !modalIncidencia.documento">
<hr class="my-2">
<label class="form-label mb-1">* Documento:</label>
<input type="file" class="form-control mt-1" accept=".pdf" id="cdFileIncidenciaExistente">
<div class="mt-4">
<label class="form-label mb-1">Fecha de inicio y termino de la incapacidad:</label>
</div>
<div class="row g-2 mt-1">
<div class="col-md-6">
<label class="form-label mb-1">* Fecha Inicio:</label>
<input type="date" class="form-control mb-2" x-model="modalIncidencia.fechaInicio">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* Fecha Fin:</label>
<input type="date"
class="form-control mb-2"
x-model="modalIncidencia.fechaFin">
</div>
</div>
<div class="mt-2">
<label class="form-label mb-1">* Sueldo día:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="modalIncidencia.sueldoDia">
</div>
</div>
<div x-show="modalIncidencia.requiereDocumento && modalIncidencia.documento">
<label class="form-label mb-1">Documento:</label>

<div class="row g-0 ms-auto w-100 justify-content-start">
                    <div class="col-12 col-md-auto d-grid">
                      <a
href=""
@click.prevent="download('docs-personal-incidencias', modalIncidencia.documento)"
class="btn bg-primary-subtle text-primary ">
<i class="ti ti-download me-2"></i>
Descargar documento
</a>
                    </div>
                  </div>



</div>
</div>
</template>

<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
<div>
<label class="form-label mb-1">Seleccione alguna de las siguientes incidencias:</label>
<div class="p-2" :class="{'border border-danger rounded': modalIncidencia.errorRadio}">
<template x-for="item in catalogoIncidencias" :key="item.id">
<div class="form-check mb-1">
<input class="form-check-input"
type="radio"
name="cdRadioIncidencia"
:id="'cdIncidencia' + item.id"
:value="item.id"
x-model="modalIncidencia.idIncidenciaSeleccionada">
<label class="form-check-label fw-light"
:for="'cdIncidencia' + item.id"
x-text="item.detalle"></label>
</div>
</template>
</div>

<div class="mt-3">
<label class="form-label mb-1">Comentario *</label>
<textarea class="form-control"
rows="2"
placeholder="Escribe un comentario..."
:class="{'is-invalid': modalIncidencia.errorComentario}"
x-model="modalIncidencia.comentario"></textarea>
</div>

<template x-if="requiereDocumento">
<div class="mt-3 p-3 border rounded bg-light">
<hr class="my-2">
<div class="mb-2">
<label class="form-label mb-1">Documento:</label>
<input type="file"
class="form-control"
accept=".pdf"
id="cdFileIncidenciaNueva">
</div>
<div class="mb-2">
<div class="form-label mb-1">Fecha de inicio y termino de la incapacidad:</div>
</div>
<div class="row g-2 mb-2">
<div class="col-md-6">
<label class="form-label mb-1">Fecha Inicio:</label>
<input type="date"
class="form-control"
x-model="modalIncidencia.fechaInicio">
</div>
<div class="col-md-6">
<label class="form-label mb-1">Fecha Fin:</label>
<input type="date"
class="form-control"
x-model="modalIncidencia.fechaFin">
</div>
</div>
<div class="mb-2">
<label class="form-label mb-1">Sueldo día:</label>
<input type="number"
step="0.01"
min="0"
class="form-control"
x-model="modalIncidencia.sueldoDia">
</div>
</div>
</template>
</div>
</template>
</div>

<div class="modal-footer">

<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid me-0 me-md-2">
                      <button type="button" 
class="btn bg-danger-subtle text-danger" 
data-bs-dismiss="modal">
<i class="ti ti-x"></i>
Cancelar
</button>
                    </div>

<!-- Botón para guardar una incidencia nueva -->
<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
    <div class="col-12 col-md-auto d-grid mt-1 mt-md-0">
        <button
            type="button"
            class="btn btn-success"
            @click="guardarIncidencia()"
            :disabled="modalIncidencia.guardando">

            <span x-show="modalIncidencia.guardando"
                  class="spinner-border spinner-border-sm me-1"></span>

            <i class="ti ti-check"></i>

            <span x-text="modalIncidencia.guardando ? 'Guardando...' : 'Guardar'"></span>
        </button>
    </div>
</template>

<!-- Botón para guardar una incidencia existente -->
<template x-if="!modalIncidencia.cargando && modalIncidencia.existe">
    <div class="col-12 col-md-auto d-grid mt-1 mt-md-0">
        <button
            type="button"
            class="btn btn-success"
            @click="guardarDocumentoIncidenciaExistente()"
            :disabled="modalIncidencia.guardando">

            <span x-show="modalIncidencia.guardando"
                  class="spinner-border spinner-border-sm me-1"></span>

            <i class="ti ti-check"></i>

            <span x-text="modalIncidencia.guardando ? 'Guardando...' : 'Guardar'"></span>
        </button>
    </div>
</template>
                  </div>






<!-----------boton de guardar de incidencia editar------->




</div>

</div>
</div>
</div>

</div>
