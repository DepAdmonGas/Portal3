<?php
$contextId = isset($context['id']) ? (int) $context['id'] : null;
$canCreateAtContext = !empty($permissions['crear']) && $contextId !== null && isset($locations[$contextId]);
$warehouseLocationId = \App\Services\RefaccionesService::getWarehouseLocationId();
$salidaSignatureUrl = $baseUrl . '/salidas-firma/';
$salidaFormUrl = $baseUrl . '/salidas-formulario/';
$salidaPdfUrl = $baseUrl . '/salidas-pdf/';
?>
<section id="refacciones-salidas" x-data="actions" data-salida-can-sign="<?= !empty($permissions['editar']) ? '1' : '0' ?>" data-salida-delete-url="<?= $escape($baseUrl) ?>/salidas/eliminar" data-salida-return-url="<?= $escape($baseUrl) ?>/salidas/retorno" data-salida-process-url="<?= $escape($baseUrl) ?>/salidas/procesar-retornos">
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
        <?php if ($canCreateAtContext): ?>
            <a class="btn bg-primary-subtle text-primary" href="<?= $escape($baseUrl . '/salidas-formulario/nueva/' . $contextId) ?>">
                <i class="ti ti-plus me-1"></i>Nueva salida
            </a>
        <?php endif; ?>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="tabla-refacciones-salidas" class="table table-striped table-bordered mb-0 align-middle">
                <thead><tr>
                    <th class="text-center align-middle" width="96px" style="width:96px;min-width:96px">#</th><th>Fecha de salida</th><th>Producto</th><th>Origen → Destino</th><th>Cantidad</th><th>Fecha de retorno</th><th>Estado del retorno</th><th>Firma</th><th>Estado</th><th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th>
                </tr></thead>
                <tbody>
                    <?php $rowNumber = 0; ?>
                    <?php foreach ($items as $row): ?>
                        <?php $rowNumber++; ?>
                        <?php
                        $movementId = (int) ($row['id_movimiento'] ?? 0);
                        $returnDate = $row['fecha_retorno'] ?? null;
                        $originId = (int) ($row['id_estacion_origen'] ?? 0);
                        $originName = $originId === $warehouseLocationId ? 'Almacen' : (string) ($locations[$originId] ?? '');
                        $originKey = mb_strtolower(trim(strtr($originName, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'])));
                        $originIsWarehouse = $originKey === 'almacen' || str_starts_with($originKey, 'almacen ');
                        $returnDateLabel = $originIsWarehouse ? 'No aplica' : ($returnDate ? formatearFecha($returnDate) : 'Sin asignar');
                        $returnIsComplete = mb_strtolower(trim((string) ($row['estado_salida'] ?? 'prestado'))) === 'devuelto';
                        $returnState = $originIsWarehouse ? 'No aplica' : ($returnIsComplete ? 'Devuelto a la estación de origen' : 'En préstamo');
                        $returnStateClass = $originIsWarehouse ? 'bg-danger text-white' : ($returnIsComplete ? 'bg-success text-white' : 'bg-warning text-white');
                        $status = (int) ($row['estatus'] ?? 0);
                        [$statusLabel, $statusClass] = match ($status) {
                            1 => ['En proceso', 'bg-warning text-white'],
                            2 => ['Finalizado', 'bg-success text-white'],
                            default => ['Pendiente', 'bg-danger text-white'],
                        };
                        ?>
                        <tr>
                            <td class="text-center" data-order="<?= $movementId ?>"><?= $movementId ?></td>
                            <td class="text-center" data-order="<?= $escape(!empty($row['fecha']) ? date('Y-m-d H:i:s', strtotime((string) $row['fecha'])) : '') ?>"><?= !empty($row['fecha']) ? $escape(formatearFecha($row['fecha'])) : 'Sin asignar' ?></td>
                            <td><?= $escape($row['producto'] ?? '') ?></td>
                            <td class="text-center"><?= $escape($originName) ?> <i class="ti ti-arrow-right mx-1"></i> <?= $escape($locations[(int) $row['id_estacion_destino']] ?? '') ?></td>
                            <td class="text-center"><?= $escape(rtrim(rtrim(number_format((float) ($row['cantidad'] ?? 0), 2, '.', ','), '0'), '.')) ?></td>
                            <td class="text-center" data-order="<?= $escape($originIsWarehouse ? '' : ($returnDate ?: '')) ?>" data-salida-return-date><?= $escape($returnDateLabel) ?></td>
                            <td class="text-center"><span class="badge <?= $escape($returnStateClass) ?>" data-salida-return-state><?= $escape($returnState) ?></span></td>
                            <td class="text-center text-nowrap">
                                <?php if ($status === 0 && !empty($row['es_orden_mantenimiento'])): ?>
                                    <a class="btn btn-sm p-0 border-0 bg-transparent text-primary" href="<?= $escape($salidaFormUrl . $movementId) ?>" title="Completar fecha, motivo y firma"><i class="ti ti-writing text-primary fs-8"></i></a>
                                <?php elseif ($status === 1): ?>
                                    <a class="btn btn-sm p-0 border-0 bg-transparent text-primary" href="<?= $escape($salidaSignatureUrl . $movementId) ?>" title="<?= $status === 0 ? 'Firmar salida' : 'Firmar recepción' ?>"><i class="ti ti-writing text-primary fs-8"></i></a>
                                <?php elseif ($status === 2): ?>
                                    <button type="button" class="btn btn-sm p-0 border-0 bg-transparent text-primary" disabled title="Firmas completadas"><i class="ti ti-writing text-primary fs-8" style="opacity:.5;cursor:default;"></i></button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm p-0 border-0 bg-transparent text-primary" disabled title="Firma pendiente"><i class="ti ti-writing text-primary fs-8" style="opacity:.5;cursor:default;"></i></button>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge <?= $escape($statusClass) ?>"><?= $escape($statusLabel) ?></span></td>
                            <td class="text-center text-nowrap">
                                <div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de salida <?= $escape($row['folio'] ?? '') ?>"><i class="ti ti-dots-vertical fs-6"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button type="button" class="dropdown-item" data-salida-detail
                                            data-folio="<?= $escape($row['folio'] ?? '') ?>"
                                            data-fecha="<?= $escape(!empty($row['fecha']) ? formatearFecha($row['fecha']) : 'Pendiente de confirmación') ?>"
                                            data-producto="<?= $escape($row['producto'] ?? '') ?>"
                                            data-origen="<?= $escape($originName) ?>"
                                            data-destino="<?= $escape($locations[(int) $row['id_estacion_destino']] ?? '') ?>"
                                            data-cantidad="<?= $escape(rtrim(rtrim(number_format((float) ($row['cantidad'] ?? 0), 2, '.', ','), '0'), '.')) ?>"
                                            data-retorno="<?= $escape($returnDateLabel) ?>"
                                            data-estado-retorno="<?= $escape($returnState) ?>"
                                            data-estado-retorno-class="<?= $escape($returnStateClass) ?>"
                                            data-estado="<?= $escape($statusLabel) ?>"
                                            data-estado-class="<?= $escape($statusClass) ?>"
                                            data-motivo="<?= $escape($row['motivo'] ?? '') ?>"
                                            data-notas="<?= $escape(trim((string) ($row['notas'] ?? '')) !== '' ? $row['notas'] : 'Sin información') ?>"
                                            data-firma-elabora="<?= $escape($row['firma_elabora'] ?? '') ?>"
                                            data-nombre-elabora="<?= $escape($row['nombre_elabora'] ?? $row['nombre_entrega'] ?? '') ?>"
                                            data-firma-recibe="<?= $escape($row['firma_recibe'] ?? '') ?>"
                                            data-nombre-recibe="<?= $escape($row['nombre_recibe'] ?? $row['acuse_nombre_recibe'] ?? '') ?>">
                                            <i class="ti ti-eye me-1"></i>Detalle
                                        </button>
                                        <?php if (!$originIsWarehouse): ?>
                                            <button type="button" class="dropdown-item <?= $returnIsComplete ? 'disabled' : '' ?>" data-salida-return data-id="<?= $movementId ?>" data-fecha-salida="<?= $escape(substr((string) $row['fecha'], 0, 10)) ?>" data-fecha-retorno="<?= $escape($returnDate ?? '') ?>" data-proveedor-retorno="<?= (int) ($row['id_proveedor_retorno'] ?? 0) ?>" data-referencia-retorno="<?= $escape($row['referencia_retorno'] ?? '') ?>" data-notas-retorno="<?= $escape($row['notas_retorno'] ?? '') ?>" data-folio="<?= $escape($row['folio'] ?? '') ?>" <?= $returnIsComplete ? 'disabled aria-disabled="true" title="El retorno ya fue procesado"' : '' ?>><i class="ti ti-calendar-event me-1"></i>Editar Retorno</button>
                                        <?php endif; ?>
                                        <?php if ($status === 2): ?>
                                            <a class="dropdown-item" href="<?= $escape($salidaPdfUrl . $movementId) ?>" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-1"></i>Descargar PDF</a>
                                        <?php else: ?>
                                            <span class="dropdown-item disabled" aria-disabled="true" title="Disponible cuando la salida esté finalizada"><i class="ti ti-file-type-pdf me-1"></i>Descargar PDF</span>
                                        <?php endif; ?>
                                        <?php if (empty($row['es_orden_mantenimiento'])): ?>
                                            <button type="button" class="dropdown-item text-danger <?= $status === 2 ? 'disabled' : '' ?>" data-salida-delete data-id="<?= $movementId ?>" data-folio="<?= $escape($row['folio'] ?? '') ?>" <?= $status === 2 ? 'disabled aria-disabled="true" title="No se puede eliminar una salida finalizada"' : '' ?>><i class="ti ti-trash me-1"></i>Eliminar</button>
                                        <?php else: ?>
                                            <span class="dropdown-item disabled" aria-disabled="true" title="Las salidas de orden de mantenimiento no se pueden eliminar"><i class="ti ti-trash me-1"></i>Eliminar</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modal-detalle-salida-refacciones" tabindex="-1" aria-labelledby="modal-detalle-salida-titulo" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modal-detalle-salida-titulo"><i class="ti ti-eye me-1"></i>Detalle de salida (<span data-salida-detail-folio></span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3 text-end"><strong>Estado:</strong> <span class="badge" data-salida-detail-estado></span></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Fecha de salida:</label><div data-salida-detail-fecha></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Producto:</label><div data-salida-detail-producto></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Estación de origen:</label><div data-salida-detail-origen></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Estación de destino:</label><div data-salida-detail-destino></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Cantidad:</label><div data-salida-detail-cantidad></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Fecha de retorno:</label><div data-salida-detail-retorno></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Estado del retorno:</label><div><span class="badge" data-salida-detail-estado-retorno></span></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label mb-1">Motivo:</label><div data-salida-detail-motivo></div></div>
                        <div class="col-12 mb-3"><label class="form-label mb-1">Notas:</label><div data-salida-detail-notas></div></div>
                    </div>
                    <label class="form-label mb-3">Firmas:</label>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="card border h-100 mb-0">
                                <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-user-check fs-5"></i><h6 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h6></div></div>
                                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:140px">
                                    <img class="img-fluid d-none" data-salida-detail-firma-imagen="elabora" alt="Firma de quien elabora" style="max-height:90px;object-fit:contain">
                                    <div class="text-muted" data-salida-detail-firma-vacia="elabora"><i class="ti ti-signature-off d-block mb-2" style="font-size:48px"></i>Sin firma registrada</div>
                                </div>
                                <div class="card-footer bg-light text-center"><div class="fw-semibold" data-salida-detail-firma-nombre="elabora">—</div></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card border h-100 mb-0">
                                <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-user-check fs-5"></i><h6 class="mb-0 text-white">NOMBRE Y FIRMA POR LA ESTACIÓN</h6></div></div>
                                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:140px">
                                    <img class="img-fluid d-none" data-salida-detail-firma-imagen="recibe" alt="Firma de recepción" style="max-height:90px;object-fit:contain">
                                    <div class="text-muted" data-salida-detail-firma-vacia="recibe"><i class="ti ti-signature-off d-block mb-2" style="font-size:48px"></i>Pendiente de firma de recepción</div>
                                </div>
                                <div class="card-footer bg-light text-center"><div class="fw-semibold" data-salida-detail-firma-nombre="recibe">—</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-fecha-retorno-salida" tabindex="-1" aria-labelledby="modal-fecha-retorno-titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="form-fecha-retorno-salida" novalidate>
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="modal-fecha-retorno-titulo"><i class="ti ti-calendar-event me-1"></i>Editar fecha de retorno</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-salida-return-id>
                    <p class="mb-3">Selecciona la fecha programada para el retorno de <strong data-salida-return-folio></strong>.</p>
                    <label for="fecha-retorno-programada" class="form-label">* Fecha de retorno:</label>
                    <input type="date" class="form-control" id="fecha-retorno-programada" name="fecha_retorno" required data-salida-return-input>
                    <div class="invalid-feedback">Selecciona una fecha de retorno válida.</div>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label for="proveedor-retorno-programado" class="form-label">* Proveedor:</label>
                            <select class="form-select" id="proveedor-retorno-programado" name="id_proveedor" data-salida-return-provider required>
                                <option value="">Selecciona un proveedor</option>
                                <?php foreach ($providerOptions as $provider): ?><option value="<?= (int) $provider['id'] ?>"><?= $escape($provider['razon_social']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="referencia-retorno-programada" class="form-label">* Referencia / factura:</label>
                            <input type="text" class="form-control" id="referencia-retorno-programada" name="referencia" maxlength="120" data-salida-return-reference required>
                            <div class="invalid-feedback">Indica la referencia o factura.</div>
                        </div>
                        <div class="col-12">
                            <label for="notas-retorno-programado" class="form-label">Notas:</label>
                            <textarea class="form-control" id="notas-retorno-programado" name="notas" rows="3" maxlength="5000" data-salida-return-notes></textarea>
                        </div>
                    </div>
                    <div class="form-text mt-3">Al llegar esta fecha se registrará una entrada con estos datos y se repondrá la existencia en la estación de origen.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-success" data-salida-return-save><i class="ti ti-check me-1"></i>Guardar fecha</button>
                </div>
            </form>
        </div>
    </div>
</section>
