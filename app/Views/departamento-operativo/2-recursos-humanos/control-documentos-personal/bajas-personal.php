<div id="bajas-personal"
     data-puede-editar="<?= !empty($puedeEditarBaja) ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= !empty($puedeEliminarBaja) ? 'true' : 'false' ?>"
     data-pendientes="<?= (int)($pendientesActuales ?? 0) ?>">
    <div id="bajas-tools-header" class="d-flex align-items-center justify-content-between mb-2 gap-2 mb-3">
        <div class="d-flex align-items-center gap-1">
            <span class="badge rounded-pill bg-danger-subtle mt-3 mb-2 text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold" id="bajas-pendientes-badge">
                <i class="ti ti-alert-circle fs-4"></i>
                <span>Pendientes: <span id="bajas-pendientes-count"><?= (int)($pendientesActuales ?? 0) ?></span></span>
            </span>
        </div>
        <div></div>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-5">
            <table id="table-bajas-personal" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
                <thead>
                    <tr class="text-center">
                        <th>#</th>
                        <th class="bajas-col-estacion">Estación</th>
                        <th>Nombre</th>
                        <th>Puesto</th>
                        <th>Fecha de baja</th>
                        <th>Motivo</th>
                        <th>Descripción</th>
                        <th>Proceso</th>
                        <th>Estatus</th>
                        <th class="text-center align-middle" width="48px"><i class="ti ti-message fs-7" title="Comentarios"></i></th>
                        <th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6" title="Opciones"></i></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<style>
    #offcanvasComentariosBajaPersonal .actividad-ticket-field {
        min-height: 62px;
        background: var(--bs-body-bg);
        font-size: .82rem;
    }
</style>
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentariosBajaPersonal" style="width: 480px; max-height: 100dvh; overflow: hidden;">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
        <div class="hstack gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                <i class="ti ti-message-circle fs-7 text-primary"></i>
            </div>
            <div>
                <h5 class="mb-1 text-white">COMENTARIOS</h5>
                <p class="mb-0 text-white opacity-75">Baja #<span id="baja-personal-comentario-id">—</span></p>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0">
        <div class="row g-2">
            <div class="col-3"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-hash fs-5"></i></span><div class="flex-grow-1 overflow-hidden"><div class="form-label small mb-0">ID:</div><div id="baja-personal-comentario-id-card"></div></div></div></div>
            <div class="col-9"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-calendar-event fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Fecha de baja:</div><div id="baja-personal-comentario-fecha"></div></div></div></div>
            <div class="col-12"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-info-subtle text-info flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-user fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Nombre completo:</div><div id="baja-personal-comentario-nombre"></div></div></div></div>
            <div class="col-12"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-warning-subtle text-warning flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-alert-circle fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Motivo:</div><div id="baja-personal-comentario-motivo"></div></div></div></div>
            <div class="col-12"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-success-subtle text-success flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-align-left fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Descripción:</div><div id="baja-personal-comentario-detalle"></div></div></div></div>
            <div class="col-6"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-secondary-subtle text-secondary flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-progress-check fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Proceso:</div><div id="baja-personal-comentario-proceso"></div></div></div></div>
            <div class="col-6"><div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100"><span class="d-inline-flex align-items-center justify-content-center rounded bg-danger-subtle text-danger flex-shrink-0" style="width:34px;height:34px;"><i class="ti ti-flag fs-5"></i></span><div class="flex-grow-1"><div class="form-label small mb-0">Estatus:</div><div id="baja-personal-comentario-estatus"></div></div></div></div>
        </div>
    </div>
    <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height:0;">
        <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height:0;">
            <div id="baja-personal-comentarios-lista" class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height:0; overscroll-behavior:contain;"></div>
            <div id="baja-personal-comentario-enviando" class="chat-comment-sending-state px-3 pb-3 d-none" role="status" aria-live="polite">
                <div class="d-flex justify-content-end"><div class="border border-primary-subtle rounded-4 bg-primary-subtle p-3" style="max-width:420px;width:100%;"><div class="d-flex align-items-center gap-3"><div class="flex-shrink-0 rounded-circle bg-white d-flex align-items-center justify-content-center text-primary" style="width:52px;height:52px;"><i class="ti ti-send fs-4"></i></div><div><div class="fw-semibold text-primary">Enviando mensaje...</div><div class="small text-muted">Estamos entregando tu comentario</div></div></div><div class="progress rounded-pill mt-3" style="height:4px;"><div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div></div></div></div>
            </div>
        </div>
    </div>
    <div class="px-3 py-3 border-top bg-white flex-shrink-0">
        <div class="d-flex align-items-center gap-2"><div class="flex-grow-1"><textarea id="baja-personal-comentario-input" class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;"></textarea></div><div class="flex-shrink-0"><button id="baja-personal-comentario-enviar" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;" type="button" disabled><i class="ti ti-send fs-5"></i></button></div></div>
    </div>
</div>

<div class="modal fade" id="modalEditarProcesoBaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="ti ti-edit me-2"></i>Actualizar proceso de baja</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-editar-proceso-baja">
                <div class="modal-body">
                    <input type="hidden" name="id_baja">
                    <div class="mb-3">
                        <label class="form-label">Proceso:</label>
                        <input type="text" class="form-control" name="proceso" list="listaProcesosBaja" maxlength="2000" placeholder="Selecciona o escribe un proceso...">
                        <datalist id="listaProcesosBaja">
                            <option value="Finiquito">
                            <option value="Junta de conciliación y arbitraje">
                            <option value="Demanda">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estatus:</label>
                        <select class="form-select" name="estado_proceso" required>
                            <option value="0">Pendiente</option>
                            <option value="1">En Proceso</option>
                            <option value="2">Finalizado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
