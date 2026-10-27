<!-- Actividades (Sistemas) -->

<style>
    /* Fila pendiente de asignar responsable: se puede pulsar para asignarlo.
       Bootstrap 5.3 pinta el fondo de las celdas con box-shadow inset, por
       eso se fijan la variable y el box-shadow para que el table-striped
       no tape el color. */
    .se-fila-asignable {
        --bs-table-bg-type: #fcfcda;
    }

    .se-fila-asignable > td {
        cursor: pointer;
        background-color: #fcfcda !important;
        box-shadow: inset 0 0 0 9999px #fcfcda !important;
    }

    #modalComentarios .actividad-ticket-field {
        min-height: 62px;
        background: var(--bs-body-bg);
        font-size: .82rem;
    }

    #modalComentarios .actividad-ticket-field > div > div:last-child {
        color: var(--bs-body-color);
        font-size: .78rem;
        overflow-wrap: anywhere;
    }

    #modalComentarios .actividad-ticket-description {
        font-size: .8rem;
        max-height: 128px;
        overflow-y: auto;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        scrollbar-width: thin;
    }


</style>

<div id="soporte-actividades"
     class="mt-3 mb-4"
     x-data="{ ...actions(), ...soporteEstacionesComponent() }"
     data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
     data-endpoint="/sistemas/actividades"
     data-eventos="soporte-actividades"
     data-tabla-id="tabla-soporte-actividades"
     data-filas-asignables="false"
     data-puede-consultar="<?= $permisos['puede_consultar'] ? 'true' : 'false' ?>"
     data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-puede-filtrar-responsable="<?= $permisos['puede_filtrar_responsable'] ? 'true' : 'false' ?>"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-mostrar-estacion="false"
     data-responsables="<?= htmlspecialchars(json_encode($responsables, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

    <div class="row g-3">
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-2 gap-2 flex-wrap">

        <!-- Izquierda: Badge de pendientes -->
        <div id="se-pending-wrapper" class="d-flex align-items-center gap-1">
            <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
                <i class="ti ti-alert-circle fs-4"></i>
                <span>Pendientes / En Proceso: <span id="se-pending-count"><?= (int) $pendientesContexto ?></span></span>
            </span>
        </div>

        <!-- Derecha: Responsable (con estilo de píldora) y Dropdown -->
        <div class="d-flex align-items-center gap-2 flex-wrap">

            <!-- Contenedor tipo píldora para el Responsable. Lo imprime el
                 Controller sólo para el usuario 496 (id_puede_filtrar_responsable). -->
            <?= $filtroResponsableHtml ?? '' ?>

            <!-- Dropdown -->
<!-- Lado Derecho: Menú desplegable -->
<div class="dropdown flex-shrink-0">
    <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="ti ti-dots-vertical fs-4"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li  x-show="puedeAgregar"> <!-- O x-show="puedeAgregar" -->
            <a class="dropdown-item pointer"@click="nuevoRegistro()">
                <i class="ti ti-plus me-1"></i> Nueva Actividad
            </a>
        </li>
        <li>
            <a class="dropdown-item pointer" @click="abrirBuscar()">
                <i class="ti ti-search me-1"></i> Buscar
            </a>
        </li>
    </ul>
</div>
        </div>

    </div>
</div>

        <div class="col-12">
            <div class="datatables">
                <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                    <table id="tabla-soporte-actividades" class="table table-striped table-bordered mb-0 w-100 align-middle"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: detalle del ticket (sin comentarios) -->
    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-eye me-2"></i>
                    <h5 class="modal-title text-white">Detalle del ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body" x-show="detalle">

                    <template x-if="detalle && !detalle.tiene_responsable">
                        <div class="alert alert-warning text-center gap-2 mb-4" role="alert">
                            <i class="ti ti-info-circle fs-4 me-1"></i>
                                Este ticket todavía no tiene responsable asignado.
                        </div>
                    </template>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label"># Ticket:</h6>
                            <div class="mb-3"><b x-text="detalle ? valorCampo(detalle.folio) : 'S/I'"></b></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Estación / Departamento:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.estacion) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Solicitante:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.solicitante) : 'S/I'"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha creación:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_creacion) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Prioridad:</h6>
                            <div class="mb-3">
                                <span :class="detalle ? clasePrioridad(detalle.prioridad) : ''"
                                      x-text="detalle ? valorCampo(detalle.prioridad) : 'S/I'"></span>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Estatus:</h6>
                            <div class="mb-3">
                                <span :class="detalle ? claseEstado(detalle.estado) : ''"
                                      x-text="detalle ? valorCampo(detalle.estado_texto) : 'S/I'"></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-1 form-label">Descripción:</h6>
                    <div class="mb-3" x-text="detalle ? valorCampo(detalle.descripcion) : 'S/I'"></div>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Responsable:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.responsable) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Porcentaje:</h6>
                            <div class="mb-3"><span x-text="detalle ? valorCampo(detalle.porcentaje) : 'S/I'"></span> %</div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha término real:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_termino_real) : 'S/I'"></div>
                        </div>
                    </div>


                    <h5 class="mb-2 text-primary">Fechas comprometidas</h5>

                    <div class="row">
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha inicio:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_inicio_texto) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Fecha término:</h6>
                            <div class="mb-3" x-text="detalle ? valorCampo(detalle.fecha_termino_texto) : 'S/I'"></div>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12">
                            <h6 class="mb-1 form-label">Tiempo solución:</h6>
                            <div class="mb-3"><span x-text="detalle ? valorCampo(detalle.tiempo_solucion) : 'S/I'"></span> días</div>
                        </div>
                    </div>

                    <h5 class="mb-2 text-primary">Actividades</h5>

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
                                            <template x-if="actividad.url">
                                                <a :href="actividad.url" download><i class="ti ti-download text-primary fs-6"></i></a>
                                            </template>
                                            <template x-if="!actividad.url">
                                                <i class="ti ti-file-off text-muted fs-6"></i>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!detalle || detalle.actividades.length === 0">
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

                <div class="modal-footer">
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
    <!-- Modal: solicitar la revisión de la actividad -->
    <div class="modal fade" id="modalRevision" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i class="ti ti-refresh me-2"></i>
                    <h5 class="modal-title text-white">¿Solicitar la revisión de la actividad?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                        <i class="ti ti-info-circle fs-4"></i>
                        <div>
                            La actividad vuelve a estado <b>Pendiente</b> para que el área de Sistemas continúe con el
                            seguimiento. Se enviará un aviso al responsable y quedará registrado en los comentarios.
                        </div>
                    </div>

                    <label class="form-label" for="se-motivo-revision">* Motivo:</label>
                    <textarea class="form-control" id="se-motivo-revision" rows="4" required
                        placeholder="Describe por qué no está conforme..."
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
<div class="mb-3">
<label class="form-label" for="se-buscar-texto">Folio o descripción:</label>
<input type="text" class="form-control" id="se-buscar-texto"
placeholder="Escribe el folio o parte de la descripción" x-model="textoBusqueda">
</div>
<label class="form-label">Estatus:</label>
<select class="form-select" x-model="filtroEstado">
<option value="5">Todos los registros</option>
<option value="1">Registros pendientes</option>
<option value="2">Registros en proceso</option>
<option value="3">Registros finalizados</option>
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
    <!-- Mismo diseño que /sistemas/soporte y /sala-juntas     -->
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

        <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
             x-show="comentariosData && comentariosData.ticket"
             x-cloak>
            <div class="row g-2">
                <div class="col-6">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-hash fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0"># ticket:</div>
                            <div x-text="comentariosData ? comentariosData.ticket.id_ticket : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-warning-subtle text-warning flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-bolt fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Prioridad:</div>
                            <div x-text="comentariosData ? valorCampo(comentariosData.ticket.prioridad) : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-info-subtle text-info flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-user-cog fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Responsable:</div>
                            <div x-text="comentariosData && comentariosData.ticket.responsable ? comentariosData.ticket.responsable : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-start gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-success-subtle text-success flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-align-left fs-5"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="form-label small mb-0">Descripción:</div>
                            <div class="actividad-ticket-description" x-text="comentariosData ? valorCampo(comentariosData.ticket.descripcion) : 'S/I'"></div>
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

                    <template x-if="comentariosCargados && comentariosData && comentariosData.comentarios.length === 0 && !guardandoComentario">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center"
                             style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-6">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
                        <template x-for="c in ordenarComentariosChat(comentariosData ? comentariosData.comentarios : [])" :key="c.id">
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
        <?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'guardandoComentario']); ?>



        <div class="px-3 py-3 border-top bg-white flex-shrink-0"
             x-show="comentariosData">
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
