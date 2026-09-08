<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= $idUsuario ?>"
     data-id-estacion="<?= $idEstacion ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...listaNegraComponent() }">

    <div class="row mb-3">

<div class="col-12">
<div class="dropdown float-end mb-4">

<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">

<li>
<a class="dropdown-item pointer" x-show="puedeCrear" @click="abrirAgregar()">
<i class="ti ti-plus me-1"></i> Nuevo
</a>
</li>

<li>
<a class="dropdown-item pointer" x-show="puedeCrear" @click="abrirBuscar()">
<i class="ti ti-search me-1"></i> Buscar
</a>
</li>

</ul>
</div>

</div>  

    
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-lista-negra" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Agregar -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-user-off me-2"></i>Nueva lista negra</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                    <label class="form-label">* Nombre completo:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="personalWrapper">
                                <select class="form-select" x-ref="personalSelect">
                                    <option value="">Selecciona un colaborador...</option>
                                    <?php foreach ($personal as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                         <label class="form-label">* Motivo:</label>
                            <textarea class="form-control" rows="2" x-model="agregarForm.motivo"></textarea>
                        </div>
                        <div class="col-12">
                        <label class="form-label">* Descripción:</label>

                     
                            <textarea class="form-control" rows="3" x-model="agregarForm.detalle"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
           <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    
                    
                    
                <button type="button" class="btn btn-success" @click="guardarAgregar()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Buscar -->
    <div class="modal fade" id="modalBuscar" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
<h5 class="modal-title text-white"><i class="ti ti-search me-2"></i>Buscar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">

                        <label class="form-label">* Fecha de inicio:</label>
                            <input type="date" class="form-control" x-model="buscarForm.fechaInicio">
                        </div>
                        <div class="col-12">
                                                    <label class="form-label">* Fecha de fin:</label>
                            <input type="date" class="form-control" x-model="buscarForm.fechaFin">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" @click="buscar()">
                        <i class="ti ti-search fs-5 me-1"></i>Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Comentarios -->
    <div class="offcanvas offcanvas-end d-flex flex-column"
         tabindex="-1"
         id="offcanvasComentarios"
         style="width: 480px; max-height: 100dvh; overflow: hidden;">

        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="position-relative">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
                         style="width:48px; height:48px;">
                        <i class="ti ti-message-circle fs-7 text-primary"></i>
                    </div>
                    <span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success">
                        <span class="visually-hidden">online</span>
                    </span>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS</h5>
                    <p class="mb-0 text-white opacity-75">
                        Lista Negra #<span x-text="comentarioIdActual"></span>
                    </p>
                </div>
            </div>
            <button type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">

                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
                     style="min-height: 0; overscroll-behavior: contain;"
                     x-ref="chatContainer">

                    <template x-if="comentarios.length === 0">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">

                        <template x-for="c in comentarios" :key="c.id">

                            <div class="d-flex mb-4"
                                 :class="c.esMio ? 'justify-content-end' : 'justify-content-start'">

                                <template x-if="!c.esMio">
                                    <div class="d-flex gap-3 align-items-start">

                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center"
                                                 style="width:45px; height:45px;">
                                                <i class="ti ti-user fs-6 text-white"></i>
                                            </div>
                                        </div>

                                        <div>
                                            <h6 class="fw-semibold mb-1"
                                                x-text="c.usuario_nombre || 'Usuario'"></h6>

                                            <div class="fs-3 text-muted mb-1"
                                                 x-text="c.fecha_formateada || ''"></div>

                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2"
                                                 style="max-width: 420px;"
                                                 x-text="c.comentario"></div>
                                        </div>

                                    </div>
                                </template>

                                <template x-if="c.esMio">
                                    <div class="d-flex flex-column align-items-end">

                                        <div class="fs-3 text-muted mb-1 text-end"
                                             x-text="c.fecha_formateada || ''"></div>

                                        <div class="p-3 bg-primary text-white rounded-3 mt-2"
                                             style="max-width: 420px;"
                                             x-text="c.comentario"></div>

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
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
                              rows="1"
                              placeholder="Escribe un comentario..."
                              style="resize:none;"
                              x-model="nuevoComentario"
                              @keydown.enter.prevent="agregarComentario()"></textarea>
                </div>

                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width:44px; height:44px;"
                            type="button"
                            @click="agregarComentario()"
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

    <!-- Modal Pruebas (archivos) -->
    <div class="modal fade" id="modalPruebas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pruebas - Lista Negra #<span x-text="pruebasIdActual"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label text-secondary fw-bold">* Descripción:</label>
                            <input type="text" class="form-control" x-model="archivoForm.descripcion">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label text-secondary fw-bold">* Archivo:</label>
                            <input type="file" class="form-control" id="archivoInput">
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-success w-100"
                                    @click="subirArchivo()"
                                    :disabled="subiendoArchivo">
                                <template x-if="!subiendoArchivo"><i class="ti ti-check fs-5 me-1"></i></template>
                                <template x-if="subiendoArchivo">
                                    <span class="spinner-border spinner-border-sm"></span>
                                </template>
                                Guardar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive overflow-x-auto overflow-y-hidden">
                        <table class="table table-striped table-bordered mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">Descripción</th>
                                <th class="text-center" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
                                <th class="text-center" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="arch in archivos" :key="arch.id">
                                <tr>
                                    <td class="text-center fw-normal" x-text="arch.descripcion"></td>
                                    <td class="text-center">
                                        <a href="" @click.prevent="downloadArchivo(arch.archivo)">
                                            <i class="ti ti-download text-primary fs-5"></i>
                                        </a>
                                    </td>
                                    <td class="text-center" x-show="puedeEliminar">
                                        <a href="" @click.prevent="eliminarArchivo(arch.id)">
                                            <i class="ti ti-trash text-danger fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="archivos.length === 0">
                                <tr>
                                    <td colspan="3" class="text-center text-primary">No se encontró información</td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>