<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-anio-actual="<?= (int)date('Y') ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-module-station-key="biometricos"
x-data="{ ...actions(), ...biometricosComponent() }"
x-cloak>

<div class="row">

<div class="col-12 mb-4 text-end">
<div class="dropdown d-inline-block">
<button type="button" class="btn bg-primary-subtle text-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-settings-2 me-1"></i>Biométrico
</button>
<ul class="dropdown-menu dropdown-menu-end shadow-sm">
<li>
<a class="dropdown-item pointer" href="#" @click.prevent="abrirModalReporte()">
<i class="ti ti-search me-1"></i> Buscar Reporte
</a>
</li>
<template x-if="multiestacion">
<li>
<a class="dropdown-item pointer" href="/departamento-operativo/recursos-humanos/biometricos/configuracion">
<i class="ti ti-adjustments-horizontal me-1"></i> Configuración biométrico
</a>
</li>
</template>
</ul>
</div>
</div>

<div id="divListadoAsistencia" x-show="!vistaReporte">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-biometricos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>#</th>
<th>Estación / Departamento</th>
<th>Nombre</th>
<th>Fecha</th>
<th>Sistema (Entrada)</th>
<th>Sistema (Salida)</th>
<th>Sensor (Entrada)</th>
<th>Sensor (Salida)</th>
<th>Detalle</th>
<th><i class="ti ti-alert-triangle fs-5"></i></th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<!-- MODAL DETALLE INCIDENCIA (solo lectura) -->
<div class="modal fade" id="modalDetalleIncidenciaBiometricos" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">

<!-- HEADER -->
<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-alert-triangle"></i>
<span x-text="'Incidencia (' + modalIncidencia.fecha + ')'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<!-- BODY -->
<div class="modal-body pb-0">
<template x-if="modalIncidencia.cargando">
<div class="text-center py-4">
<div class="spinner-border text-primary" role="status"></div>
<p class="text-muted mt-2 mb-0">Cargando información...</p>
</div>
</template>

<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
<div class="text-center py-3">
<p class="fs-6 fw-light text-muted mb-0">No se encontró información de incidencias en el sistema</p>
</div>
</template>

<template x-if="!modalIncidencia.cargando && modalIncidencia.existe">
<div>
<div class="mb-3">
<label class="form-label">Fecha:</label>
<div class="" x-text="modalIncidencia.fecha || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label">Incidencia:</label>
<div class="" x-text="modalIncidencia.incidencia || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label">Comentario:</label>
<div class="" x-text="modalIncidencia.comentario || '—'"></div>
</div>
<div class="">
<label class="form-label">Sueldo día:</label>
<div class="" x-text="modalIncidencia.puntos ?? '—'"></div>
</div>

<div x-show="modalIncidencia.requiereDocumento && !modalIncidencia.documento">
<hr class="my-2">
<label class="form-label">Documento:</label>
<input type="file" class="form-control mt-1" accept=".pdf" id="bioFileDetalleIncidencia">
<div class="text-end mt-2">
<button type="button" 
class="btn btn-success btn-sm" 
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
<hr class="my-2">
<label class="form-label">Documento:</label>
<div class="mt-1">
<a href="" @click.prevent="download('docs-personal-incidencias', modalIncidencia.documento)" class="btn btn-primary">
<i class="ti ti-file-text me-1"></i>Descargar PDF
</a>
</div>
</div>
</div>
</template>
</div> <!-- /modal-body -->

<!-- FOOTER (Ahora al mismo nivel de header y body) -->
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
</div>

</div> 
</div> 
</div> 

<!-- MODAL AGREGAR INCIDENCIA -->
<div class="modal fade" id="modalAgregarIncidenciaBiometricos" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">


<!-- HEADER -->
<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-alert-triangle"></i>
<span x-text="'Incidencia (' + modalIncidencia.fecha + ')'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
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
<label class="form-label">Fecha:</label>
<div class="" x-text="modalIncidencia.fecha || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label">Incidencia:</label>
<div class="" x-text="modalIncidencia.incidencia || '—'"></div>
</div>
<div class="mb-3">
<label class="form-label">Comentario:</label>
<div class="" x-text="modalIncidencia.comentario || '—'"></div>
</div>
<div x-show="modalIncidencia.requiereDocumento && !modalIncidencia.documento">
<hr class="my-2">
<label class="form-label">* Documento:</label>
<input type="file" class="form-control mt-1" accept=".pdf" id="bioFileIncidenciaExistente">
<div class="mt-4">
<label class="form-label">Fecha de inicio y termino de la incapacidad:</label>
</div>
<div class="row g-2 mt-1">
<div class="col-md-6">
<label class="form-label">* Fecha Inicio:</label>
<input type="date" class="form-control" x-model="modalIncidencia.fechaInicio">
</div>
<div class="col-md-6">
<label class="form-label">* Fecha Fin:</label>
<input type="date" class="form-control" x-model="modalIncidencia.fechaFin">
</div>
</div>
<div class="mt-2">
<label class="form-label">* Sueldo día:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="modalIncidencia.sueldoDia">
</div>
</div>
<div x-show="modalIncidencia.requiereDocumento && modalIncidencia.documento">
<hr class="my-2">
<label class="form-label">Documento:</label>
<div>
<a href=""
@click.prevent="download('docs-personal-incidencias', modalIncidencia.documento)"
class="btn btn-primary px-4">
<i class="ti ti-download me-2"></i>
Descargar documento
</a>
</div>
</div>
</div>
</template>

<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
<div>
<label class="form-label">Seleccione alguna de las siguientes incidencias:</label>
<div class="p-2" :class="{'border border-danger rounded': modalIncidencia.errorRadio}">
<template x-for="item in catalogoIncidencias" :key="item.id">
<div class="form-check mb-1">
<input class="form-check-input"
type="radio"
name="bioRadioIncidencia"
:id="'bioIncidencia' + item.id"
:value="item.id"
x-model="modalIncidencia.idIncidenciaSeleccionada">
<label class="form-check-label fw-light"
:for="'bioIncidencia' + item.id"
x-text="item.detalle"></label>
</div>
</template>
</div>

<div class="mt-3">
<label class="form-label fw-semibold">Comentario *</label>
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
<label class="form-label fw-semibold">Documento</label>
<input type="file"
class="form-control"
accept=".pdf"
id="bioFileIncidenciaNueva">
</div>
<div class="mb-2">
<small class="text-secondary fw-bold">Fecha de inicio y termino de la incapacidad:</small>
</div>
<div class="row g-2 mb-2">
<div class="col-md-6">
<label class="form-label">Fecha Inicio</label>
<input type="date"
class="form-control"
x-model="modalIncidencia.fechaInicio">
</div>
<div class="col-md-6">
<label class="form-label">Fecha Fin</label>
<input type="date"
class="form-control"
x-model="modalIncidencia.fechaFin">
</div>
</div>
<div class="mb-2">
<label class="form-label">Sueldo día</label>
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
<button class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>

<template x-if="!modalIncidencia.cargando && !modalIncidencia.existe">
<button type="button" class="btn btn-success" @click="guardarIncidencia()" :disabled="modalIncidencia.guardando">
<!-- Spinner cuando está guardando -->
<template x-if="modalIncidencia.guardando">
<span class="spinner-border spinner-border-sm me-1"></span>
</template>

<!-- Ícono Check solo cuando NO está guardando -->
<template x-if="!modalIncidencia.guardando">
<i class="ti ti-check me-1"></i>
</template>

<span x-text="modalIncidencia.guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</template>

<template x-if="!modalIncidencia.cargando && modalIncidencia.existe">
<button type="button" class="btn btn-success" @click="guardarDocumentoIncidenciaExistente()" :disabled="modalIncidencia.guardando">
<!-- Spinner cuando está guardando -->
<template x-if="modalIncidencia.guardando">
<span class="spinner-border spinner-border-sm me-1"></span>
</template>

<!-- Ícono Check solo cuando NO está guardando -->
<template x-if="!modalIncidencia.guardando">
<i class="ti ti-check me-1"></i>
</template>

<span x-text="modalIncidencia.guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</template>
</div>

</div>
</div>
</div>

<!-- MODAL BUSCAR REPORTE -->
<div class="modal fade" id="modalBuscarReporteBiometricos" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2"><i class="ti ti-search me-1"></i> Buscar Reporte</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<div class="mb-3">
<label class="form-label">Año</label>
<select class="form-select" x-model="reporte.year">
<option value="">Selecciona una opción...</option>
<?php for ($i = 2022; $i <= (int)date('Y'); $i++): ?>
<option value="<?= $i ?>"><?= $i ?></option>
<?php endfor; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Mes</label>
<select class="form-select" x-model="reporte.mes">
<option value="">Selecciona una opción...</option>
<option :value="1" x-text="'Enero'"></option>
<option :value="2" x-text="'Febrero'"></option>
<option :value="3" x-text="'Marzo'"></option>
<option :value="4" x-text="'Abril'"></option>
<option :value="5" x-text="'Mayo'"></option>
<option :value="6" x-text="'Junio'"></option>
<option :value="7" x-text="'Julio'"></option>
<option :value="8" x-text="'Agosto'"></option>
<option :value="9" x-text="'Septiembre'"></option>
<option :value="10" x-text="'Octubre'"></option>
<option :value="11" x-text="'Noviembre'"></option>
<option :value="12" x-text="'Diciembre'"></option>
</select>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger"><i class="ti ti-x"></i> Cancelar</button>
<button type="button" class="btn btn-primary" @click="buscarReporte()" :disabled="reporte.cargando">
<template x-if="reporte.cargando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<i class="ti ti-search me-1"></i>Buscar
</button>
</div>

</div>
</div>
</div>

<!-- CONTENIDO DEL REPORTE -->
<div id="DivBusquedaReporte" class="mt-4" x-show="vistaReporte" x-cloak>
<div class="d-flex justify-content-between align-items-center mb-3">
<div>
<h5 class="fw-semibold mb-1" x-text="'Reporte de asistencias (' + nombreMesReporte() + ' ' + reporte.year + ')'"></h5>
</div>
<button type="button" class="btn btn-sm btn-danger" @click="regresarListado()">
<i class="ti ti-arrow-left me-1"></i>Regresar al listado
</button>
</div>
<div id="contenido-reporte-biometricos" x-html="reporte.html"></div>
</div>

</div>
