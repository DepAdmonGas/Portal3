<div id="container" class="mt-4 mb-4"
data-id-dia="<?= $idDia ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-estado="<?= $estado ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
x-data="monederoComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando monedero...</p>
</div>
</template>

<template x-if="!loading">
<div class="row">
<div class="col-12">
    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" :colspan="multiestacion ? 9 : 15">MÉTODOS DE PAGO</th>
<th class="text-center align-middle" colspan="6">CARTERA DE CLIENTES ATIO</th>
</tr>
<tr>
<th class="text-center align-middle" :colspan="multiestacion ? 4 : 5">TARJETAS BANCARIAS</th>
<th class="text-center align-middle" colspan="5">TARJETAS (OTROS)</th>
<th x-show="!multiestacion" class="text-center align-middle" colspan="5">VALES</th>
<th class="text-center align-middle" colspan="2">CRÉDITO</th>
<th class="text-center align-middle" colspan="2">DÉBITO</th>
<th class="text-center align-middle">PAGOS</th>
<th class="text-center align-middle">CONSUMOS</th>
</tr>
<tr>
<th class="text-center align-middle">BANCOMER</th>
<th class="text-center align-middle">AMEX</th>
<th x-show="!multiestacion" class="text-center align-middle">INBURGAS</th>
<th class="text-center align-middle">INBURSA</th>
<th class="text-center align-middle">TOTAL</th>
<th class="text-center align-middle">TICKETCARD</th>
<th class="text-center align-middle">TICKETCARD+</th>
<th class="text-center align-middle">EFECTICARD</th>
<th class="text-center align-middle">SODEXO</th>
<th class="text-center align-middle">TOTAL</th>
<th x-show="!multiestacion" class="text-center align-middle">VALE ACCORD</th>
<th x-show="!multiestacion" class="text-center align-middle">VALE EFECTIVALE</th>
<th x-show="!multiestacion" class="text-center align-middle">VALE SODEXO</th>
<th x-show="!multiestacion" class="text-center align-middle">SI VALE</th>
<th x-show="!multiestacion" class="text-center align-middle">TOTAL</th>
<th class="text-center align-middle">PAGOS</th>
<th class="text-center align-middle">CONSUMOS</th>
<th class="text-center align-middle">PAGOS</th>
<th class="text-center align-middle">CONSUMOS</th>
<th class="text-center align-middle">TOTAL</th>
<th class="text-center align-middle">TOTAL</th>
</tr>
</thead>
<tbody>
<tr>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.bancarias.bancomer)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.bancarias.amex)"></td>
<td x-show="!multiestacion" class="align-middle text-end" x-text="'$ ' + formatNum(d.bancarias.inburgas)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.bancarias.inbursa)"></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(multiestacion ? (d.bancarias.bancomer + d.bancarias.amex + d.bancarias.inbursa) : d.bancarias.total)"></strong></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.tarjetas_otros.ticketcard)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.tarjetas_otros.g500fleet)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.tarjetas_otros.efecticard)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.tarjetas_otros.sodexo)"></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(d.tarjetas_otros.total)"></strong></td>
<td x-show="!multiestacion" class="align-middle text-end" x-text="'$ ' + formatNum(d.vales.vale_accord)"></td>
<td x-show="!multiestacion" class="align-middle text-end" x-text="'$ ' + formatNum(d.vales.vale_efectivale)"></td>
<td x-show="!multiestacion" class="align-middle text-end" x-text="'$ ' + formatNum(d.vales.vale_sodexo)"></td>
<td x-show="!multiestacion" class="align-middle text-end" x-text="'$ ' + formatNum(d.vales.si_vale)"></td>
<td x-show="!multiestacion" class="align-middle text-end"><strong x-text="'$ ' + formatNum(d.vales.total)"></strong></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.credito.pago)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.credito.consumo)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.debito.pago)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(d.debito.consumo)"></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(d.total_pago)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(d.total_consumo)"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</template>

</div>
