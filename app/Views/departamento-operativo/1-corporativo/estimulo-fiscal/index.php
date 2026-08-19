<div id="container" class="pb-4"
x-data="{ ...actions(), ...estimuloFiscalComponent() }"
@estimulo-fiscal:editar.window="abrirModalEditar($event.detail.id)"
@estimulo-fiscal:eliminar.window="eliminarPago($event.detail.id)">

<div class="text-end mb-4">
<div class="btn-group">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>
<ul class="dropdown-menu animated rubberBand pointer">
<li x-show="permisos && permisos.id_puesto !== 6"><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modal-agregar"><i class="ti ti-plus"></i> Nuevo</a></li>
<li><a class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#modal-buscar"><i class="ti ti-search"></i> Buscar Reporte</a></li>
</ul>
</div>
</div>

<div class="row">
<div class="col-xl-5 col-lg-6 col-md-12 mb-3">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th colspan="3" class="text-center">
Fecha de reporte: <span x-text="resumen.fecha_inicio ? formatearFecha(resumen.fecha_inicio) + ' al día ' + formatearFecha(resumen.fecha_termino) : ''"></span>
</th>
</tr>
<tr>
<th class="text-center text-white fw-bold" style="background:#78bd24">G SUPER</th>
<th class="text-center text-white fw-bold" style="background:#e01483">G PREMIUM</th>
<th class="text-center text-white fw-bold" style="background:#5e0f8e">G DIESEL</th>
</tr>
</thead>
<tbody>
<tr>
<td class="text-center fw-bold" x-text="numberFormat(resumen.g_super)"></td>
<td class="text-center fw-bold" x-text="numberFormat(resumen.g_premium)"></td>
<td class="text-center fw-bold" x-text="numberFormat(resumen.g_diesel)"></td>
</tr>
<tr>
<td colspan="2" class="text-end fw-normal">Total de Litros Comprados</td>
<td class="text-end fw-bold" x-text="numberFormat(resumen.total_litros)"></td>
</tr>
<tr>
<td colspan="2" class="text-end fw-normal">Total a pagar</td>
<td class="text-end fw-bold" x-text="'$ ' + formatMoney(resumen.total_pagar)"></td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="col-xl-7 col-lg-6 col-md-12">
    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th class="text-center" colspan="3">Periodo</th>
<th class="text-center" colspan="2">Pago</th>
<th class="text-center" colspan="2">Complemento</th>
<th class="text-center" width="48px"><i class="fas fa-ellipsis-v"></i></th>

</tr>
<tr>
<th class="text-center" width="96px">#</th>
<th class="text-center">Fecha de inicio</th>
<th class="text-center">Fecha de termino</th>
<th class="text-center" width="48px"><i class="ti ti-file-type-pdf fs-6 text-danger"></i></th>
<th class="text-center" width="48px"><i class="ti ti-file-type-xls fs-6 text-success"></i></th>
<th class="text-center" width="48px"><i class="ti ti-file-type-pdf fs-6 text-danger"></i></th>
<th class="text-center" width="48px"><i class="ti ti-file-type-xls fs-6 text-success"></i></th>
<th class="text-center" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="p in pagos" :key="p.id">
<tr>
<td class="text-center fw-bold" x-text="p.id"></td>
<td class="text-center" x-text="p.fecha_inicio_formateada"></td>
<td class="text-center" x-text="p.fecha_termino_formateada"></td>

<td class="text-center">
<template x-if="p.pdf">
<a class="pointer" @click.prevent="p.pdf ? download('estimulo-fiscal', p.pdf) : void 0">
<i class="ti ti-file-type-pdf fs-6 text-danger"></i>
</a>
</template>
<template x-if="!p.pdf">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>

<td class="text-center">
<template x-if="p.xml">
<a class="pointer" @click.prevent="p.xml ? download('estimulo-fiscal', p.xml) : void 0">
<i class="ti ti-file-type-xls fs-6 text-success"></i>
</a>
</template>
<template x-if="!p.xml">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>

<td class="text-center">
<template x-if="p.co_pdf">
<a class="pointer" @click.prevent="p.co_pdf ? download('estimulo-fiscal', p.co_pdf) : void 0">
<i class="ti ti-file-type-pdf fs-6 text-danger"></i>
</a>
</template>
<template x-if="!p.co_pdf">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>

<td class="text-center">
<template x-if="p.co_xml">
<a class="pointer" @click.prevent="p.co_xml ? download('estimulo-fiscal', p.co_xml) : void 0">
<i class="ti ti-file-type-xls fs-6 text-success"></i>
</a>
</template>
<template x-if="!p.co_xml">
<i class="ti ti-file-off text-muted fs-6"></i>
</template>
</td>

<td class="text-center" x-show="permisos && permisos.id_puesto !== 6">
<div x-data="{}">
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown">
<i class="ti ti-dots-vertical fs-5 text-muted"></i>
</a>
<div class="dropdown-menu pointer">
<a class="dropdown-item pointer" @click="$dispatch('estimulo-fiscal:editar', {id: p.id})">
<i class="ti ti-pencil me-1"></i> Editar
</a>
<a class="dropdown-item pointer" @click="$dispatch('estimulo-fiscal:eliminar', {id: p.id})">
<i class="ti ti-trash me-1"></i> Eliminar
</a>
</div>
</div>
</div>
</td>

</tr>

</template>
<template x-if="pagos.length === 0">
<tr>
<td colspan="8" class="text-center text-secondary py-2">
<small>No se encontró información para mostrar</small>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>

<div class="modal fade" id="modal-buscar" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Buscar Reporte</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row">
<div class="col-6 mb-3">
<label class="form-label">* Fecha Inicio:</label>
<input type="date" class="form-control" x-model="buscarForm.fecha_inicio">
</div>
<div class="col-6 mb-3">
<label class="form-label">* Fecha Termino:</label>
<input type="date" class="form-control" x-model="buscarForm.fecha_termino">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" :disabled="loading">Cerrar</button>
<button type="button" class="btn btn-primary" @click="buscarReporte" :disabled="loading">Buscar</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modal-agregar" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Nuevo comprobante de pago</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h5 class="fw-bold">PERIODO</h5>
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">* Fecha Inicio:</label>
<input type="date" class="form-control" x-model="form.fecha_inicio">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">* Fecha Termino:</label>
<input type="date" class="form-control" x-model="form.fecha_termino">
</div>
</div>
<hr>
<div class="row">
<div class="col-12 mb-3">
<label class="form-label">* Agregar Factura PDF:</label>
<input type="file" class="form-control" x-ref="EPDF_file_input">
</div>
<div class="col-12 mb-3">
<label class="form-label">* Agregar Factura XML:</label>
<input type="file" class="form-control" x-ref="EXML_file_input">
</div>
</div>
<div class="text-danger mt-3 small">NOTA: USO DEL CFDI ES GASTOS EN GENERAL Y LA FORMA DE PAGO ES PPD</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" :disabled="loading">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarPago" :disabled="loading">Guardar</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modal-editar" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Editar comprobante de pago</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<h5 class="fw-bold">PERIODO</h5>
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">* Fecha Inicio:</label>
<input type="date" class="form-control" x-model="editForm.fecha_inicio">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">* Fecha Termino:</label>
<input type="date" class="form-control" x-model="editForm.fecha_termino">
</div>
</div>
<hr>
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">Agregar Factura PDF:</label>
<input type="file" class="form-control" x-ref="edit_EPDF_file_input">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">Agregar Factura XML:</label>
<input type="file" class="form-control" x-ref="edit_EXML_file_input">
</div>
</div>
<hr>
<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">Agregar Complemento PDF:</label>
<input type="file" class="form-control" x-ref="edit_CPDF_file_input">
</div>
<div class="col-xl-6 col-lg-6 col-md-6 mb-3">
<label class="form-label">Agregar Complemento XML:</label>
<input type="file" class="form-control" x-ref="edit_CXML_file_input">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" :disabled="loading">Cancelar</button>
<button type="button" class="btn btn-success" @click="editarPago" :disabled="loading">Guardar</button>
</div>
</div>
</div>
</div>

</div>
