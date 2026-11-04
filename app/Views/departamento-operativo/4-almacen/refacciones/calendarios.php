<div class="calender-sidebar app-calendar mt-3">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100 gap-2 mb-3">
        <div class="d-flex align-items-center flex-wrap">
            <div class="border-end pe-3">
                <h6 class="text-muted fw-normal mb-1">Pendientes</h6>
                <b class="text-danger fs-5" data-calendar-total="pendientes"><?= (int) ($calendarTotals['pendientes'] ?? 0) ?></b>
            </div>
            <div class="ms-4 ms-md-5 border-end pe-3">
                <h6 class="text-muted fw-normal mb-1">Finalizados</h6>
                <b class="text-success fs-5" data-calendar-total="finalizados"><?= (int) ($calendarTotals['finalizados'] ?? 0) ?></b>
            </div>
            <div class="ms-4 ms-md-5 border-end pe-3">
                <h6 class="text-muted fw-normal mb-1">Cancelados</h6>
                <b class="text-danger fs-5" data-calendar-total="cancelados"><?= (int) ($calendarTotals['cancelados'] ?? 0) ?></b>
            </div>
            <div class="ms-4 ms-md-5">
                <h6 class="text-muted fw-normal mb-1">Total</h6>
                <b class="fs-5" data-calendar-total="total"><?= (int) ($calendarTotals['total'] ?? 0) ?></b>
            </div>
        </div>
        <?php if (!empty($permissions['crear']) || !empty($canSendCalendarReminders)): ?>
            <div class="d-grid d-md-block mb-3 mb-md-0 mt-3 mt-md-0">
                <?php if (!empty($canSendCalendarReminders)): ?>
                    <button type="button" class="btn btn-outline-primary me-1" data-calendar-send-reminders data-endpoint="<?= $escape($baseUrl) ?>/calendario/recordatorios"><i class="ti ti-bell me-1"></i>Enviar recordatorios</button>
                <?php endif; ?>
                <?php if (!empty($permissions['crear'])): ?>
                    <button type="button" class="pointer btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modalRefaccionesEvento"><i class="ti ti-plus me-1"></i>Nuevo</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="refacciones-calendar" aria-label="Calendario de Refacciones"></div>
</div>

<div class="modal fade" id="modalRefaccionesDia" tabindex="-1" aria-labelledby="modalRefaccionesDiaLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h2 class="modal-title fs-5 text-white" id="modalRefaccionesDiaLabel"><i class="ti ti-calendar-event me-1"></i><span data-calendar-day-title></span></h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Evento</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody data-calendar-day-events>
                            <tr><td colspan="4" class="text-center text-muted py-4">Cargando eventos…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRefaccionesDetalle" tabindex="-1" aria-labelledby="modalRefaccionesDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h2 class="modal-title fs-5 text-white" id="modalRefaccionesDetalleLabel"><i class="ti ti-file-search me-1"></i>Detalle del evento</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold mb-1">Título:</label><div data-calendar-detail="titulo">—</div></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold mb-1">Tipo de evento:</label><div data-calendar-detail="tipo">—</div></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold mb-1">Fecha:</label><div data-calendar-detail="fecha">—</div></div>
                    <div class="col-12 col-md-6 d-none" data-calendar-detail-time-wrap><label class="form-label fw-semibold mb-1">Hora:</label><div data-calendar-detail="hora">—</div></div>
                    <div class="col-12 col-md-6 d-none" data-calendar-detail-category-wrap><label class="form-label fw-semibold mb-1">Categoría:</label><div data-calendar-detail="categoria">—</div></div>
                    <div class="col-12 col-md-6 d-none" data-calendar-detail-provider-wrap><label class="form-label fw-semibold mb-1">Proveedor externo:</label><div data-calendar-detail="proveedor_externo">—</div></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold mb-1">Estación / departamento:</label><div data-calendar-detail="ubicacion">—</div></div>
                    <div class="col-12 col-md-6"><label class="form-label fw-semibold mb-1">Estado:</label><div><span class="badge text-white" data-calendar-detail-state>—</span></div></div>
                    <div class="col-12"><label class="form-label fw-semibold mb-1">Correo del responsable:</label><div data-calendar-detail="correo_responsable">—</div></div>
                    <div class="col-12"><label class="form-label fw-semibold mb-1">Notas:</label><div data-calendar-detail="notas">—</div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary d-none" data-calendar-detail-back><i class="ti ti-arrow-left me-1"></i>Regresar</button>
                <button type="button" class="btn bg-warning-subtle text-warning d-none" data-calendar-detail-edit data-calendar-editable="<?= !empty($permissions['editar']) ? '1' : '0' ?>"><i class="ti ti-edit me-1"></i>Editar</button>
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRefaccionesEditarEstado" tabindex="-1" aria-labelledby="modalRefaccionesEditarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h2 class="modal-title fs-5 text-white" id="modalRefaccionesEditarEstadoLabel"><i class="ti ti-edit me-1"></i>Editar estado del evento</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold" for="refacciones-event-new-state">* Estado:</label>
                <select class="form-select" id="refacciones-event-new-state" data-calendar-edit-status>
                    <option value="">Selecciona una opción</option>
                    <option value="finalizado">Finalizado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-calendar-edit-back><i class="ti ti-arrow-left me-1"></i>Regresar</button>
                <button type="button" class="btn bg-danger-subtle text-danger" data-calendar-edit-close><i class="ti ti-x me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-success text-white" data-calendar-edit-save><i class="ti ti-check me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($permissions['crear'])): ?>
<div class="modal fade" id="modalRefaccionesEvento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-refacciones-calendar-form novalidate data-endpoint="<?= $escape($baseUrl) ?>/calendario/guardar">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h2 class="modal-title fs-5 text-white"><i class="ti ti-calendar-plus me-1"></i>Nuevo evento</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1" for="refacciones-event-type">* Tipo de evento:</label>
                        <select class="form-select" id="refacciones-event-type" name="tipo_evento" data-required>
                            <option value="">Selecciona una opción</option>
                            <option value="compra">Compras programadas</option>
                            <option value="mantenimiento_externo">Mantenimientos por proveedores externos</option>
                            <option value="prueba">Registro, preparación y pruebas</option>
                            <option value="calibracion">Calibraciones</option>
                        </select>
                    </div>

                    <div class="row g-3 d-none" data-calendar-fields>
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-title">* Título:</label>
                            <input class="form-control" id="refacciones-event-title" name="titulo" maxlength="180" placeholder="Ej. Compra de uniformes" data-required>
                        </div>
                        <div class="col-12" data-calendar-date-column>
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-date">* Fecha:</label>
                            <input class="form-control" id="refacciones-event-date" type="date" name="fecha" data-required>
                        </div>
                        <div class="col-md-6 d-none" data-calendar-field="hora">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-time">* Hora:</label>
                            <input class="form-control" id="refacciones-event-time" type="time" name="hora">
                        </div>
                        <div class="col-12 d-none" data-calendar-field="categoria">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-category">* Categoría:</label>
                            <input class="form-control" id="refacciones-event-category" name="categoria" maxlength="120" placeholder="Escribe la categoría">
                        </div>
                        <div class="col-12 d-none" data-calendar-field="proveedor_externo">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-provider">* Nombre del proveedor externo:</label>
                            <input class="form-control" id="refacciones-event-provider" name="proveedor_externo" maxlength="180" placeholder="Nombre del proveedor">
                        </div>
                        <?php if (($context['id'] ?? null) === null): ?>
                        <div class="col-12 select2-modal-field" data-calendar-location-select>
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-location">* Estación / departamento:</label>
                            <select class="form-select" id="refacciones-event-location" name="id_estacion" data-calendar-single-location data-required>
                                <option value="">Selecciona una estación o departamento</option>
                                <?php foreach ($locations as $id => $name): ?>
                                    <option value="<?= (int) $id ?>"><?= $escape($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select d-none" id="refacciones-event-locations" name="id_estaciones[]" data-calendar-multiple-locations multiple aria-label="Estaciones y departamentos del evento">
                                <?php foreach ($locations as $id => $name): ?>
                                    <option value="<?= (int) $id ?>"><?= $escape($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                            <div class="col-12" data-calendar-location-fixed>
                                <label class="form-label fw-semibold mb-1">* Estación / departamento:</label>
                                <div class="form-control-plaintext py-1" data-calendar-location-name><?= $escape($locations[(int) $context['id']] ?? $context['nombre'] ?? '') ?></div>
                                <input type="hidden" name="id_estacion" value="<?= (int) $context['id'] ?>" data-required>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-email">* Correo del responsable:</label>
                            <input class="form-control" id="refacciones-event-email" type="email" name="correo_responsable" maxlength="180" placeholder="Correo del responsable" data-required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-1" for="refacciones-event-notes">Notas:</label>
                            <textarea class="form-control" id="refacciones-event-notes" name="notas" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
                    <button class="btn btn-success" type="submit" data-calendar-save disabled><i class="ti ti-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
