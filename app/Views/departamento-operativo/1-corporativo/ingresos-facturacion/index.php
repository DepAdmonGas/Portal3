<div id="ingresos-facturacion-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4"<?= $idEstacion ? ' style="display:none"' : '' ?>>
Debes de seleccionar una estación del menú superior para poder visualizar la información de Ingresos vs Facturación.
</div>

<div class="pb-4" id="ingresos-facturacion-content" class="mt-2"<?= $idEstacion ? '' : ' style="display:none"' ?>
x-data="ingresosFacturacionComponent()"
data-id-year="<?= $idYear ?>"
data-id-estacion="<?= $idEstacion ?>">

<div class="row mb-3">
<div class="col-12">
<button type="button" class="btn btn-primary float-end" @click="abrirEntregables()">
<i class="ti ti-file-pen me-1"></i>Entregables
</button>
</div>
</div>

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
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th colspan="14" class="align-middle text-center">Comparativo de Facturación</th>
</tr>
<tr>
<th class="align-middle text-center">Cortes diarios</th>
<th class="align-middle text-end">Enero</th>
<th class="align-middle text-end">Febrero</th>
<th class="align-middle text-end">Marzo</th>
<th class="align-middle text-end">Abril</th>
<th class="align-middle text-end">Mayo</th>
<th class="align-middle text-end">Junio</th>
<th class="align-middle text-end">Julio</th>
<th class="align-middle text-end">Agosto</th>
<th class="align-middle text-end">Septiembre</th>
<th class="align-middle text-end">Octubre</th>
<th class="align-middle text-end">Noviembre</th>
<th class="align-middle text-end">Diciembre</th>
<th class="align-middle text-center">Total Ejercicio</th>
</tr>
</thead>
<tbody class="bg-white">
<template x-for="row in posicion1" :key="row.id">
<tr>
<th class="fw-normal" x-text="row.detalle"></th>
<template x-for="(m, mi) in meses" :key="mi">
<td class="align-middle text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input type="text" inputmode="decimal" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 20px !important; min-width: 80px;"
x-init="$el.value = formatInput(row[m])"
@focus="$el.value = (parseFloat(row[m]) || 0).toString(); $el.select()"
@blur="const v = parseFloat($el.value.replace(/,/g, '')) || 0; row[m] = v; editIF(row.id, m, 1); $el.value = formatInput(v)">
</div>
</td>
</template>
<td class="align-middle text-end fw-semibold" x-text="formatMoney(totalFila(row))"></td>
</tr>
</template>
</tbody>
<tfoot>
<tr class="table-dark">
<th class="align-middle text-white">Total cortes diarios</th>
<template x-for="(m, mi) in meses" :key="'t1-' + mi">
<th class="align-middle text-white text-end" x-text="totales1[m]"></th>
</template>
<th class="align-middle text-white text-end" x-text="totales1.ejercicio"></th>
</tr>
</tfoot>
</table>
</div>

<div class="table-responsive mt-4">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th colspan="14" class="align-middle text-center">Facturación</th>
</tr>
<tr>
<th class="align-middle text-center">Facturación</th>
<th class="align-middle text-end">Enero</th>
<th class="align-middle text-end">Febrero</th>
<th class="align-middle text-end">Marzo</th>
<th class="align-middle text-end">Abril</th>
<th class="align-middle text-end">Mayo</th>
<th class="align-middle text-end">Junio</th>
<th class="align-middle text-end">Julio</th>
<th class="align-middle text-end">Agosto</th>
<th class="align-middle text-end">Septiembre</th>
<th class="align-middle text-end">Octubre</th>
<th class="align-middle text-end">Noviembre</th>
<th class="align-middle text-end">Diciembre</th>
<th class="align-middle text-center">Total Ejercicio</th>
</tr>
</thead>
<tbody class="bg-white">
<template x-for="row in posicion2" :key="row.id">
<tr>
<th class="fw-normal" x-text="row.detalle"></th>
<template x-for="(m, mi) in meses" :key="mi">
<td class="align-middle text-end p-0">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-2">$</span>
<input type="text" inputmode="decimal" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 20px !important; min-width: 80px;"
x-init="$el.value = formatInput(row[m])"
@focus="$el.value = (parseFloat(row[m]) || 0).toString(); $el.select()"
@blur="const v = parseFloat($el.value.replace(/,/g, '')) || 0; row[m] = v; editIF(row.id, m, 2); $el.value = formatInput(v)">
</div>
</td>
</template>
<td class="align-middle text-end" x-text="formatMoney(totalFila(row))"></td>
</tr>
</template>
</tbody>
<tfoot>
<tr class="table-dark">
<th class="align-middle text-white">Total XML Timbrados</th>
<template x-for="(m, mi) in meses" :key="'t2-' + mi">
<th class="align-middle text-end text-white" x-text="totales2[m]"></th>
</template>
<th class="align-middle text-end text-white" x-text="totales2.ejercicio"></th>
</tr>
<tr class="table-dark">
<th class="align-middle text-white">Total Diferencias</th>
<template x-for="(m, mi) in meses" :key="'td-' + mi">
<th class="align-middle text-end text-white" x-text="diferencias[m]"></th>
</template>
<th class="align-middle text-end text-white" x-text="diferencias.ejercicio"></th>
</tr>
</tfoot>
</table>
</div>
</div>
</template>

<div class="modal fade" id="modalEntregables" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Entregables</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<label class="form-label">* Archivo:</label>
<input class="form-control" type="file" id="archivoInput">
<div class="text-end mt-3">
<button type="button" class="btn btn-success" @click="guardarArchivo()" :disabled="guardando">Guardar</button>
</div>
<div class="table-responsive mt-3">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="align-middle text-center">Fecha</th>
<th class="align-middle text-end" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<th class="align-middle text-end" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody class="bg-white">
<template x-for="a in archivos" :key="a.id">
<tr>
<th class="fw-normal align-middle text-center" x-text="a.fecha_formateada"></th>
<td class="align-middle text-end">
<span x-data="actions()">
<a href="" @click.prevent="download('ingresos-facturacion', a.archivo)">
<i class="ti ti-download text-primary fs-5"></i>
</a>
</span>
</td>
<td class="align-middle text-end">
<span x-data="actions()">
<a href="" @click.prevent="deleteAction({url: '/departamento-operativo/ingresos-facturacion/delete-file', id: a.id, name: a.fecha_formateada, table: null}).then(r => r?.success && $dispatch('if:file-deleted'))">
<i class="ti ti-trash text-danger fs-5"></i>
</a>
</span>
</td>
</tr>
</template>
<template x-if="archivos.length === 0">
<tr>
<th colspan="3" class="text-center text-secondary py-2">
<small>No se encontró información para mostrar</small>
</th>
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

