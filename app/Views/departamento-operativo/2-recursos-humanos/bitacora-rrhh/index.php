<div id="container" class="mt-4 mb-5"
     data-id-year="<?= (int)$idYear ?>"
     data-id-mes="<?= (int)$idMes ?>"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-multiestacion="<?= !empty($multiestacion) ? 'true' : 'false' ?>"
     data-id-estacion="<?= (int)($idEstacion ?? 0) ?>"
     data-contexto-nombre="<?= htmlspecialchars($contextoNombre ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-crear="<?= isset($puedeCrear) && $puedeCrear ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= isset($puedeEliminar) && $puedeEliminar ? 'true' : 'false' ?>"
     data-puede-descargar="<?= isset($puedeDescargar) && $puedeDescargar ? 'true' : 'false' ?>"
     data-puede-finalizar="<?= isset($puedeFinalizar) && $puedeFinalizar ? 'true' : 'false' ?>"
     data-puede-eliminar-doc="<?= isset($puedeEliminarDoc) && $puedeEliminarDoc ? 'true' : 'false' ?>"
     data-puede-ver-visualizaciones="<?= isset($puedeVerVisualizaciones) && $puedeVerVisualizaciones ? 'true' : 'false' ?>"
     data-total-pendientes="<?= (int)($totalPendientes ?? 0) ?>"
     x-data="{ ...actions(), ...bitacoraRrhhComponent() }">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
 
    <!-- Indicador a la izquierda -->
    <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
            <i class="ti ti-alert-circle fs-4"></i>
            <span>Pendientes: <span id="br-pending-count"><?= (int)($totalPendientes ?? 0) ?></span></span>
        </span>
    </div>

    <!-- Botón a la derecha -->
<button type="button" class="btn bg-primary-subtle text-primary" x-show="contextoEspecifico && puedeCrear" @click="abrirCrear()">
<i class="ti ti-plus"></i> Nuevo
</button>


</div>
 

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-bitacora-rrhh" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-book-2 me-2"></i>Nuevo registro de bitácora</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">* Descripción:</label>
                            <textarea class="form-control" rows="4" x-model="crearForm.descripcion" placeholder="Describe la incidencia o solicitud..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="guardarCrear()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
                        <template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Comentarios -->
    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                    <i class="ti ti-message-circle fs-7 text-primary"></i>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS</h5>
                    <p class="mb-0 text-white opacity-75">Bitácora #0<span x-text="comentarioIdActual"></span></p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;" x-ref="chatContainer">
                    <template x-if="comentarios.length === 0">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>
                    <div class="chat-list active-chat p-2">
                        <template x-for="c in comentarios" :key="c.id">
                            <div class="d-flex mb-4" :class="c.esMio ? 'justify-content-end' : 'justify-content-start'">
                                <template x-if="!c.esMio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                                <i class="ti ti-user fs-6 text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
                                            <div class="fs-3 text-muted mb-1" x-text="c.fecha_formateada || ''"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="c.esMio">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_formateada || ''"></div>
                                        <div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        <div class="px-3 py-3 border-top bg-white flex-shrink-0">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;"
                              x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px;" type="button"
                            @click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
                        <template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
                        <template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div class="modal fade" id="modalDetalle" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white">
    <i class="ti ti-eye me-2"></i>Detalle Bitácora RRHH <span x-text="'(#0' + docIdActual + ')'"></span>
</h5>                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
      <div class="row g-3 mb-3">
    <div class="col-12 mb-1">
        <label class="form-label">Fecha y hora:</label>
        <div x-text="detalle ? (detalle.fecha || 'Sin información') : '...'"></div>
    </div>
    
    <div class="col-md-5 mb-1">
        <label class="form-label">Nombre del solicitante:</label>
        <div x-text="detalle ? (detalle.nombre_solicitante || 'Sin información') : '...'"></div>
    </div>
    
    <div class="col-md-7 mb-1">
        <label class="form-label">Estación / Departamento:</label>
        <div x-text="detalle ? (detalle.estacion || 'Sin información') : '...'"></div>
    </div>
    
    <div class="col-12 mb-1">
        <label class="form-label">Descripción:</label>
        <div x-text="detalle ? (detalle.descripcion || 'Sin información') : '...'"></div>
    </div>
</div>
                    <div class="row">
                        <div class="col-12">
                            <h5 class="form-label text-primary fw-bold">DOCUMENTACIÓN</h5>
                        </div>

                        <div class="col-12 mb-3">
                                    <label class="form-label">* Nombre del documento:</label>
                            <input type="text" class="form-control" x-model="docForm.nombre">
                        </div>

<div class="col-12 mb-3">
<label class="form-label">* Documento:</label>
    <div class="input-group">
        <input type="file" class="form-control" id="docInput">
        <button type="button" class="btn btn-success" @click="subirDocumento()" :disabled="subiendoDoc">
            <template x-if="!subiendoDoc"><i class="ti ti-check fs-5 me-1"></i></template>
            <template x-if="subiendoDoc"><span class="spinner-border spinner-border-sm me-1"></span></template>
            Guardar
        </button>
    </div>
</div>

                    </div>


                    <div class="table-responsive overflow-x-auto overflow-y-hidden mt-3">
                        <table class="table table-striped table-bordered mb-0">
                            <thead>
                            <tr>
                                <th class="text-center align-middle" width="64px">#</th>
                                <th class="text-center align-middle">Nombre</th>
                                <th class="text-center align-middle">Subido por</th>
                                <th class="text-center align-middle" width="48px" x-show="puedeDescargar"><i class="ti ti-download text-primary fs-5"></i></th>
                                <th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="doc in documentos" :key="doc.id">
                                <tr>
                                    <td class="text-center align-middle" x-text="'0'+doc.id"></td>

                                    <td class="text-center align-middle" x-text="doc.nombre"></td>
                                    <td class="text-center align-middle" x-text="doc.subido_por"></td>
                                    <td class="text-center align-middle" x-show="puedeDescargar">
                                        <a href="" @click.prevent="downloadDocumento(doc.archivo)"><i class="ti ti-download text-primary fs-5"></i></a>
                                    </td>
                                    <td class="text-center align-middle" x-show="puedeEliminarDoc">
                                        <a href="" @click.prevent="eliminarDocumento(doc.id)"><i class="ti ti-trash text-danger fs-5"></i></a>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="documentos.length === 0">
                                <tr><td colspan="5" class="text-center text-primary">No se encontró información</td></tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>

<div class="modal-footer">
                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                    <i class="ti ti-x"></i> Cancelar
                </button>
</div>


            </div>
        </div>
    </div>

    <!-- Modal Visualizaciones -->
    <div class="modal fade" id="modalVisualizaciones" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-user-plus"></i>
Visualizaciones Bitácora RRHH <span x-text="'(#0' + visIdActual + ')'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>


                <div class="modal-body">
                    <div class="table-responsive overflow-x-auto">
                        <table class="table table-striped table-bordered mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">Nombre del personal</th>
                                <th class="text-center">Fecha</th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="v in visualizaciones" :key="v.id">
                                <tr>
                                    <td class="text-center fw-normal" x-text="v.usuario_nombre"></td>
                                    <td class="text-center fw-normal" x-text="v.fecha_hora"></td>
                                </tr>
                            </template>
                            <template x-if="visualizaciones.length === 0">
                                <tr><td colspan="2" class="text-center text-primary">No se encontró información</td></tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
