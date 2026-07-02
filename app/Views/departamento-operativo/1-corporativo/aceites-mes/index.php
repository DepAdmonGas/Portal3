<?php if (!$idEstacion): ?>
<div class="row mt-4">
<div class="col-12">
<div class="alert alert-secondary border-0 text-center text-muted py-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Resumen Aceites.
</div>
</div>
</div>
<?php else: ?>
<div class="row pb-4"
id="aceites-mes-container"
data-year="<?= $idYear ?>"
data-mes="<?= $idMes ?>"
data-id-mes-db="<?= $idMesDb ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-finalizado="<?= $finalizado ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-dias-en-mes="<?= $diasEnMes ?>"
data-nombre-estacion="<?= htmlspecialchars($nombreEstacion, ENT_QUOTES, 'UTF-8') ?>"
x-data="aceitesMesComponent()"
@refresh-documentos.window="cargarDocumentos()"
@refresh-facturas.window="cargarFacturas()">

<div class="col-12 mt-3 mb-4">
<div class="float-end ">

<div class="d-flex gap-2">
<template x-if="!finalizado">
<template x-if="!multiestacion">
<button class="btn btn-success" @click="finalizarInventario()" :disabled="loading"> Finalizar Resumen</button>
</template>
</template>
<template x-if="finalizado">
<span class="badge bg-success d-flex align-items-center px-3">
<i class="ti ti-check-circle me-1"></i> Resumen Finalizado
</span>
</template>

<div class="dropdown">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-tools me-1"></i>
</button>
<ul class="dropdown-menu dropdown-menu-end">

<!-- TODOS -->
<li>
<a class="dropdown-item pointer" @click.prevent="abrirModalFacturas()">
<i class="ti ti-files"></i> Archivos de Aceites
</a>
</li>

<li>
<a class="dropdown-item pointer" @click.prevent="abrirModalDocumentos()">
<i class="ti ti-file-text"></i> Documentos de Aceites
</a>
</li>

<?php if ($multiestacion): ?>
<li><hr class="dropdown-divider"></li>

<li>
<a class="dropdown-item pointer" @click.prevent="descargarResumenExcel()">
<i class="ti ti-file-spreadsheet"></i> Descargar Resumen Excel
</a>
</li>

<li>
<a class="dropdown-item pointer" @click.prevent="abrirListaAceites()">
<i class="ti ti-list"></i> Lista de Aceites
</a>
</li>

<li>
<a class="dropdown-item pointer" @click.prevent="abrirResumenImpuestos()">
<i class="ti ti-report"></i> Resumen Impuestos/Monederos
</a>
</li>
<?php endif; ?>

<?php if ($esDireccionOperaciones): ?>

<li><hr class="dropdown-divider"></li>

<li>
<a class="dropdown-item pointer" @click.prevent="abrirEvaluacionKpi()">
<i class="ti ti-chart-bar"></i> Evaluación de Aceites (KPI's)
</a>
</li>

<?php endif; ?>

</ul>
</div>

</div>
</div>

</div>

<div class="col-12">

<div x-show="loading" class="text-center py-5">
<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
<p class="mt-2 text-muted">Cargando datos...</p>
</div>

<div x-show="!loading && rows.length === 0" class="text-center py-5 text-muted">
<i class="ti ti-droplet-off" style="font-size:3rem;"></i>
<p class="mt-2">No hay registros de aceites para este mes</p>
</div>

<div class="table-responsive" x-show="!loading && rows.length > 0" style="overflow-x:auto;">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">#</th>
<th colspan="2">Concepto</th>
<template x-if="multiestacion">
<th class="text-center">Pzas caja</th>
</template>
<th class="text-end">Precio Unitario</th>
<th class="text-end">Bodega</th>
<th class="text-end">Exhibidores</th>
<th class="text-end">Inventario Inicial</th>
<th class="text-end">Compras / Pedido</th>
<th class="text-end">Ventas del mes</th>
<th class="text-end">Inventario Final</th>
<th class="text-end">Inventario fisico Bodega</th>
<th class="text-end">Inventario fisico Exhibidores</th>
<th class="text-end">Inventario fisico Final</th>
<th class="text-end">Diferencia</th>
<th class="text-end">Diferencia $</th>
<th class="text-end">Prod. Facturados</th>
<th class="text-end">Factura venta mostrador</th>
<th class="text-end">Fac. total</th>
<th class="text-end">Dif. En Facturacion</th>
<template x-for="d in totalDias" :key="'hc'+d">
<th class="text-center" x-text="d"></th>
</template>
<th class="text-center">Total</th>
<template x-for="d in totalDias" :key="'hi'+d">
<th class="text-center" x-text="d"></th>
</template>
<th class="text-center">Total</th>
</tr>
</thead>
<tbody>
<template x-for="(row, idx) in rows" :key="row.id">
<tr>
<th class="text-center align-middle" x-text="row.id"></th>
<td class="text-center align-middle" x-text="row.id_aceite"></td>
<td class="text-start align-middle" x-text="row.concepto"></td>
<template x-if="multiestacion">
<td class="text-center align-middle" x-text="row.piezas"></td>
</template>
<td class="text-center align-middle" x-text="(multiestacion ? '$ ' : '$ ') + formatNum(row.precio)"></td>
<td class="text-center align-middle" x-text="row.bodega"></td>
<td class="text-center align-middle" x-text="row.exibidores"></td>
<td class="text-center align-middle fw-semibold table-primary" x-text="inventarioInicial(row)"></td>
<template x-if="multiestacion">
<td class="text-center align-middle" x-text="row.pedido"></td>
</template>
<template x-if="!multiestacion">
<td class="text-center align-middle p-0">
<template x-if="!finalizado && puedeEditar">
<input class="form-control form-control-sm border-0 text-end shadow-none" type="number" step="any"
x-model="tempValues[row.id].pedido" @change="guardarCampo(row, 'pedido')">
</template>
<template x-if="finalizado || !puedeEditar">
<span x-text="row.pedido"></span>
</template>
</td>
</template>
<td class="text-center align-middle" x-text="row.ventas_mes"></td>
<td class="text-center align-middle fw-semibold table-primary" x-text="inventarioFinal(row)"></td>
<template x-if="multiestacion">
<td class="text-center align-middle" x-text="row.inventario_bodega"></td>
</template>
<template x-if="!multiestacion">
<td class="text-center align-middle p-0">
<template x-if="!finalizado && puedeEditar">
<input class="form-control form-control-sm border-0 text-end shadow-none" type="number" step="any"
x-model="tempValues[row.id].inventario_bodega"
@change="guardarCampo(row, 'inventario_bodega')">
</template>
<template x-if="finalizado || !puedeEditar">
<span x-text="row.inventario_bodega"></span>
</template>
</td>
</template>
<template x-if="multiestacion">
<td class="text-center align-middle" x-text="row.inventario_exibidores"></td>
</template>
<template x-if="!multiestacion">
<td class="text-center align-middle p-0">
<template x-if="!finalizado && puedeEditar">
<input class="form-control form-control-sm border-0 text-end shadow-none" type="number" step="any"
x-model="tempValues[row.id].inventario_exibidores"
@change="guardarCampo(row, 'inventario_exibidores')">
</template>
<template x-if="finalizado || !puedeEditar">
<span x-text="row.inventario_exibidores"></span>
</template>
</td>
</template>
<td class="text-center align-middle" x-text="fisicoFinal(row) === 0 ? '0' : fisicoFinal(row)"></td>
<td class="text-center align-middle">
<span x-text="diferencia(row)"></span>
<template x-if="!multiestacion">
<template x-if="diferencia(row) < 0 && finalizado">
<span>
<i x-show="pagoExiste(row.id, row.id_aceite)"
class="ti ti-circle-check text-success ms-1 pointer fs-7"
@click="verDetalleDiferencia(row.id, row.id_aceite, row.concepto)"
title="Pago realizado"></i>
<i x-show="!pagoExiste(row.id, row.id_aceite)"
class="ti ti-alert-triangle text-warning ms-1 pointer fs-7"
@click="abrirModalDiferencia(row.id, row.id_aceite, row.concepto, diferencia(row))"
title="Agregar diferencia de pago"></i>
</span>
</template>
</template>
</td>
<td class="text-end fw-semibold table-primary" x-text="'$ ' + formatNum(diferenciaPrecio(row))"></td>
<template x-if="multiestacion">
<td class="text-end" x-text="formatNum(row.producto_facturado)"></td>
</template>
<template x-if="!multiestacion">
<td class="text-center align-middle p-0">
<template x-if="!finalizado && puedeEditar">
<input class="form-control form-control-sm border-0 text-end shadow-none" type="number" step="any"
x-model="tempValues[row.id].producto_facturado"
@change="guardarCampo(row, 'producto_facturado')">
</template>
<template x-if="finalizado || !puedeEditar">
<span x-text="row.producto_facturado"></span>
</template>
</td>
</template>
<template x-if="multiestacion">
<td class="text-center align-middle" x-text="row.factura_venta_mostrador"></td>
</template>
<template x-if="!multiestacion">
<td class="text-center align-middle p-0">
<template x-if="!finalizado && puedeEditar">
<input class="form-control form-control-sm border-0 text-end shadow-none" type="number" step="any"
x-model="tempValues[row.id].factura_venta_mostrador"
@change="guardarCampo(row, 'factura_venta_mostrador')">
</template>
<template x-if="finalizado || !puedeEditar">
<span x-text="row.factura_venta_mostrador"></span>
</template>
</td>
</template>
<td class="text-center align-middle" x-text="formatNum(factotal(row))"></td>
<td class="text-center align-middle" x-text="formatNum(diffactura(row))"></td>
<template x-for="d in totalDias" :key="'dc'+row.id+'-'+d">
<td class="text-center" x-text="getDiaria(row, d, 'cantidad')"></td>
</template>
<td class="text-center align-middle fw-semibold table-primary" x-text="totalDiariaCantidad(row)"></td>
<template x-for="d in totalDias" :key="'di'+row.id+'-'+d">
<td class="text-end" x-text="'$ ' + getDiaria(row, d, 'importe')"></td>
</template>
<td class="text-end fw-semibold table-primary" x-text="'$ ' + totalDiariaImporte(row)"></td>
</tr>
</template>
</tbody>
<tfoot class="table-light fw-semibold table-primary">
<tr class="table-dark fw-semibold">
<td :colspan="multiestacion ? 5 : 4">Total</td>
<td class="text-center align-middle" x-text="formatNum(sum('bodega'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('exibidores'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('inventarioI'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('pedido'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('ventasM'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('inventarioF'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('inventario_bodega'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('inventario_exibidores'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('inventario_final'), 0)"></td>
<td class="text-center align-middle" x-text="formatNum(sum('diferencia'), 0)"></td>
<td class="text-end align-middle" x-text="'$ ' + formatNum(sum('difPrecio'))"></td>
<td colspan="4"></td>
<template x-for="d in totalDias" :key="'ftc'+d">
<td class="text-center align-middle" x-text="formatNum(sumDiaria(d, 'cantidad'), 0)"></td>
</template>
<td class="text-center align-middle" x-text="formatNum(sumDiariaTotal('cantidad'), 0)"></td>
<template x-for="d in totalDias" :key="'fti'+d">
<td class="text-end" x-text="'$ ' + formatNum(sumDiaria(d, 'importe'))"></td>
</template>
<td class="text-end" x-text="'$ ' + formatNum(sumDiariaTotal('importe'))"></td>
</tr>
</tfoot>
</table>
</div>
</div>

<!-- Modal Documentos -->
<div class="modal fade" id="modalDocumentos" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalDocumentos">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Documentos de Aceites</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<div class="row">
<div class="col-12 mb-3">
<h6>Ficha Depósito:</h6>
<template x-if="documentoEditId && documentoEdit.ficha_deposito">
<div class="mt-1">
<span x-data="actions()"><a href="" @click.prevent="download('aceites-documentos', documentoEdit.ficha_deposito)" class="text-primary">
<i class="ti ti-download"></i> <span x-text="documentoEdit.ficha_deposito"></span>
</a></span>
</div>
</template>
<input type="file" class="form-control mt-1" accept=".pdf,.jpg,.png" @change="documentoFiles.ficha_deposito = $event.target.files[0]">
</div>

<div class="col-12 mb-3">
<h6>Imagen Bodega:</h6>
<template x-if="documentoEditId && documentoEdit.imagen_bodega">
<div class="mt-1">
<span x-data="actions()"><a href="" @click.prevent="download('aceites-documentos', documentoEdit.imagen_bodega)" class="text-primary">
<i class="ti ti-download"></i> <span x-text="documentoEdit.imagen_bodega"></span>
</a></span>
</div>
</template>
<input type="file" class="form-control mt-1" accept=".jpg,.png" @change="documentoFiles.imagen_bodega = $event.target.files[0]">
</div>

<div class="col-12 mb-3">
<h6>Factura Venta:</h6>
<template x-if="documentoEditId && documentoEdit.factura_venta">
<div class="mb-1">
<span x-data="actions()">
<a href="" @click.prevent="download('aceites-documentos', documentoEdit.factura_venta)" class="text-primary">
<i class="ti ti-download"></i> <span x-text="documentoEdit.factura_venta">

</span>
</a>
</span>
</div>
</template>
<input type="file" class="form-control mt-1" @change="documentoFiles.factura_venta = $event.target.files[0]">
</div>

<div class="col-12 mb-3" x-show="!documentoEditId">
<button class="btn btn-success float-end" @click="subirDocumento" :disabled="subiendoDocumento">
<span x-show="subiendoDocumento" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!subiendoDocumento"></i>
<span x-text="documentoEditId ? 'Actualizar' : 'Guardar'"></span>
</button>
</div>

</div>

<div class="table-responsive" x-show="!documentoEditId">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-start align-middle">Fecha</th>
<th class="text-center align-middle">Ficha Depósito</th>
<th class="text-center align-middle">Imagen Bodega</th>
<th class="text-center align-middle">Factura Venta</th>
<th class="text-center align-middle" width="60px"><i class="ti ti-edit text-info fs-5"></i></th>
<th class="text-center align-middle" width="60px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="doc in documentos" :key="doc.id">
<tr>
<td class="text-start align-middle" x-text="doc.fecha_formateada"></td>
<td class="text-center align-middle">
<template x-if="doc.ficha_deposito">
<span x-data="actions()">
<a href="" @click.prevent="download('aceites-documentos', doc.ficha_deposito)" class="text-primary"><i class="ti ti-file fs-5"></i></a>
</span>
</template>
<template x-if="!doc.ficha_deposito"><i class="ti ti-file-off fs-5 text-muted"></i></template>
</td>
<td class="text-center align-middle">
<template x-if="doc.imagen_bodega">
<span x-data="actions()"><a href="" @click.prevent="download('aceites-documentos', doc.imagen_bodega)" class="text-primary">
<i class="ti ti-file fs-5"></i>
</a></span>
</template>
<template x-if="!doc.imagen_bodega">
<i class="ti ti-file-off fs-5 text-muted"></i>
</template>
</td>
<td class="text-center align-middle">
<template x-if="doc.factura_venta">
<span x-data="actions()"><a href="" @click.prevent="download('aceites-documentos', doc.factura_venta)" class="text-primary">
<i class="ti ti-file fs-5"></i>
</a></span>
</template>
<template x-if="!doc.factura_venta">
<i class="ti ti-file-off fs-5 text-muted"></i>
</template>
</td>
<td class="text-center align-middle">
<i class="ti ti-edit text-primary fs-5 pointer" @click="editarDocumento(doc)"></i>
</td>
<td class="text-center align-middle">
<template x-if="puedeEliminar">
<span x-data="actions()">
<i class="ti ti-trash text-danger fs-5 pointer" @click="deleteAction({url: '/departamento-operativo/aceites-mes/eliminar-documento', id: doc.id, name: 'del dia ' + doc.fecha_formateada, table: null}).then(r => r?.success && $dispatch('refresh-documentos'))"></i>
</span>
</template>
</td>
</tr>
</template>
<tr x-show="documentos.length === 0">
<td colspan="6" class="text-center text-muted">No se encontro información</td>
</tr>
</tbody>
</table>
</div>

</div>
<div class="modal-footer">
<button class="btn btn-secondary" x-show="documentoEditId" @click="cancelarEdicionDocumento()">Regresar</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button class="btn btn-success float-end" @click="subirDocumento" :disabled="subiendoDocumento" x-show="documentoEditId">
<span x-show="subiendoDocumento" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!subiendoDocumento"></i>
<span x-text="documentoEditId ? 'Actualizar' : 'Guardar'"></span>
</button>
</div>
</div>
</div>
</div>

<!-- Modal Facturas / Archivos Aceites -->
<div class="modal fade" id="modalFacturas" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalFacturas">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Archivos Aceites</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

<div class="row">
<div class="col-12 mb-3">
<h6>* Fecha:</h6>
<input type="date" class="form-control mt-1" x-model="facturaForm.fecha">
</div>
<div class="col-12 mb-3">
<h6>* Concepto:</h6>
<select class="form-select mt-1" x-model="facturaForm.concepto">
<option value="">Selecciona una opciòn...</option>
<option value="Nota de remisión QUAKER STATE">Nota de remisión QUAKER STATE</option>
<option value="Factura QUAKER STATE">Factura QUAKER STATE</option>
<option value="Nota de remisión G5">Nota de remisión G5</option>
<option value="Factura G5">Factura G5</option>
<option value="Nota de remisión BARDAHL">Nota de remisión BARDAHL</option>
<option value="Factura BARDAHL">Factura BARDAHL</option>
</select>
</div>
<div class="col-12 mb-3">
<h6>* Archivo:</h6>
<input type="file" class="form-control" @change="facturaArchivo = $event.target.files[0]">
</div>
<div class="col-12 mb-3">
<button class="btn btn-success float-end" @click="subirFactura" :disabled="subiendoFactura">
<span x-show="subiendoFactura" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!subiendoFactura"></i> Guardar
</button>
</div>
</div>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-start align-middle">Fecha</th>
<th class="text-center align-middle">Concepto</th>
<th class="text-center align-middle" width="60px"> text-info fs-5"></i></th>
<th class="text-center align-middle" width="60px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="fac in facturas" :key="fac.id">
<tr>
<td class="text-start align-middle" x-text="fac.fecha_formateada"></td>
<td class="text-center align-middle" x-text="fac.nombre_anexo"></td>
<td class="text-center align-middle">
<template x-if="fac.archivo">
<span x-data="actions()">
<a href="" @click.prevent="download('aceites-facturas', fac.archivo)" class="text-primary">
 fs-5"></i>
</a>
</span>
</template>
<template x-if="!fac.archivo">
<i class="ti ti-file-off fs-5 text-danger"></i>
</template>
</td>
<td class="text-center align-middle">
<template x-if="puedeEliminar">
<span x-data="actions()">
<a href="" @click.prevent="deleteAction({url: '/departamento-operativo/aceites-mes/eliminar-factura', id: fac.id, name: fac.nombre_anexo, table: null}).then(r => r?.success && $dispatch('refresh-facturas'))" class="text-primary">
<i class="ti ti-trash text-danger fs-5"></i>
</a>
</span>
</template>
</td>
</tr>
</template>
<tr x-show="facturas.length === 0">
<td colspan="4" class="text-center text-secondary">No se encontro información</td>
</tr>
</tbody>
</table>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
</div>
</div>
</div>
</div>

<!-- Modal Diferencias de Pago (Lista) -->
<div class="modal fade" id="modalDiferencias" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalDiferencias">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Diferencias de Pago</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>Fecha</th>
<th>Aceite</th>
<th>Diferencia</th>
<th>Documento</th>
<th>Comentario</th>
<th>Estado</th>
</tr>
</thead>
<tbody>
<template x-for="d in diferencias" :key="d.id">
<tr>
<td x-text="d.fecha_formateada || ''"></td>
<td x-text="d.nomaceite || d.id_aceite"></td>
<td class="text-end" x-text="d.diferencia"></td>
<td class="text-center">
<template x-if="d.documento">
<span x-data="actions()"><a href="" @click.prevent="download('aceites-diferencias', d.documento)" class="text-primary">
<i class="ti ti-download"></i>
</a></span>
</template>
<template x-if="!d.documento">
<span class="text-muted">Sin archivo</span>
</template>
</td>
<td x-text="d.comentario || 'S/C'"></td>
<td>
<span class="badge" :class="d.estado == 1 ? 'bg-success' : 'bg-warning'" x-text="d.estado == 1 ? 'Pagado' : 'Pendiente'"></span>
</td>
</tr>
</template>
<tr x-show="diferencias.length === 0">
<td colspan="6" class="text-center text-muted">No hay diferencias registradas</td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
</div>
</div>
</div>
</div>

<!-- Modal Pago de Diferencia -->
<div class="modal fade" id="modalPagoDiferencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalPagoDiferencia">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Pago de diferencia</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="alert alert-warning text-center" role="alert">Solo cuentas con <b>5 días </b> para realizar el pago de diferencias.</div>
<div>Se debe pagar la cantidad de <strong class="text-danger" x-text="Math.abs(diferenciaForm.diferencia) + 'pzs'"></strong>, del siguiente aceite o lubricante: </div>
<span class="badge rounded-pill bg-primary mt-1"><strong x-text="diferenciaForm.concepto"></strong></span>

<h6 class="mt-3">* Documento de pago (PDF):</h6>
<input type="file" class="form-control mt-1 mb-3" id="docDiferencia" accept=".pdf">
<h6>* Comentarios:</h6>
<textarea class="form-control mt-1" x-model="diferenciaForm.comentario" rows="4"></textarea>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="pagarDiferencia()" :disabled="loading">
<span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!loading"></i>Guardar
</button>
</div>
</div>
</div>
</div>

<!-- Modal Detalle Pago de Diferencia -->
<div class="modal fade" id="modalDetalleDiferencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalDetalleDiferencia">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Detalle pago de diferencia</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h6 x-text="detalleDiferencia.fecha_formateada || detalleDiferencia.fecha || ''"></h6>
<div>Se pagó la cantidad de <strong class="text-success" x-text="Math.abs(detalleDiferencia.diferencia) + 'pzs'"></strong>, del siguiente aceite o lubricante</div>
<span class="badge rounded-pill bg-primary mt-1 mb-3"><strong x-text="detalleDiferencia.concepto"></strong></span>

<h6>Ficha de pago:</h6>
<template x-if="detalleDiferencia.documento">
<span x-data="actions()">
<a href="" @click.prevent="download('aceites-diferencias', detalleDiferencia.documento)">
<i class="ti ti-download fs-6"></i> Descargar Documento
</a>
</span>
</template>
<template x-if="!detalleDiferencia.documento">
<div class="d-flex gap-2 align-items-center">
<input type="file" class="form-control" id="docDiferenciaUpdate" accept=".pdf">
<button class="btn btn-primary" @click="actualizarDocumentoDiferencia()" :disabled="loading">
<span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!loading" class="ti ti-upload"></i> Subir
</button>
</div>
</template>
<div class="mt-2 mb-1"><small>Comentario</small></div>
<div class="p-2 border rounded" x-text="detalleDiferencia.comentario || 'S/C'"></div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
</div>
</div>
</div>
</div>

<!-- Modal Importar Facturas (CSV) -->
<div class="modal fade" id="modalImportar" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalImportar">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="ti ti-upload"></i> Importar Facturas (CSV)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">Archivo CSV</label>
<input type="file" class="form-control" accept=".csv" @change="csvFile = $event.target.files[0]">
</div>
<p class="small text-muted">Formato: fecha, concepto, archivo (nombre del archivo)</p>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button class="btn btn-primary" @click="importarFacturas" :disabled="importando">
<span x-show="importando" class="spinner-border spinner-border-sm me-1"></span>
<i x-show="!importando" class="ti ti-upload"></i> Importar
</button>
</div>
</div>
</div>
</div>

<!-- Modal Evaluaciòn de Aceites (KPI's) -->
<div class="modal fade" id="modalPuntajes" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" x-ref="modalPuntajes">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="ti ti-chart-bar"></i> Evaluación de Aceites (KPI's)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="table-responsive">
<table class="table table-bordered mb-0">
<thead>
<tr>
<th>Indicador</th>
<th class="text-center">Promedio</th>
</tr>
</thead>
<tbody>
<tr>
<td>Evaluaciòn Ficha Depòsito</td>
<td class="text-center"><strong x-text="puntajes.promedio_ficha"></strong></td>
</tr>
<tr>
<td>Evaluaciòn Factura (Documentos)</td>
<td class="text-center"><strong x-text="puntajes.promedio_factura_doc"></strong></td>
</tr>
<tr>
<td>Evaluaciòn Factura (Anexos)</td>
<td class="text-center"><strong x-text="puntajes.promedio_factura_anexo"></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
</div>
</div>
</div>
</div>

</div>
<?php endif; ?>
