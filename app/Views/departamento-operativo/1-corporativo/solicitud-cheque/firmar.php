<div x-data="solicitudChequeFirmarComponent()" class="mt-3 mb-4"
data-id-solicitud="<?= $detalle['id'] ?>"
data-id-year="<?= $detalle['id_year'] ?>"
data-id-mes="<?= $detalle['id_mes'] ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-usuario="<?= $idUsuario ?>"
data-status="<?= $detalle['status'] ?>"
data-periodo-vencido="<?= $periodoVencido ? 'true' : 'false' ?>"
data-firma-b="<?= $firmaB ?>"
data-firma-c="<?= $firmaC ?>"
data-es-user30="<?= $esUser30 ? 'true' : 'false' ?>"
data-es-user19="<?= $esUser19 ? 'true' : 'false' ?>"
data-es-user2="<?= $esUser2 ? 'true' : 'false' ?>"
data-es-user22="<?= $esUser22 ? 'true' : 'false' ?>"
data-es-gestoria="<?= $esGestoria ? 'true' : 'false' ?>"
data-id-depto="<?= $detalle['depto'] ?? 0 ?>"
data-detalle='<?= htmlspecialchars(json_encode($detalle), ENT_QUOTES, 'UTF-8') ?>'>

<!---------- STATUS ALERTS ---------->
<?php if ($detalle['status'] === 0 && $periodoVencido): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-danger border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡La Solicitud de Cheque a expirado!</h4>
<p class="mb-0">
El periodo permitido para obtener las firmas <strong>expiró</strong>. Será necesario generar una nueva solicitud de cheque.
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 0 && !$periodoVencido && $puedeFirmarVOBO && $firmaB === 0): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0" >
La <strong>Solicitud de Cheque</strong> ya fue creada correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma del Visto Bueno</strong> mediante su token de seguridad.
</p>

</div>

</div>
</div>

<?php elseif ($detalle['status'] === 1 && $firmaB === 1 && $puedeFirmarAuth && $firmaC === 0): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0" >
El <strong>Visto Bueno</strong> ya fue firmado correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma de autorización</strong> mediante su token de seguridad.
</p>

</div>

</div>
</div>

<?php elseif ($detalle['status'] === 0 && !$periodoVencido && $puedeFirmarAuth && $firmaB === 0): ?>

<div class="row">
<div class="col-12 mb-4">

<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0" >Es necesario que el responsable de <strong>firmar el Visto Bueno</strong> finalice el proceso.</p>
</div>

</div>
</div>

<?php elseif ($detalle['status'] === 1 && $firmaB === 1 && $firmaC === 0 && !$puedeFirmarAuth): ?>

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

<?php endif; ?>

<div class="card">
<div class="card-body">
<div class="row">

<?php if ($idEstacion == 8): ?>
<div class="col-12 mb-3">
<label class="form-label">Razón Social:</label>
<p class="mb-0 fw-medium"><?= $detalle['razonsocial'] ?: 'S/I' ?></p>
</div>
<?php endif; ?>

<div class="col-md-4 mb-3">
<label class="form-label">Fecha:</label>
<p class="mb-0 fw-medium"><?= $detalle['fecha_formateada'] ?></p>
</div>

<div class="col-md-8 mb-3">
<label class="form-label">Nombre del Beneficiario:</label>
<p class="mb-0 fw-medium"><?= $detalle['beneficiario'] ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Monto / Moneda:</label>
<p class="mb-0 fw-medium">$<?= number_format($detalle['monto'], 2) ?> <?= $detalle['moneda'] ?></p>
</div>

<div class="col-md-8 mb-3">
<label class="form-label">Importe con letra:</label>
<p class="mb-0 fw-medium"><?= $detalle['importe_letra'] ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">No. Factura:</label>
<p class="mb-0 fw-medium"><?= $detalle['no_factura'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Correo Electrónico:</label>
<p class="mb-0 fw-medium"><?= $detalle['email'] ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Concepto:</label>
<p class="mb-0 fw-medium"><?= nl2br(htmlspecialchars($detalle['concepto'])) ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Nombre del solicitante:</label>
<p class="mb-0 fw-medium"><?= $detalle['solicitante'] ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Teléfono:</label>
<p class="mb-0 fw-medium"><?= $detalle['telefono'] ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Uso del CFDI:</label>
<p class="mb-0 fw-medium"><?= $detalle['cfdi'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Método de Pago:</label>
<p class="mb-0 fw-medium"><?= $detalle['metodo_pago'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Forma de Pago:</label>
<p class="mb-0 fw-medium"><?= $detalle['forma_pago'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Banco:</label>
<p class="mb-0 fw-medium"><?= $detalle['banco'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">No. de Cuenta:</label>
<p class="mb-0 fw-medium"><?= $detalle['no_cuenta'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">No. de Cuenta (CLABE):</label>
<p class="mb-0 fw-medium"><?= $detalle['cuenta_clabe'] ?: 'S/I' ?></p>
</div>

<div class="col-md-4 mb-3">
<label class="form-label">Referencia / Convenio:</label>
<p class="mb-0 fw-medium"><?= $detalle['referencia'] ?: 'S/I' ?></p>
</div>

</div>
</div>
</div>

<!---------- OBSERVACIONES ---------->
<div class="row">
<div class="col-12 mb-4">
<div class="card h-100">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-2"></i>OBSERVACIONES</h5>
</div>
</div>
<div class="card-body">
<p class="mb-0"><?= nl2br(htmlspecialchars($detalle['observaciones'] ?: 'Sin observaciones')) ?></p>
</div>
</div>
</div>
</div>

<!---------- DOCUMENTOS ---------->
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-file me-2"></i>DOCUMENTOS</h5>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>Nombre del documento</th>
<th class="text-center" width="48px"><i class="ti ti-download text-primary" title="Descargar"></i></th>
</tr>
</thead>
<tbody>
<template x-if="detalle.documentos && detalle.documentos.length > 0">
<template x-for="d in detalle.documentos" :key="d.id">
<tr>
<td x-text="d.nombre"></td>
<td class="text-center"><i class="ti ti-download pointer text-primary" @click.prevent="download('solicitud-cheque', d.documento)" title="Descargar"></i></td>
</tr>
</template>
</template>
<template x-if="!detalle.documentos || detalle.documentos.length === 0">
<tr><td colspan="2" class="text-center text-secondary">No se encontró información</td></tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>



<!---------- FIRMAS ---------->
<div class="row">

<?php
$firmaARec = current(array_filter($detalle['firmas'], fn($f) => $f['tipo_firma'] === 'A')) ?: null;
$firmaBRec = current(array_filter($detalle['firmas'], fn($f) => $f['tipo_firma'] === 'B')) ?: null;
$firmaCRec = current(array_filter($detalle['firmas'], fn($f) => $f['tipo_firma'] === 'C')) ?: null;
?>

<!-- Card A: ENCARGADO / ELABORÓ -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaARec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= $firmaARec['tipo_label'] ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<?php if ($firmaARec['tipo_firma'] === 'A' && $firmaARec['firma_img_url']): ?>
<img src="<?= $firmaARec['firma_img_url'] ?>" class="img-fluid" style="max-height:90px;object-fit:contain;">
<?php else: ?>
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark"><?= $firmaARec['firma_texto'] ?? '' ?></small>
<?php endif; ?>
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
<h6 class="mb-0 text-white">ELABORÓ / ENCARGADO</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">Sin firma registrada</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card B: VOBO -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaBRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= $firmaBRec['tipo_label'] ?></h6>
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
<?php elseif ($detalle['status'] === 0 && !$periodoVencido && $puedeFirmarVOBO): ?>
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
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
<button class="btn btn-outline-success" type="button" @click="firmarSolicitud('B')" :disabled="!token.trim()">
Firmar solicitud
</button>
</div>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
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
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card C: AUTORIZACIÓN -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaCRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= $firmaCRec['tipo_label'] ?></h6>
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
<?php elseif ($detalle['status'] === 1 && $firmaB >= 1 && $puedeFirmarAuth): ?>
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
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
<button class="btn btn-outline-success" type="button" @click="firmarSolicitud('C')" :disabled="!token.trim()">
Firmar solicitud
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
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
<?php endif; ?>
</div>

</div>

</div>
