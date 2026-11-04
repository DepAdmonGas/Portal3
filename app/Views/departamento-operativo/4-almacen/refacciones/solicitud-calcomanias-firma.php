<?php
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$formatDateTime = static function ($value): string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) return '—';
    $date = function_exists('formatearFecha') ? formatearFecha(date('Y-m-d', $timestamp)) : date('d/m/Y', $timestamp);
    return $date . ', ' . date('g:i a', $timestamp);
};
$canSign = !empty($puedeFirmar);
$hasAuthorization = (int) ($solicitud['estado'] ?? -1) === 2;
$authorizationIsImage = preg_match('#^data:image/(png|jpeg);base64,#', (string) ($solicitud['firma_autorizacion'] ?? '')) === 1;
?>
<section class="mt-3 mb-5" id="solicitud-calcomanias-firma"
    data-base-url="<?= $escape($baseUrl) ?>"
    data-request-id="<?= (int) $solicitud['id'] ?>"
    data-can-authorize="<?= $canSign ? '1' : '0' ?>">
    <div class="card mb-3">
        <div class="card-header bg-primary">
            <h5 class="mb-0 text-white"><i class="ti ti-sticker me-1"></i>DETALLE DE LA SOLICITUD</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="form-label mb-1">Folio:</div><div>#00<?= (int) $solicitud['id'] ?></div></div>
                <div class="col-md-4"><div class="form-label mb-1">Estación / departamento:</div><div><?= $escape($solicitud['nombre_ubicacion'] ?? '—') ?></div></div>
                <div class="col-md-4"><div class="form-label mb-1">Fecha y hora:</div><div><?= $escape($formatDateTime($solicitud['fecha_hora'] ?? null)) ?></div></div>
                <div class="col-md-4"><div class="form-label mb-1">Nombre del personal:</div><div><?= $escape($solicitud['nombre_personal'] ?? '—') ?></div></div>
                <div class="col-md-4"><div class="form-label mb-1">Puesto:</div><div><?= $escape($solicitud['puesto'] ?: '—') ?></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-sticker me-1"></i>CALCOMANÍAS</h5></div>
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-bordered table-striped align-middle text-nowrap mb-0">
                <thead><tr><th class="text-center" width="70">#</th><th>Calcomanía</th><th class="text-center" width="140">Piezas</th></tr></thead>
                <tbody>
                <?php foreach (($solicitud['detalle'] ?? []) as $index => $item): ?>
                    <tr><td class="text-center"><?= $index + 1 ?></td><td><?= $escape($item['nombre_calcomania'] ?? '') ?></td><td class="text-center"><?= (int) ($item['cantidad'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($solicitud['detalle'])): ?><tr><td colspan="3" class="text-center text-primary py-3">No se encontró información</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i>OBSERVACIONES</h5></div>
        <div class="card-body"><?= nl2br($escape($solicitud['observaciones'] ?: 'Sin observaciones')) ?></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6 col-lg-6 col-md-6">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center"><div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px"><i class="ti ti-user-check fs-6"></i></div><div class="ms-3"><h6 class="mb-0 text-white">FIRMA DEL ENCARGADO</h6></div></div></div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <?php if (!empty($solicitud['firma_encargado'])): ?><img src="<?= $escape($solicitud['firma_encargado']) ?>" alt="Firma del encargado" class="img-fluid" style="max-height:90px;object-fit:contain">
                    <?php else: ?><i class="ti ti-signature-off text-gray mb-3" style="font-size:100px"></i><h6 class="text-muted mb-0">Sin firma registrada</h6><?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate"><?= $escape($solicitud['nombre_personal'] ?? 'Sin nombre registrado') ?></h6><small class="text-muted"><?= $escape($formatDateTime($solicitud['firmado_encargado_en'] ?? null)) ?></small></div>
            </div>
        </div>
        <div class="col-xl-6 col-lg-6 col-md-6">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center"><div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px"><i class="ti <?= $hasAuthorization ? 'ti-user-check' : 'ti-circle-check' ?> fs-6"></i></div><div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE AUTORIZACIÓN</h6></div></div></div>
                <?php if ($hasAuthorization): ?>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                        <?php if ($authorizationIsImage): ?><img src="<?= $escape($solicitud['firma_autorizacion']) ?>" alt="Firma de autorización" class="img-fluid" style="max-height:90px;object-fit:contain">
                        <?php else: ?><i class="ti ti-signature text-primary mb-3" style="font-size:100px"></i><small class="text-dark">Autorización electrónica mediante token de seguridad</small><?php endif; ?>
                    </div>
                    <div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate">Autorización de Almacén</h6><small class="text-muted"><?= $escape($formatDateTime($solicitud['firmado_autorizacion_en'] ?? null)) ?></small></div>
                <?php elseif ($canSign): ?>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                        <h4 class="text-primary mb-3">Recepción de Token</h4>
                        <small class="text-primary mb-4">Ingrese el token de seguridad que recibió por Telegram o correo electrónico. Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.</small>
                        <div class="row w-100">
                            <div class="col-md-6 mb-3"><button type="button" class="btn btn-success w-100" data-create-token="telegram"><i class="ti ti-brand-telegram me-1"></i>Generar token vía Telegram</button></div>
                            <div class="col-md-6 mb-3"><button type="button" class="btn btn-info text-white w-100" data-create-token="email"><i class="ti ti-mail me-1"></i>Generar token vía Email</button></div>
                            <div class="col-12"><div class="input-group"><input type="text" class="form-control" placeholder="Token de seguridad" data-authorization-token inputmode="numeric" maxlength="6"><button class="btn btn-outline-success" type="button" data-finish-authorization disabled>Finalizar</button></div></div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de autorización electrónica</small></div>
                <?php else: ?>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4"><i class="ti ti-signature-off text-gray mb-3" style="font-size:100px"></i><h6 class="text-muted mb-0">¡Falta la autorización!</h6></div>
                    <div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de autorización electrónica</small></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
