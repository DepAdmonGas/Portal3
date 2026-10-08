<div id="container" class="pb-4" x-data="{ ...actions(), ...proveedoresComponent() }">
<div class="row mt-3">

<!-- ENCABEZADO Y BOTÓN AGREGAR -->
<div class="col-12 mb-3">
<div class="float-end">
<div>
<a href="/departamento-operativo/almacen/proveedores-nuevo" class="btn bg-primary-subtle text-primary">
<i class="ti ti-plus me-1"></i> Nuevo
</a>
</div>
</div>
</div>

<!-- TABLA PRINCIPAL CON CARD HEADER AZUL -->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-proveedores" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead></thead>
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

<!-- MODAL DETALLE DEL PROVEEDOR (REEMPLAZO DE OFFCANVAS) -->
<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-building text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">
Detalle del Proveedor <span x-show="detalle && detalle.razon_social" x-text="'(' + detalle.razon_social + ')'"></span>
</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<template x-if="cargandoDetalle">
<div class="d-flex flex-column align-items-center justify-content-center py-5">
<div class="spinner-border text-primary mb-3" role="status"></div>
<span class="text-muted fw-semibold">Consultando información del proveedor...</span>
</div>
</template>

<template x-if="!cargandoDetalle && detalle">
<div>
<span class="badge bg-primary-subtle text-primary mb-3 fs-3 px-3 py-2 fw-semibold">Información General</span>

<div class="row g-3 mb-3">
<div class="col-md-6">
<label class="form-label mb-1">Folio:</label>
<div x-text="detalle.folio"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Fecha:</label>
<div x-text="detalle.fecha"></div>
</div>
<div class="col-md-12">
<label class="form-label mb-1">Razón social:</label>
<div x-text="detalle.razon_social"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">RFC:</label>
<div class="text-uppercase" x-text="detalle.rfc || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Correo electrónico:</label>
<div x-text="detalle.email || 'S/I'"></div>
</div>
<div class="col-12">
<label class="form-label mb-1">Actividad económica:</label>
<div x-text="detalle.actividad_economica || 'S/I'"></div>
</div>
</div>

<span class="badge bg-primary-subtle text-primary mb-3 fs-3 px-3 py-2 fw-semibold">Datos de Contacto y Pago</span>

<div class="row g-3 mb-3">
<div class="col-md-6">
<label class="form-label mb-1">Ciudad:</label>
<div x-text="detalle.ciudad || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Dirección:</label>
<div x-text="detalle.direccion || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Teléfono 1:</label>
<div x-text="detalle.telefono_1 || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Teléfono 2:</label>
<div x-text="detalle.telefono_2 || 'S/I'"></div>
</div>
<div class="col-12">
<label class="form-label mb-1">Nombre del beneficiario:</label>
<div x-text="detalle.beneficiario || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Banco:</label>
<div x-text="detalle.banco || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Método de pago:</label>
<div x-text="detalle.metodo_pago || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Uso del CFDI:</label>
<div x-text="detalle.cfdi || 'S/I'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Moneda:</label>
<div x-text="detalle.moneda || 'MXN'"></div>
</div>
<div class="col-md-6">
<label class="form-label mb-1">Forma de pago:</label>
<div x-text="detalle.forma_pago || 'S/I'"></div>
</div>
<div class="col-12">
<label class="form-label mb-1">Descripción de productos / servicios:</label>
<div class="bg-light p-3 rounded" x-text="detalle.descripcion || 'Sin descripción'"></div>
</div>
</div>

<span class="badge bg-primary-subtle text-primary mb-3 fs-3 px-3 py-2 fw-semibold">Documentación Adjunta</span>
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-start align-middle">Documento</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download fs-6 text-primary"></i></th>
</tr>
</thead>
<tbody>
<template x-for="d in detalle.documentos" :key="d.id">
<tr>
<td class="text-start align-middle" x-text="d.nombre"></td>
<td class="text-center align-middle">
<a href="javascript:void(0)" class="text-primary" @click="download('proveedores', d.archivo)">
<i class="ti ti-download fs-6 text-primary"></i>
</a>
</td>
</tr>
</template>
<template x-if="!detalle.documentos || detalle.documentos.length === 0">
<tr><td colspan="2" class="text-center text-muted py-3">Sin documentos registrados</td></tr>
</template>
</tbody>
</table>
</div>
</div>
</template>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
</div>
</div>
</div>
</div>

<!-- MODAL DOCUMENTACIÓN DEL PROVEEDOR -->
<div class="modal fade" id="modalArchivos" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-primary">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-file-text text-white fs-5"></i>
<h5 class="modal-title text-white mb-0">
Documentación del Proveedor
</h5>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">

<div class="alert alert-primary text-center mb-3">
<i class="ti ti-info-circle me-1"></i> <b>Nota:</b> La documentación debe ser actualizada cada 3 meses.
</div>

<label class="form-label mb-1">Nombre comercial de la empresa (Proveedor):</label>
<div class="mb-3"><span x-text="proveedorSeleccionadoNombre ? proveedorSeleccionadoNombre : ''"></span></div>    

<div class="row g-3 mb-3">
<div class="col-12">
<label class="form-label mb-1">* Tipo de Documento:</label>
<select class="form-select" 
x-model="formArchivo.tipo" 
:style="errorsArchivo.tipo ? 'border: 2px solid #A52525 !important;' : ''"
@change="errorsArchivo.tipo = false">
<option value="">Seleccione una opción...</option>
<option value="Caratula Bancaria">Caratula Bancaria</option>
<option value="Constancia de Situacion Fiscal">Constancia de Situacion Fiscal</option>
</select>
</div>
<div class="col-12">
<label class="form-label mb-1">* Fecha de última actualización:</label>
<input type="date" 
class="form-control" 
x-model="formArchivo.fecha"
:style="errorsArchivo.fecha ? 'border: 2px solid #A52525 !important;' : ''"
@input="errorsArchivo.fecha = false">
</div>
<div class="col-12">
<label class="form-label mb-1">* Archivo (PDF):</label>
<input type="file" 
class="form-control" 
id="inputDocArchivo"
@change="formArchivo.archivo = $event.target.files[0] || null; errorsArchivo.archivo = false"
:style="errorsArchivo.archivo ? 'border: 2px solid #A52525 !important;' : ''"
accept=".pdf">
</div>
</div>

<span class="badge bg-primary-subtle text-primary mb-3 fs-3 px-3 py-2 fw-semibold">Documentación Adjunta</span>

<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-start align-middle">Documento</th>
<th class="text-center align-middle">Fecha de última actualización</th>
<th class="text-center align-middle">Estatus</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-for="d in listaDocsProveedor" :key="d.id">
<tr :style="d.esta_vencido ? 'background-color: #ffb6af;' : ''">
<td class="text-start align-middle" x-text="d.nombre"></td>
<td class="text-center align-middle" x-text="d.fecha"></td>
<td class="text-center align-middle">
<template x-if="d.esta_vencido">
<span class="badge bg-danger">
Actualizar
</span>
</template>
<template x-if="!d.esta_vencido">
<span class="badge bg-success">
Actualizada
</span>
</template>
</td>
<td class="text-center align-middle">
<a href="javascript:void(0)" @click="download('proveedores', d.archivo)">
<i class="ti ti-download text-primary fs-5"></i>
</a>
</td>
</tr>
</template>
<template x-if="listaDocsProveedor.length === 0">
<tr><td colspan="4" class="text-center text-primary py-3">No se encontro información</td></tr>
</template>
</tbody>
</table>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>

<button type="button" class="btn btn-success" @click="actualizarArchivo()" :disabled="guardandoArchivo">
<template x-if="!guardandoArchivo"><i class="ti ti-check me-1"></i></template>
<template x-if="guardandoArchivo"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardandoArchivo ? 'Actualizando...' : 'Actualizar'"></span>
</button>

</div>
</div>
</div>
</div>
</div>