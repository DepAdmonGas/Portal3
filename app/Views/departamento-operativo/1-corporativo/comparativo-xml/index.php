<style>
#ck-editor-container .ck-editor {
resize: vertical !important;
overflow: auto !important;
min-height: 200px !important;
border: none !important;
}
#ck-editor-container .ck-editor__main .ck-editor__editable {
min-height: 200px !important;
border: none !important;
outline: none !important;
box-shadow: none !important;
}
#ck-editor-container .ck-toolbar {
border-radius: 0 !important;
}
</style>

<div class="pb-5" id="container" data-module-station-key="comparativo-xml">

<div x-data="comparativoXmlComponent()" x-init='cargarDatos(<?= $idYear ?>, <?= $idEstacion ?>)'>

<div id="comparativo-xml-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4"<?= $idEstacion ? ' style="display:none"' : '' ?>>
Debes de seleccionar una estación del menú superior para poder visualizar el Comparativo XML.
</div>

<div id="comparativo-xml-content"<?= $idEstacion ? '' : ' style="display:none"' ?>>

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando información...</p>
</div>
</template>

<template x-if="!loading && error">
<div class="alert alert-danger">
<i class="ti ti-alert-circle me-1"></i> <span x-text="error"></span>
</div>
</template>

<template x-if="!loading && !error">
<div>

<div class="row mb-3">
<div class="col-12">

<template x-if="esDireccionOperaciones">
<div class="dropdown d-inline float-end ms-2">
<button type="button" class="btn btn-light dropdown-toggle  text-dark" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-dots-vertical"></i>
</button>
<ul class="dropdown-menu">
<li><a class="dropdown-item pointer" @click="descargarExcel()"><i class="ti ti-file-spreadsheet"></i> Descargar Excel</a></li>
<li><a class="dropdown-item pointer" @click="verSeguimiento()"><i class="ti ti-users"></i> Seguimiento</a></li>
</ul>
</div>
</template>

<template x-if="!esDireccionOperaciones">
<div class="row g-0 ms-auto w-100 justify-content-end mb-1 mt-3">
                    <div class="col-12 col-md-auto d-grid">
                     <button type="button" class="btn btn-labeled2 btn-success float-end" @click="descargarExcel()">
<span class="btn-label2"><i class="ti ti-file-spreadsheet"></i></span>Descargar Excel
</button> 
                    </div>
                  </div>



</template>

</div>
</div>

<div class="table-responsive mb-5 overflow-x-auto overflow-y-hidden">
<table id="tabla-comparativo" class="table table-striped table-bordered mb-0 text-nowrap align-middle comparativo-xml-seleccionable portal-table portal-table--selectable">
<thead>
<tr>
<th class="text-center align-middle" rowspan="2" width="48px">#</th>
<th class="text-center align-middle" rowspan="2">Mes</th>
<th class="text-center align-middle"  :colspan="config.colspanDespachos">Despachos</th>
<th class="text-center align-middle"  :colspan="config.colspanVentas">Ventas</th>
<th class="text-center align-middle"  :colspan="config.colspanFacturacion">Facturación</th>
<th class="text-center align-middle"  :colspan="config.colspanDiferencia">Diferencias</th>
</tr>
<tr>
<th class="text-center align-middle">Mensual</th>
<template x-if="tb.monederos">
<th class="text-center align-middle">Monederos</th>
</template>
<template x-if="tb.monederos_c_iva">
<th class="text-center align-middle">Monederos c/IVA</th>
</template>
<template x-if="tb.monederos_s_iva">
<th class="text-center align-middle">Monederos s/IVA</th>
</template>
<template x-if="tb.clientes_despachos">
<th class="text-center align-middle">Clientes</th>
</template>
<th class="text-center align-middle">Octanos 87</th>
<th class="text-center align-middle">Octanos 91</th>
<template x-if="tb.diesel">
<th class="text-center align-middle">Diésel</th>
</template>
<template x-if="tb.aceites_lubricantes">
<th class="text-center align-middle">Aceites y Lubricantes</th>
</template>
<th class="text-center align-middle">IEPS</th>
<template x-if="tb.aceites">
<th class="text-center align-middle">Aceites</th>
</template>
<template x-if="tb.renta_espacios">
<th class="text-center align-middle">Renta de Espacios</th>
</template>
<template x-if="tb.renta">
<th class="text-center align-middle">Renta</th>
</template>
<template x-if="tb.ingresos">
<th class="text-center align-middle">Ingresos</th>
</template>
<template x-if="tb.total_global">
<th class="text-center align-middle">Total Global</th>
</template>
<template x-if="tb.iva">
<th class="text-center align-middle">IVA</th>
</template>
<template x-if="tb.total">
<th class="text-center align-middle">Total</th>
</template>
<template x-if="tb.monederos_ventas">
<th class="text-center align-middle">Monederos</th>
</template>
<template x-if="tb.clientes_ventas">
<th class="text-center align-middle">Clientes</th>
</template>
<th class="text-center align-middle">IVA CV</th>
<th class="text-center align-middle">Total CV</th>
<th class="text-center align-middle">Monederos</th>
<template x-if="tb.ingresos_2">
<th class="text-center align-middle">Ingresos</th>
</template>
<template x-if="tb.clientes_factuacion">
<th class="text-center align-middle">Clientes</th>
</template>
<th class="text-center align-middle">IVA SAT</th>
<th class="text-center align-middle">Total SAT</th>
<th class="text-center align-middle">Monederos</th>
<template x-if="tb.clientes_factuacion_2">
<th class="text-center align-middle">Clientes</th>
</template>
<th class="text-center align-middle">Diferencias</th>
<template x-if="tb.total_2">
<th class="text-center align-middle">Total</th>
</template>
<template x-if="tb.diferencias_monederos">
<th class="text-center align-middle">Diferencia total con monederos</th>
</template>
<template x-if="tb.sin_iva_2">
<th class="text-center align-middle">Soportes</th>
</template>
</tr>
</thead>
<tbody>

<template x-for="(row, idx) in rows" :key="row.mes">
<tr class="pointer" :class="{ selected: selectedRows.principal === row.mes }" @click="seleccionarFila('principal', row.mes)">
<td class="align-middle text-center" x-text="row.mes"></td>
<td class="align-middle text-center" x-text="row.nombre_mes"></td>
<template x-for="cell in row.cells" :key="cell.campo">
<td class="align-middle text-center p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input type="text" inputmode="decimal"
class="border-0 p-3 text-end w-100 bg-transparent"
style="padding-left: 20px !important; min-width: 80px;"
x-init="$el.value = formatInput(cell.valor)"
:disabled="!canEdit"
@focus="$event.target.value = _rawFromDom($event.target).toString(); $event.target.select()"
@input="limpiarNumero($event.target); _onInput($event.target, row, cell)"
@blur="_onBlur($event.target, row, cell)">
</div>
</td>
</template>

</tr>
</template>
</tbody>

<tfoot>
<tr class="table-dark fw-bold">
<td class="align-middle text-center" colspan="2">Totales</td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.mensual)"></td>
<template x-if="tb.monederos"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_1)"></td></template>
<template x-if="tb.monederos_c_iva"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_con_iva)"></td></template>
<template x-if="tb.monederos_s_iva"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_sin_iva)"></td></template>
<template x-if="tb.clientes_despachos"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.clientes_1)"></td></template>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.octanos_87)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.octanos_91)"></td>
<template x-if="tb.diesel"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.diesel)"></td></template>
<template x-if="tb.aceites_lubricantes"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.aceites_lubricantes)"></td></template>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.ieps)"></td>
<template x-if="tb.aceites"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.aceites)"></td></template>
<template x-if="tb.renta_espacios"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.renta_espacios)"></td></template>
<template x-if="tb.renta"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.renta)"></td></template>
<template x-if="tb.ingresos"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.ingresos)"></td></template>
<template x-if="tb.total_global"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.total_global)"></td></template>
<template x-if="tb.iva"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.iva)"></td></template>
<template x-if="tb.total"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.total)"></td></template>
<template x-if="tb.monederos_ventas"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_2)"></td></template>
<template x-if="tb.clientes_ventas"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.clientes_2)"></td></template>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.iva_cv)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.total_cv)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_3)"></td>
<template x-if="tb.ingresos_2"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.ingresos_2)"></td></template>
<template x-if="tb.clientes_factuacion"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.clientes_3)"></td></template>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.iva_sat)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.total_sat)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.monederos_4)"></td>
<template x-if="tb.clientes_factuacion_2"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.clientes_4)"></td></template>
<td class="align-middle text-center" x-text="'$' + formatNumber(totales.diferencia)"></td>
<template x-if="tb.total_2"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.total_2)"></td></template>
<template x-if="tb.diferencias_monederos"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.diferencia_total_monederos)"></td></template>
<template x-if="tb.sin_iva_2"><td class="align-middle text-center" x-text="'$' + formatNumber(totales.sin_iva)"></td></template>
</tr>
</tfoot>
</table>
</div>

<div class="row">
<template x-for="month in satData.months" :key="month.mes">
<div class="col-xl-4 col-lg-4 col-md-6 col-12 mb-2">
<div class="card ">

<div class="card-header text-bg-primary">
    <div class="d-flex flex-wrap align-items-center gap-2">

        <!-- Mes -->
        <div class="d-flex align-items-center me-auto">
            <i class="ti ti-calendar-month fs-5 me-2"></i>
            <h5 class="mb-0 text-white" x-text="month.nombre"></h5>
        </div>


        <button
            type="button"
            class="btn btn-light position-relative"
            @click="abrirModalComentarios(month.mes)">

            <template x-if="month.comment_count > 0">
                <span class="badge-historico position-absolute top-0 start-100 translate-middle"
                      x-text="month.comment_count"></span>
            </template>

            <i class="ti ti-message text-primary"></i>
        </button>


        <div class="col-12 col-md-auto d-grid">
            <button
                type="button"
                class="btn btn-success"
                @click="abrirModalDocumentos(month.mes)">
                <i class="ti ti-plus"></i> Nuevo
            </button>
        </div>

    </div>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle comparativo-xml-seleccionable portal-table portal-table--selectable">

<thead>
<tr>
<th class="align-middle text-center">Categoría</th>
<th class="align-middle text-end">SAT</th>
<th class="align-middle text-end">Despachos</th>
<th class="align-middle text-end">Diferencia</th>
</tr>
</thead>

<tbody>
<template x-for="item in month.items" :key="item.id">
<tr class="pointer" :class="{ selected: selectedRows['sat-' + month.mes] === item.id }" @click="seleccionarFila('sat-' + month.mes, item.id)">
<td class="text-center align-middle fw-semibold" x-text="item.categoria"></td>

<td class="text-center align-middle p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input
type="text"
inputmode="decimal"
class="border-0 p-3 text-end w-100 bg-transparent"
style="padding-left:20px !important;"
x-init="$el.value = formatInput(item.sat_monto)"
:disabled="!canEdit"
@focus="$event.target.value = _rawFromDom($event.target).toString(); $event.target.select()"
@input="limpiarNumero($event.target); _onInputComparativo($event.target, item, month, 1)"
@blur="_onBlurComparativo($event.target, item, month, 1)">
</div>
</td>

<td class="align-middle text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input
type="text"
inputmode="decimal"
class="border-0 p-3 text-end w-100 bg-transparent"
style="padding-left:20px !important;"
x-init="$el.value = formatInput(item.despacho_monto)"
:disabled="!canEdit"
@focus="$event.target.value = _rawFromDom($event.target).toString(); $event.target.select()"
@input="limpiarNumero($event.target); _onInputComparativo($event.target, item, month, 2)"
@blur="_onBlurComparativo($event.target, item, month, 2)">
</div>
</td>

<td class="align-middle text-end" x-text="'$' + formatNumber(item.diferencia)"></td>
</tr>
</template>

<tr class="fw-semibold table-dark">
<td class="align-middle text-center">TOTAL</td>
<td class="align-middle text-end" x-text="'$' + formatNumber(month.total_sat)"></td>
<td class="align-middle text-end" x-text="'$' + formatNumber(month.total_despacho)"></td>
<td class="align-middle text-end" x-text="'$' + formatNumber(month.total_diferencia)"></td>
</tr>

</tbody>
</table>
</div>
</div>

</div>
</div>
</template>

<div class="col-12 mt-1 mb-3">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white">
<i class="ti ti-chart-bar me-2"></i> Resumen Anual
</h5>
</div>
</div>

<div class="card-body p-0">
    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="align-middle text-center">Categoría</th>
<th class="align-middle text-center">SAT</th>
<th class="align-middle text-center">Despachos</th>
<th class="align-middle text-center">Diferencia</th>
</tr>
</thead>

<tbody>
<tr class="fw-bold table-dark">
<td class="align-middle text-center">TOTAL ANUAL</td>
<td class="align-middle text-center" x-text="'$' + formatNumber(satData.total_anual_sat)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(satData.total_anual_despacho)"></td>
<td class="align-middle text-center" x-text="'$' + formatNumber(satData.total_anual_diferencia)"></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

<!---------- OBSERVACIONES ---------->
<div class="row">
<div class="col-12 mt-2">
<div class="card border-0">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="ti ti-eye me-2"></i>Observaciones
</h5>
</div>
</div>

<div class="card-body p-0" id="ck-editor-container">
<textarea id="editor" style="display: none;" placeholder="escribe tu comentario..."></textarea>
</div>

<div class="card-footer bg-white border-0 text-end">
<button
type="button"
class="btn btn-success"
@click="guardarObservaciones()">
<i class="ti ti-check"></i>
Guardar
</button>
</div>
</div>
</div>
</div>

</div>
</template>

</div>

<div class="modal fade" id="modalDocumentos" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h5 class="modal-title text-white">
    <i class="ti ti-copy"></i>
<span x-text="'Anexos (' + mesActualNombre + ' <?= $idYear ?>)'"></span>   
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<template x-if="canEdit">
<div class="row justify-content-end">
    <div class="col-12 mb-3">
        <label class="form-label mb-1">* Anexo:</label>
        <input class="form-control" type="text" x-model="nuevoAnexo"
               placeholder="Nombre del anexo" id="anexoInput">
    </div>

    <div class="col-12 mb-3">
        <label class="form-label mb-1">* Archivo:</label>
    </div>
    
   
<div class="row g-0 w-100 mt-2 mb-3">
    <div class="col-12">
        <div class="input-group">
            <input
                class="form-control"
                type="file"
                x-ref="fileInput"
                id="docFile"
            >

            <button
                type="button"
                class="btn btn-success"
                @click="guardarDocumento()"
                :disabled="guardandoDocumento"
            >
                <i class="ti ti-check" x-show="!guardandoDocumento"></i>
                <span x-text="guardandoDocumento ? 'Guardando...' : 'Guardar'"></span>
                <span
                    x-show="guardandoDocumento"
                    class="spinner-border spinner-border-sm ms-1"
                ></span>
            </button>
        </div>
    </div>
</div>

</div>
</template>

    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<div x-data="actions()">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle comparativo-xml-seleccionable portal-table portal-table--selectable">
<thead>
<tr>
<th class="text-center align-middle">Fecha</th>
<th class="align-middle">Anexo</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<template x-if="canEdit">
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</template>
</tr>
</thead>
<tbody>
<template x-if="documentos.length === 0">
<tr><td colspan="4" class="text-center text-secondary align-middle">No se encontró información</td></tr>
</template>
<template x-for="doc in documentos" :key="doc.id">
<tr class="pointer" :class="{ selected: selectedRows.documentos === doc.id }" @click="seleccionarFila('documentos', doc.id)">
<td class="text-center align-middle" x-text="doc.fecha_formato"></td>
<td class="align-middle" x-text="doc.anexo"></td>
<td class="text-center align-middle">
<a href="" @click.prevent="download('comparativo-xml', doc.archivo)">
<i class="ti ti-download text-primary fs-5 pointer"></i>
</a>
</td>
<template x-if="canEdit">
<td class="text-center align-middle">
<i class="ti ti-trash text-danger fs-5 pointer" @click="deleteAction({url: '/departamento-operativo/comparativo-xml/delete-document', id: doc.id, name: doc.anexo, table: null}).then(r => r?.success && cargarDocumentos(mesActual))"></i>
</td>
</template>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
<div class="modal-footer">
    <div class="row ms-auto g-0 w-100 justify-content-end">
        <div class="col-12 col-md-auto d-grid">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i>    
Cancelar</button>
        </div>
    </div>

</div>
</div>
</div>
</div>

<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75" x-text="mesActualNombre"></p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>

<div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
     x-show="mesActualNombre && idYear"
     x-cloak>
    <div class="row g-2">
        <div class="col-12">
            <div class="border rounded-2 bg-body px-2 py-2 d-flex align-items-center gap-2" style="font-size:.82rem;">
                <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                    <i class="ti ti-calendar-event fs-5"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="form-label small mb-0">Periodo:</div>
                    <div x-text="mesActualNombre + ' ' + idYear"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;" x-ref="chatContainer">
<template x-if="comentarios.length === 0 && !guardandoComentario">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>
<div class="p-2">
<template x-for="c in ordenarComentariosChat(comentarios)" :key="c.id">
<div class="d-flex mb-4" :class="c.esMio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.esMio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-6"></i>
</div>
</div>
<div>
<h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_formateada || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.esMio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_formateada || ''"></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>
</div>
</template>
</div>

</div>
</div>
</div>
<?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'guardandoComentario']); ?>





<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;" x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px;" type="button" @click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>

</div>
</div>
