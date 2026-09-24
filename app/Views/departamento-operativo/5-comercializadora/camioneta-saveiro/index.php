<div id="container" x-data="{ ...actions(), ...camionetaSaveiroComponent() }">
    <div class="row mt-3">

        <!-- BARRA SUPERIOR: SELECTOR DE TIPO Y BOTÓN AGREGAR -->
        <div class="col-12 mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
 
<div class="d-inline-flex align-items-center bg-light border rounded-pill px-4 py-1 gap-4">
    <span class="text-muted fw-semibold text-nowrap d-flex align-items-center gap-1">
        <i class="ti ti-filter fs-4 text-primary"></i> Filtrar por:
    </span>
    <select class="form-select form-select-sm border-0 bg-transparent fw-semibold w-auto" 
            id="selectTipoDoc" 
            x-model="tipoActual" 
            @change="cambiarTipo()">
        <option value="Todos los documentos">Todos los documentos</option>
        <option value="Documentos Generales">Documentos Generales</option>
        <option value="Facturas">Facturas</option>
        <option value="Póliza de Seguro">Póliza de Seguro</option>
        <option value="Tarjeta de circulación ">Tarjeta de circulación </option>
        <option value="Tenencia">Tenencia</option>
        <option value="Servicios">Servicios</option>
        <option value="Verificación">Verificación</option>
    </select>
</div>

                <div>
                    <button type="button" 
                            class="btn bg-primary-subtle text-primary" 
                            x-show="tipoActual !== 'Todos los documentos'" 
                            x-cloak
                            @click="abrirModalCrear()">
                        <i class="ti ti-plus me-1"></i> Nuevo
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLA PRINCIPAL CON HEADER AZUL -->
        <div class="col-12">
            <div class="card">
                <div class="card-header text-bg-primary">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="ti ti-file-text me-2"></i>
                            <span x-text="tipoActual.toUpperCase()"></span>
                        </h5>
                    </div>
                </div>

                <div class="card-body">
                    <div class="datatables">
                        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                            <table id="tabla-camioneta" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR / EDITAR DOCUMENTO -->
    <div class="modal fade" id="modalDocumento" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
    <!-- Icono dinámico -->
    <i class="text-white fs-4" :class="{
        'ti ti-pencil': editando,
        'ti ti-truck': tipoActual === 'Camioneta',
        'ti ti-file-text': tipoActual !== 'Camioneta' && !editando
    }"></i>

    <!-- Título dinámico -->
    <h5 class="modal-title text-white mb-0" x-text="editando ? 'Editar Documento' : `Nuevo Documento (${tipoActual})`"></h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Fecha:</label>
                        <input type="date" 
                               class="form-control" 
                               x-model="form.fecha"
                               :style="errors.fecha ? 'border: 2px solid #A52525 !important;' : ''"
                               @input="errors.fecha = false">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Descripción:</label>
                        <input type="text" 
                               class="form-control" 
                               x-model="form.descripcion"
                               :style="errors.descripcion ? 'border: 2px solid #A52525 !important;' : ''"
                               @input="errors.descripcion = false">
                    </div>

     <div class="mb-2">
    <label class="form-label fw-semibold d-block mb-1">
        <div>
            <span x-show="!editando">* </span> Documento:
        </div>
        <small class="form-text text-muted fw-normal" x-show="editando">
            Deja el campo vacío para conservar el archivo existente.
        </small>
    </label>
    <input type="file" 
           class="form-control" 
           id="inputArchivo"
           @change="seleccionarArchivo($event)"
           :style="errors.archivo ? 'border: 2px solid #A52525 !important;' : ''">
</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i>  Cancelar</button>
                    <button type="button" class="btn btn-success" @click="guardarDocumento()" :disabled="guardando">
    
                        <template x-if="!guardando"><i class="ti ti-check"></i></template>
                        <template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
                        <span x-text="guardando ? 'Guardando...' : (editando ? 'Editar' : 'Guardar')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS COMENTARIOS -->
    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="position-relative">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="ti ti-message-circle text-primary fs-7"></i>
                    </div>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS</h5>
                    <p class="mb-0 text-white opacity-75">Documento: <span x-text="documentoSeleccionadoDescripcion"></span></p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;" x-ref="chatContainer">
                    <template x-if="comentarios.length === 0 && !cargandoComentarios">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
                        <template x-for="c in comentarios" :key="c.id">
                            <div class="d-flex mb-3" :class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">
                                <template x-if="!c.esPropio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-5"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
                                            <div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="c.esPropio">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_hora || ''"></div>
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
<input type="text" 
       class="form-control border-0 bg-light rounded-pill"
       placeholder="Escribe un comentario..."
       x-model="nuevoComentario"
       :style="errorComentario ? 'border: 2px solid #A52525 !important;' : ''"
       @input="errorComentario = false"
       @keydown.enter.prevent="agregarComentario()">
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width:44px; height:44px;" 
                            type="button"
                            @click="agregarComentario()"
                            :disabled="guardandoComentario || !nuevoComentario.trim()">
                        <template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
                        <template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>