<section id="refacciones-salida-firma" class="pb-4" data-salida-signature data-pending="<?= !empty($salidaPending) ? '1' : '0' ?>" data-endpoint="<?= $escape($baseUrl) ?>/salidas/firmar" data-return-url="<?= $escape($baseUrl) ?>/salidas" data-movement-id="<?= (int) $salida['id_movimiento'] ?>">
    <?php
    [$statusLabel, $statusClass] = match ((int) ($salida['estatus'] ?? 0)) {
        1 => ['En proceso', 'bg-warning text-white'],
        2 => ['Finalizado', 'bg-success text-white'],
        default => ['Pendiente', 'bg-danger text-white'],
    };
    ?>
    <?php if (!empty($salidaPending)): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="submit" form="form-refacciones-recepcion" class="btn btn-success" data-receiver-submit><i class="ti ti-check me-1"></i>Finalizar</button>
        </div>
    <?php elseif (!empty($salidaElaboraPendiente)): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="submit" form="form-refacciones-elabora" class="btn btn-success" data-elabora-submit><i class="ti ti-check me-1"></i>Continuar a recepción</button>
        </div>
    <?php endif; ?>
    <div class="card mb-3">
        <div class="card-header bg-primary d-flex align-items-center">
            <h3 class="card-title text-white mb-0"><i class="ti ti-file-description me-1"></i>Datos registrados</h3>
        </div>
        <div class="card-body"><div class="row g-3">
            <div class="col-md-6"><div class="form-label">Folio:</div><div><?= $escape($salida['folio']) ?></div></div>
            <div class="col-md-6"><div class="form-label">Fecha de salida:</div><div><?= $salida['fecha'] ? $escape(formatearFecha($salida['fecha'])) : '—' ?></div></div>
            <div class="col-md-6"><div class="form-label">Refacción:</div><div><?= $escape(($salida['sku'] ?? '') . ' · ' . ($salida['producto'] ?? '')) ?></div></div>
            <div class="col-md-6"><div class="form-label">Cantidad:</div><div><?= $escape(rtrim(rtrim(number_format((float) $salida['cantidad'], 2, '.', ','), '0'), '.')) ?> <?= $escape($salida['unidad_medida'] ?? 'Pieza') ?></div></div>
            <div class="col-md-6"><div class="form-label">Estación de origen:</div><div><?= $escape($salida['origen']) ?></div></div>
            <div class="col-md-6"><div class="form-label">Estación de destino:</div><div><?= $escape($salida['destino']) ?></div></div>
            <div class="col-md-6"><div class="form-label">Motivo:</div><div><?= $escape($salida['motivo'] ?? '—') ?></div></div>
            <div class="col-md-6"><div class="form-label">Estado:</div><div><span class="badge <?= $escape($statusClass) ?>"><?= $escape($statusLabel) ?></span></div></div>
        </div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <?php if (!empty($salidaElaboraPendiente)): ?>
            <form id="form-refacciones-elabora" class="card h-100" novalidate>
                <div class="card-header text-bg-primary py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-signature fs-5"></i><h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5></div></div>
                <div class="card-body d-flex flex-column">
                    <div class="form-label">* Firma:</div>
                    <div data-signature-wrapper class="position-relative flex-grow-1" style="min-height:180px;border:2px dashed #adb5bd;border-radius:6px;background:#fff;touch-action:none"><canvas data-signature-canvas aria-label="Firma de quien elabora" style="position:absolute;inset:0;width:100%;height:100%;display:block"></canvas></div>
                    <input type="hidden" name="id" value="<?= (int) $salida['id_movimiento'] ?>"><input type="hidden" name="firma_elabora" data-signature-value>
                </div>
                <button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" data-signature-clear><i class="ti ti-eraser me-1"></i>Limpiar firma</button>
            </form>
            <?php else: ?>
            <div class="card h-100">
                <div class="card-header text-bg-primary py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-signature fs-5"></i><h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5></div></div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <?php if (!empty($salida['firma_elabora'])): ?>
                        <img src="/<?= $escape($salida['firma_elabora']) ?>" alt="Firma de quien elabora" class="img-fluid" style="max-height:150px;object-fit:contain">
                    <?php else: ?>
                        <i class="ti ti-signature-off text-muted mb-3" style="font-size:70px"></i><div class="text-muted">Sin firma registrada</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center"><div class="fw-semibold"><?= $escape($salida['nombre_elabora'] ?? $salida['nombre_entrega'] ?? '—') ?></div></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-6">
            <?php if (!empty($salidaPending)): ?>
                <form id="form-refacciones-recepcion" class="card h-100" novalidate>
                    <div class="card-header text-bg-primary py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-user-check fs-5"></i><h5 class="mb-0 text-white">NOMBRE Y FIRMA POR LA ESTACIÓN</h5></div></div>
                    <div class="card-body d-flex flex-column">
                        <label class="form-label">* Encargado:</label>
                        <select class="form-select mb-3" name="id_encargado" data-receiver-employee><option value="">Selecciona al encargado</option>
                            <?php foreach ($salidaEmployees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= $escape($employee['nombre']) ?></option><?php endforeach; ?>
                        </select>
                        <?php if ($salidaEmployees === []): ?><div class="form-text text-danger mb-2">No hay encargados activos asignados a esta ubicación.</div><?php endif; ?>
                        <div class="form-label mt-3">* Firma de recepción:</div>
                        <div data-signature-wrapper class="position-relative flex-grow-1" style="min-height:180px;border:2px dashed #adb5bd;border-radius:6px;background:#fff;touch-action:none"><canvas data-signature-canvas aria-label="Firma de recepción" style="position:absolute;inset:0;width:100%;height:100%;display:block"></canvas></div>
                        <input type="hidden" name="id" value="<?= (int) $salida['id_movimiento'] ?>"><input type="hidden" name="firma_recibe" data-signature-value>
                    </div>
                    <button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" data-signature-clear><i class="ti ti-eraser me-1"></i>Limpiar firma</button>
                </form>
            <?php else: ?>
                <div class="card h-100">
                    <div class="card-header text-bg-primary py-3 border-0"><div class="d-flex align-items-center gap-2"><i class="ti ti-user-check fs-5"></i><h5 class="mb-0 text-white">NOMBRE Y FIRMA POR LA ESTACIÓN</h5></div></div>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                        <?php if (!empty($salida['firma_recibe'])): ?><img src="/<?= $escape($salida['firma_recibe']) ?>" alt="Firma de recepción" class="img-fluid" style="max-height:150px;object-fit:contain"><?php else: ?><i class="ti ti-signature-off text-muted mb-3" style="font-size:70px"></i><div class="text-muted">Pendiente de firma de recepción</div><?php endif; ?>
                    </div>
                    <div class="card-footer bg-light text-center"><div class="fw-semibold"><?= $escape($salida['nombre_recibe'] ?? $salida['acuse_nombre_recibe'] ?? '—') ?></div></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
