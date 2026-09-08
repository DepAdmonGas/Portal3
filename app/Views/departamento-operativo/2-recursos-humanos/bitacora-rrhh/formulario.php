<div id="formulario-bitacora-rrhh" class="mt-3 mb-4"
     data-id-bitacora="<?= (int)$detalle['id'] ?>"
     data-id-year="<?= (int)$detalle['year'] ?>"
     data-id-mes="<?= (int)$detalle['mes'] ?>"
     data-estatus="<?= (int)$detalle['estatus'] ?>"
     data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
     data-puede-finalizar="<?= $puedeFinalizar ? 'true' : 'false' ?>"
     data-puede-eliminar-doc="<?= $puedeEliminarDoc ? 'true' : 'false' ?>"
     data-puede-ver-visualizaciones="<?= $puedeVerVisualizaciones ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...bitacoraRrhhFormularioComponent() }">

    <div class="row">
        <div class="col-12 mb-3">
            <button type="button" class="btn btn-success float-end"
                    x-show="puedeFinalizar && estatus == 0"
                    @click="finalizar()" :disabled="finalizando">
                <template x-if="!finalizando"><i class="ti ti-check fs-5 me-1"></i></template>
                <template x-if="finalizando"><span class="spinner-border spinner-border-sm me-1"></span></template>
                Finalizar
            </button>
        </div>
    </div>

    <div class="row">

            <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12 mb-3">


    <div class="card mb-3">
        <div class="card-header text-bg-primary">
            <h5 class="mb-0 text-white"><i class="ti ti-info-circle me-2"></i>DETALLE DE LA SOLICTUD</h5>
        </div>
        <div class="card-body pb-1">
<div class="row g-3 mb-3">
    <div class="col-md-4 mb-1">
        <label class="form-label">Fecha y hora:</label>
        <div><?= htmlspecialchars($detalle['fecha'] ?? 'Sin información', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    
    <div class="col-md-4 mb-1">
        <label class="form-label">Nombre del solicitante:</label>
        <div><?= htmlspecialchars($detalle['nombre_solicitante'] ?? 'Sin información', ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="col-md-4 mb-1">
        <label class="form-label">Descripción:</label>
        <div><?= htmlspecialchars($detalle['descripcion'] ?? 'Sin información', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</div>
        </div>
    </div>


            <div class="card mb-3">
                <div class="card-header text-bg-primary">
                    <h5 class="mb-0 text-white"><i class="ti ti-file-text me-2"></i>DOCUMENTACIÓN</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                                    <label class="form-label">* Nombre del documento:</label>
                            <input type="text" class="form-control" x-model="docForm.nombre" placeholder="Ingresa aquí el nombre del documento...">
                        </div>

      <div class="col-12 mb-3">
                                    <label class="form-label">* Nombre del documento:</label>
    <div class="input-group">
        <input type="file" class="form-control" x-ref="docInput">
        <button type="button" class="btn btn-success" @click="subirDocumento()" :disabled="subiendoDoc">
            <template x-if="!subiendoDoc"><i class="ti ti-check fs-5 me-1"></i></template>
            <template x-if="subiendoDoc"><span class="spinner-border spinner-border-sm me-1"></span></template>
            Guardar
        </button>
    </div>
</div>

                    </div>

                    <div class="table-responsive overflow-x-auto overflow-y-hidden">
                        <table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                            <thead>
                            <tr>
                                <th class="text-center" width="64px">#</th>
                                <th class="text-center">Nombre del documento</th>
                                <th class="text-center">Subido por</th>
                                <th class="text-center" width="48px" x-show="puedeDescargar"><i class="ti ti-download text-primary fs-5"></i></th>
                                <th class="text-center" width="48px" x-show="puedeEliminarDoc"><i class="ti ti-trash text-danger fs-5"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="(doc, index) in documentos" :key="doc.id">
                                <tr>
                                    <td class="text-center fw-bold" x-text="doc.id"></td>
                                    <td class="text-center fw-normal" x-text="doc.nombre"></td>
                                    <td class="text-center fw-normal" x-text="doc.subido_por"></td>
                                    <td class="text-center" x-show="puedeDescargar">
                                        <a href="" @click.prevent="downloadDocumento(doc.archivo)"><i class="ti ti-download text-primary fs-5"></i></a>
                                    </td>
                                    <td class="text-center" x-show="puedeEliminarDoc">
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
            </div>
        </div>
<!-- COMENTARIOS -->
<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 mb-4">
    <div class="card overflow-hidden ">

        <!-- HEADER -->
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary">
            <div class="hstack gap-3">
                <div class="position-relative">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="ti ti-message-circle text-primary fs-7"></i>
                    </div>
                    <span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success"><span class="visually-hidden">online</span></span>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS (FEED BACK)</h5>
                    <p class="mb-0 text-white opacity-75">Conversación activa</p>
                </div>
            </div>
        </div>

        <!-- BODY -->
        <div class="d-flex parent-chat-box">
            <div class="chat-box w-100 h-100">

                <!-- LISTA -->
                <div class="chat-box-inner p-3" style="max-height: 420px; overflow-y: auto;" x-ref="chatContainer">

                    <!-- SIN COMENTARIOS -->
                    <template x-if="comentarios.length === 0">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">No se encontraron comentarios.</p>
                        </div>
                    </template>

                    <!-- COMENTARIOS -->
                    <div class="chat-list active-chat p-2">
                        <template x-for="c in comentarios" :key="c.id">
                            <div class="d-flex mb-4" :class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">

                                <!-- MENSAJES OTROS -->
                                <template x-if="!c.esPropio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-6"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
                                            <div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2 text-break" style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>

                                <!-- MIS MENSAJES -->
                                <template x-if="c.esPropio">
                                    <div class="d-flex flex-column align-items-end">
                                       <!--  <div class="fs-3 text-muted mb-1 text-end" x-text="c.usuario_nombre"></div>-->
                                        <div class="fs-3 text-muted mt-1 text-end" x-text="c.fecha_hora"></div>

                                        <div class="p-3 bg-primary text-white rounded-3 mt-2 text-break" style="max-width: 420px;" x-text="c.comentario"></div>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="px-3 py-3 border-top chat-send-message-footer bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;"
                                      x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
                        </div>
                        <div class="flex-shrink-0">
                            <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px;"
                                    type="button" @click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
                                <template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
                                <template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
    </div>

</div>