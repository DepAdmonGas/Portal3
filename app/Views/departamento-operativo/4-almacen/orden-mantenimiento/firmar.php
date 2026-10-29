<?php
$e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$detail = $detalle;
$stageLabel = $tipoFirma === 'B' ? 'VO.BO.' : 'Autorización';
$signaturesByType = [];
foreach (($detail['firmas'] ?? []) as $signature) {
    $signaturesByType[(string)($signature['tipo'] ?? '')] = $signature;
}
$serviceLabels = [1 => 'Preventivo', 2 => 'Correctivo', 3 => 'Emergente'];
$workLabels = [1 => 'Sí', 2 => 'No', 3 => 'Ambas'];
$riskLabels = [1 => 'Sí', 2 => 'No'];
?>
<div id="orden-firma-app" class="pb-4" data-base-url="/departamento-operativo/almacen/orden-mantenimiento" data-record-id="<?= (int)$detail['id'] ?>" data-sign-type="<?= $e($tipoFirma) ?>">
    <div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mt-3 mb-4">
        <h4 class="fw-semibold mb-2"><i class="ti ti-signature me-1"></i> Firma pendiente: <?= $e($stageLabel) ?></h4>
        <p class="mb-0">La Orden de Mantenimiento requiere tu firma de <?= $e($stageLabel) ?>. Solicita un token por Telegram o correo e ingrésalo para continuar.</p>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-clipboard-text me-1"></i> Datos generales</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="form-label mb-1">Folio:</div><div><?= $e($detail['folio_label']) ?></div></div>
                <div class="col-12 col-md-3"><div class="form-label mb-1">Fecha:</div><div><?= $e($detail['fecha_label']) ?></div></div>
                <div class="col-12 col-md-3"><div class="form-label mb-1">Estación / Departamento:</div><div><?= $e($detail['estacion']) ?></div></div>
                <div class="col-12 col-md-3"><div class="form-label mb-1">Estatus:</div><div><span class="badge bg-warning text-white">En proceso: <?= $e($stageLabel) ?></span></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-align-box-left-top me-1"></i> ¿En qué afecta a la estación?</h5></div>
        <div class="card-body"><p class="mb-0 text-break"><?= nl2br($e($detail['afectacion'])) ?></p></div>
    </div>

    <?php
    $selectedOptionsTable = static function (string $title, array $options, int $selected): string {
        $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header = '<tr>'.implode('', array_map(static fn($option): string => '<th class="text-center align-middle">'.$e($option).'</th>', $options)).'</tr>';
        $cells = [];
        foreach ($options as $index => $_option) {
            $cells[] = '<td class="text-center align-middle">'.($selected === $index + 1 ? '<input class="form-check-input m-0" type="radio" checked disabled aria-label="Seleccionado">' : '').'</td>';
        }
        $body = $selected >= 1 && $selected <= count($options)
            ? '<tr>'.implode('', $cells).'</tr>'
            : '<tr><td colspan="'.count($options).'" class="text-center text-primary">No se encontró información seleccionada.</td></tr>';
        return '<div class="col-12 col-lg-4"><div class="card"><div class="card-header bg-primary"><h5 class="mb-0 text-white">'. $e($title).'</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-striped align-middle text-center w-100 mb-0" data-portal-table-selection="false"><thead>'.$header.'</thead><tbody>'.$body.'</tbody></table></div></div></div></div>';
    };
    ?>
    <div class="row g-3 mb-3">
        <?= $selectedOptionsTable('Tipo de servicio', ['Preventivo', 'Correctivo', 'Emergente'], (int)$detail['tipo_servicio']) ?>
        <?= $selectedOptionsTable('¿La orden de trabajo se puede atender internamente?', ['Sí', 'No', 'Ambas'], (int)$detail['orden_trabajo']) ?>
        <?= $selectedOptionsTable('¿La orden de trabajo es de alto riesgo?', ['Sí', 'No'], (int)$detail['orden_riesgo']) ?>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-map-pin me-1"></i> Áreas</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-striped table-bordered align-middle w-100 mb-0" data-portal-table-selection="false">
                <tbody>
                    <?php if (($detail['areas'] ?? []) === []): ?><tr><td colspan="2" class="text-center text-primary">No se encontró información</td></tr><?php endif; ?>
                    <?php foreach (($detail['areas'] ?? []) as $area): ?>
                        <?php $subareaNames = array_map(static fn($subarea): string => trim((string)$subarea['nombre']), $area['subareas'] ?? []); $subareaNames = array_values(array_filter($subareaNames, static fn($name): bool => $name !== '')); ?>
                        <tr>
                            <td>
                                <span><?= $e($area['area']) ?></span>
                                <?php if ($subareaNames !== []): ?><small class="d-block text-muted mt-1">(<?= $e(implode(', ', $subareaNames)) ?>)</small><?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int)$area['estatus'] === 1 ? '<i class="ti ti-check text-success fs-6"></i>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-tool me-1"></i> Refacciones solicitadas</h5></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-bordered align-middle w-100 mb-0" data-portal-table-selection="false">
            <thead><tr><th>Refacción</th><th class="text-center">Cantidad</th><th>Nota</th></tr></thead><tbody>
                <?php if (($detail['materiales'] ?? []) === []): ?><tr><td colspan="3" class="text-center text-primary">No se encontró información</td></tr><?php endif; ?>
                <?php foreach (($detail['materiales'] ?? []) as $material): ?><tr><td><?= $e($material['concepto']) ?></td><td class="text-center"><?= $e($material['cantidad']) ?></td><td><?= $e($material['nota']) ?></td></tr><?php endforeach; ?>
            </tbody>
        </table></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-camera me-1"></i> Evidencias</h5></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-bordered align-middle w-100 mb-0" data-portal-table-selection="false">
            <thead><tr><th class="text-center" style="width:96px">#</th><th class="text-center">Área</th><th class="text-center">Motivo</th><th class="text-center" style="width:48px"><i class="ti ti-download fs-6 text-primary" title="Descargar"></i></th></tr></thead><tbody>
                <?php if (($detail['evidencias'] ?? []) === []): ?><tr><td colspan="4" class="text-center text-primary">No se encontró información</td></tr><?php endif; ?>
                <?php foreach (($detail['evidencias'] ?? []) as $file): ?><tr><td class="text-center"><?= (int)$file['id'] ?></td><td class="text-center"><?= $e($file['area']) ?></td><td class="text-center"><?= $e($file['motivo']) ?></td><td class="text-center"><a class="btn btn-link p-0 text-decoration-none" href="<?= $e($file['url']) ?>" title="Descargar"><i class="ti ti-download fs-6 text-primary"></i></a></td></tr><?php endforeach; ?>
            </tbody>
        </table></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary"><h5 class="mb-0 text-white"><i class="ti ti-message-circle me-1"></i> Comentarios</h5></div>
        <div class="card-body"><p class="mb-0 text-break"><?= nl2br($e(trim((string)($detail['comentarios_generales'] ?? '')) !== '' ? $detail['comentarios_generales'] : 'S/I')) ?></p></div>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            'A' => ['label' => 'NOMBRE Y FIRMA DEL ENCARGADO', 'activeLabel' => 'FIRMA DEL ENCARGADO', 'pendingLabel' => 'ELABORÓ / ENCARGADO'],
            'B' => ['label' => 'NOMBRE Y FIRMA DE VO.BO.', 'activeLabel' => 'FIRMA DE VO.BO.', 'pendingLabel' => 'VO.BO.'],
            'C' => ['label' => 'FIRMA DE AUTORIZACIÓN', 'activeLabel' => 'FIRMA DE AUTORIZACIÓN', 'pendingLabel' => 'AUTORIZACIÓN'],
        ] as $type => $stage): ?>
            <?php
            $signature = $signaturesByType[$type] ?? null;
            $isCurrent = $type === $tipoFirma;
            $label = $stage['label'];
            $headerIcon = $signature ? 'ti-user-check' : ($isCurrent ? 'ti-circle-check' : 'ti-clock-hour-4');
            $headerLabel = $signature ? $label : ($isCurrent ? $stage['activeLabel'] : $stage['pendingLabel']);
            $missingSignatureLabel = match ($type) {
                'A' => 'el encargado',
                'B' => 'Vo.Bo.',
                default => 'Autorización',
            };
            ?>
            <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                <div class="card border h-100">
                    <div class="card-header bg-primary text-white py-3 border-0">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                                <i class="ti <?= $headerIcon ?> fs-6"></i>
                            </div>
                            <div class="ms-3 overflow-hidden"><h6 class="mb-0 text-white"><?= $e($headerLabel) ?></h6></div>
                        </div>
                    </div>

                    <?php if ($signature): ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                            <?php if (!empty($signature['firma_url'])): ?>
                                <img src="<?= $e($signature['firma_url']) ?>" class="img-fluid mb-3" style="max-height:100px;object-fit:contain" alt="Firma registrada">
                            <?php elseif (!empty($signature['electronica'])): ?>
                                <i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
                                <small class="text-dark fw-semibold">Fecha: <?= $e($signature['fecha'] ?? 'S/I') ?></small>
                                <small class="text-dark">La orden de mantenimiento se firmó por un medio electrónico.</small>
                            <?php else: ?>
                                <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                                <small class="text-muted">La firma está registrada, pero su imagen no está disponible.</small>
                            <?php endif; ?>
                            <?php if (!empty($signature['firma_url']) && !empty($signature['fecha'])): ?><small class="text-muted mt-2">Fecha: <?= $e($signature['fecha']) ?></small><?php endif; ?>
                        </div>
                        <div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate"><?= $e($signature['usuario']) ?></h6></div>
                    <?php elseif ($isCurrent): ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center p-3">
                            <h4 class="text-primary text-center mb-3">Recepción de Token</h4>
                            <small class="text-primary text-center mb-4 w-100">Ingrese el token de seguridad que recibió por Telegram o correo electrónico. Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.</small>
                            <div class="row g-2 justify-content-center w-100 mx-0">
                                <div class="col-6 px-1 d-flex"><button type="button" class="btn btn-success w-100 h-100 d-flex align-items-center justify-content-center text-center js-orden-token" data-channel="telegram"><i class="ti ti-brand-telegram me-1 flex-shrink-0"></i> Generar token vía Telegram</button></div>
                                <div class="col-6 px-1 d-flex"><button type="button" class="btn btn-info text-white w-100 h-100 d-flex align-items-center justify-content-center text-center js-orden-token" data-channel="email"><i class="ti ti-mail me-1 flex-shrink-0"></i> Generar token vía Email</button></div>
                                <div class="col-12 px-1 mt-3">
                                    <div class="input-group"><input id="orden-firma-token" type="text" class="form-control" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="Token de seguridad"><button id="orden-firma-confirmar" type="button" class="btn btn-outline-success" disabled><i class="ti ti-signature me-1"></i> Firmar solicitud</button></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de firma electrónica</small></div>
                    <?php else: ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                            <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                            <h6 class="text-muted mb-0">¡Falta la firma de <?= $e($missingSignatureLabel) ?>!</h6>
                        </div>
                        <div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de firma electrónica</small></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
