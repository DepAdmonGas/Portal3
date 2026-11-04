<?php
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$itemsSolicitud = $solicitud['detalle'] ?? [];
$editable = !empty($puedeEditarSolicitud);
$formatDateTime = static function ($value): string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) return '—';
    $date = function_exists('formatearFecha') ? formatearFecha(date('Y-m-d', $timestamp)) : date('d/m/Y', $timestamp);
    return $date . ', ' . date('g:i a', $timestamp);
};
?>
<section class="mt-3" id="solicitud-calcomanias-detalle" x-data="actions()"
    data-base-url="<?= $escape($baseUrl) ?>" data-request-id="<?= (int) $solicitud['id'] ?>"
    data-editable="<?= $editable ? '1' : '0' ?>">
    <?php if ($editable): ?><div class="row"><div class="col-12 mb-3"><div class="d-flex justify-content-end"><button type="button" class="btn btn-success" data-sign-requester><i class="ti ti-check me-1"></i>Finalizar</button></div></div></div><?php endif; ?>

    <div class="card mb-3">
        <div class="card-header bg-primary d-flex align-items-center justify-content-between"><h5 class="mb-0 text-white"><i class="ti ti-info-circle me-1"></i>DETALLE DE LA SOLICITUD</h5></div>
        <div class="card-body"><div class="row g-3">
            <div class="col-md-3"><div class="form-label mb-1">Folio:</div><div>#00<?= (int) $solicitud['id'] ?></div></div>
            <div class="col-md-3"><div class="form-label mb-1">Fecha y hora:</div><div><?= $escape($formatDateTime($solicitud['fecha_hora'])) ?></div></div>
            <div class="col-md-3"><div class="form-label mb-1">Nombre del personal:</div><div><?= $escape($solicitud['nombre_personal']) ?></div></div>
            <div class="col-md-3"><div class="form-label mb-1">Puesto:</div><div><?= $escape($solicitud['puesto'] ?: '—') ?></div></div>
        </div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white"><i class="ti ti-sticker me-1"></i>CALCOMANÍAS</h5>
            <?php if ($editable): ?><button type="button" class="btn btn-success" data-open-calcomania><i class="ti ti-plus me-1"></i>Nuevo</button><?php endif; ?>
        </div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-bordered align-middle text-center mb-0" id="tabla-calcomanias-solicitud">
            <thead><tr><th style="width:70px">#</th><th class="text-start">Calcomanía</th><th style="width:150px">Piezas</th><?php if ($editable): ?><th style="width:60px"><i class="ti ti-trash fs-6 text-danger" title="Eliminar" aria-label="Eliminar"></i></th><?php endif; ?></tr></thead>
            <tbody data-calcomanias-body>
                <?php foreach ($itemsSolicitud as $index => $item): ?><tr data-detail-id="<?= (int) $item['id'] ?>" data-catalog-id="<?= (int) $item['id_calcomania'] ?>"><td><?= $index + 1 ?></td><td class="text-start"><?= $escape($item['nombre_calcomania'] ?? '') ?></td><td><?= (int) $item['cantidad'] ?></td><?php if ($editable): ?><td><button type="button" class="btn btn-link p-0 text-decoration-none" data-remove-calcomania="<?= (int) $item['id'] ?>" data-calcomania-name="<?= $escape($item['nombre_calcomania'] ?? '') ?>" aria-label="Eliminar calcomanía" title="Eliminar"><i class="ti ti-trash fs-6 text-danger"></i></button></td><?php endif; ?></tr><?php endforeach; ?>
                <?php if ($itemsSolicitud === []): ?><tr data-empty-row><td colspan="<?= $editable ? 4 : 3 ?>" class="text-center py-3">No se encontró información</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary d-flex align-items-center justify-content-between"><h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i>OBSERVACIONES</h5></div>
        <div class="card-body p-0">
            <?php if ($editable): ?><textarea class="form-control border-0 p-3" rows="5" maxlength="5000" data-observaciones placeholder="Ingresa aquí las observaciones de la solicitud..."><?= $escape($solicitud['observaciones'] ?? '') ?></textarea>
            <?php else: ?><div><?= nl2br($escape($solicitud['observaciones'] ?: 'Sin observaciones')) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6"><div class="card border-0 bg-white">
            <div class="card-header text-bg-primary py-3 border-0"><div class="d-flex align-items-center"><div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px"><i class="ti ti-signature fs-6"></i></div><div class="ms-3"><h5 class="mb-0 text-white">FIRMA DEL ENCARGADO</h5></div></div></div>
            <?php if (!empty($solicitud['firma_encargado'])): ?><div class="card-body d-flex align-items-center justify-content-center"><img src="<?= $escape($solicitud['firma_encargado']) ?>" alt="Firma del encargado" class="img-fluid" style="max-height:150px"></div><div class="card-footer text-center"><?= $escape($solicitud['nombre_personal']) ?> · <?= $escape($formatDateTime($solicitud['firmado_encargado_en'])) ?></div>
            <?php elseif ($editable): ?><div class="card-body p-3"><div data-signature-pad class="signature-pad-wrapper" style="border:2px dashed #adb5bd;border-radius:6px;cursor:crosshair"><div class="signature-pad--body"><canvas data-signature-canvas style="width:100%;height:250px;display:block;touch-action:none"></canvas></div></div></div><button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius:6px;border-bottom-right-radius:6px" data-clear-signature><i class="ti ti-eraser me-1"></i>Limpiar firma</button>
            <?php else: ?><div class="card-body text-center text-muted">Firma pendiente.</div><?php endif; ?>
        </div></div>
    </div>

    <?php if ($editable): ?>
    <div class="modal fade" id="modal-calcomania-solicitud" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title text-white"><i class="ti ti-sticker me-1"></i>Nueva calcomanía a la solicitud</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label for="solicitud-calcomania-select" class="form-label">* Calcomanía:</label><select class="form-select" id="solicitud-calcomania-select" data-select-calcomania><option value="">Selecciona una calcomanía</option><?php foreach (($productosCalcomanias ?? []) as $product): ?><option value="<?= (int) $product['id'] ?>"><?= $escape(trim(($product['sku'] ?? '') . ' - ' . ($product['nombre'] ?? ''), ' -')) ?></option><?php endforeach; ?></select></div>
                <div><label for="solicitud-calcomania-cantidad" class="form-label">* Piezas:</label><input type="number" id="solicitud-calcomania-cantidad" data-calcomania-cantidad min="1" step="1" class="form-control" value="1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button><button type="button" class="btn btn-success" data-save-calcomania><i class="ti ti-check me-1"></i>Guardar</button></div>
        </div></div>
    </div>
    <?php endif; ?>
</section>
