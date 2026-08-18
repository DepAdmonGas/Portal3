<div x-data="formatosFirmarComponent()" class="mt-3 mb-4"
data-id-formato="<?= $detalle['id'] ?>"
data-id-usuario="<?= $idUsuario ?>"
data-status="<?= $detalle['status'] ?>"
data-firma-b="<?= $firmaB ?>"
data-firma-c="<?= $firmaC ?>"
data-firma-d="<?= $firmaD ?>"
data-es-firmante-vobo="<?= $permisos['esFirmanteVOBO'] ? 'true' : 'false' ?>"
data-es-firmante-auth="<?= $permisos['esFirmanteAutorizacion'] ? 'true' : 'false' ?>"
data-es-firmante-verificacion="<?= $permisos['esFirmanteVerificacion'] ? 'true' : 'false' ?>">

<!---------- STATUS ALERTS ---------->
<?php if ($detalle['status'] === 0): ?>

<div class="row">
<div class="col-12 mb-4">
   
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Elaboración Pendiente!</h4>
<p class="mb-0">
El <strong><?= $detalle['formato_nombre'] ?></strong> ya fue creado correctamente. <br>
Es necesario que quien lo elabora firme en el formulario para finalizarlo.
</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 1 && $firmaB === 0 && $permisos['puedeFirmarVOBO']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">
La <strong>elaboración</strong> ya fue firmada correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma del Visto Bueno</strong> mediante su token de seguridad.
</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 2 && $firmaC === 0 && $permisos['puedeFirmarAuth']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0">
El <strong>Visto Bueno</strong> ya fue firmado correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma de autorización</strong> mediante su token de seguridad.
</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 1 && $firmaB === 0 && !$permisos['puedeFirmarVOBO']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">Es necesario que el responsable de <strong>firmar el Visto Bueno</strong> finalice el proceso.</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 2 && $firmaC === 0 && !$permisos['puedeFirmarAuth']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0">
El <strong>Visto Bueno</strong> ya fue firmado correctamente. <br>
Es necesario que el responsable de <strong>firmar la autorización</strong> finalice el proceso.</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 3 && $firmaD === 0 && $permisos['puedeFirmarVerificacion']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Verificación Pendiente!</h4>
<p class="mb-0">
La <strong>autorización</strong> ya fue firmada correctamente. <br>
Para finalizar el proceso, es necesario que realice la <strong>firma de verificación</strong> sobre el canvas de abajo.
</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 3 && $firmaD === 0 && !$permisos['puedeFirmarVerificacion']): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Verificación Pendiente!</h4>
<p class="mb-0">
La <strong>autorización</strong> ya fue firmada correctamente. <br>
Es necesario que el responsable de <strong>firmar la verificación</strong> finalice el proceso.</p>
</div>

</div>
</div>

<?php endif; ?>

<div class="card">
<div class="card-body">
<div class="row">

<div class="col-12 mb-4 text-end">
<div class="mb-3"><span class="badge rounded-pill <?= $detalle['status'] === 0 ? 'bg-danger' : ($detalle['status'] >= 3 ? 'bg-success' : 'bg-warning text-white') ?>"><?= $detalle['status_label'] ?></span></div>
<div>Formato: <span class="text-primary"><?= $detalle['codigo_formato'] ?: '' ?></span></div>
<div>No. de control: <span class="text-primary"><?= $detalle['no_control'] ?: '—' ?></span></div>
<div><?= $detalle['encabezado_ciudad'] ?: '' ?></div>
</div>


<div class="col-md-12">
<p class="mb-0 fw-medium"><?= $detalle['dirigido_a'] ?></p>
</div>

<?php if (!empty($detalle['intro'])): ?>
<div class="col-md-12 mb-3">
<p class="mb-0 text-muted"><?= $detalle['intro'] ?></p>
</div>
<?php endif; ?>

</div>

<?php if (!empty($detalle['tabla']['headers'])): ?>
<div class="table-responsive mb-4">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<?php foreach ($detalle['tabla']['headers'] as $hi => $header): ?>
<th class="text-center"><?= $detalle['formato'] == 1 && $header === 'Archivo' ? '<i class="ti ti-file-text text-primary fs-5"></i>' : htmlspecialchars($header) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php if (empty($detalle['tabla']['rows'])): ?>
<tr>
<td colspan="<?= count($detalle['tabla']['headers']) ?>" class="text-center text-muted py-4">
<i class="ti ti-table-empty me-1"></i> No se encontró información para mostrar
</td>
</tr>
<?php else: ?>
<?php foreach ($detalle['tabla']['rows'] as $row): ?>
<tr>
<?php foreach ($row as $cell): ?>
<?php if (!empty($cell['archivos'])): ?>
<td class="text-center">
<i class="ti ti-file-text text-primary fs-5 pointer"
data-archivos='<?= htmlspecialchars(json_encode($cell['archivos']), ENT_QUOTES) ?>'
data-nombre-empleado="<?= htmlspecialchars($cell['empleado_nombre'] ?? '', ENT_QUOTES) ?>"
title="Ver documentos del empleado"></i>
</td>
<?php else: ?>
<?php $tag = !empty($cell['header']) ? 'th' : 'td'; ?>
<<?= $tag ?> class="text-center" <?= !empty($cell['colspan']) ? 'colspan="' . (int)$cell['colspan'] . '"' : '' ?>><?= htmlspecialchars($cell['value']) ?></<?= $tag ?>>
<?php endif; ?>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>


</div>
</div>

<!---------- FIRMAS ---------->
<div class="row">

<?php
$firmaARec = current(array_filter($firmas, fn($f) => $f['tipo_firma'] === 'A')) ?: null;
$firmaBRec = current(array_filter($firmas, fn($f) => $f['tipo_firma'] === 'B')) ?: null;
$firmaCRec = current(array_filter($firmas, fn($f) => $f['tipo_firma'] === 'C')) ?: null;
$firmaDRec = current(array_filter($firmas, fn($f) => $f['tipo_firma'] === 'D')) ?: null;
?>

<!-- Card A: ELABORÓ -->
<div class="col-xl-3 col-lg-6 col-md-12 col-sm-12 mb-4">
<?php if ($firmaARec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white text-truncate"><?= $firmaARec['tipo_label'] ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<?php if ($firmaARec['tipo_firma'] === 'A' && $firmaARec['firma_img_url']): ?>
<img src="<?= $firmaARec['firma_img_url'] ?>" onerror="this.style.display='none'" class="img-fluid" style="max-height:90px;object-fit:contain;">
<?php endif; ?>
<small class="text-dark"><?= $firmaARec['firma_texto'] ?? '' ?></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= $firmaARec['usuario_nombre'] ?></h6>
</div>
</div>
<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">ELABORÓ</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">Sin firma registrada</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card B: VOBO -->
<div class="col-xl-3 col-lg-6 col-md-12 col-sm-12 mb-4">
<?php if ($firmaBRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white text-truncate"><?= $firmaBRec['tipo_label'] ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark"><?= $firmaBRec['firma_texto'] ?? '' ?></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= $firmaBRec['usuario_nombre'] ?></h6>
</div>
</div>
<?php elseif ($firmaB === 0 && $permisos['puedeFirmarVOBO'] && $detalle['status'] === 1): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-circle-check fs-6"></i>
</div>
<div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE VO.BO.</h6></div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<h4 class="text-primary mb-3">Recepción de Token</h4>
<small class="text-primary mb-4">
Ingrese el token de seguridad que recibió por Telegram o correo electrónico.
Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.
</small>
<div class="row w-100">
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-success w-100" @click="crearTokenTelegram()" :disabled="botonesDeshabilitados">
<i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
</button>
</div>
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-info text-white w-100" @click="crearTokenEmail()" :disabled="botonesDeshabilitados">
<i class="ti ti-mail me-1"></i> Generar token vía Email
</button>
</div>
<div class="col-12">
<div class="input-group">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token" inputmode="numeric" maxlength="6">
<button class="btn btn-outline-success" type="button" @click="firmarFormato('B')" :disabled="!token.trim()">
Firmar formato
</button>
</div>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">VO.BO.</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Vo.Bo.!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card C: AUTORIZACIÓN -->
<div class="col-xl-3 col-lg-6 col-md-12 col-sm-12 mb-4">
<?php if ($firmaCRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white text-truncate"><?= $firmaCRec['tipo_label'] ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark"><?= $firmaCRec['firma_texto'] ?? '' ?></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= $firmaCRec['usuario_nombre'] ?></h6>
</div>
</div>
<?php elseif ($firmaB >= 1 && $firmaC === 0 && $permisos['puedeFirmarAuth'] && $detalle['status'] === 2): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-circle-check fs-6"></i>
</div>
<div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE AUTORIZACIÓN</h6></div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<h4 class="text-primary mb-3">Recepción de Token</h4>
<small class="text-primary mb-4">
Ingrese el token de seguridad que recibió por Telegram o correo electrónico.
Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.
</small>
<div class="row w-100">
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-success w-100" @click="crearTokenTelegram()" :disabled="botonesDeshabilitados">
<i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
</button>
</div>
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-info text-white w-100" @click="crearTokenEmail()" :disabled="botonesDeshabilitados">
<i class="ti ti-mail me-1"></i> Generar token vía Email
</button>
</div>
<div class="col-12">
<div class="input-group">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token" inputmode="numeric" maxlength="6">
<button class="btn btn-outline-success" type="button" @click="firmarFormato('C')" :disabled="!token.trim()">
Firmar formato
</button>
</div>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">AUTORIZACIÓN</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Autorización!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card D: VERIFICACIÓN -->
<div class="col-xl-3 col-lg-6 col-md-12 col-sm-12 mb-4">
<?php if ($firmaDRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white text-truncate"><?= $firmaDRec['tipo_label'] ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<?php if ($firmaDRec['tipo_firma'] === 'D' && $firmaDRec['firma_img_url']): ?>
<img src="<?= $firmaDRec['firma_img_url'] ?>" onerror="this.style.display='none'" class="img-fluid" style="max-height:90px;object-fit:contain;">
<?php endif; ?>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= $firmaDRec['usuario_nombre'] ?></h6>
</div>
</div>
<?php elseif ($detalle['status'] === 3 && $permisos['puedeFirmarVerificacion']): ?>
<div class="card border h-100">
<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-pencil fs-6"></i>
</div>
<div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE VERIFICACIÓN</h6></div>
</div>
<button type="button" class="btn btn-sm bg-danger text-white" @click="limpiarFirma"><i class="ti ti-eraser me-1"></i> Limpiar</button>
</div>
</div>
<div class="card-body p-0">
<div id="signature-pad" class="signature-pad border-0" style="cursor:crosshair;">
<div class="signature-pad--body">
<canvas id="canvas" style="width:100%;height:200px;"></canvas>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<button type="button" class="btn btn-primary w-100" @click="firmarVerificacion()" :disabled="firmandoVerificacion">
<i class="ti ti-writing me-1"></i> Firmar verificación
</button>
</div>
</div>
<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">VERIFICACIÓN</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Verificación!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma</small>
</div>
</div>
<?php endif; ?>
</div>

</div>

</div>

<?php if ($detalle['formato'] == 1): ?>
<div class="modal fade" id="modalArchivosEmpleadoFirma" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">
<div class="modal-header bg-light">
<h5 class="modal-title"><i class="ti ti-files me-1"></i> Documentación de <span class="text-primary" id="archivosEmpleadoFirmaNombre">—</span></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body" id="archivosEmpleadoFirmaBody">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>
<?php endif; ?>
