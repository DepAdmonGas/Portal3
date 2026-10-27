<style>
    #vacaciones-comentarios .vacaciones-chat-detail {
        min-height: 62px;
        background: var(--bs-body-bg);
        font-size: .82rem;
    }
    #vacaciones-comentarios-list {
        overflow-y: auto;
        min-height: 0;
    }
    #vacaciones-comentarios-body {
        min-height: 0;
    }
</style>

<div id="vacaciones-container"
     class="mt-4 mb-5"
     data-year="<?= (int)$year ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
     data-puede-firmar="<?= $puedeFirmar ? 'true' : 'false' ?>"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-contexto-seleccionado="<?= (int)$contextoSeleccionado ?>"
     data-mostrar-estacion="<?= $mostrarEstacion ? 'true' : 'false' ?>"
     x-data="{ ...actions() }">

    <div class="d-flex flex-column gap-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-2 px-3 py-2">
                    <i class="ti ti-alert-circle fs-5"></i>
                    <span>Pendientes de firma: <span id="vacaciones-pendientes-count"><?= (int)$pendientesActuales ?></span></span>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-primary d-none" id="vacaciones-nuevo" <?= !$puedeCrear ? 'disabled' : '' ?>><i class="ti ti-plus me-1"></i>Nuevo</button>
            </div>
        </div>
    </div>


                <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                <table id="table-vacaciones" class="table table-striped table-bordered mb-0 align-middle w-100">
                    <tbody></tbody>
                </table>
            </div>

</div>

<div class="modal fade" id="modal-vacaciones-detalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="ti ti-calendar-event me-2"></i>Detalle de vacaciones</h5>
                <button type="button" class="btn-close btn-close-white js-vacaciones-detalle-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="vacaciones-detalle-loading" class="text-center py-4 d-none"><span class="spinner-border spinner-border-sm me-2"></span>Cargando información...</div>
                <div id="vacaciones-detalle-content" class="row"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary js-vacaciones-detalle-back"><i class="ti ti-arrow-left me-1"></i>Regresar</button>
                <button type="button" class="btn bg-danger-subtle text-danger js-vacaciones-detalle-close" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-vacaciones-historial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="ti ti-history me-2"></i><span id="vacaciones-historial-title">Historial de vacaciones</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="vacaciones-historial-loading" class="text-center py-4 d-none"><span class="spinner-border spinner-border-sm me-2"></span>Cargando registros...</div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0" id="table-vacaciones-historial">
                        <thead><tr>
                            <th class="text-center align-middle">Fecha de solicitud</th>
                            <th class="text-center align-middle">Días</th>
                            <th class="text-center align-middle">Fecha de inicio</th>
                            <th class="text-center align-middle">Fecha de término</th>
                            <th class="text-center align-middle">Fecha de regreso</th>
                            <th class="text-center align-middle"><i class="ti ti-eye fs-6 text-primary" title="Detalle"></i></th>
                            <th class="text-center align-middle"><i class="ti ti-trash fs-6 text-danger" title="Eliminar"></i></th>
                        </tr></thead>
                        <tbody id="vacaciones-historial-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>


<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="vacaciones-comentarios" style="width:480px;max-width:100vw;height:100dvh;overflow:hidden">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
        <div class="hstack gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                <i class="ti ti-message-circle text-primary fs-7"></i>
            </div>
            <div>
                <h5 class="mb-1 text-white">COMENTARIOS</h5>
                <p class="mb-0 text-white opacity-75" id="vacaciones-comentarios-subtitle">Vacaciones</p>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0" id="vacaciones-comentarios-detalle"></div>
    <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height:0">
        <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height:0">
            <div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height:0;overscroll-behavior:contain" id="vacaciones-comentarios-list"></div>
        </div>
    </div>
    <div id="vacaciones-enviando" class="chat-comment-sending-state d-none px-3 pb-3 flex-shrink-0" role="status" aria-live="polite">
        <div class="d-flex justify-content-end">
            <div class="border border-primary-subtle rounded-4 bg-primary-subtle p-3 shadow-sm" style="max-width:420px;width:100%">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 rounded-circle bg-white d-flex align-items-center justify-content-center text-primary" style="width:52px;height:52px"><i class="ti ti-send fs-4"></i></div>
                    <div><div class="fw-semibold text-primary">Enviando mensaje...</div><div class="small text-muted">Estamos entregando tu comentario</div></div>
                </div>
                <div class="progress rounded-pill mt-3" style="height:4px" aria-label="Enviando comentario"><div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div></div>
            </div>
        </div>
    </div>
    <div class="px-3 py-3 border-top bg-white flex-shrink-0">
        <form id="vacaciones-comentario-form">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" id="vacaciones-comentario-input" rows="1" maxlength="5000" placeholder="Escribe un comentario..." style="resize:none"></textarea>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px" type="submit" id="vacaciones-comentario-send" aria-label="Enviar comentario" disabled><i class="ti ti-send fs-5"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>
