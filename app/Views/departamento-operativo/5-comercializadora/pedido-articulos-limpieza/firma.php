<div id="container" class="mt-4 mb-5"
data-pedido='<?= htmlspecialchars(json_encode($pedido, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-id-pedido="<?= (int)$pedido['id'] ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-articulos-limpieza"
x-data="{ ...actions(), ...pedidoLimpiezaFirmaComponent() }">

<div class="row">

<!---------- AVISOS ---------->
<template x-if="pedido && pedido.status === 1 && puedeFirmarVoBo" x-cloak>
<div class="col-12 mb-4">
<div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2">¡Visto Bueno Pendiente!</h4>
<p class="mb-0">
El pedido ya fue elaborado y finalizado por el encargado. <br>
Para completar el proceso, es necesario que realice la <strong>firma del Visto Bueno</strong> mediante su token de seguridad.
</p>
</div>
</div>
</template>

<template x-if="pedido && pedido.status === 2" x-cloak>
<div class="col-12 mb-4">
<div class="alert alert-success border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0">
<h4 class="fw-semibold mb-2"><i class="ti ti-circle-check me-1"></i> ¡El pedido fue finalizado!</h4>
<p class="mb-0">El pedido ya cuenta con la firma del Visto Bueno.</p>
</div>
</div>
</template>

<!---------- DATOS DEL PEDIDO ---------->
<div class="row g-3 mb-3">

<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-spray me-1"></i> DETALLE DEL PEDIDO</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-4"><div class="form-label mb-1">Folio:</div><div x-text="pedido ? '#00' + pedido.id : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Estacion:</div><div x-text="pedido ? pedido.nombre_estacion : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Fecha y hora:</div><div x-text="pedido ? pedido.fecha_hora : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Nombre del personal:</div><div x-text="pedido ? pedido.personal : '—'"></div></div>
<div class="col-md-4"><div class="form-label mb-1">Puesto:</div><div x-text="pedido ? pedido.puesto : '—'"></div></div>
</div>

</div>
</div>
</div>


<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-shopping-cart me-1"></i> PRODUCTOS</h5>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="48px">#</th>
<th class="text-center align-middle">Unidad</th>
<th class="text-start align-middle">Nombre del producto</th>
<th class="text-center align-middle">Piezas</th>
</tr>
</thead>
<tbody>
<template x-for="it in (pedido ? (pedido.detalle || []) : [])" :key="it.id">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.unidad"></td>
<td class="text-start align-middle" x-text="it.producto"></td>
<td class="text-center align-middle" x-text="it.piezas"></td>
</tr>
</template>
<tr x-show="!pedido || !pedido.detalle || !pedido.detalle.length">
<td colspan="4" class="text-center text-primary">No se encontro informacion</td>
</tr>
</tbody>
<tfoot>
<tr class="table-dark">
<td colspan="3" class="text-center fw-semibold text-white">Total de piezas</td>
<td class="text-center fw-semibold text-white" x-text="pedido ? (pedido.total_piezas || 0) : 0"></td>
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

<div class="col-xl-6 col-lg-6 col-md-6 mb-4">

<template x-if="pedido && pedido.firmas && pedido.firmas.B" x-cloak>
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="pedido.firmas.B.tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark" x-text="pedido.firmas.B.firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="pedido.firmas.B.usuario_nombre"></h6>
<template x-if="pedido.firmas.B.fecha" x-cloak>
<small class="text-muted" x-text="pedido.firmas.B.fecha"></small>
</template>
</div>
</div>
</template>

<template x-if="pedido && !(pedido.firmas && pedido.firmas.B) && pedido.status === 1 && puedeFirmarVoBo" x-cloak>
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
<button class="btn btn-outline-success" type="button" @click="firmarVobo()" :disabled="firmandoVobo || !token.trim()">
Firmar
</button>
</div>
</div>
</div>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>

<template x-if="pedido && !(pedido.firmas && pedido.firmas.B) && !(pedido.status === 1 && puedeFirmarVoBo)" x-cloak>
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
</template>

</div>

</div>

</div>