<?php
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$statusLabels = [0 => 'Pendiente', 1 => 'En proceso', 2 => 'Finalizado'];
$statusClasses = [0 => 'bg-danger text-white', 1 => 'bg-warning text-white', 2 => 'bg-success text-white'];
$currentLocationId = (int) ($context['id'] ?? 0);
$currentUserId = (int) (\App\Core\Auth::user()?->id ?? 0);
$isAuthorizationUser = $currentUserId === 19;
$badgeStatus = $isAuthorizationUser ? 1 : 0;
$badgeCount = count(array_filter($solicitudesCalcomanias, static fn (array $item): bool => (int) ($item['estado'] ?? -1) === $badgeStatus));
$badgeLabel = $isAuthorizationUser ? 'En proceso' : 'Pendientes';
$badgeClasses = $isAuthorizationUser ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger-emphasis';
$isMulti = !empty($solicitudCalcomaniasMultiestacion);
$formatDateTime = static function ($value): string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) return '—';
    $date = function_exists('formatearFecha') ? formatearFecha(date('Y-m-d', $timestamp)) : date('d/m/Y', $timestamp);
    return $date . ', ' . date('g:i a', $timestamp);
};
?>
<section id="refacciones-solicitud-calcomanias" x-data="actions()"
    data-base-url="<?= $escape($baseUrl) ?>" data-location-id="<?= $currentLocationId ?>"
    data-multiestacion="<?= $isMulti ? '1' : '0' ?>">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <span class="badge rounded-pill <?= $badgeClasses ?> px-3 py-2">
            <i class="ti <?= $isAuthorizationUser ? 'ti-hourglass' : 'ti-clock' ?> me-1"></i> <?= $badgeLabel ?>: <?= $badgeCount ?>
        </span>
        <?php if (!empty($puedeCrearSolicitudCalcomanias) && $currentLocationId > 0): ?>
            <button type="button" class="btn bg-primary-subtle text-primary" data-crear-solicitud><i class="ti ti-plus me-1"></i>Nuevo</button>
        <?php endif; ?>
    </div>
    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="tabla-solicitudes-calcomanias" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead><tr>
                    <th class="text-center">#</th><th>Nombre del personal</th><th class="text-center">Puesto</th>
                    <?php if (($context['id'] ?? null) === null): ?><th class="text-center">Estación / departamento</th><?php endif; ?>
                    <th class="text-center">Fecha y hora</th><th class="text-center">Firma</th><th class="text-center">Estatus</th><th class="text-center" width="48"><i class="ti ti-dots-vertical fs-5"></i></th>
                </tr></thead>
                <tbody>
                <?php foreach ($solicitudesCalcomanias as $row):
                    $id = (int) $row['id'];
                    $status = (int) $row['estado'];
                    $detailUrl = $baseUrl . '/solicitud-calcomanias/' . $id;
                    $dateOrder = strtotime((string) $row['fecha_hora']) ?: 0;
                    $ownedByCurrentUser = (int) ($row['id_usuario'] ?? 0) === $currentUserId;
                    $canEditRow = !$isMulti && $status === 0 && $ownedByCurrentUser;
                    $canDeleteRow = $isMulti
                        ? !empty($solicitudCalcomaniasPuedeEliminar) && in_array($status, [0, 1], true)
                        : $status === 0 && $ownedByCurrentUser;
                    ?>
                    <tr data-request-row data-request-id="<?= $id ?>">
                        <td class="text-center">00<?= $id ?></td>
                        <td><?= $escape($row['nombre_personal']) ?></td>
                        <td class="text-center"><?= $escape($row['puesto'] ?: '—') ?></td>
                        <?php if (($context['id'] ?? null) === null): ?><td class="text-center"><?= $escape($locations[(int) $row['id_ubicacion']] ?? '—') ?></td><?php endif; ?>
                        <td class="text-center" data-order="<?= (int) $dateOrder ?>"><?= $escape($formatDateTime($row['fecha_hora'])) ?></td>
                        <td class="text-center">
                            <?php if ($status === 0): ?><span class="text-muted" title="Pendiente de finalizar"><i class="ti ti-writing text-dark fs-8"></i></span>
                            <?php elseif ($status === 1 && $isAuthorizationUser): ?><a href="<?= $escape($baseUrl) ?>/solicitud-calcomanias-firma/<?= $id ?>" title="Firmar autorización" aria-label="Firmar autorización"><i class="ti ti-writing text-primary fs-8"></i></a>
                            <?php elseif ($status === 1): ?><span class="text-muted" title="En espera de autorización"><i class="ti ti-clock-hour-4 fs-8" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default"></i></span>
                            <?php else: ?><i class="ti ti-signature text-success fs-10" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default" title="Solicitud finalizada"></i><?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge <?= $statusClasses[$status] ?? 'bg-secondary' ?>"><?= $statusLabels[$status] ?? 'Desconocido' ?></span></td>
                        <td class="text-center">
                            <div class="dropdown dropstart">
                                <a href="javascript:void(0)" data-bs-toggle="dropdown" aria-label="Acciones" aria-expanded="false"><i class="ti ti-dots-vertical fs-5"></i></a>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item pointer js-solicitud-detalle" data-id="<?= $id ?>"><i class="ti ti-eye me-1"></i>Detalle</a>
                                    <a class="dropdown-item pointer js-solicitud-pdf<?= $status === 2 ? '' : ' disabled' ?>" data-id="<?= $id ?>" aria-disabled="<?= $status === 2 ? 'false' : 'true' ?>"><i class="ti ti-file-text me-1"></i>Descargar PDF</a>
                                    <a class="dropdown-item pointer js-solicitud-editar<?= $canEditRow ? '' : ' disabled' ?>" href="<?= $escape($detailUrl) ?>" aria-disabled="<?= $canEditRow ? 'false' : 'true' ?>"><i class="ti ti-pencil me-1"></i>Editar</a>
                                    <a class="dropdown-item pointer js-solicitud-eliminar<?= $canDeleteRow ? '' : ' disabled' ?>" data-id="<?= $id ?>" data-name="Solicitud #00<?= $id ?>" aria-disabled="<?= $canDeleteRow ? 'false' : 'true' ?>"><i class="ti ti-trash me-1"></i>Eliminar</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modal-detalle-solicitud-calcomanias" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl"><div class="modal-content">
            <div class="modal-header bg-primary"><h4 class="modal-title text-white"><i class="ti ti-eye me-1"></i>Detalle de la solicitud (<span data-modal-folio></span>)</h4><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="form-label mb-1">Folio:</div><div data-modal-folio-value></div></div>
                    <div class="col-md-3"><div class="form-label mb-1">Fecha y hora:</div><div data-modal-fecha></div></div>
                    <div class="col-md-3"><div class="form-label mb-1">Nombre del personal:</div><div data-modal-personal></div></div>
                    <div class="col-md-3"><div class="form-label mb-1">Puesto:</div><div data-modal-puesto></div></div>
                    <div class="col-md-3"><div class="form-label mb-1">Estación / departamento:</div><div data-modal-ubicacion></div></div>
                    <div class="col-md-3"><div class="form-label mb-1">Estado:</div><div data-modal-estado></div></div>
                </div>
                <div class="card mb-3"><div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-sticker me-1"></i>Calcomanías</h5></div><div class="table-responsive"><table class="table table-bordered table-striped align-middle text-center mb-0"><thead><tr><th>#</th><th class="text-start">Calcomanía</th><th>Piezas</th></tr></thead><tbody data-modal-items></tbody></table></div></div>
                <div class="card mb-3"><div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i>Observaciones</h5></div><div class="card-body" data-modal-observaciones></div></div>
                <label class="form-label mb-3">Firmas:</label>
                <div class="row">
                    <div class="col-md-6 mb-3"><div class="card border h-100">
                        <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center"><div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-user-check fs-5" data-modal-firma-icon></i></div><div class="ms-3 flex-grow-1" style="min-width:0"><h6 class="mb-0 text-white" style="white-space:normal;overflow-wrap:anywhere;line-height:1.15">NOMBRE Y FIRMA DEL ENCARGADO</h6></div></div></div>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:175px" data-modal-firma-encargado></div>
                        <div class="card-footer bg-light text-center" data-modal-firma-encargado-footer></div>
                    </div></div>
                    <div class="col-md-6 mb-3"><div class="card border h-100">
                        <div class="card-header bg-primary text-white py-3 border-0"><div class="d-flex align-items-center"><div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-user-check fs-5" data-modal-firma-icon></i></div><div class="ms-3 flex-grow-1" style="min-width:0"><h6 class="mb-0 text-white" style="white-space:normal;overflow-wrap:anywhere;line-height:1.15">NOMBRE Y FIRMA DE AUTORIZACIÓN</h6></div></div></div>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:175px" data-modal-firma-autorizacion></div>
                        <div class="card-footer bg-light text-center" data-modal-firma-autorizacion-footer></div>
                    </div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button></div>
        </div></div>
    </div>
</section>
