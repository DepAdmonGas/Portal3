<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Resumen Monedero.
</div>
<?php else: ?>
<?php
$verShell = ($idEstacion == 2 || $idEstacion == 14);
$colspanMetodos = $verShell ? 19 : 18;
$colspanTarjetas = $verShell ? 8 : 7;
$totalCols = $verShell ? 25 : 24;
?>
<div id="container" class="mt-4 mb-4"
data-id-mes-db="<?= $idMesDb ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-ver-prosegur="<?= $verProsegur ? 'true' : 'false' ?>"
data-tipo-puesto="<?= $tipoPuesto ?>"
data-es-corporativo="<?= $esCorporativo ? 'true' : 'false' ?>"
data-id-puesto="<?= $idPuesto ?>"
x-data="resumenMonederoComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando resumen monedero...</p>
</div>
</template>

<template x-if="!loading && error">
<div class="alert alert-danger">
<i class="ti ti-alert-circle me-1"></i> <span x-text="error"></span>
</div>
</template>

<template x-if="!loading && !error">

<div class="row">
<div class="col-12 mb-4">

<div class="d-flex justify-content-end mb-3">
<div class="dropdown d-inline ms-2">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-tools me-1"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><a class="dropdown-item pointer" @click.prevent="abrirModalFacturas()"><i class="ti ti-file-text me-2"></i>Facturas</a></li>
<li><a class="dropdown-item pointer" @click.prevent="descargarExcel()"><i class="ti ti-file-spreadsheet me-2"></i>Descargar Resumen <?= nombremes($idMes) ?> <?= $idYear ?></a></li>
<?php if ($esDireccionOperaciones): ?>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item pointer" @click.prevent="resumenPorPeriodo()"><i class="ti ti-report-analytics me-2"></i>Resumen por Periodo</a></li>
<li><a class="dropdown-item pointer" @click.prevent="evaluacionKPI()"><i class="ti ti-chart-line me-2"></i>Evaluacion Facturas de Monederos (KPI's)</a></li>
<?php endif; ?>
</ul>
</div>
</div>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle" colspan="<?= $colspanMetodos ?>">Metodos de pago</th>
<th class="text-center align-middle" colspan="6">Cartera de Clientes ATIO</th>
<th class="text-center align-middle" colspan="10" x-show="verProsegur" rowspan="2">PROSEGUR</th>
</tr>
<tr>
<td></td>
<th class="text-center align-middle fw-semibold" colspan="5">Tarjetas Bancarias</th>
<th class="text-center align-middle fw-semibold" colspan="<?= $colspanTarjetas ?>">Tarjetas</th>
<th class="text-center align-middle fw-semibold" colspan="5">Vales</th>
<th class="text-center align-middle fw-semibold" colspan="2">Crédito</th>
<th class="text-center align-middle fw-semibold" colspan="2">Débito</th>
<th class="text-center align-middle fw-semibold">Pagos</th>
<th class="text-center align-middle fw-semibold">Consumos</th>
</tr>
<tr>
<th class="text-center align-middle">Fecha</th>
<th class="text-center align-middle">BANCOMER</th>
<th class="text-center align-middle">AMEX</th>
<th class="text-center align-middle">INBURGAS</th>
<th class="text-center align-middle">INBURSA</th>
<th class="text-center align-middle fw-semibold">TOTAL</th>
<th class="text-center align-middle">TICKETCARD</th>
<th class="text-center align-middle">TICKETCARD+</th>
<th class="text-center align-middle">EFECTICARD</th>
<th class="text-center align-middle">SODEXO</th>
<th class="text-center align-middle">ULTRAGAS</th>
<th class="text-center align-middle">ENERGEX</th>
<th class="text-center align-middle <?= $verShell ? '' : 'd-none' ?>">SHELL</th>
<th class="text-center align-middle fw-semibold">TOTAL</th>
<th class="text-center align-middle">VALE ACCORD</th>
<th class="text-center align-middle">VALE EFECTIVALE</th>
<th class="text-center align-middle">VALE SODEXO</th>
<th class="text-center align-middle">SI VALE</th>
<th class="text-center align-middle fw-semibold">TOTAL</th>
<th class="text-center align-middle">Pagos</th>
<th class="text-center align-middle">Consumos</th>
<th class="text-center align-middle">Pagos</th>
<th class="text-center align-middle">Consumos</th>
<th class="text-center align-middle fw-semibold">TOTAL</th>
<th class="text-center align-middle fw-semibold">TOTAL</th>
<th class="text-center align-middle" x-show="verProsegur">Billete Matutino</th>
<th class="text-center align-middle" x-show="verProsegur">Billete Vespertino</th>
<th class="text-center align-middle" x-show="verProsegur">Billete Nocturno</th>
<th class="text-center align-middle" x-show="verProsegur">Morralla</th>
<th class="text-center align-middle" x-show="verProsegur">Deposito Bancario</th>
<th class="text-center align-middle" x-show="verProsegur">Cheque 1</th>
<th class="text-center align-middle" x-show="verProsegur">Transferencia 1</th>
<th class="text-center align-middle" x-show="verProsegur">Cheque 2</th>
<th class="text-center align-middle" x-show="verProsegur">Transferencia 2</th>
<th class="text-center align-middle fw-semibold" x-show="verProsegur">Total</th>
</tr>
</thead>
<tbody>
<template x-for="(row, idx) in rows" :key="idx">
<tr>
<th class="text-start fw-normal" x-text="row.fecha"></th>
<td class="text-end" x-text="'$' + formato(row.bancomer)"></td>
<td class="text-end" x-text="'$' + formato(row.amex)"></td>
<td class="text-end" x-text="'$' + formato(row.inburgas)"></td>
<td class="text-end" x-text="'$' + formato(row.inbursa)"></td>
<td class="text-end fw-semibold table-primary" x-text="'$' + formato(row.total_tb_fixed)"></td>
<td class="text-end" x-text="'$' + formato(row.ticketcard)"></td>
<td class="text-end" x-text="'$' + formato(row.g500fleet)"></td>
<td class="text-end" x-text="'$' + formato(row.efecticard)"></td>
<td class="text-end" x-text="'$' + formato(row.sodexo)"></td>
<td class="text-end" x-text="'$' + formato(row.ultragas)"></td>
<td class="text-end" x-text="'$' + formato(row.energex)"></td>
<td class="text-end<?= $verShell ? '' : ' d-none' ?>" x-text="'$' + formato(row.shell)"></td>
<td class="text-end fw-semibold table-primary" x-text="'$' + formato(row.total_tarjetas)"></td>
<td class="text-end" x-text="'$' + formato(row.vale_accord)"></td>
<td class="text-end" x-text="'$' + formato(row.vale_efectivale)"></td>
<td class="text-end" x-text="'$' + formato(row.vale_sodexo)"></td>
<td class="text-end" x-text="'$' + formato(row.si_vale)"></td>
<td class="text-end fw-semibold table-primary" x-text="'$' + formato(row.total_vales)"></td>
<td class="text-end" x-text="'$' + formato(row.credito_pago)"></td>
<td class="text-end" x-text="'$' + formato(row.credito_consumo)"></td>
<td class="text-end" x-text="'$' + formato(row.debito_pago)"></td>
<td class="text-end" x-text="'$' + formato(row.debito_consumo)"></td>
<td class="text-end fw-semibold table-primary" x-text="'$' + formato(row.total_pago)"></td>
<td class="text-end fw-semibold table-primary" x-text="'$' + formato(row.total_consumo)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.billete_matutino)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.billete_vespertino)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.billete_nocturno)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.morralla)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.deposito_bancario)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.cheque1)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.transferencia1)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.cheque2)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(row.transferencia2)"></td>
<td class="text-end fw-semibold table-primary" x-show="verProsegur" x-text="'$' + formato(row.total_prosegur)"></td>
</tr>
</template>
<template x-if="rows.length === 0">
<tr>
<td :colspan="verProsegur ? <?= $totalCols + 10 ?> : <?= $totalCols ?>" class="text-center text-muted py-3">
No hay registros para este mes
</td>
</tr>
</template>
</tbody>
<tfoot>
<tr class="table-dark fw-semibold">
<td class="text-start">Total</td>
<td class="text-end" x-text="'$' + formato(totales.bancomer)"></td>
<td class="text-end" x-text="'$' + formato(totales.amex)"></td>
<td class="text-end" x-text="'$' + formato(totales.inburgas)"></td>
<td class="text-end" x-text="'$' + formato(totales.inbursa)"></td>
<td class="text-end" x-text="'$' + formato(totales.total_tb_fixed)"></td>
<td class="text-end" x-text="'$' + formato(totales.ticketcard)"></td>
<td class="text-end" x-text="'$' + formato(totales.g500fleet)"></td>
<td class="text-end" x-text="'$' + formato(totales.efecticard)"></td>
<td class="text-end" x-text="'$' + formato(totales.sodexo)"></td>
<td class="text-end" x-text="'$' + formato(totales.ultragas)"></td>
<td class="text-end" x-text="'$' + formato(totales.energex)"></td>
<td class="text-end<?= $verShell ? '' : ' d-none' ?>" x-text="'$' + formato(totales.shell)"></td>
<td class="text-end" x-text="'$' + formato(totales.total_tarjetas)"></td>
<td class="text-end" x-text="'$' + formato(totales.vale_accord)"></td>
<td class="text-end" x-text="'$' + formato(totales.vale_efectivale)"></td>
<td class="text-end" x-text="'$' + formato(totales.vale_sodexo)"></td>
<td class="text-end" x-text="'$' + formato(totales.si_vale)"></td>
<td class="text-end" x-text="'$' + formato(totales.total_vales)"></td>
<td class="text-end" x-text="'$' + formato(totales.credito_pago)"></td>
<td class="text-end" x-text="'$' + formato(totales.credito_consumo)"></td>
<td class="text-end" x-text="'$' + formato(totales.debito_pago)"></td>
<td class="text-end" x-text="'$' + formato(totales.debito_consumo)"></td>
<td class="text-end" x-text="'$' + formato(totales.total_pago)"></td>
<td class="text-end" x-text="'$' + formato(totales.total_consumo)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.billete_matutino)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.billete_vespertino)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.billete_nocturno)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.morralla)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.deposito_bancario)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.cheque1)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.transferencia1)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.cheque2)"></td>
<td class="text-end" x-show="verProsegur" x-text="'$' + formato(totales.transferencia2)"></td>
<td class="text-end fw-semibold" x-show="verProsegur" x-text="'$' + formato(totales.total_prosegur)"></td>
</tr>
</tfoot>
</table>
</div>

</div>
</div>
</template>

<!-- MODAL FACTURAS -->
<div class="modal fade" id="modalFacturas" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl">
<div class="modal-content">

<div class="modal-header">
<h4 class="modal-title" x-text="modalFacturaTitulo"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body mb-0">
<template x-if="modalFacturaVista === 'lista'">
<div>
<div class="table-responsive mt-3">
    
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Fecha</th>
<th class="text-center align-middle">Monedero</th>
<th class="text-center align-middle text-end">Diferencia</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-type-pdf text-danger fs-6"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-type-xml text-primary fs-6"></i></th>
<th class="text-center align-middle" width="48px" x-show="multiestacion || idPuesto != 6"><i class="ti ti-file-spreadsheet text-success fs-6"></i></th>
<th class="text-center align-middle" width="48px" x-show="multiestacion"><i class="ti ti-file-description text-info fs-6"></i></th>
<th class="text-center align-middle" width="48px">EDI</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-files text-secondary fs-6"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-edit text-primary fs-6"></i></th>
<th class="text-center align-middle" width="48px" x-show="multiestacion"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>

<tbody>
<template x-for="doc in documentos" :key="doc.id">
<tr>
<td class="align-middle text-center fw-semibold" x-text="doc.fecha"></td>
<td class="align-middle text-center" x-text="doc.monedero"></td>
<td class="align-middle text-end" x-text="'$ ' + formato(doc.diferencia)"></td>
<td class="align-middle text-center">
<template x-if="doc.pdf">
<span x-data="actions()"><i class="ti ti-file-type-pdf text-danger fs-6 pointer" @click.prevent="download('monedero-documentos', doc.pdf)"></i></span>
</template>
<template x-if="!doc.pdf">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>
<td class="align-middle text-center">
<template x-if="doc.xml">
<span x-data="actions()"><i class="ti ti-file-type-xml text-primary fs-6 pointer" @click.prevent="download('monedero-documentos', doc.xml)"></i></span>
</template>
<template x-if="!doc.xml">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>
<td class="align-middle text-center" x-show="multiestacion || idPuesto != 6">
<template x-if="doc.excel">
<span x-data="actions()"><i class="ti ti-file-spreadsheet text-success fs-6 pointer" @click.prevent="download('monedero-documentos', doc.excel)"></i></span>
</template>
<template x-if="!doc.excel">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>
<td class="align-middle text-center" x-show="multiestacion">
<template x-if="doc.sodi">
<span x-data="actions()"><i class="ti ti-file-description text-info fs-6 pointer" @click.prevent="download('monedero-documentos', doc.sodi)"></i></span>
</template>
<template x-if="!doc.sodi">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>
<td class="align-middle text-center">
<i class="ti ti-file-zip text-warning fs-6 pointer" @click="abrirEdi(doc.id)"></i>
</td>
<td class="align-middle text-center">
<i class="ti ti-files text-secondary fs-6 pointer" @click="abrirDocumentacion(doc.id)"></i>
</td>
<td class="align-middle text-center">
<i class="ti ti-edit text-primary fs-6 pointer" @click="editarFactura(doc)"></i>
</td>
<td class="align-middle text-center" x-show="multiestacion">
<template x-if="puedeEliminarDoc">
<span x-data="actions()"><i class="ti ti-trash text-danger fs-6 pointer" @click="deleteAction({url: '/departamento-operativo/resumen-monedero/eliminar-documento', id: doc.id, name: doc.monedero + ' - ' + doc.fecha, table: null}).then(r => r?.success && cargarDocumentos())"></i></span>
</template>
<template x-if="!puedeEliminarDoc">
<i class="ti ti-trash text-muted fs-6"></i>
</template>
</td>
</tr>
</template>
<template x-if="documentos.length === 0">
<tr>
<td colspan="11" class="text-center text-muted py-3">No se encontraron facturas</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</template>

<template x-if="modalFacturaVista === 'form'">
<div class="row">
<div class="col-12 mb-3">
<label class="form-label mb-1">* Fecha:</label>
<template x-if="multiestacion">
<input type="date" class="form-control" x-model="facturaForm.fecha">
</template>
<template x-if="!multiestacion">
<div class="form-control-plaintext py-1" x-text="facturaForm.fecha"></div>
</template>
</div>
<div class="col-12 mb-3">
<label class="form-label mb-1">* Monedero:</label>
<template x-if="multiestacion">
<select class="form-select" x-model="facturaForm.monedero">
<option value="">Selecciona una opción...</option>
<option>Edenred</option>
<option>Efectivale</option>
<option>Inburgas</option>
<option>Ultragas</option>
<option>Sodexo</option>
<option>Shell</option>
</select>
</template>
<template x-if="!multiestacion">
<div class="form-control-plaintext py-1" x-text="facturaForm.monedero"></div>
</template>
</div>
<div class="col-12 mb-3">
<label class="form-label mb-1">* Diferencia:</label>
<input type="number" class="form-control" step="any" x-model="facturaForm.diferencia">
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3" x-show="multiestacion">
<label class="form-label mb-1">PDF:</label>
<input class="form-control" type="file" id="facturaPDF" :required="!facturaForm.id">
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3" x-show="multiestacion">
<label class="form-label mb-1">XML:</label>
<input class="form-control" type="file" id="facturaXML" :required="!facturaForm.id">
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3" x-show="facturaForm.id && multiestacion">
<label class="form-label mb-1">Excel:</label>
<input class="form-control" type="file" id="facturaEXCEL">
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3" x-show="facturaForm.id && multiestacion">
<label class="form-label mb-1">Soporte de diferencia:</label>
<input class="form-control" type="file" id="facturaSoporteD">
</div>
</div>
</template>

<template x-if="modalFacturaVista === 'edi'">
<div>

<div class="row">
<div class="col-12 mb-3">
<label class="form-label mb-1">* Complemento:</label>
<select class="form-select" x-model="ediForm.complemento">
<option value="">Selecciona una opción...</option>
<option>Complemento 1</option>
<option>Complemento 2</option>
</select>
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label mb-1">* PDF:</label>
<input class="form-control" type="file" id="ediPDF">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label mb-1">* XML:</label>
<input class="form-control" type="file" id="ediXML">
</div>

<div class="col-12 text-end mb-3">
<button type="button" class="btn btn-success" @click="guardarEdi()" :disabled="guardandoEdi">
<span x-text="guardandoEdi ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Complemento</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-type-pdf text-danger fs-6"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-type-xml text-primary fs-6"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="edi in ediLista" :key="edi.id">
<tr>
<th class="align-middle text-center fw-normal" x-text="edi.complemento"></th>
<td class="text-center">
<template x-if="!edi.pdf">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
<template x-if="edi.pdf">
<span x-data="actions()"><i class="ti ti-file-type-pdf text-danger fs-5 pointer" @click.prevent="download('monedero-documentos', edi.pdf)"></i></span>
</template>
</td>
<td class="text-center">
<template x-if="!edi.xml">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
<template x-if="edi.xml">
<span x-data="actions()"><i class="ti ti-file-type-xml text-primary fs-5 pointer" @click.prevent="download('monedero-documentos', edi.xml)"></i></span>
</template>
</td>
<td class="text-center">
<template x-if="puedeEliminarDoc">
<span x-data="actions()"><i class="ti ti-trash text-danger fs-5 pointer" @click="deleteAction({url: '/departamento-operativo/resumen-monedero/eliminar-edi', id: edi.id, name: edi.complemento, table: null}).then(r => r?.success && abrirEdi(ediForm.idDocumento))"></i></span>
</template>
<template x-if="!puedeEliminarDoc">
<i class="ti ti-trash text-muted fs-5"></i>
</template>
</td>
</tr>
</template>
<template x-if="ediLista.length === 0">
<tr>
<td colspan="4" class="text-center text-muted py-2">No se encontro información</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</template>

<template x-if="modalFacturaVista === 'documentacion'">
<div>
<label class="form-label mb-1">* Nombre del documento:</label>
<input type="text" class="form-control mb-3" x-model="docForm.descripcion">

<label class="form-label mb-1">* Documento (PDF o XLSX):</label>
<input class="form-control mb-3" type="file" id="docArchivoPDF">

<div class="text-end">
<button type="button" class="btn btn-success mb-3" @click="guardarDocumentacion()" :disabled="guardandoDoc">
<span x-text="guardandoDoc ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>

<div class="table-responsive">
<table class="table table-striped table-bordered mt-2" style="font-size: 14px;">
<thead>
<tr>
<th class="align-middle text-center" width="96px">No.</th>
<th class="align-middle text-center">Fecha</th>
<th class="align-middle text-center">Descripcion</th>
<th class="align-middle text-center" width="48px"><i class="ti ti-file-download fs-6 text-primary"></i></th>
<th class="align-middle text-center" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></th>
</tr>
</thead>
<tbody>
<template x-for="(docItem, idx) in docLista" :key="docItem.id">
<tr>
<th class="align-middle text-center fw-semibold" x-text="idx + 1"></th>
<td class="align-middle text-center" x-text="docItem.fecha_formateada || docItem.fecha_hora || ''"></td>
<td class="align-middle text-center" x-text="docItem.descripcion"></td>
<td class="align-middle text-center">
<span x-data="actions()"><i class="ti ti-file-download fs-6 text-primary pointer" @click.prevent="download('monedero-lista-documentos', docItem.archivo)"></i></span>
</td>
<td class="align-middle text-center">
<template x-if="puedeEliminarDoc">
<span x-data="actions()"><i class="ti ti-trash fs-6 text-danger pointer" @click="deleteAction({url: '/departamento-operativo/resumen-monedero/eliminar-lista-documento', id: docItem.id, name: docItem.descripcion, table: null}).then(r => r?.success && abrirDocumentacion(docForm.idMonedero))"></i></span>
</template>
<template x-if="!puedeEliminarDoc">
<i class="ti ti-trash text-muted fs-5"></i>
</template>
</td>
</tr>
</template>
<template x-if="docLista.length === 0">
<tr>
<td colspan="5" class="text-center text-muted py-2">No se encontro información</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</template>

</div>
<div class="modal-footer">
<div x-show="multiestacion">
<template  x-if="modalFacturaVista === 'lista'">
<button type="button" class="btn btn-primary" @click="nuevaFactura()">
<i class="ti ti-plus me-1"></i>Agregar factura
</button>
</template>
</div>
<template x-if="modalFacturaVista === 'form'">
<div class="d-flex gap-2">
<button type="button" class="btn btn-danger" @click="cancelarFormulario()">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarFactura()" :disabled="guardandoFactura">
<span x-text="guardandoFactura ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</template>
<template x-if="modalFacturaVista === 'edi'">
<button type="button" class="btn btn-danger" @click="volverListaFacturas()">Regresar</button>
</template>
<template x-if="modalFacturaVista === 'documentacion'">
<button type="button" class="btn btn-danger" @click="volverListaFacturas()">Regresar</button>
</template>
</div>
</div>
</div>
</div>

</div>
<?php endif; ?>
