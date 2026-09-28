<div id="container" class="mt-4 mb-5"
data-solicitud='<?= htmlspecialchars(json_encode($solicitud, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-id-solicitud="<?= (int)$solicitud['id'] ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-aditivo"
x-data="{ ...actions(), ...pedidoAditivoFirmaComponent() }">

<div class="row">

<!---------- AVISOS ---------->
<template x-if="solicitud && solicitud.status === 1 && puedeFirmarVoBo" x-cloak>
<div class="col-12">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">
La solicitud ya fue elaborada y finalizada por el encargado. <br>
Para completar el proceso, es necesario que realice la <strong>firma del Visto Bueno</strong> mediante su token de seguridad.
</p>
</div>
</div>
</template>

<template x-if="solicitud && solicitud.status === 2" x-cloak>
<div class="col-12">
<div class="alert alert-success border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2"><i class="ti ti-circle-check me-1"></i> ¡La solicitud fue finalizada!</h4>
<p class="mb-0">La solicitud ya cuenta con la firma del Visto Bueno.</p>
</div>
</div>
</template>

<!---------- DATOS DE LA SOLICITUD ---------->
<div class="row g-3 mb-3">

<!---------- ENCABEZADO ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i> DETALLE ORDEN DE COMPRA</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-4"><div class="form-label mb-1">Folio:</div><div x-text="solicitud ? '#00' + solicitud.id : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Orden de compra:</div><div x-text="solicitud ? (solicitud.orden_compra || '—') : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Nombre del personal:</div><div x-text="solicitud ? solicitud.personal : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Puesto:</div><div x-text="solicitud ? solicitud.puesto : '—'"></div></div>
</div>
</div>
</div>
</div>

<!---------- DATOS EDITABLES (status 0) ---------->
<div class="col-12 mb-3">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-settings me-1"></i> DATOS DE LA SOLICITUD</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-4"><div class="form-label mb-1">Fecha:</div><div x-text="solicitud ? solicitud.fecha : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Para:</div><div x-text="solicitud ? (solicitud.para || '—') : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Fecha de entrega:</div><div x-text="solicitud ? (solicitud.fecha_entrega || 'Sin fecha asignada') : '—'"></div></div>
<div class="col-12"><div class="form-label mb-1">Comentarios:</div><div x-text="solicitud ? (solicitud.comentarios || 'Sin información') : '—'"></div></div>
</div>
</div>
</div>
</div>

<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-oil-barrel me-1"></i> TAMBOS</h5>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-center align-middle">Cantidad de tambores</th>
<th class="text-center align-middle">Nombre del producto</th>
<th class="text-center align-middle">Nombre del aditivo</th>
<th class="text-center align-middle">Kilogramo por tambor</th>
<th class="text-center align-middle">Total de kilos</th>
</tr>
</thead>
<tbody>
<template x-for="it in (solicitud ? (solicitud.tambos || []) : [])" :key="it.id">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.cantidad"></td>
<td class="text-center align-middle" x-text="it.producto"></td>
<td class="text-start align-middle" x-text="it.aditivo"></td>
<td class="text-center align-middle" x-text="it.kilogramo"></td>
<td class="text-center align-middle" x-text="it.cantidad * it.kilogramo"></td>
</tr>
</template>
<tr x-show="!solicitud || !solicitud.tambos || !solicitud.tambos.length">
<td colspan="6" class="text-center text-primary">No se encontró información</td>
</tr>
</tbody>
<tfoot x-show="solicitud && solicitud.tambos && solicitud.tambos.length">
<tr class="table-dark">
<td colspan="1" class="text-center fw-semibold text-white">Totales</td>
<td class="text-center fw-semibold text-white" x-text="solicitud ? (solicitud.total_tambos || 0) : 0"></td>
<td colspan="2"></td>
<td class="text-center fw-semibold text-white" x-text="solicitud && solicitud.tambos ? solicitud.tambos.reduce((acc, it) => acc + Number(it.kilogramo || 0), 0) : 0"></td>
<td class="text-center fw-semibold text-white" x-text="solicitud ? (solicitud.total_kilogramos || 0) : 0"></td>
</tr>
</tfoot>
</table>
</div>

</div>
</div>
</div>

</div>

<!---------- FIRMA VOBO ---------->
<div class="row">

<div class="col-xl-6 col-lg-6 col-md-6">

<template x-if="solicitud && solicitud.firmas && solicitud.firmas.B" x-cloak>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="solicitud.firmas.B.tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark" x-text="solicitud.firmas.B.firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="solicitud.firmas.B.usuario_nombre"></h6>
<template x-if="solicitud.firmas.B.fecha" x-cloak>
<small class="text-muted" x-text="solicitud.firmas.B.fecha"></small>
</template>
</div>
</div>
</template>

<template x-if="solicitud && !(solicitud.firmas && solicitud.firmas.B) && solicitud.status === 1 && puedeFirmarVoBo" x-cloak>
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
<small class="text-primary">
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
<button class="btn btn-outline-success" type="button" @click="firmarVobo()" :disabled="firmandoVobo || !token.trim()">
Firmar
</button>
</div>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
</template>

<template x-if="solicitud && !(solicitud.firmas && solicitud.firmas.B) && !(solicitud.status === 1 && puedeFirmarVoBo)" x-cloak>
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
</template>

</div>

</div>

</div>