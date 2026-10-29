<div class="modal fade" id="modal-detalle-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-eye me-1"></i> Detalle Orden de Mantenimiento</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body pb-0"><div id="orden-detalle-contenido" class="row g-2"></div></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-evidencia-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white"><i class="ti ti-paperclip fs-7 me-1"></i><h4 class="modal-title text-white">Evidencias <span id="orden-evidencia-folio"></span></h4><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div id="orden-evidencia-subir" class="col-12 mb-3">
                    <label for="orden-modal-evidencia-archivo" class="form-label mb-1">* Evidencia:</label>
                    <div class="input-group">
                        <input id="orden-modal-evidencia-archivo" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <button id="orden-modal-evidencia-agregar" type="button" class="btn btn-success"><i class="ti ti-check me-1"></i> Guardar</button>
                    </div>
                </div>
                <div id="orden-modal-evidencia-lista" class="row g-3"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-causa-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white"><i class="ti ti-help-circle fs-7 me-1"></i><h4 class="modal-title text-white">Causa</h4><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div id="orden-causa-form" class="row g-3 mb-3">
                    <div class="col-12"><label for="orden-descripcion-causa" class="form-label mb-1">* Descripción de la causa:</label><textarea id="orden-descripcion-causa" class="form-control" rows="2" maxlength="2000"></textarea></div>
                    <div class="col-12 col-md-6"><label for="orden-refaccion-causa" class="form-label mb-1">* La refacción se queda en:</label><select id="orden-refaccion-causa" class="form-select"><option value="">Selecciona una opción...</option><option value="Estación">Estación</option><option value="Almacen">Almacen</option></select></div>
                    <div class="col-12 col-md-6"><label for="orden-precio-causa" class="form-label mb-1">Precio:</label><input id="orden-precio-causa" class="form-control" type="number" min="0" step="0.01"></div>
                    <div class="col-12"><label for="orden-pdf-causa" class="form-label mb-1">Factura PDF:</label><div class="input-group"><input id="orden-pdf-causa" class="form-control" type="file" accept=".pdf"><button id="orden-guardar-causa" class="btn btn-success" type="button"><i class="ti ti-check me-1"></i> Guardar</button></div></div>
                </div>
                <div id="orden-modal-causa-lista" class="table-responsive"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button></div>
        </div></div>
    </div>

    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvas-comentarios-orden" style="width:480px;max-height:100dvh;overflow:hidden">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="ti ti-message-circle text-primary fs-7"></i></div>
                <div><h5 class="mb-1 text-white">COMENTARIOS</h5><p class="mb-0 text-white opacity-75">Orden de Mantenimiento (#<span id="orden-comentarios-folio"></span>)</p></div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"><div id="orden-comentario-detalle" class="row g-2"></div></div>
        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height:0">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height:0">
                <div id="orden-comentarios-lista" class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height:0;overscroll-behavior:contain"></div>
                <div id="orden-enviando" class="chat-comment-sending-state px-3 pb-3" role="status" aria-live="polite" hidden>
                    <div class="d-flex justify-content-end"><div class="border border-primary-subtle rounded-4 bg-primary-subtle p-3" style="max-width:420px;width:100%">
                        <div class="d-flex align-items-center gap-3"><div class="flex-shrink-0 rounded-circle bg-white d-flex align-items-center justify-content-center text-primary" style="width:52px;height:52px"><i class="ti ti-send fs-4"></i></div><div><div class="fw-semibold text-primary">Enviando mensaje...</div><div class="small text-muted">Estamos entregando tu comentario</div></div></div>
                        <div class="progress rounded-pill mt-3" style="height:4px"><div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div></div>
                    </div></div>
                </div>
            </div>
        </div>
        <div class="px-3 py-3 border-top bg-white flex-shrink-0"><div class="d-flex align-items-center gap-2">
            <div class="flex-grow-1"><textarea id="orden-nuevo-comentario" class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none"></textarea></div>
            <div class="flex-shrink-0"><button id="orden-enviar-comentario" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px" type="button" aria-label="Enviar comentario"><i class="ti ti-send fs-5"></i></button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-refaccion-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-tool me-1"></i> Nueva refacción</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="mb-3 select2-modal-field"><label for="orden-refaccion" class="form-label mb-1">* Refacción:</label><select id="orden-refaccion" class="form-select" aria-describedby="orden-refaccion-ayuda"><option value="">Otra...</option></select><div id="orden-refaccion-ayuda" class="form-text">Selecciona una refacción del catálogo o captura otra.</div></div>
                <div class="mb-3"><label for="orden-otra-refaccion" class="form-label mb-1">Otra refacción:</label><input id="orden-otra-refaccion" class="form-control" maxlength="250" autocomplete="off"></div>
                <div><label for="orden-cantidad-refaccion" class="form-label mb-1">* Cantidad (mínimo 1):</label><input id="orden-cantidad-refaccion" class="form-control" type="number" min="1" step="0.01" inputmode="decimal"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button><button id="orden-guardar-refaccion" type="button" class="btn btn-success"><i class="ti ti-check me-1"></i> Guardar</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-area-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-map-pin me-1"></i> Nueva área</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><label for="orden-nombre-area" class="form-label mb-1">* Área:</label><input id="orden-nombre-area" class="form-control" maxlength="180" autocomplete="off"></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button><button id="orden-guardar-area" class="btn btn-success" type="button"><i class="ti ti-check me-1"></i> Guardar</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-opciones-area-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header bg-primary"><h5 id="orden-opciones-area-titulo" class="modal-title text-white"><i class="ti ti-map-pin me-1"></i> Opciones del área</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body p-0"><div id="orden-opciones-area-lista" class="table-responsive"></div></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="modal-agregar-evidencia-orden" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-paperclip me-1"></i> Nueva evidencia</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><div id="orden-subir-evidencia" class="row g-3">
                <div class="col-12"><label for="orden-archivo-evidencia" class="form-label mb-1">* Archivo:</label><input id="orden-archivo-evidencia" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"></div>
                <div class="col-12 col-md-6"><label for="orden-area-evidencia" class="form-label mb-1">* Área:</label><input id="orden-area-evidencia" class="form-control" maxlength="255"></div>
                <div class="col-12 col-md-6"><label for="orden-motivo-evidencia" class="form-label mb-1">* Motivo:</label><input id="orden-motivo-evidencia" class="form-control" maxlength="1000"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button><button id="orden-guardar-evidencia" type="button" class="btn btn-success"><i class="ti ti-check me-1"></i> Guardar</button></div>
        </div></div>
    </div>

