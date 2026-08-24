<div x-data="diaDobleFirmarComponent()" class="mt-3 mb-4"
data-id-reporte="<?= $detail['id'] ?>"
data-year="<?= $detail['year'] ?>"
data-quincena="<?= $detail['quincena'] ?>"
data-status="<?= $detail['status'] ?>"
data-id-usuario="<?= $idUsuario ?>"
data-firma-b="<?= $firmaB ?>"
data-firma-c="<?= $firmaC ?>"
data-puede-firmar-b="<?= $permisosFirma['puedeFirmarB'] ? 'true' : 'false' ?>"
data-puede-firmar-c="<?= $permisosFirma['puedeFirmarC'] ? 'true' : 'false' ?>"
data-detalle='<?= htmlspecialchars(json_encode($detail), ENT_QUOTES, 'UTF-8') ?>'>

<!---------- STATUS ALERTS ---------->
<?php if ($detail['status'] === 3): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-success border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2"><i class="ti ti-circle-check me-1"></i> Día Doble Autorizado</h4>
<p class="mb-0">El reporte de días dobles ha sido <strong>autorizado</strong> correctamente. Todas las firmas fueron completadas.</p>
</div>
</div>
</div>

<?php elseif ($detail['status'] === 0): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">
El reporte de días dobles ya fue creado correctamente. <br>
Es necesario que el responsable de <strong>firmar el Visto Bueno</strong> finalice el proceso.
</p>
</div>
</div>
</div>

<?php elseif ($detail['status'] === 1 && $permisosFirma['puedeFirmarB']): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0">
La <strong>Solicitud de Día Doble</strong> ya fue creada correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma del Visto Bueno</strong> mediante su token de seguridad.
</p>
</div>
</div>
</div>

<?php elseif ($detail['status'] === 1 && !$permisosFirma['puedeFirmarB']): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">
El Visto Bueno aún no ha sido firmado. <br>
Es necesario que el responsable de <strong>firmar el Visto Bueno</strong> finalice el proceso.
</p>
</div>
</div>
</div>

<?php elseif ($detail['status'] === 2 && $permisosFirma['puedeFirmarC']): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0">
El <strong>Visto Bueno</strong> ya fue firmado correctamente. <br>
Para completar el proceso, es necesario que realice la <strong>firma de Autorización</strong> mediante su token de seguridad.
</p>
</div>
</div>
</div>

<?php elseif ($detail['status'] === 2 && !$permisosFirma['puedeFirmarC']): ?>
<div class="row">
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Autorización Pendiente!</h4>
<p class="mb-0">
El <strong>Visto Bueno</strong> ya fue firmado correctamente. <br>
Es necesario que el responsable de <strong>firmar la Autorización</strong> finalice el proceso.
</p>
</div>
</div>
</div>

<?php endif; ?>

<!---------- DESCRIPCION / DOCUMENTO ---------->
<div class="card mb-4">
<div class="card-body">

<div class="row mb-3">
<div class="col-12 text-end">
<b>No. de Folio:</b> 00<?= $detail['id'] ?>
<p><?= $detail['fecha_formateada'] ?></p>
</div>
</div>

<div class="row mb-3">
<div class="col-12">
<b>Lic. Alejandro Guzmán</b>
<br>
<p><b>Departamento de Recursos Humanos</b></p>
<p>Buenos días, por medio de la presente, les informo sobre los días dobles asignados al personal del Departamento de Dirección de Operaciones, correspondientes a la <b>Quincena No. <?= $detail['quincena'] ?></b>,
que abarca del <b><?= $detail['inicio_quincena'] ?></b>
al <b><?= $detail['fin_quincena'] ?></b>
<br> A continuación, detallo la información para cada uno de los colaboradores:
</p>
</div>
</div>

<div class="row mb-3">
<div class="col-12">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle" width="100%">
<thead>
<tr>
<th class="text-center align-middle" width="50">#</th>
<th class="align-middle">Empleado</th>
<th class="text-center align-middle">Día Doble</th>
</tr>
</thead>
<tbody>
<?php if (count($detail['empleados']) > 0): ?>
<?php foreach ($detail['empleados'] as $idx => $emp): ?>
<tr>
<td class="text-center align-middle fw-bold"><?= ($idx + 1) ?></td>
<td class="align-middle fw-semibold"><?= htmlspecialchars($emp['nombre']) ?></td>
<td class="text-center align-middle"><?= htmlspecialchars($emp['fecha_label']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="3" class="text-center text-muted py-2">No se encontró información</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<div class="row">
<div class="col-12 mt-3 mb-2 text-center"><p>Sin más por el momento quedo de usted.</p></div>
</div>

</div>
</div>

<!---------- FIRMAS ---------->
<div class="row">

<?php
$firmaARec = current(array_filter($detail['firmas'], fn($f) => $f['tipo_firma'] === 'A')) ?: null;
$firmaBRec = current(array_filter($detail['firmas'], fn($f) => $f['tipo_firma'] === 'B')) ?: null;
$firmaCRec = current(array_filter($detail['firmas'], fn($f) => $f['tipo_firma'] === 'C')) ?: null;
?>

<!-- Card A: ELABORÓ -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaARec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= htmlspecialchars($firmaARec['tipo_label']) ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<?php if ($firmaARec['es_imagen']): ?>
<img src="<?= htmlspecialchars($firmaARec['firma_img_url']) ?>" class="img-fluid" style="max-height:90px;object-fit:contain;">
<?php else: ?>
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark"><?= htmlspecialchars($firmaARec['fecha_label'] ?? '') ?>, <?= htmlspecialchars($firmaARec['hora_label'] ?? '') ?></small>
<?php endif; ?>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= htmlspecialchars($firmaARec['usuario_nombre']) ?></h6>
</div>
</div>
<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">NOMBRE Y FIRMA DE QUIEN ELABORÓ</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">Sin firma registrada</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma</small>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card B: VO.BO. -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaBRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= htmlspecialchars($firmaBRec['tipo_label']) ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>
<small class="text-dark"><?= $firmaBRec['firma_texto'] ?? '' ?></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= htmlspecialchars($firmaBRec['usuario_nombre']) ?></h6>
</div>
</div>

<?php elseif ($detail['status'] === 1 && $permisosFirma['puedeFirmarB']): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-circle-check fs-5"></i>
</div>
<div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE VO.BO.</h6></div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<h4 class="text-primary mb-3">Token Móvil</h4>
<small class="text-secondary" style="font-size:.85em;">Ingrese el token de seguridad que recibió por Telegram o correo electrónico. Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo:</small>
<div class="row w-100 mt-2">
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-success w-100" @click="crearToken('telegram')" :disabled="botonesDeshabilitados">
<i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
</button>
</div>
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-info text-white w-100" @click="crearToken('email')" :disabled="botonesDeshabilitados">
<i class="ti ti-mail me-1"></i> Generar token vía Email
</button>
</div>
<div class="col-12">
<div class="input-group">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
<button class="btn btn-outline-success" type="button" @click="firmarSolicitud('B')" :disabled="firmando || !token.trim()">
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

<?php elseif ($detail['status'] === 1 && !$permisosFirma['puedeFirmarB']): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
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

<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
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
<div class="col-xl-4 col-lg-6 col-md-6 mb-4">
<?php if ($firmaCRec): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white"><?= htmlspecialchars($firmaCRec['tipo_label']) ?></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>
<small class="text-dark"><?= $firmaCRec['firma_texto'] ?? '' ?></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate"><?= htmlspecialchars($firmaCRec['usuario_nombre']) ?></h6>
</div>
</div>

<?php elseif ($detail['status'] === 2 && $permisosFirma['puedeFirmarC']): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-circle-check fs-5"></i>
</div>
<div class="ms-3"><h6 class="mb-0 text-white">FIRMA DE AUTORIZACIÓN</h6></div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<h4 class="text-primary mb-3">Token Móvil</h4>
<small class="text-secondary" style="font-size:.85em;">Ingrese el token de seguridad que recibió por Telegram o correo electrónico. Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo:</small>
<div class="row w-100 mt-2">
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-success w-100" @click="crearToken('telegram')" :disabled="botonesDeshabilitados">
<i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
</button>
</div>
<div class="col-md-6 mb-3">
<button type="button" class="btn btn-info text-white w-100" @click="crearToken('email')" :disabled="botonesDeshabilitados">
<i class="ti ti-mail me-1"></i> Generar token vía Email
</button>
</div>
<div class="col-12">
<div class="input-group">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
<button class="btn btn-outline-success" type="button" @click="firmarSolicitud('C')" :disabled="firmando || !token.trim()">
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

<?php elseif ($detail['status'] === 2 && !$permisosFirma['puedeFirmarC']): ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
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

<?php else: ?>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
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

</div>

</div>
