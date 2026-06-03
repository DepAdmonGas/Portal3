<div id="container" class="mt-4 mb-4"
data-id-dia="<?= $idDia ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-estado="<?= $estado ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-es-superviso="<?= $esSuperviso ? 'true' : 'false' ?>"
data-es-vobo="<?= $esVoBo ? 'true' : 'false' ?>"
x-data="ventasComponent()">

<div class="row">
<div class="col-12">
<div class="d-flex align-items-center mb-4">
<div class="ms-auto">
<button type="button" class="btn btn-danger me-2" @click="downloadPdf"><i class="ti ti-file-type-pdf me-1"></i>Descargar PDF</button>

<template x-if="!multiestacion && estado == 0">
<button type="button" class="btn btn-success" @click="abrirModalFirma"><i class="ti ti-check me-1"></i> Finalizar</button>
</template>
</div>
</div>
</div>


<div class="col-xl-7 col-lg-7 col-md-12 col-sm-12 mb-3">
<div class="row">

<div class="col-12 mb-3">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-report-money me-2"></i>CONCENTRADO DE VENTAS</h5>
<template x-if="!multiestacion && estado == 0">
<button type="button"class="btn btn-success" @click="newVenta"><i class="ti ti-plus me-1"></i>Agregar producto</button>
</template>
</div>
</div>

<div class="card-body">
<div class="row">

<!-- CONCENTRADO DE VENTAS -->
<div class="col-12" id="divConcentradoVentas">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

<thead> 
<tr>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Litros</th>
<th class="text-center align-middle">Jarras</th>
<th class="text-center align-middle">Total Litros</th>
<th class="text-center align-middle">Precio por litro</th>
<th class="text-center align-middle">Importe Total</th>
</tr>
</thead>

<tbody>

<template x-for="v in ventas_dia" :key="v.id">
<tr>

<td class="p-0" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<select class="form-select border-0" x-model="v.producto"
@change="editVenta(v.id, 'producto', v.producto)">
<option value="">Selecciona una opción...</option>
<option value="G SUPER">G SUPER</option>
<option value="G PREMIUM">G PREMIUM</option>
<option value="G DIESEL">G DIESEL</option>
</select>
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block" x-text="v.producto || 'PRODUCTO'"></span>
</template>
</td>

<td class="p-0 align-middle" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent"
x-model="v.litros"
@keyup.debounce="editVenta(v.id, 'litros', v.litros)">
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="formatNum(v.litros)"></span>
</template>
</td>

<td class="p-0 align-middle" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent"
x-model="v.jarras"
@keyup.debounce="editVenta(v.id, 'jarras', v.jarras)">
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="formatNum(v.jarras)"></span>
</template>
</td>

<td class="align-middle text-end" x-text="formatNum(calcTotalLitros(v))"></td>

<td class="p-0 align-middle fw-normal" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<!-- Editable -->
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model="v.precio_litro" @keyup.debounce="editVenta(v.id, 'precio_litro', v.precio_litro)">
</div>
</template>

<!-- Solo lectura -->
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(v.precio_litro)"></span>
</template>
</td>

<td class="align-middle text-end" x-text="'$ ' + formatNum(calcImporteTotal(v))"></td>
</tr>
</template>

<tr id="trSubTotales" x-show="ventas_dia.length > 0">
<td><strong>A SUB-TOTAL (1+2+3)</strong></td>
<td class="align-middle text-end"><strong x-text="formatNum(totales_ventas.subTLitros)"></strong></td>
<td class="align-middle text-end"><strong x-text="formatNum(totales_ventas.subJarras)"></strong></td>
<td class="align-middle text-end"><strong x-text="formatNum(totales_ventas.subTotalLitros)"></strong></td>
<td colspan="2" class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales_ventas.subImporteTotal)"></strong></td>
</tr>

<template x-for="o in ventas_dia_otros" :key="o.id">
<tr>
<td x-text="o.concepto"></td>
<td class="align-middle text-end" x-text="o.piezas"></td>
<td class="align-middle text-end"></td>
<td class="align-middle text-end"></td>
<td class="align-middle text-end"></td>
<template x-if="o.concepto === '4 ACEITES Y LUBRICANTES'">
<td class="align-middle text-end"><b x-text="'$ ' + formatNum(o.importe)"></b></td>
</template>

<template x-if="o.concepto !== '4 ACEITES Y LUBRICANTES'">
<td class="p-0 align-middle"
:class="estado == 0 && !multiestacion ? '' : 'disabledOP'">

<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model="o.importe"
@keyup.debounce="editVentaOtros(o.id, o.importe)">
</div>
</template>

<!-- Solo lectura -->
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(o.importe)"></span>
</template>
</td>
</template>

</tr>
</template>

<tr id="trTotales" x-show="ventas_dia_otros.length > 0">
<th colspan="5" class="text-end">B TOTAL (A+4+5+6)</th>
<td class="align-middle text-end "><strong x-text="'$ ' + formatNum(totales_ventas.totalNeto)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>

</div>
</div>

</div>
</div>

<!---------- RELACION DE VENTA DE ACEITES Y LUBRICANTES ---------->
<div class="col-12 mb-3">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-engine me-2"></i>RELACION DE VENTA DE ACEITES Y LUBRICANTES</h5>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divAceitesLubricantes">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="align-middle text-center">#</th>
<th class="align-middle text-center">Concepto</th>
<th class="align-middle text-center">Cantidad</th>
<th class="align-middle text-center">Precio Unitario</th>
<td class="align-middle text-center">Importe</td>
</tr>
</thead>
<tbody>
<template x-for="a in aceites" :key="a.id">
<tr>
<td class="align-middle text-center" x-text="a.id_aceite"></td>
<td class="align-middle text-center" x-text="a.concepto"></td>
<td class="p-0 align-middle" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<input type="number" min="0" class="border-0 p-3 text-center w-100"
x-model="a.cantidad"
@keyup.debounce="editAceite(a.id, 'cantidad', a.cantidad)">
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-center" x-text="a.cantidad"></span>
</template>
</td>
<td class="p-0 align-middle text-end" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" class="border-0 p-3 text-center w-100" style="padding-left: 25px !important;" x-model="a.precio_unitario" @keyup.debounce="editAceite(a.id, 'precio_unitario', a.precio_unitario)">
</div>
</template>

<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(a.precio_unitario)"></span>
</template>
</td>
<td class="align-middle text-end " x-text="'$ ' + formatNum(calcAceiteImporte(a))"></td>
</tr>
</template>
<template x-if="aceites.length === 0">
<tr>
<th colspan="5" class="text-center text-secondary p-3 ">
<small>No se encontró información, verifica que el inventario del mes pasado este finalizado.</small>
</th>
</tr>
</template>
<tr x-show="aceites.length > 0">
<th colspan="2" class="text-center">TOTAL (PRODUCTO):</th>
<td class="align-middle text-center"><strong x-text="totales_aceites.totalCantidad"></strong></td>
<th class="align-middle text-center">TOTAL (IMPORTE):</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales_aceites.totalPrecio)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

<!---------- DOCUMENTACION ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-file me-2"></i>DOCUMENTACIÓN</h5>
<template x-if="!multiestacion && estado == 0">
<button type="button" class="btn btn-success" @click="abrirModalDocumento"><i class="ti ti-plus"></i> Agregar documento</button>
</template>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divDocumentos">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Descripción</th>
<th class="text-center align-middle" width="40px"><i class="ti ti-download text-secondary fs-6"></i></th>
<template x-if="!multiestacion && estado == 0"><th class="text-center align-middle" width="40px"><i class="ti ti-trash text-danger fs-6"></i></th></template>
</tr>
</thead>
<tbody>
<template x-if="documentos.length === 0">
<tr>
<th colspan="4" class="text-center text-secondary">
<small>No se encontró información para mostrar</small>
</th>
</tr>
</template>
<template x-for="d in documentos" :key="d.id">
<tr>
<th class="align-middle" x-text="d.detalle"></th>
<td class=" text-center" width="40px">
<span x-data="actions()">
<a href="javascript:void(0)" @click="download('documentos-ventas', d.documento)" class="text-secondary">
<i class="ti ti-download fs-5"></i>
</a>
</span>
</td>
<template x-if="!multiestacion && estado == 0">
<td class=" text-center" width="40px">
<span x-data="actions()">
<a href="#" @click.prevent="async () => { const r = await deleteAction({url: '/departamento-operativo/ventas/eliminar-documento', id: d.id, name: d.detalle}); if (r && r.success) loadData(); }" class="text-danger">
<i class="ti ti-trash fs-5"></i>
</a>
</span>
</td>
</template>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

</div>
</div>


<!---------- SEGUNDO APARTADO ---------->
<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 mb-3">
<div class="row">

<!---------- PROSEGUR ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-shield-lock me-2"></i>PROSEGUR</h5>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divProsegur">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Denominación</th>
<th class="text-center">Recibo</th>
<th class="text-center">Importe</th>
</tr>
</thead>
<tbody>
<template x-for="p in prosegur" :key="p.id">
<tr>
<td class="text-center align-middle p-3" x-text="p.denominacion"></td>
<td class="p-0 align-middle" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<input type="text" class="border-0 p-3 w-100 text-center bg-transparent"
x-model="p.recibo"
@keyup.debounce="editProsegur(p.id, 'recibo', p.recibo)">
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-center" x-text="p.recibo || ''"></span>
</template>
</td>
<td class="p-0 align-middle" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 w-100 text-end bg-transparent" style="padding-left: 25px !important;" x-model="p.importe"
@keyup.debounce="editProsegur(p.id, 'importe', p.importe)">
</div>
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(p.importe)"></span>
</template>
</td>
</tr>
</template>
<tr>
<th class="text-center" colspan="2">TOTAL 1</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales1234.total1)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

<!---------- PROSEGUR ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-wallet me-2"></i>MONEDEROS Y BANCOS</h5>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divTarjetasBancarias">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center" colspan="2">Concepto / Banco</th>
<th class="text-center">Importe</th>
</tr>
</thead>
<tbody>
<template x-for="t in tarjetas_cb" :key="t.id">
<tr>
<th class="align-middle text-center "><b x-text="t.num"></b></th>
<td class="align-middle " x-text="t.concepto"></td>
<template x-if="['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURGAS', 'ULTRAGAS', 'ENERGEX', 'SHELL FLEET NAVIGATOR', 'SANTANDER', 'INBURSA'].includes(t.concepto)">
<td class="align-middle text-end " x-text="'$ ' + formatNum(t.baucher)"></td>
</template>
<template x-if="!['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURGAS', 'ULTRAGAS', 'ENERGEX', 'SHELL FLEET NAVIGATOR', 'SANTANDER', 'INBURSA'].includes(t.concepto)">
<td class="p-0 align-middle text-end" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'" style="width: 50%">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 20px !important;" x-model="t.baucher"
@keyup.debounce="editTarjeta(t.id, t.baucher)">
</div>
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(t.baucher)">
</span>
</template>
</td>
</template>
</tr>
</template>
<tr>
<th class="text-center" colspan="2">TOTAL 2</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales1234.total2)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

<!---------- CLIENTES (ATIO) ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-users me-2"></i>CLIENTES (ATIO)</h5>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divControlgas">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Concepto</th>
<th class="text-center align-middle">Pagos</th>
<th class="text-center align-middle">Consumos</th>
</tr>
</thead>
<tbody>
<template x-for="c in controlgas" :key="c.id">
<tr>
<td class="text-center align-middle p-3 " x-text="c.concepto"></td>
<td class="align-middle p-0" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model="c.pago"
@keyup.debounce="editControlgas(c.id, 'pago', c.pago)">
</div>
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(c.pago)"></span>
</template>
</td>

<td class="align-middle p-0" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model="c.consumo"
@keyup.debounce="editControlgas(c.id, 'consumo', c.consumo)">
</div>
</template>

<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(c.consumo)"></span>
</template>
</td>
</tr>
</template>
<tr>
<th class="text-center">TOTAL 3</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(pago_total)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales1234.total3)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>

</div>
</div>

<!---------- DIFERENCIAS ---------->
<div class="col-12">
<div class="card">
<div class="card-body">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tr>
<th>C TOTAL (1+2+3)</th>
<td class="align-middle pointer  text-end"><strong x-text="'$ ' + formatNum(totales1234.cTotal)"></strong></td>
</tr>
<tr>
<th>DIFERENCIA (B-C)</th>
<td class="align-middle pointer  text-end">
<strong x-text="'$ ' + formatNum(totales1234.cTotal - totales_ventas.totalNeto)" :class="{'text-danger': (totales1234.cTotal - totales_ventas.totalNeto) < 0}"></strong>
</td>
</tr>
</table>
</div>
</div>
</div>
</div>

<!---------- PAGO CLIENTES ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-cash me-2"></i>PAGO CLIENTES</h5>
</div>
</div>
<div class="card-body">
<div class="row">
<div class="col-12" id="divPagoClientes">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Concepto</th>
<th class="text-center align-middle">Importe</th>
<th class="text-center align-middle">Nota</th>
</tr>
</thead>
<tbody>
<template x-for="pc in pago_clientes" :key="pc.id">
<tr>
<th class="align-middle " x-text="pc.concepto"></th>
<td class="align-middle p-0" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<div class="position-relative">
<span
class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model="pc.importe"
@keyup.debounce="editPagoCliente(pc.id, 'importe', pc.importe)">
</div>
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-end" x-text="'$ ' + formatNum(pc.importe)"> </span></template>
</td>
<td class="align-middle p-0" :class="estado == 0 && !multiestacion ? '' : 'disabledOP'">
<template x-if="estado == 0 && !multiestacion">
<input type="text" class="border-0 p-3 w-100 text-center bg-transparent"
x-model="pc.nota"
@keyup.debounce="editPagoCliente(pc.id, 'nota', pc.nota)">
</template>
<template x-if="estado != 0 || multiestacion">
<span class="p-3 d-block text-center" x-text="pc.nota || ''"></span>
</template>
</td>
</tr>
</template>
<tr>
<th class="text-center">TOTAL 4</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(total_pago_clientes)"></strong></td>
<td class="align-middle text-end"></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

<!---------- DIFERENCIAS PAGO CLIENTES ---------->
<div class="col-12">
<div class="card">
<div class="card-body">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tr>
<td class="align-middle text-center">DIF PAGO DE CLIENTES</td>
<td class="align-middle text-center">
<strong x-text="'$ ' + formatNum(pago_total - total_pago_clientes)"></strong>
</td>
<td class="align-middle text-center">(4-5)</td>
</tr>
</table>
</div>
</div>
</div>
</div>


<!---------- OBSERVACIONES ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-2"></i>OBSERVACIONES</h5>
</div>
</div>
<div class="card-body p-0">
<textarea class="form-control border-0 p-4" style="height:200px;" placeholder="Escribe tus observaciones aquí..." x-model="observaciones" @keyup="editObservaciones"
:disabled="multiestacion || estado != 0">
</textarea>
</div>
</div>
</div>

<!---------- FIRMA ---------->
<template x-if="!multiestacion && estado == 0">
<div class="col-12">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-signature me-2"></i>FIRMA DE QUIEN ELABORA</h5>
<button type="button" class="btn bg-danger text-white" @click="limpiarFirma"><i class="ti ti-eraser me-1"></i> Limpiar firma </button>
</div>
</div>

<div class="card-body p-0">
<div id="signature-pad" class="signature-pad border-0" style="cursor: crosshair;">
<div class="signature-pad--body">
<canvas id="canvas" style="width: 100%; height: 250px;"></canvas>
</div>
</div>
</div>

<div class="card-footer border-top bg-white">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="terminosid" x-model="aceptoTerminos">
<label class="form-check-label" for="terminosid">
<strong class="text-primary">Acepto los resultados del corte del día: <?= $fecha ?></strong>
</label>
</div>
</div>
</div>
</div>
</template>

</div>
</div>


<!---------- FIRMAS DEL PERSONAL ---------->
<template x-if="estado == 1">
<div class="col-12">
<div class="row g-3 mt-1 align-items-stretch">

<!-- CARD DE QUIEN ELABORÓ -->
<div class="col-xl-4 col-lg-4 col-md-6 col-12 d-flex">
<div class="card h-100 w-100 d-flex flex-column">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white text-center"><i class="ti ti-signature me-2"></i>ELABORÓ</h5>
</div>

<div class="card-body text-center d-flex flex-column justify-content-center">
<template x-if="firmasElaboro">
<div>
<img :src="'/assets/img/firmas/' + firmasElaboro.firma" width="150" height="70" class="img-fluid mb-3">
<div class="mt-4 mb-0">
<h5 class="text-muted"><strong x-text="firmasElaboro.nombre_usuario"></strong></h5>
</div>
</div>
</template>

<template x-if="!firmasElaboro">
<div class="text-muted">
<strong>¡Falta la firma de quien elaboró!</strong>
</div>
</template>
</div>

</div>
</div>

<!-- CARD DE QUIEN SUPERVISÓ -->
<div class="col-xl-4 col-lg-4 col-md-6 col-12 d-flex">
<div class="card h-100 w-100 d-flex flex-column">

<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white text-center"><i class="ti ti-shield-check me-2"></i>SUPERVISÓ</h5>
</div>

<div class="card-body text-center d-flex flex-column justify-content-center">
<template x-if="firmasSuperviso">
<div>
<div class="alert alert-success py-2">
<div class="text-success">El formato se firmó por un medio electrónico.<br>
<b>Fecha: <span x-text="firmasSuperviso.fecha_formateada"></span></b>
</div>
</div>

<div class="mt-4 mb-0">
<h5 class="text-muted"><strong x-text="firmasSuperviso.nombre_usuario"></strong></h5>
</div>
</div>
</template>

<template x-if="!firmasSuperviso && esSuperviso">
<div>
<h4 class="text-primary"><i class="ti ti-key me-2"></i>Token móvil</h4>
<small class="text-secondary d-block mb-3">Solicite el envío del token a su Telegram o correo electrónico:</small>

<div class="d-grid gap-2 mb-3">
<button class="btn btn-success btn-sm" @click="crearToken('telegram')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
<i class="ti ti-brand-telegram me-1" x-show="!enviandoToken"></i>Enviar token por Telegram
</button>
<button class="btn btn-info btn-sm" @click="crearToken('email')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
<i class="ti ti-mail me-1" x-show="!enviandoToken"></i>Enviar token por Email
</button>
</div>

<div class="input-group input-group-sm">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="tokenInput">
<button class="btn btn-outline-success" type="button" @click="firmarConToken('Superviso')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>Firmar
</button>
</div>

<template x-if="tokenError">
<small class="text-danger d-block mt-2" x-text="tokenError"></small>
</template>
</div>
</template>

<template x-if="!firmasSuperviso && !esSuperviso">
<div class="text-muted">
<strong>¡Falta la firma de quien supervisó!</strong>
</div>
</template>

</div>

</div>
</div>

<!-- CARD DEL VO.BO -->
<div class="col-xl-4 col-lg-4 col-md-12 col-12 d-flex">

<div class="card h-100 w-100 d-flex flex-column">

<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white text-center"><i class="ti ti-circle-check me-2"></i>VO.BO.</h5>
</div>

<div class="card-body text-center d-flex flex-column justify-content-center">
<template x-if="firmasVoBo">
<div>
<div class="alert alert-success py-2">
<div class="text-success">El formato se firmó por un medio electrónico.<br>
<b>Fecha: <span x-text="firmasVoBo.fecha_formateada"></span></b>
</div>
</div>

<div class="mt-4 mb-0">
<h5 class="text-muted"><strong x-text="firmasVoBo.nombre_usuario"></strong></h5>
</div>
</div>
</template>

<template x-if="!firmasVoBo && esVoBo">
<div>

<h4 class="text-primary"><i class="ti ti-key me-2"></i>Token móvil</h4>
<small class="text-secondary d-block mb-3">Solicite el envío del token a su Telegram o correo electrónico:</small>

<div class="d-grid gap-2 mb-3">
<button class="btn btn-success btn-sm" @click="crearToken('telegram')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
<i class="ti ti-brand-telegram me-1" x-show="!enviandoToken"></i>Enviar token por Telegram
</button>
<button class="btn btn-info btn-sm" @click="crearToken('email')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
<i class="ti ti-mail me-1" x-show="!enviandoToken"></i>Enviar token por Email
</button>
</div>

<div class="input-group input-group-sm">
<input type="text" class="form-control" placeholder="Token de seguridad" x-model="tokenInput">
<button class="btn btn-outline-success" type="button" @click="firmarConToken('VoBo')" :disabled="enviandoToken">
<span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>Firmar
</button>
</div>

<template x-if="tokenError">
<small class="text-danger d-block mt-2" x-text="tokenError"></small>
</template>

</div>
</template>

<template x-if="!firmasVoBo && !esVoBo">
<div class="text-muted"><strong>¡Falta la firma de Vo.Bo!</strong></div>
</template>
</div>

</div>

</div>
</div>
</div>
</template>

</div>

<div class="modal fade" id="modalDocumento" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Agregar Documento</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h6 class="mb-2">* Documento</h6>
<select class="form-select mb-1" x-model="nuevoDocumento.nombre">
<option value="">Selecciona una opcion...</option>
<option value="Ficha prosegur">Ficha prosegur</option>
<option value="Ficha 1 prosegur">Ficha 1 prosegur</option>
<option value="Ficha 2 prosegur">Ficha 2 prosegur</option>
<option value="Ficha 3 prosegur">Ficha 3 prosegur</option>
<option value="Ficha banco">Ficha banco</option>
<option value="Corte cierre de efectivale">Corte cierre de efectivale</option>
<option value="Cierres de lote">Cierres de lote</option>
<option value="Corte">Corte</option>
<option value="Documento/archivo adicional">Documento/archivo adicional</option>
</select>
<h6 class="mb-2 mt-3">* Documento</h6>
<input class="form-control" type="file" id="inputDocumento" @change="nuevoDocumento.file = $event.target.files[0]">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-success" @click="guardarDocumento" :disabled="subiendoDocumento">
<span x-show="subiendoDocumento" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!subiendoDocumento"></i> Guardar
</button>
</div>
</div>
</div>
</div>



