<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Control Volumétrico.
</div>
<?php else: ?>
<div id="container" class="mt-4 mb-4"
data-id-mes-db="<?= $idMesDb ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-estado="<?= $estado ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-tipo-puesto="<?= $tipoPuesto ?>"
x-data="controlVolumetricoComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando control volumétrico...</p>
</div>
</template>

<template x-if="!loading">

<div class="row">

<!-- CARDS DE PRODUCTOS -->
<div class="col-md-8">

<!-- PRODUCTOS -->
<template x-for="p in productos" :key="p.id">
<div class="row">

<div class="col-12">
<div class="card">

<div class="card-header d-flex align-items-center gap-2" :style="{ backgroundColor: getProductColor(p.producto), borderBottom: 'none' }">
<i class="ti ti-gas-station text-white fs-6"></i>
<h5 class="mb-0 text-white text-start" x-text="p.producto"></h5>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

<thead>
<tr>
<th class="text-center align-middle"></th>
<th class="text-center align-middle">Rep. Volumétrico</th>
<th class="text-center align-middle">Reg. Contables</th>
<th class="text-center align-middle">Diferencias</th>
</tr>
</thead>

<tbody>

<!-- Inventario final -->
<tr>
<td class="fw-semibold ">Inventario final</td>

<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
x-init="$el.value = formatDisplay(p.dato1)" @focus="$el.value = (p.dato1 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0;
p.dato1 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato1', v)" @input="p.dato1 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
x-init="$el.value = formatDisplay(p.dato2)" @focus="$el.value = (p.dato2 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0;
p.dato2 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato2', v)"
@input="p.dato2 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" :class="diffColor(p.dato1 - p.dato2)" x-text="formatDisplay(p.dato1 - p.dato2)"></td>
</tr>

<!-- Compras L -->
<tr>
<td class="fw-semibold">Compras L</td>

<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
x-init="$el.value = formatDisplay(p.dato3)" @focus="$el.value = (p.dato3 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0; p.dato3 = v;
$el.value = formatDisplay(v); editarResumen(p.id, 'dato3', v)"
@input="p.dato3 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" x-text="formatDisplay(p.dato4)"></td>
<td class="text-end fw-bold" :class="diffColor(p.dato3 - p.dato4)" x-text="formatDisplay(p.dato3 - p.dato4)"></td>
</tr>

<!-- $ Compras -->
<tr>

<td class="fw-semibold">$</td>

<td class="text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
style="padding-left: 25px !important;" x-init="$el.value = formatDisplay(p.dato5)"
@focus="$el.value = (p.dato5 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0;
p.dato5 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato5', v)"
@input="p.dato5 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" x-text="'$ ' + formatDisplay(p.dato6)"> </td>
<td class="text-end fw-bold" :class="diffColor(p.dato5 - p.dato6)" x-text="'$ ' + formatDisplay(p.dato5 - p.dato6)"></td>
</tr>

<!-- Ventas L -->
<tr>

<td class="fw-semibold">Ventas L</td>

<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
x-init="$el.value = formatDisplay(p.dato7)" @focus="$el.value = (p.dato7 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0; p.dato7 = v;
$el.value = formatDisplay(v); editarResumen(p.id, 'dato7', v)"
@input="p.dato7 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" x-text="formatDisplay(p.dato8)"></td>
<td class="text-end fw-bold" :class="diffColor(p.dato7 - p.dato8)" x-text="formatDisplay(p.dato7 - p.dato8)"></td>
</tr>

<!-- $ Ventas -->
<tr>

<td class="fw-semibold">$</td>
<td class="text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;"
x-init="$el.value = formatDisplay(p.dato9)" @focus="$el.value = (p.dato9 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0; p.dato9 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato9', v)"
@input="p.dato9 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" x-text="'$ ' + formatDisplay(p.dato10)"></td>
<td class="text-end fw-bold" :class="diffColor(p.dato9 - p.dato10)" x-text="'$ ' + formatDisplay(p.dato9 - p.dato10)"></td>
</tr>

<!-- Despachos L -->
<tr>

<td class="fw-semibold">Despachos L</td>
<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent" x-init="$el.value = formatDisplay(p.dato11)"
@focus="$el.value = (p.dato11 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0; p.dato11 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato11', v)"
@input="p.dato11 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end" x-text="formatDisplay(p.dato12)"></td>
<td class="text-end fw-bold" :class="diffColor(p.dato11 - p.dato12)" x-text="formatDisplay(p.dato11 - p.dato12)">
</td>
</tr>

<!-- $ Despachos -->
<tr>

<td class="fw-semibold">$</td>
<td class="text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>

<input type="text" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;"
x-init="$el.value = formatDisplay(p.dato13)" @focus="$el.value = (p.dato13 || 0).toString(); $el.select()"
@blur=" const v = parseFloat($el.value.replace(/,/g, '')) || 0; p.dato13 = v; $el.value = formatDisplay(v); editarResumen(p.id, 'dato13', v)"
@input="p.dato13 = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>

<td class="text-end"x-text="'$ ' + formatDisplay(p.dato14)"></td>
<td class="text-end fw-bold" :class="diffColor(p.dato13 - p.dato14)" x-text="'$ ' + formatDisplay(p.dato13 - p.dato14)"></td>
</tr>

<!-- PARAMETRO -->
<tr>
<td class="text-center align-middle" colspan="2"><strong>Parametro 1.5%</strong></td>
<td class="text-center align-middle" colspan="2">
<span :class="paramColor(p.parametro)" x-text="formatDisplay(p.parametro) + '%'"></span>
</td>
</tr>

</tbody>
</table>

</div>
</div>
</div>
</div>

<!-- CARD COMENTARIO -->
<div class="col-12">
<div class="card border-0">

<div class="card-header bg-primary d-flex align-items-center gap-2">
<i class="ti ti-eye text-white fs-6"></i>
<h5 class="mb-0 text-white">OBSERVACIONES</h5>
</div>

<div class="card-body p-0">
<textarea class="form-control border-0 p-3" rows="5"
placeholder="Escribe tu comentario aquí..."
style="resize: vertical;"
x-model="p.comentario"
@keyup="editarComentarioResumen(p.id)">
</textarea>
</div>

</div>
</div>

</div>
</template>

<div class="row">

<!-- ACEITES -->
<div class="col-12">
<div class="card ">
<div class="card-header bg-primary d-flex align-items-center gap-2">
<i class="ti ti-droplet text-white fs-6"></i>
<h5 class="mb-0 text-white">ACEITES</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th></th>
<th class="text-center">Piezas</th>
<th class="text-center">Rep. Volumétrico</th>
<th class="text-center">Reg. Contables</th>
<th class="text-center">Diferencias</th>
</tr>
</thead>
<tbody>
<tr>
<td class="fw-semibold text-center">Ventas</td>
<td class="text-center" x-text="formatInt(aceites.piezas)"></td>
<td class="text-end p-0">
<div class="position-relative">
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent" x-init="$el.value = formatDisplay(aceites.volumetrico)" @focus="$el.value = (aceites.volumetrico || 0).toString(); $el.select()"
@blur="const v = parseFloat($el.value.replace(/,/g, '')) || 0; aceites.volumetrico = v; $el.value = formatDisplay(v); editarAceite()"
@input="aceites.volumetrico = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>
<td class="text-end" x-text="'$ ' + formatDisplay(aceites.contables)"></td>
<td class="text-end fw-bold" :class="diffColor(aceites.diferencia)" x-text="'$ ' + formatDisplay(aceites.diferencia)"></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- GRAN TOTAL -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary d-flex align-items-center gap-2">
<i class="ti ti-calculator text-white fs-6"></i>
<h5 class="mb-0 text-white">GRAN TOTAL</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th></th>
<th class="text-center">Rep. Volumétrico</th>
<th class="text-center">Reg. Contables</th>
<th class="text-center">Diferencias</th>
</tr>
</thead>
<tbody>
<tr>
<td class="fw-semibold">Compras L</td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato3)"></td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato4)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif2)" x-text="formatNum(totales.dif2)"></td>
</tr>
<tr>
<td class="fw-semibold">$</td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato5)"></td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato6)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif3)" x-text="'$ ' + formatNum(totales.dif3)"></td>
</tr>
<tr>
<td class="fw-semibold">Ventas L</td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato7)"></td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato8)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif4)" x-text="formatNum(totales.dif4)"></td>
</tr>
<tr>
<td class="fw-semibold">$</td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato9)"></td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato10)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif5)" x-text="'$ ' + formatNum(totales.dif5)"></td>
</tr>
<tr>
<td class="fw-semibold">Despachos L</td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato11)"></td>
<td class="text-end fw-bold" x-text="formatNum(totales.dato12)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif6)" x-text="formatNum(totales.dif6)"></td>
</tr>
<tr>
<td class="fw-semibold">$</td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato13)"></td>
<td class="text-end fw-bold" x-text="'$ ' + formatNum(totales.dato14)"></td>
<td class="text-end fw-bold" :class="diffColor(totales.dif7)" x-text="'$ ' + formatNum(totales.dif7)"></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>

</div>
</div>


<div class="col-md-4">

<div class="row">

<!-- ANEXOS -->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-file me-2"></i>ANEXOS</h5>
<button type="button" class="btn btn-success" @click="abrirModalDocumento()"><i class="ti ti-plus"></i> Agregar Anexo</button>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Fecha</th>
<th class="text-center">Anexos</th>
<th class="text-center" style="width: 40px;"><i class="ti ti-dots-vertical fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-if="documentos.length === 0">
<tr>
<td colspan="3" class="text-center text-secondary">
No se encontro información
</td>
</tr>
</template>
<template x-for="d in documentos" :key="d.id">
<tr>
<td class="text-center" x-text="d.fecha_formateada || ''"></td>
<td x-text="d.anexos"></td>
<td class="text-center">
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown" class="text-decoration-none">
<i class="ti ti-dots-vertical fs-6"></i>
</a>
<ul class="dropdown-menu">
<li>
<span x-data="actions()">
<a class="dropdown-item d-flex align-items-center gap-2" href="#"
@click.prevent="download('control-volumetrico', d.documento)">
<i class="ti ti-download fs-5"></i> Descargar
</a>
</span>
</li>
<li>
<span x-data="actions()">
<a class="dropdown-item d-flex align-items-center gap-2 text-danger"
href="#"
@click.prevent="async () => { const r = await deleteAction({url: '/departamento-operativo/control-volumetrico/eliminar-documento', id: d.id, name: d.anexos}); if (r && r.success) cargarDatos(); }">
<i class="ti ti-trash fs-5"></i> Eliminar
</a>
</span>
</li>
</ul>
</div>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- PREFIJOS -->
<div class="col-12">
<div class="card">
<div class="card-header bg-primary d-flex align-items-center gap-2">
<i class="ti ti-tag text-white fs-6"></i>
<h5 class="mb-0 text-white">PREFIJOS</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Serie</th>
<th>Detalle</th>
<th class="text-end">Total</th>
</tr>
</thead>
<tbody>
<template x-if="prefijos.length === 0">
<tr>
<td colspan="3" class="text-center text-secondary">
No se encontro información
</td>
</tr>
</template>
<template x-for="pf in prefijos" :key="pf.id">
<tr>
<td class="text-center  fw-semibold" x-text="pf.serie"></td>
<td class="" x-text="pf.descripcion"></td>
<td class="text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="text" class="border-0 p-3 text-end w-100 bg-transparent"
style="padding-left: 25px !important;" x-init="$el.value = formatDisplay(pf.total)" @focus="$el.value = (pf.total || 0).toString(); $el.select()"
@blur="const v = parseFloat($el.value.replace(/,/g, '')) || 0; pf.total = v; $el.value = formatDisplay(v); editarPrefijo(pf.id)"
@input="pf.total = parseFloat($event.target.value.replace(/,/g, '')) || 0">
</div>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>

<!-- GRAN TOTAL -->
<div class="col-12 mb-4">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody>
<tr>
<th class="text-center no-hover">Subtotal Gasolina:</th>
<th class="text-center no-hover fw-normal" x-text="'$ ' + formatNum(prefijoTotals.sum_gasolina)"></th>
</tr>
<tr>
<th class="text-center no-hover">Subtotal Rentas:</th>
<th class="text-center no-hover fw-normal" x-text="'$ ' + formatNum(prefijoTotals.sum_rentas)"></th>
</tr>
<tr>
<th class="text-center no-hover">Subtotal Sodexo:</th>
<th class="text-center no-hover fw-normal" x-text="'$ ' + formatNum(prefijoTotals.sum_sodexo)"></th>
</tr>
<template x-if="prefijoTotals.sum_autolavado > 0">
<tr>
<th class="text-center no-hover">Subtotal Autolavado:</th>
<th class="text-center no-hover fw-normal" x-text="'$ ' + formatNum(prefijoTotals.sum_autolavado)"></th>
</tr>
</template>
<tr class="table-dark">
<th class="text-center text-white">GRAN TOTAL:</th>
<th class="text-center text-white" x-text="'$ ' + formatNum(prefijoTotals.sum_gtotal)"></th>
</tr>
</tbody>
</table>
</div>
</div>

<!-- COMENTARIOS -->
<div class="col-12">
<div class="card overflow-hidden">

<!-- HEADER -->
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary">
<div class="hstack gap-3">

<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>

<span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success"><span class="visually-hidden">online</span></span>
</div>

<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">Conversación activa</p>
</div>

</div>
</div>

<!-- BODY -->
<div class="d-flex parent-chat-box">

<div class="chat-box w-100">

<!-- LISTA -->
<div class="chat-box-inner p-3" style="max-height: 420px; overflow-y: auto;" x-ref="chatContainer">

<!-- SIN COMENTARIOS -->
<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<!-- COMENTARIOS -->
<div class="chat-list active-chat p-2">

<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-4" :class="c.es_mio ? 'justify-content-end' : 'justify-content-start'">

<!-- MENSAJES OTROS -->
<template x-if="!c.es_mio">
<div class="d-flex gap-3 align-items-start">

<!-- ICONO -->
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-6"></i>
</div>
</div>

<!-- CONTENIDO -->
<div>
<h6 class="fw-semibold mb-1" x-text="c.usuario || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_formateada || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"> </div>
</div>

</div>
</template>

<!-- MIS MENSAJES -->
<template x-if="c.es_mio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_formateada || ''"></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>

</div>
</template>

</div>
</div>

<!-- FOOTER -->
<div class="px-3 py-3 border-top chat-send-message-footer bg-white">
<div class="d-flex align-items-center gap-2">

<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;"
x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
</div>

<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px;"
type="button" @click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>

</div>
</div>

</div>
</div>

</div>
</div>

</div>
</div>

<!-- Resultado -->
<div class="col-12">
<div class="alert alert-dark text-center text-white mb-3">
<div class="fw-bold fs-8" x-text="'TOTAL: $ ' + formatNum(granTotal)"></div>
<!-- <div class="text-white">( $ <span x-text="formatNum(totales.dato9)"></span> - $ <span x-text="formatNum(prefijoTotals.sum_gasolina)"></span> )</div> -->
</div>
</div>

</div>
</template>

<!-- Modal Subir Documento -->
<div class="modal fade" id="modalDocumento" tabindex="-1">
<div class="modal-dialog modal-LG">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Agregar anexos</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h6 class="mb-1">* Fecha:</h6>
<input type="date" id="docFecha" class="form-control">

<h6 class="mb-1 mt-3">* Nombre del Anexo:</h6>
<div class="select2-modal-field is-select2-pending" x-ref="anexosWrapper">
<select id="docAnexos" data-width="100%">
<option></option>
<template x-for="opt in anexosOpciones" :key="opt">
<option x-text="opt" :value="opt"></option>
</template>
</select>
</div>

<h6 class="mb-1 mt-3">* Documento</h6>
<input class="form-control" type="file" id="docFile">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarDocumento()" :disabled="subiendoDocumento">
<span x-text="subiendoDocumento ? 'Subiendo...' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

</div>
<?php endif; ?>
