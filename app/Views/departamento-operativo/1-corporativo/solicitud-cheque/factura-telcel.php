<div class="mt-3 mb-4">
<div x-data="facturaTelcelComponent()" x-init="initData(<?= $idYear ?>, <?= $idMes ?>, <?= $idEstacion ?>, '<?= addslashes($comentario) ?>')" @refresh-directorio.window="cargarDirectorio()" @refresh-facturas.window="cargarFacturas()">

<div class="row mb-3">
<div class="col-12">

<div class="dropdown float-end">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="ti ti-tools"></i></button>
<ul class="dropdown-menu dropdown-menu-end">
<li><a class="dropdown-item pointer" @click.prevent="abrirModalDirectorio()"><i class="ti ti-address-book me-1"></i> Directorio</a></li>
<li><a class="dropdown-item pointer" @click.prevent="abrirModalFacturaTelcel()"><i class="ti ti-file-plus me-1"></i> Factura</a></li>
</ul>
</div>

</div>
</div>

<div class="row">

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-file-report me-2"></i>DIRECTORIO</h5>
</div>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table id="tabla-directorio" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Cuenta</th>
<th class="text-center">Puesto</th>
<th class="text-center">Clave</th>
<th class="text-center" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-if="directorio.length === 0">
<tr><td colspan="5" class="text-center text-muted"><small>No se encontró información para mostrar</small></td></tr>
</template>
<template x-for="d in directorio" :key="d.id">
<tr>
<td class="text-center" x-text="d.cuenta"></td>
<td class="text-center" x-text="d.puesto"></td>
<td class="text-center" x-text="d.clave"></td>

<td class="text-center">
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown" class="text-decoration-none">
<i class="ti ti-dots-vertical fs-6"></i>
</a>
<ul class="dropdown-menu">

<li>
<span x-data="actions()">
<a class="dropdown-item pointer d-flex align-items-center gap-2 pointer" @click="editarDirectorio(d)">
<i class="ti ti-pencil fs-5"></i> Editar
</a>
</span>
</li>

<li>
<span x-data="actions()">
<a class="dropdown-item pointer d-flex align-items-center gap-2 pointer text-danger" @click="deleteAction({url:'/departamento-operativo/solicitud-cheque/factura-telcel/delete-directorio',id:d.id,name:d.cuenta}).then(r=>r?.success&&$dispatch('refresh-directorio'))">
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

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-report-money me-2"></i>FACTURA</h5>
</div>
</div>

<div class="table-responsive">
<table id="tabla-facturas" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Detalle</th>
<th class="text-center">Fecha y hora</th>
<th class="text-center" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th>

</tr>
</thead>
<tbody>

<template x-if="facturas.length === 0">
<tr><td colspan="3" class="text-center text-muted"><small>No se encontró información para mostrar</small></td></tr>
</template>

<template x-for="f in facturas" :key="f.id">
<tr>
<td class="text-center" x-text="f.detalle"></td>
<td class="text-center" x-text="f.fecha_formateada"></td>
<td class="text-center">
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown" class="text-decoration-none"><i class="ti ti-dots-vertical fs-6"></i></a>
<ul class="dropdown-menu">  
<li>
<span x-data="actions()">
<a class="dropdown-item pointer d-flex align-items-center gap-2 pointer" @click.prevent="download('solicitud-cheque', f.factura)">
<i class="ti ti-download fs-5"></i> Descargar
</a>
</span>
</li>
<li>
<span x-data="actions()">
<a class="dropdown-item pointer d-flex align-items-center gap-2 pointer text-danger" @click="deleteAction({url:'/departamento-operativo/solicitud-cheque/factura-telcel/delete',id:f.id,name:'Factura '+f.id}).then(r=>r?.success&&$dispatch('refresh-facturas'))">
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

<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-2"></i>OBSERVACIONES</h5>
</div>
</div>

<div class="card-body p-0">
<textarea class="form-control border-0 p-3" x-model="comentario" placeholder="Escribe aqui tus observaciones..." style="height: 180px;" @input="guardarComentarioDebounced"></textarea>
</div>

</div>

</div>
</div>

<div class="modal fade" id="modalDirectorio" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" x-text="editandoDirectorio ? 'Editar directorio' : 'Nuevo directorio'"></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="guardando"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">* Cuenta:</label>
<input type="text" class="form-control" x-model="formCuenta">
</div>
<div class="mb-3">
<label class="form-label">* Puesto:</label>
<input type="text" class="form-control" x-model="formPuesto">
</div>
<div>
<label class="form-label">* Clave:</label>
<input type="text" class="form-control" x-model="formClave">
</div>
</div>   
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" :disabled="guardando">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarDirectorio" :disabled="guardando">Guardar</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalFacturaTelcel" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Nuevo documento</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="guardando"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">* Detalle:</label>
<select class="form-select" x-model="formDetalle">
<option value="">Selecciona una opción...</option>
<option value="Factura">Factura</option>
<option value="Pago">Pago</option>
<option value="Nota de crédito">Nota de crédito</option>
<option value="Otros">Otros</option>
<option value="Estado de cuenta">Estado de cuenta</option>
<option value="XML">XML</option>
</select>
</div>
<div>
<label class="form-label">* Documento:</label>
<input type="file" class="form-control" x-ref="facturaFileInput" accept=".pdf,.jpg,.png">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" :disabled="guardando">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarFacturaTelcel" :disabled="guardando">Guardar</button>
</div>
</div>
</div>
</div>
</div>
</div>
