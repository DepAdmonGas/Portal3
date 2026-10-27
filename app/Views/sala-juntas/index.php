<div id="container" class="pb-4"
    data-permisos="<?= htmlspecialchars(json_encode($permisos ?? []), ENT_QUOTES, 'UTF-8') ?>"
    data-id-usuario="<?= (int)($idUsuario ?? 0) ?>"
    data-nombre-usuario="<?= htmlspecialchars((string)($nombreUsuario ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    data-id-puesto-usuario="<?= (int)($idPuestoUsuario ?? 0) ?>"
    x-data="{ ...actions(), ...calendario()}">

    <div class="calender-sidebar app-calendar mt-3">


<div class="d-flex flex-column flex-md-row flex-wrap justify-content-between align-items-md-center gap-3">

    <!-- Totales -->
    <div class="d-flex align-items-center">

        <div class="border-end pe-3">
            <label class="text-muted fw-normal mb-1">
                Programadas
            </label>
            <b class="text-success fs-5"
               x-text="totales.programadas"></b>
        </div>

        <div class="ms-3 ms-md-5 border-end pe-3">
            <label class="text-muted fw-normal mb-1">
                Solicitudes
            </label>
            <b class="text-warning fs-5"
               x-text="totales.solicitudes"></b>
        </div>

        <div class="ms-3 ms-md-5">
            <label class="text-muted fw-normal mb-1">
                Total
            </label>
            <b class="fs-5"
               x-text="totales.total"></b>
        </div>

    </div>

    <!-- Botones -->
    <div class="d-grid d-md-flex gap-2 ms-md-auto">

        <button
            type="button"
            class="btn bg-primary-subtle text-primary"
            @click="abrirModalNuevaJunta()"
            :title="permisos.crear ? 'Agregar una nueva junta' : 'No tienes permiso para agregar juntas'">
            <i class="ti ti-plus"></i>
            Nuevo
        </button>

        <button
            type="button"
            class="btn btn-outline-danger"
            @click="descargarManual()">
            <i class="ti ti-file-type-pdf fs-5"></i>
            Descargar Manual
        </button>

    </div>

</div>


        <div class="text-capitalize " id="calendar"></div>

    </div>



<!---------- JUNTAS DEL DIA --------->
<div class="modal fade" id="modalDia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
<i class="ti ti-calendar-user me-1"></i> Juntas del <span x-text="fechaSeleccionada"></span></h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<div class="table-responsive">
<table class="table table-striped p-0 text-nowrap align-middle mb-0">

<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-start align-middle">Tema de la reunión</th>
<th class="text-center align-middle">Horario</th>
<th class="text-center align-middle">Estado</th>
<th class="text-center align-middle" width="96px"><i class="ti ti-message fs-7"></i></th>
</tr>
</thead>

<tbody>
<template x-for="(junta, index) in actividadesDia" :key="junta.id">
<tr class="pointer" @click="abrirJunta(junta.id)">

<td class="text-center" x-text="index + 1"></td>

<td class="text-start align-middle">
<span x-text="junta.descripcion"></span>
<div class="mt-1">
<strong x-text="junta.convoca"></strong>
</div>
</td>

<td class="text-center align-middle">
<span x-text="formatearHora12(junta.hora_inicio)"></span>
<span class="text-muted">-</span>
<span x-text="formatearHora12(junta.hora_termino)"></span>
</td>

<td class="text-center align-middle">
<span class="badge text-capitalize"
:class="{
'bg-success': ['Programada'].includes(junta.estatus),
'bg-warning': ['Horario Solicitado'].includes(junta.estatus),
'bg-danger': ['Cancelada'].includes(junta.estatus),
'bg-secondary': !['Programada', 'Horario Solicitado', 'Cancelada'].includes(junta.estatus)
}"
x-text="junta.estatus || 'S/I'"></span>
</td>

<td class="text-center align-middle">
<!-- Comentarios con el badge-historico -->
<span class="btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center"
x-show="(junta.comentarios ?? 0) > 0"
:title="junta.comentarios === 1 ? '1 comentario' : junta.comentarios + ' comentarios'">
<i class="ti ti-message fs-7"></i>
<span class="badge-historico position-absolute top-0 start-100 translate-middle"
x-text="junta.comentarios"></span>
</span>
</td>

</tr>
</template>

<template x-if="actividadesDia.length === 0">
<tr>
<td colspan="5" class="text-center text-primary py-5">
No existen juntas programadas para este día.
</td>
</tr>
</template>

</tbody>
</table>
</div>
</div>

<div class="modal-footer">
    <div class="row g-0 ms-auto w-100 justify-content-end">
        <div class="col-12 col-md-auto d-grid">
            <button
                type="button"
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal">
                <i class="ti ti-x"></i> Cerrar
            </button>
        </div>
    </div>
</div>

</div>
</div>
</div>



<!---------- MODAL DETALLE DE LA JUNTA ---------->
<div class="modal fade" id="modalJunta" tabindex="-1" aria-labelledby="modalJuntaLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header bg-primary">
<h5 class="modal-title text-white" id="modalJuntaLabel"><i class="ti ti-eye"></i> Detalle de la junta</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">


<div class="row">

<!-- Estatus -->
<div class="col-12 mb-3">
<label class="form-label mb-1">Estatus:</label>
<div>
<span class="badge" :class="{
'bg-success': ['Programada'].includes(juntaSeleccionada?.estatus),
'bg-warning': ['Horario Solicitado'].includes(juntaSeleccionada?.estatus),
'bg-danger': ['Cancelada'].includes(juntaSeleccionada?.estatus),
'bg-secondary': !['Programada', 'Horario Solicitado', 'Cancelada'].includes(juntaSeleccionada?.estatus)
}" x-text="juntaSeleccionada?.estatus || 'S/I'"></span>
</div>
</div>


<!-- Tema -->
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<label class="form-label mb-1">Tema de la reunión:</label>
<div x-text="juntaSeleccionada?.descripcion ?? ''"></div>
</div>

<!-- Fecha programada -->
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<label class="form-label mb-1">Fecha programada:</label>
<div x-text="juntaSeleccionada?.fecha_label ?? ''"></div>
</div>

<!-- Persona que convoca -->
<div class="col-12 mb-3">
<label class="form-label mb-1">Persona que convoca:</label>
<div x-text="juntaSeleccionada?.convoca ?? ''"></div>
</div>

<!-- Hora de inicio -->
<div class="col-md-6 mb-3">
<label class="form-label mb-1">Hora de inicio:</label>
<div x-text="formatearHora12(juntaSeleccionada?.hora_inicio)"></div>
</div>

<!-- Hora de termino -->
<div class="col-md-6 mb-3">
<label class="form-label mb-1">Hora de término:</label>
<div x-text="formatearHora12(juntaSeleccionada?.hora_termino)"></div>
</div>

<div class="col-12 mb-2">
<h5 class="form-label">Dirigido a:</h5>
</div>

<!-- Listado agrupado por puesto / estacion -->
<div class="col-12">

<div class="text-muted" x-show="(juntaSeleccionada?.personal ?? []).length === 0">
No hay personal asignado a esta reunión.
</div>

<template x-for="grupo in (juntaSeleccionada?.personal ?? [])" :key="grupo.puesto + (grupo.estacion ?? '')">
<div class="mb-3">
<label class="fw-semibold mb-1" x-show="!grupo.estacion" x-text="grupo.puesto"></label>

<label class="mb-1"
x-show="grupo.estacion">
<span x-text="grupo.puesto"></span>
<span class="text-dark" x-show="grupo.estacion"
x-text="'(' + grupo.estacion + ')'"></span>
</label>

<ul class="mb-0">
<template x-for="persona in (grupo.usuarios ?? [])" :key="persona.id">
<li x-text="persona.nombre"></li>
</template>
</ul>
</div>
</template>

</div>
</div>
</div>


<div class="modal-footer">
    <div class="row g-2 w-100 justify-content-end align-items-center">

        <!-- Cerrar -->
        <div class="col-12 col-md-auto d-grid">
            <button
                type="button"
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal">
                <i class="ti ti-x me-1"></i>
                Cerrar
            </button>
        </div>

        <!-- Acciones de la junta -->
        <template x-if="!juntaSeleccionada?.tieneSolicitudPendiente">
            <div class="row g-2 col-12 col-md-auto">

                <div class="col-12 col-md-auto d-grid">
                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        @click="eliminarJunta(juntaSeleccionada.id, juntaSeleccionada.descripcion)"
                        :disabled="!juntaSeleccionada?.puedeEliminar">
                        <i class="ti ti-trash me-1"></i>
                        Cancelar junta
                    </button>
                </div>

                <div class="col-12 col-md-auto d-grid">
                    <button
                        type="button"
                        class="btn bg-warning-subtle text-warning"
                        @click="abrirModalEditar(juntaSeleccionada.id)"
                        :disabled="!juntaSeleccionada?.puedeEditar">
                        <i class="ti ti-edit me-1"></i>
                        Editar
                    </button>
                </div>

                <div class="col-12 col-md-auto d-grid">
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="abrirComentarios(juntaSeleccionada)"
                        :disabled="!juntaSeleccionada?.puedeComentar">
                        <i class="ti ti-message-circle me-1"></i>
                        Comentarios
                    </button>
                </div>

                <div class="col-12 col-md-auto d-grid">
                    <button
                        type="button"
                        class="btn btn-success text-white"
                        @click="abrirSolicitudHorario(juntaSeleccionada.id)"
                        :disabled="!juntaSeleccionada?.puedeSolicitar">
                        <i class="ti ti-clock me-1"></i>
                        Solicitar horario
                    </button>
                </div>

            </div>
        </template>

    </div>
</div>




</div>
</div>
</div>

    <!-- ===================================================== -->
    <!-- ===================================================== -->
    <!-- PANEL: COMENTARIOS DE LA JUNTA (offcanvas)            -->
    <!-- Mismo diseno que /departamento-operativo/solicitud-cheque -->
    <!-- ===================================================== -->
    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentariosJunta"
        style="width: 480px; max-height: 100dvh; overflow: hidden;">

        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="position-relative">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                        style="width:48px; height:48px;">
                        <i class="ti ti-message-circle text-primary fs-7"></i>
                    </div>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS</h5>
                    <p class="mb-0 text-white opacity-75">
                        Junta (<span x-text="juntaComentarios?.tema || ''"></span>)
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <style>
            #modalComentariosJunta .actividad-ticket-field {
                min-height: 62px;
                background: var(--bs-body-bg);
                font-size: .82rem;
            }
        </style>
        <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
             x-show="juntaComentarios"
             x-cloak>
            <div class="row g-2">
                <div class="col-12">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-message-2 fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Tema de la reunión:</div>
                            <div x-text="juntaComentarios && juntaComentarios.tema ? juntaComentarios.tema : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-info-subtle text-info flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-user fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Persona que convoca:</div>
                            <div x-text="juntaComentarios && juntaComentarios.convoca ? juntaComentarios.convoca : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-success-subtle text-success flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-calendar-event fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Fecha programada:</div>
                            <div x-text="juntaComentarios && juntaComentarios.fecha ? juntaComentarios.fecha : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-warning-subtle text-warning flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-clock-hour-4 fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Hora de inicio:</div>
                            <div x-text="juntaComentarios && juntaComentarios.hora_inicio ? juntaComentarios.hora_inicio : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-danger-subtle text-danger flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-clock-hour-8 fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Hora de término:</div>
                            <div x-text="juntaComentarios && juntaComentarios.hora_termino ? juntaComentarios.hora_termino : 'S/I'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
                    style="min-height: 0; overscroll-behavior: contain;"
                    x-ref="chatContainer">

                    <template x-if="!comentariosCargados">
                        <div class="d-flex align-items-center justify-content-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </template>

                    <template x-if="comentariosCargados && comentarios.length === 0 && !guardandoComentario">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center"
                            style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
<template x-for="c in ordenarComentariosChat(comentarios)" :key="c.id">
                            <div class="d-flex mb-3"
                                :class="c.esMio ? 'justify-content-end' : 'justify-content-start'">

                                <template x-if="!c.esMio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center"
                                                style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-5"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></label>
                                            <div class="fs-3 text-muted mb-1" x-text="c.fecha_formateada || ''"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2"
                                                style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="c.esMio">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_formateada || ''"></div>
                                        <div class="p-3 bg-primary text-white rounded-3 mt-2"
                                            style="max-width: 420px;" x-text="c.comentario"></div>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>

</div>
            </div>
        </div>
        <?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'guardandoComentario']); ?>




        <!-- Regresar al detalle de la junta
        <div class="px-3 pt-2 border-top bg-white flex-shrink-0" x-show="volverAlDetalle">
            <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                @click="regresarAlDetalle()">
                <i class="ti ti-arrow-left me-1"></i>
                Regresar al detalle de la junta
            </button>
        </div>
-->


        <div class="px-3 py-3 border-top bg-white flex-shrink-0" x-show="juntaComentarios?.puedeComentar">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
                        rows="1"
                        placeholder="Escribe un comentario..."
                        style="resize:none;"
                        x-model="nuevoComentario"
                        @keydown.enter.prevent="guardarComentario()"></textarea>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                        style="width:44px; height:44px;"
                        type="button"
                        @click="guardarComentario()"
                        :disabled="guardandoComentario || !nuevoComentario.trim()">
                        <template x-if="!guardandoComentario">
                            <i class="ti ti-send fs-5"></i>
                        </template>
                        <template x-if="guardandoComentario">
                            <span class="spinner-border spinner-border-sm"></span>
                        </template>
                    </button>
                </div>
            </div>
        </div>

    </div>


<!---------- MODAL REUNIÓN ---------->
<div
class="modal fade"
id="modalNuevaJunta"
tabindex="-1"
aria-labelledby="modalNuevaJuntaLabel"
aria-hidden="true"
data-bs-backdrop="static" data-bs-keyboard="false">

<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<h5
class="modal-title text-white d-flex align-items-center gap-2"
id="modalNuevaJuntaLabel">

<i :class="idJuntaEditando > 0 ? 'ti ti-edit' : 'ti ti-calendar-plus'"></i>
<span x-text="idJuntaEditando > 0 ? 'Editar Reunión' : 'Nueva Reunión'"></span>
</h5>

<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>


<!-- BODY -->
<div class="modal-body">

<div class="row g-3">

<!-- Información mejorada sin tabla -->
<div class="col-12">
<div class="card border-0 bg-light mb-3">
<div class="card-body">
<!-- Título con icono -->
<div class="d-flex align-items-center mb-3">
<i class="ti ti-info-circle fs-4 me-2"></i>
<label class="card-title mb-0">Información para agendar una reunión</label>
</div>

<!-- Lista ordenada con mejor espaciado -->
<ol class="mb-0 ps-3">
<li class="mb-2">
Quien convoca es la persona responsable de organizar la reunión.
</li>
<li class="mb-2">
Puede elegir convocar a todo un <b>departamento</b> sin seleccionar manualmente a cada integrante.
</li>
<li class="mb-2">
También puede seleccionar únicamente al personal específico requerido.
</li>
<li>
Puede combinar ambas opciones: departamentos y personal específico.
</li>
</ol>
</div>
</div>
</div>


<!-- Tema -->
<div class="col-12">
<label class="form-label mb-1">* Tema de la reunión:</label>
<textarea class="form-control" rows="3" placeholder="Describe el tema de la reunión"
x-model="nuevaJunta.descripcion"
:class="{ 'is-invalid': erroresJunta.descripcion }"
@input="limpiarErrorJunta('descripcion')"></textarea>
</div>


<!-- Convoca -->
<div class="col-12">
<label class="form-label mb-1">Quién convoca:</label>
<div><?= htmlspecialchars((string)($nombreUsuario ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
<!-- <div class="form-text">La junta siempre queda a nombre de quien la registra.</div> -->
</div>

<!-- Fecha -->
<div class="col-12">
<label class="form-label mb-1">* Fecha:</label>
<input type="date" class="form-control"
x-model="nuevaJunta.fecha"
:class="{ 'is-invalid': erroresJunta.fecha }"
:min="fechaMinima()"
@change="limpiarErrorJunta('fecha'); cargarHorasDisponibles()">
</div>


<!-- Hora inicio -->
<div class="col-md-6">
<label class="form-label mb-1">* Hora inicio:</label>

<select class="form-select" x-model="nuevaJunta.hora_inicio"
:class="{ 'is-invalid': erroresJunta.hora_inicio }"
@change="actualizarHorasTermino(); limpiarErrorJunta('hora_inicio')"
:disabled="cargandoHoras">
<option value="">Selecciona una opción...</option>
<template
x-for="hora in horasDisponibles"
:key="hora">
<option
:value="hora"
x-text="formatearHora12(hora)"></option>
</template>
</select>

<div class="small text-muted mt-1" x-show="cargandoHoras">Consultando disponibilidad...</div>
</div>

<!-- Hora término -->
<div class="col-md-6">
<label class="form-label mb-1">* Hora término:</label>

<select class="form-select" x-model="nuevaJunta.hora_termino"
:class="{ 'is-invalid': erroresJunta.hora_termino }"
:disabled="
!nuevaJunta.hora_inicio
|| horasTermino.length === 0"
@change="limpiarErrorJunta('hora_termino')">

<option value="">Selecciona una opción...</option>

<template x-for="hora in horasTermino"
:key="hora">
<option :value="hora" x-text="formatearHora12(hora)"></option>
</template>

</select>
</div>

<!-- Sin disponibilidad -->
<div class="col-12" x-show="
nuevaJunta.fecha
&& !cargandoHoras
&& horasDisponibles.length === 0 ">
<div class="alert alert-warning mb-0">
<i class="ti ti-alert-triangle me-1"></i>
No existen horarios disponibles para esta fecha.
</div>
</div>


<!-- Departamentos -->
<div class="col-12">
<h5 class="form-label">Dirigido a:</h5>

<label class="form-label mb-1">* Departamento / Estación:</label>
<select id="dirigidoa" class="form-select"
:class="{ 'is-invalid': erroresJunta.destinatarios }" multiple></select>
<div class="form-text mt-1">El personal se convocara por estación y/o departamento completo.</div>
</div>


<!-- Personal específico -->
<div class="col-12">
<label class="form-label mb-1">* Personal:</label>
<select id="personal" class="form-select"
:class="{ 'is-invalid': erroresJunta.destinatarios }" multiple></select>
</div>

</div>
</div>

<!-- FOOTER -->
<div class="modal-footer">
    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                     <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cancelar
</button>
                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" class="btn btn-success" @click="guardarJunta()"
:disabled="guardandoJunta">

<span x-show="!guardandoJunta">

<i class="ti ti-check"></i>
<span x-text="idJuntaEditando > 0 ? 'Actualizar' : 'Guardar'"></span>

</span>

<span x-show="guardandoJunta">
<span class="spinner-border spinner-border-sm me-1"></span>
Guardando...
</span>

</button>
                    </div>
                  </div>






</div>

</div>
</div>
</div>


<!---------- MODAL SOLICITUD DE USO DE LA SALA DE JUNTAS ---------->
<div class="modal fade" id="modalSolicitud" tabindex="-1"
aria-labelledby="modalSolicitudLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white" id="modalSolicitudLabel">
    <i class="ti ti-inbox me-1"></i>
    Solicitud de uso de la sala de juntas
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
    aria-label="Cerrar"></button>
</div>

                <div class="modal-body">

                    <div class="row">

                        <!-- Motivo -->
                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">
                                * Motivo de solicitud para el uso de la sala de juntas
                            </label>
                            <textarea class="form-control" rows="3"
                                placeholder="Describe el motivo para solicitar el horario..."
                                x-model="solicitud.motivo"
                                :class="{ 'is-invalid': erroresSolicitud.motivo }"
                                @input="limpiarErrorSolicitud('motivo')"></textarea>
                        </div>

                        <!-- Tema -->
                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">
                                * Tema de la reunión
                            </label>
                            <textarea class="form-control" rows="2"
                                placeholder="Describe el tema de la reunión..."
                                x-model="solicitud.descripcion"
                                :class="{ 'is-invalid': erroresSolicitud.descripcion }"
                                @input="limpiarErrorSolicitud('descripcion')"></textarea>
                        </div>

                        <!-- Quien convoca: SELECT deshabilitado como el legacy -->
                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">* Quien convoca:</label>
                            <select class="form-select" id="solicitudConvoca" disabled></select>
                        </div>

                        <!-- Fecha programada: input date deshabilitado -->
                        <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                            <label class="form-label mb-1">* Fecha programada:</label>
                            <input type="date" class="form-control" id="solicitudFecha" disabled>
                            <!-- <div class="form-text" id="solicitudFechaLabel"></div> -->
                        </div>

                        <!-- Hora inicio: SELECT deshabilitado -->
                        <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                            <label class="form-label mb-1">* Hora inicio:</label>
                            <select class="form-control" id="solicitudHoraInicio" disabled></select>
                        </div>

                        <!-- Hora termino: SELECT deshabilitado -->
                        <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                            <label class="form-label mb-1">* Hora termino:</label>
                            <select class="form-control" id="solicitudHoraTermino" disabled></select>
                        </div>

<div class="col-12 mb-2">
<h5 class="text-primary fw-semibold">Dirigido a:</h5>
</div>

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">
                                * Departamento / Estación
                            </label>
                            <select id="solicitudDirigidoa" class="form-select"
                                :class="{ 'is-invalid': erroresSolicitud.destinatarios }" multiple></select>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label mb-1">
                                * Personal
                            </label>
                            <select id="solicitudPersonal" class="form-select"
                                :class="{ 'is-invalid': erroresSolicitud.destinatarios }" multiple></select>
                        </div>

                        <!--
                            Los errores de este modal se muestran con
                            Notify.error() (toast rojo arriba a la derecha) y
                            los campos obligatorios se marcan con is-invalid.
                        -->

                      </div>


                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary"
                        @click="guardarSolicitudHorario()"
                        :disabled="guardandoSolicitud">
                        <i class="ti ti-send me-1"></i>
                        <span x-show="!guardandoSolicitud">Enviar solicitud</span>
                        <span x-show="guardandoSolicitud">Enviando...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>


<!-- MODAL: RESPUESTA A SOLICITUD DE USO (creador)          -->
<div class="modal fade" id="modalRespuestaSolicitud" tabindex="-1" aria-labelledby="modalRespuestaSolicitudLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white" id="modalRespuestaSolicitudLabel">
<i class="ti ti-inbox me-1"></i>
Solicitud de uso de la sala de juntas
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="alert alert-warning d-flex align-items-start gap-2">
<i class="ti ti-alert-triangle fs-5 flex-shrink-0"></i>
<div>
Esta es una solicitud pendiente de resolver.
Mientras exista no se puede editar, cancelar ni comentar esta junta.
</div>
</div>

<div class="row">

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<label class="form-label mb-1">Personal que solicita el uso de la sala de juntas:</label>
<div x-text="respuestaSolicitud?.solicitante ?? ''"></div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
<label class="form-label mb-0">Tema de la reunión:</label>
<div x-text="respuestaSolicitud?.tema ?? ''"></div>
</div>

<div class="col-12 mb-3">
<label class="form-label mb-1">Motivo:</label>
<div x-text="respuestaSolicitud?.motivo ?? ''"></div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<label class="form-label mb-1">Fecha programada:</label>
<div x-text="respuestaSolicitud?.fecha_label ?? ''"></div>
</div>

<div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 mb-3">
<label class="form-label mb-1">Hora de inicio:</label>
<div x-text="formatearHora12(respuestaSolicitud?.hora_inicio)"></div>
</div>

<div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 mb-3">
<label class="form-label mb-1">Hora de término:</label>
<div x-text="formatearHora12(respuestaSolicitud?.hora_termino)"></div>
</div>

</div>
</div>

<div class="modal-footer">

<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>

<button type="button" class="btn btn-danger"
@click="responderSolicitud(false)"
:disabled="respondiendoSolicitud">
<i class="ti ti-playstation-x me-1"></i>
Denegar
</button>

<button type="button" class="btn btn-success"
@click="abrirAutorizacion()"
:disabled="respondiendoSolicitud">
<i class="ti ti-check me-1"></i>
Aceptar
</button>

</div>

</div>
</div>
</div>


<!---------- MODAL: AUTORIZACIÓN DE USO DE LA SALA DE JUNTAS   ---------->
<div class="modal fade" id="modalAutorizacion" tabindex="-1" aria-labelledby="modalAutorizacionLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white" id="modalAutorizacionLabel">
<i class="ti ti-calendar-check me-1"></i>
Autorización de uso de la sala de juntas
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="alert alert-info d-flex align-items-start gap-2">
<i class="ti ti-info-circle fs-5 flex-shrink-0"></i>
<div>
Al autorizar, <b x-text="respuestaSolicitud?.solicitante ?? 'el solicitante'"></b>
se queda con el horario que solicitó y con el tema de su reunión. <br>
Elija a qué fecha y horario se moverá <b>su</b> reunión.
Solo se muestran los horarios libres de ese día.
</div>
</div>

<div class="row g-3">

<div class="col-12">
<label class="form-label mb-1">* Nueva fecha de su reunión:</label>
<input type="date" class="form-control" x-model="autorizacion.fecha"
:class="{ 'is-invalid': erroresAutorizacion.fecha }"
:min="fechaMinima()"
@change="cargarHorasAutorizacion(); limpiarErrorAutorizacion('fecha')">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Hora de inicio:</label>
<select class="form-select" x-model="autorizacion.hora_inicio"
:class="{ 'is-invalid': erroresAutorizacion.hora_inicio }"
:disabled="cargandoHorasAutorizacion"
@change="calcularHorasTerminoAutorizacion(); limpiarErrorAutorizacion('hora_inicio')">
<option value="">Selecciona una opción...</option>
<template x-for="hora in horasAutorizacion" :key="'ai' + hora">
<option :value="hora" x-text="formatearHora12(hora)"></option>
</template>
</select>
<div class="small text-muted mt-1" x-show="cargandoHorasAutorizacion">Consultando disponibilidad...</div>
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Hora de término:</label>
<select class="form-select" x-model="autorizacion.hora_termino"
:class="{ 'is-invalid': erroresAutorizacion.hora_termino }"
:disabled="!autorizacion.hora_inicio || horasTerminoAutorizacion.length === 0"
@change="limpiarErrorAutorizacion('hora_termino')">
<option value="">Selecciona una opción...</option>
<template x-for="hora in horasTerminoAutorizacion" :key="'at' + hora">
<option :value="hora" x-text="formatearHora12(hora)"></option>
</template>
</select>
</div>

<div class="col-12" x-show="!cargandoHorasAutorizacion && autorizacion.fecha && horasAutorizacion.length === 0">
<div class="alert alert-warning mb-0 text-center">
<i class="ti ti-alert-triangle me-1"></i>
No hay horarios disponibles para esa fecha.
</div>
</div>

<div class="col-12">
<h5 class="text-primary fw-semibold">Datos de la solicitud:</h5>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<label class="form-label mb-1">Personal que solicita:</label>
<div x-text="respuestaSolicitud?.solicitante ?? ''"></div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<label class="form-label mb-1">Tema de la reunión del solicitante:</label>
<div x-text="respuestaSolicitud?.tema ?? ''"></div>
</div>

<div class="col-12">
<label class="form-label mb-1">Fecha y horario que ocupa:</label>
<div>
<span x-text="respuestaSolicitud?.fecha_label ?? ''"></span>
<span class="text-muted mx-1">de</span>
<span x-text="formatearHora12(respuestaSolicitud?.hora_inicio)"></span>
<span class="text-muted mx-1">a</span>
<span x-text="formatearHora12(respuestaSolicitud?.hora_termino)"></span>
</div>
</div>

<div class="col-12">
<label class="form-label mb-1">Motivo:</label>
<div x-text="respuestaSolicitud?.motivo ?? ''"></div>
</div>

</div>
</div>

<div class="modal-footer">

<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i>
Cancelar
</button>

<button type="button" class="btn btn-success"
@click="autorizarSolicitud()"
:disabled="respondiendoSolicitud">
<i class="ti ti-check me-1"></i>
Autorizar
</button>

</div>

</div>
</div>

</div>
</div>
