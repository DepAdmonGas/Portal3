<!-- Soporte (Estaciones) — Seguimiento del ticket -->
<div id="soporte-estaciones-seguimiento"
class="mt-3 mb-4"
x-data="{ ...actions(), ...soporteEstacionesSeguimientoComponent() }"
data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
data-id-ticket="<?= (int) $ticket['id_ticket'] ?>"
data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
data-puede-seguimiento="<?= $ticket['puede_seguimiento'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-endpoint="<?= htmlspecialchars(isset($endpoint) ? $endpoint : '/sistemas/estaciones', ENT_QUOTES, 'UTF-8') ?>">

<div class="row">

<div class="col-12" x-show="cargando">
<div class="alert alert-info d-flex align-items-center gap-2" role="alert">
<i class="ti ti-loader fs-4"></i>
Cargando la información del ticket...
</div>
</div>

<template x-if="!puedeSeguimiento">
<div class="col-12">
<div class="alert alert-warning text-center gap-2" role="alert">
<i class="ti ti-lock fs-4 me-1"></i>
Sólo el responsable asignado del ticket puede registrar el seguimiento.
</div>
</div>
</template>


<!-- Cabecera del ticket -->
<div class="col-12">
<div class="card">

<div class="card-header modal-colored-header bg-primary text-white d-flex align-items-center justify-content-between flex-wrap">
<h5 class="mb-0 text-white"><i class="ti ti-info-circle me-2"></i> INFORMACIÓN DEL TICKET</h5>
</div>

<div class="card-body pb-2">
<div class="row">

<div class="col-md-4">
<h6 class="mb-1 form-label"># Ticket:</h6>
<div class="mb-3"><b x-text="ticket ? ticket.folio : 'S/I'"></b></div>
</div>
<div class="col-md-4">
<h6 class="mb-1 form-label">Estación / Departamento:</h6>
<div class="mb-3" x-text="ticket ? valorCampo(ticket.estacion) : 'S/I'"></div>
</div>

<div class="col-md-4">
<h6 class="mb-1 form-label">Estatus:</h6>
<div class="mb-3">
<span :class="ticket ? claseEstado(ticket.estado) : ''"
x-text="ticket ? valorCampo(ticket.estado_texto) : 'S/I'"></span>
</div>
</div>

<div class="col-md-4">
<h6 class="mb-1 form-label">Solicitante:</h6>
<div class="mb-3" x-text="ticket ? valorCampo(ticket.solicitante) : 'S/I'"></div>
</div>


<div class="col-md-4">
<h6 class="mb-1 form-label">Prioridad:</h6>
<div class="mb-3">
<span :class="ticket ? clasePrioridad(ticket.prioridad) : ''"
x-text="ticket ? valorCampo(ticket.prioridad) : 'S/I'"></span>
</div>
</div>

<div class="col-md-4">
<h6 class="mb-1 form-label">Descripción:</h6>
<div class="mb-3" x-text="ticket ? valorCampo(ticket.descripcion) : 'S/I'"></div>
</div>

<div class="col-md-4">
<h6 class="mb-1 form-label">Responsable:</h6>
<div class="mb-3" x-text="ticket ? valorCampo(ticket.responsable) : 'S/I'"></div>
</div>

<div class="col-md-4">
<h6 class="mb-1 form-label">Porcentaje:</h6>
<div class="mb-3"><span x-text="ticket ? valorCampo(ticket.porcentaje) : 'S/I'"></span> %</div>
</div>

</div>
</div>
</div>
</div>

<!-- Fechas -->
<div class="col-12">
<div class="card">

<div class="card-header modal-colored-header bg-primary text-white">
<h5 class="mb-0 text-white"><i class="ti ti-calendar-event me-2"></i> FECHA COMPROMETIDA</h5>
</div>

<div class="card-body">
<div class="row g-3 align-items-end">

<div class="col-md-4">
<label class="form-label" for="se-dias-habiles">Días hábiles:</label>
<input type="number" class="form-control" id="se-dias-habiles" min="1" max="60"
x-model.number="diasHabiles" @change="calcularFechas()" :disabled="seguimientoDeshabilitado">
</div>
<div class="col-md-4">
<label class="form-label" for="se-fecha-inicio">Fecha inicio:</label>
<input type="date" class="form-control" id="se-fecha-inicio" x-model="fechaInicio" :disabled="seguimientoDeshabilitado">
</div>
<div class="col-md-4">
<label class="form-label" for="se-fecha-termino">Fecha término:</label>
<input type="date" class="form-control" id="se-fecha-termino" x-model="fechaTermino" :disabled="seguimientoDeshabilitado">
</div>
</div>


<div class="row mt-3" x-show="fechaRegistro">
<div class="col-12">
<div class="p-3 bg-light border rounded">
<div class="row text-center">
<div class="col-md-6 mb-2 mb-md-0">
<span class="d-block text-muted small mb-1">
<i class="ti ti-calendar me-1"></i> Fecha inicio registrada:
</span>
<span class="fw-semibold text-dark" x-text="ticket ? valorCampo(ticket.fecha_inicio_texto) : 'S/I'"></span>
</div>
<div class="col-md-6">
<span class="d-block text-muted small mb-1">
<i class="ti ti-calendar-event me-1"></i> Fecha término registrada:
</span>
<span class="fw-semibold text-dark" x-text="ticket ? valorCampo(ticket.fecha_termino_texto) : 'S/I'"></span>
</div>
</div>
</div>
</div>
</div>

</div>

<template x-if="!trabajoFinalizado">
<div class="card-footer d-flex justify-content-between align-items-center">
<p class="text-muted small mb-0">
<i class="ti ti-bell me-1"></i>
Al guardar se notifica al solicitante las fechas comprometidas para tu ticket.
</p>
<button type="button" class="btn btn-success" @click="guardarSeguimiento()"
:disabled="guardarDeshabilitado">
<span x-show="!guardando"><i class="ti ti-check"></i> Guardar</span>
<span x-show="guardando">
<span class="spinner-border spinner-border-sm"></span> Guardando...
</span>
</button>
</div>
</template>

</div>
</div>

<!-- Actividades -->
<div class="col-12">
<div class="card">
<div class="card-header modal-colored-header bg-primary text-white d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-list-check me-2"></i> Actividades</h5>
<span class="badge rounded-pill bg-light text-primary" x-text="actividades.length"></span>
</div>

<div class="card-body p-0">
<template x-if="!puedeEditarActividades">
<template x-if="!trabajoFinalizado">
<div class="alert alert-warning rounded-0 border-0 mb-0 text-center" role="alert">
<i class="ti ti-lock fs-4 me-1"></i>
Registra primero las <b>fechas comprometidas</b> del ticket para poder modificar las actividades.
</div>
</template>
</template>

<div class="table-responsive">
<table class="table table-striped table-bordered align-middle mb-0" width="100%">
<thead>
<tr>
<th class="align-middle text-center">#</th>
<th class="align-middle text-start">Descripción de la actividad</th>
<th class="align-middle text-center">Fecha inicio</th>
<th class="align-middle text-center">Fecha término</th>
<th class="align-middle text-center">Estatus</th>
<th class="align-middle text-center" width="48px"><i class="ti ti-download text-primary fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="(actividad, indice) in actividades" :key="actividad.id">
<tr>
<td class="align-middle text-center" x-text="indice + 1"></td>
<td class="align-middle text-start" x-text="valorCampo(actividad.descripcion)"></td>
<td class="align-middle text-center p-0">
<input type="date" class="form-control p-3 border-0 bg-transparent text-center"
x-model="actividad.fecha_inicio_iso" :disabled="actividadesDeshabilitadas">
</td>
<td class="align-middle text-center p-0">
<input type="date" class="form-control p-3 border-0 bg-transparent text-center"
x-model="actividad.fecha_termino_iso" :disabled="actividadesDeshabilitadas">
</td>
<td class="align-middle text-center p-0">
<select class="form-select form-select p-3 border-0 bg-transparent text-center"
x-model.number="actividad.estado_valor"
@change="guardarActividad(actividad, 3)"
:disabled="actividadesDeshabilitadas">
<option value="0">Pendiente</option>
<option value="1">En proceso</option>
<option value="2">Finalizada</option>
</select>
</td>
<td class="align-middle text-center">
<template x-if="actividad.url && puedeDescargar">
<a :href="actividad.url" download><i class="ti ti-download text-primary fs-6"></i></a>
</template>
<template x-if="!actividad.url || !puedeDescargar">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>
</tr>
</template>
<template x-if="actividades.length === 0">
<tr>
<td colspan="6" class="text-center text-secondary">
<small>No se encontró información para este ticketóm para este ticket</small>
</td>
</tr>
</template>
</tbody>
</table>
</div>

</div>

<template x-if="!trabajoFinalizado">
<div class="card-footer">
<p class="text-muted small mb-0">
<i class="ti ti-bell me-1"></i>
Cada cambio de estatus de una actividad notifica al solicitante.
Las fechas de las actividades se envían junto con las del ticket al usar
<b>Guardar</b>.
</p>
</div>
</template>

</div>
</div>


<!-- Cierre -->
<div class="col-12">
<div class="card">
<div class="card-header modal-colored-header bg-primary text-white">
<h5 class="mb-0 text-white"><i class="ti ti-ticket me-2"></i>Finalizar ticket</h5>
</div>
<div class="card-body">
<p class="text-muted text-center">
Al finalizar, las actividades quedan como <b>Finalizadas</b> y el solicitante tiene
<b>3 días</b> para validar que la actividad se realizó. Si no lo hace, la solicitud
se finalizará automáticamente y se le notificará.
</p>

<template x-if="trabajoFinalizado">
<div class="alert alert-success text-center gap-2" role="alert">
<i class="ti ti-circle-check fs-4 me-1"></i>
El trabajo ya fue cerrado. El ticket quedó a la espera de la validación del solicitante.
</div>
</template>

<template x-if="!trabajoFinalizado">
<div class="d-grid gap-2">
<button type="button" class="btn btn-success" @click="finalizarTrabajo()"
:disabled="cierreDeshabilitado">
<span x-show="!guardando"><i class="ti ti-check"></i> Finalizar</span>
<span x-show="guardando">
<span class="spinner-border spinner-border-sm"></span> Finalizando...
</span>
</button>
</div>
</template>

</div>
</div>
</div>


</div>
</div>
