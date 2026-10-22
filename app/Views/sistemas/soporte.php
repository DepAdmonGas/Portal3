<!-- Soporte (Departamento de Sistemas) -->
<div id="soporte"
class="mt-3 mb-4"
x-data="{ ...actions(), ...soporteComponent() }"
data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
data-puede-consultar="<?= $permisos['puede_consultar'] ? 'true' : 'false' ?>"
data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>">

<div class="row g-3">

<div class="col-12">
<div class="alert alert-primary mt-2 py-3 px-4 d-flex justify-content-between align-items-center gap-3" role="alert">
<!-- Lado Izquierdo: Mensaje mejorado con ícono -->
<div class="d-flex align-items-center gap-2">
<div>
Aquí podrás crear tus solicitudes para el área de sistemas y tener el seguimiento de la solución a dichas alertas.
</div>
</div>

<!-- Lado Derecho: Menú desplegable -->
<div class="dropdown flex-shrink-0">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<?php if ($permisos['puede_agregar']): ?>
<li>
<a class="dropdown-item pointer" @click="nuevoRegistro('Actividad')">
<i class="ti ti-calendar-check me-1"></i> Nueva actividad
</a>
</li>
<?php endif; ?>
<li>
<a class="dropdown-item pointer" @click="abrirBuscar()">
<i class="ti ti-search me-1"></i> Buscar Registro
</a>
</li>
</ul>
</div>
</div>
</div>

<div class="col-12">
<div class="datatables">
<div class="table-responsive pb-4">
<table id="tabla-soporte" class="table table-striped table-bordered mb-0 w-100 align-middle"></table>
</div>
</div>
</div>
</div>

<!-- Modal: detalle del ticket -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-eye me-2"></i>
<h5 class="modal-title text-white">Detalle de ticket</h5>
<button type="button" class="btn-close btn-close-white"
data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body" x-show="detalle">

<template x-if="detalle && detalle.puede_validar">
<div class="alert alert-info py-3 px-3 mb-3 text-center gap-2" role="alert" style="font-size:.85rem;">
<i class="ti ti-info-circle fs-5 me-1"></i>

El área de Sistemas terminó la atención. Confirma que la actividad se realizó
para cerrar tu solicitud.

</div>
</template>


                    <h6 class="mb-1 form-label"># Ticket:</h6>
                    <div class="mb-3" x-text="detalle ? valorCampo(detalle.folio) : 'S/I'"></div>

                    <h6 class="mb-1 form-label">Fecha creación:</h6>
                    <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_creacion) : 'S/I'"></div>

                    <h6 class="mb-1 form-label">Descripción:</h6>
                    <div class="mb-3" x-text="detalle ? valorCampo(detalle.descripcion) : 'S/I'"></div>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Prioridad:</h6>
                            <div class="mb-3">
                                <span :class="detalle ? clasePrioridad(detalle.prioridad) : ''"
                                      x-text="detalle ? valorCampo(detalle.prioridad) : 'S/I'"></span>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha inicio:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_inicio) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha término:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_termino) : 'S/I'"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Porcentaje:</h6>
                            <div class="mb-3"><span x-text="detalle ? valorCampo(detalle.porcentaje) : 'S/I'"></span> %</div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Estatus:</h6>
                            <div class="mb-3">
                                <span :class="detalle ? claseEstado(detalle.estado) : ''"
                                      x-text="detalle ? valorCampo(detalle.estado_texto) : 'S/I'"></span>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha término real:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_termino_real) : 'S/I'"></div>
                        </div>
                    </div>

                    <template x-if="detalle && detalle.categoria === 'Actividad'">
                        <div>
                            <h5 class="mb-1 text-primary">Actividad</h5>
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
                                        <template x-for="(actividad, indice) in (detalle ? detalle.actividades : [])" :key="actividad.id">
                                            <tr>
                                                <td class="align-middle text-center" x-text="indice + 1"></td>
                                                <td class="align-middle text-start" x-text="valorCampo(actividad.descripcion)"></td>
                                                <td class="align-middle text-center" x-text="valorCampo(actividad.fecha_inicio)"></td>
                                                <td class="align-middle text-center" x-text="valorCampo(actividad.fecha_termino)"></td>
                                                <td class="align-middle text-center" x-text="valorCampo(actividad.estado)"></td>
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
                                        <template x-if="!cargandoDetalle && (!detalle || detalle.actividades.length === 0)">
                                            <tr>
                                                <td colspan="6" class="text-center text-secondary">
                                                    <small>No se encontró información</small>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="d-flex flex-wrap align-items-center justify-content-between w-100 gap-2">

                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cerrar
                            </button>
                            <template x-if="detalle && detalle.puede_validar">
                                <button type="button" class="btn bg-danger text-white" @click="solicitarRevision()">
                                    <i class="ti ti-refresh"></i> No concluido
                                </button>
                            </template>
                            <template x-if="detalle && detalle.puede_validar">
                                <button type="button" class="btn btn-success" @click="validarSolicitud()">
                                    <i class="ti ti-check"></i> Finalizar
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: solicitar la revisión de la solicitud -->
    <div class="modal fade" id="modalRevision" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-refresh me-2"></i>
                    <h5 class="modal-title text-white">¿Solicitar la revisión de la solicitud?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                        <i class="ti ti-info-circle fs-4"></i>
                        <div>
                            La solicitud vuelve a estado <b>Pendiente</b> para que el área de Sistemas continúe con el
                            seguimiento. Se enviará un aviso al responsable y quedará registrado en los comentarios.
                        </div>
                    </div>

                    <label class="form-label" for="se-motivo-revision">* Motivo:</label>
                    <textarea class="form-control" id="se-motivo-revision" rows="4" required
                        placeholder="Describe por qué no está conforme...."
                        x-model="motivoRevision"></textarea>
                </div>

                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success" @click="confirmarSolicitarRevision()">
                                <i class="ti ti-send fs-4"></i> Sí, solicitar revisión
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: buscar registro -->
    <div class="modal fade" id="modalBuscar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-search me-2"></i>
                    <h5 class="modal-title text-white">Buscar registro</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Estatus:</label>
                    <select class="form-select" x-model="filtroEstado">
                        <option value="5">Todos los registros</option>
                        <option value="0">Registros en proceso de creación</option>
                        <option value="1">Registros pendientes</option>
                        <option value="2">Registros en proceso</option>
                        <option value="3">Registros finalizados</option>
                        <option value="4">Registros cancelados</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success" @click="buscar()">
                                <i class="ti ti-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- PANEL: COMENTARIOS DEL TICKET (offcanvas)             -->
    <!-- Mismo diseno que /sala-juntas y /solicitud-cheque     -->
    <!-- ===================================================== -->
    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentarios"
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
                        Ticket (#<span x-text="comentariosData ? ('0' + comentariosData.ticket.id_ticket) : ''"></span>)
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <!-- Descripción del ticket -->
        <div class="px-3 py-2 border-bottom bg-light flex-shrink-0" x-show="comentariosData">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-1 small">
                <div><b>Prioridad:</b> <span x-text="comentariosData ? valorCampo(comentariosData.ticket.prioridad) : 'S/I'"></span></div>
                <div><b>Porcentaje:</b> <span x-text="comentariosData ? valorCampo(comentariosData.ticket.porcentaje) : 'S/I'"></span> %</div>
            </div>
            <div class="small">
                <b>Descripción:</b>
                <span x-text="comentariosData ? valorCampo(comentariosData.ticket.descripcion) : 'S/I'"></span>
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

                    <template x-if="comentariosCargados && comentariosData && comentariosData.comentarios.length === 0">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center"
                             style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-6">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
                        <template x-for="c in (comentariosData ? comentariosData.comentarios : [])" :key="c.id">
                            <div class="d-flex mb-3"
                                 :class="c.es_propio ? 'justify-content-end' : 'justify-content-start'">

                                <template x-if="!c.es_propio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center"
                                                 style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-6"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="fw-semibold mb-1" x-text="c.nombre || 'Usuario'"></label>
                                            <div class="fs-3 text-muted mb-1" x-text="valorCampo(c.fecha)"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2"
                                                 style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="c.es_propio">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-3 text-muted mb-1 text-end" x-text="valorCampo(c.fecha)"></div>
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

        <div class="px-3 py-3 border-top bg-white flex-shrink-0"
             x-show="comentariosData && comentariosData.puede_comentar">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
                        rows="1"
                        placeholder="Escribe un comentario..."
                        style="resize:none;"
                        x-model="nuevoComentario" @input="comentarioDeshabilitado = !nuevoComentario.trim()"
                        @keydown.enter.prevent="guardarComentario()"></textarea>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                        style="width:44px; height:44px;"
                        type="button"
                        @click="guardarComentario()"
                        :disabled="comentarioDeshabilitado">
                        <template x-if="!guardandoComentario">
                            <i class="ti ti-send fs-6"></i>
                        </template>
                        <template x-if="guardandoComentario">
                            <span class="spinner-border spinner-border-sm"></span>
                        </template>
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>
