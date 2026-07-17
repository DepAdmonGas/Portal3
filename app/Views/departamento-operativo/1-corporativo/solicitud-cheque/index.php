<?php if (!$multiestacion && !$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de Solicitud de Cheques.
</div>
<?php else: ?>

<div id="container" class="mt-3 mb-3"
data-year-mes-template="/departamento-operativo/solicitud-cheque/{year}/{mes}"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-depto="<?= $idDepto ?>"
data-id-puesto="<?= $idPuesto ?>"
data-nombre-puesto="<?= $nombrePuesto ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-module-station-key="solicitud-cheques"
data-es-gestoria="<?= $esGestoria ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-ver-comentarios="<?= $puedeVerComentarios ? 'true' : 'false' ?>"
data-puede-agregar-comentarios="<?= $puedeAgregarComentarios ? 'true' : 'false' ?>"
data-puede-agregar-documentos="<?= $puedeAgregarDocumentos ? 'true' : 'false' ?>"
data-puede-gestionar-pagos="<?= $puedeGestionarPagos ? 'true' : 'false' ?>"
data-puede-gestionar-telcel="<?= $puedeGestionarTelcel ? 'true' : 'false' ?>"
data-puede-exportar="<?= $puedeExportar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-es-mes-actual="<?= $esMesActual ? 'true' : 'false' ?>"
x-data="{ ...actions(), ...solicitudChequeComponent() }">

<div id="sc-tools-header" class="d-flex align-items-center justify-content-between mb-2 gap-2 mb-3">
<div id="sc-pending-wrapper" class="d-flex align-items-center gap-1">
<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle"></i>
<span>Pendientes: <span id="sc-pending-count">0</span></span>
</span>
</div>
<div id="sc-tools-anchor" class="d-flex"></div>
</div>

<template id="sc-tools-tmpl">
<div id="sc-tools-wrapper" class="d-flex justify-content-end">
<div class="dropdown d-inline-block" id="sc-tools-dropdown">
    
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu">
<li id="sc-tool-agregar" class="sc-tool-item"><a class="dropdown-item pointer" onclick="irACrearSolicitud()"><i class="ti ti-plus me-1"></i> Nueva solicitud</a></li>
<li id="sc-tool-telcel" class="sc-tool-item"><a class="dropdown-item pointer" onclick="abrirFacturasTelcelGlobal()"><i class="ti ti-device-mobile me-1"></i> Facturas Telcel</a></li>
<li id="sc-tool-comprobante" class="sc-tool-item"><a class="dropdown-item pointer" onclick="abrirComprobantePago()"><i class="ti ti-file-invoice me-1"></i> Comprobante Pago</a></li>
<li id="sc-tool-excel" class="sc-tool-item"><a class="dropdown-item pointer" onclick="descargarExcel()"><i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel</a></li>
</ul>
</div>
</div>
</template> 

<div class="table-responsive pb-5" style="overflow-y: visible; overflow-x: auto;">
<table id="tabla-solicitud-cheque" class="table table-striped table-bordered mb-0 text-nowrap align-middle"></table>
</div>



<div id="modalTelcelGlobal" class="modal fade" tabindex="-1" data-bs-backdrop="static" x-ref="modalTelcelGlobal">
<div class="modal-dialog modal-lg">
<div class="modal-content">

<div class="modal-header">
<h4 class="modal-title">Comprobante de Pago</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<template x-if="!telcelEditandoId">
<div>

<template x-if="multiestacion">
<div class="row">

<div class="col-12 mb-3">
<label class="form-label">* Factura Telcel:</label>
<input type="file" class="form-control" x-ref="nuevoTelcelGlobalFile" accept=".pdf">
</div>

<div class="col-12 mb-3">
<button class="btn btn-success float-end" @click="agregarTelcelGlobal()" :disabled="guardandoTelcelGlobal">Guardar</button>
</div>

</div>
</template>

<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center">Factura telcel</th>
<th class="text-center">Comprobante de pago</th>
<th class="text-center" width="48px"><i class="ti ti-edit text-warning fs-5"></i></th>
<th class="text-center" width="48px" x-show="multiestacion"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-if="telcelGlobal.length > 0">
<template x-for="t in telcelGlobal" :key="t.id">
<tr>

<td class="text-center">
<template x-if="t.factura">
<i class="ti ti-file-text pointer text-primary fs-5" @click.prevent="download('solicitud-cheque', t.factura)"></i>
</template>
</td>

<td class="text-center">
<template x-if="t.c_pago">
<i class="ti ti-download pointer text-primary fs-5" @click.prevent="download('solicitud-cheque', t.c_pago)"></i>
</template>

<template x-if="!t.c_pago">
<i class="ti ti-file-off text-muted fs-5"></i>
</template>
</td>

<td class="text-center"><i class="ti ti-edit pointer text-warning fs-5" @click="editarTelcelGlobal(t)"></i></td>
<td class="text-center" x-show="multiestacion"><i class="ti ti-trash pointer text-danger fs-5" @click="eliminarTelcelGlobal(t.id)"></i></td>
</tr>
</template>
</template>

<template x-if="telcelGlobal.length === 0">
<tr><td colspan="4" class="text-center text-secondary">No se encontro información</td></tr>
</template>

</tbody>
</table>

</div>
</template>

<template x-if="telcelEditandoId">
<div>
<template x-if="multiestacion">
<div class="mb-3">
<label class="form-label">* Factura Telcel:</label>
<input type="file" class="form-control" x-ref="editarFacturaFile" accept=".pdf">
</div>
</template>
<div class="mb-3">
<label class="form-label">* Comprobante de Pago:</label>
<input type="file" class="form-control" x-ref="editarPagoFile" accept=".pdf,.jpg,.png">
</div>
</div>
</template>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>

<template x-if="telcelEditandoId">
<div class="d-flex gap-2">
<button class="btn btn-secondary"
@click="cancelarEditarTelcelGlobal()">
Regresar
</button>

<button class="btn btn-success"
@click="guardarEditarTelcelGlobal()"
:disabled="guardandoTelcelGlobal">
Guardar
</button>
</div>
</template>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" x-ref="modalDetalle">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title">Detalle de solicitud (# <span x-text="detalle ? detalle.id : ''"></span>)</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body pb-0">
<template x-if="detalle">
<div>

<div class="row">
<div class="col-12 mb-3 text-end"><strong>Estatus:</strong> <span class="badge" :class="detalle.status === 0 ? 'bg-danger text-white' : detalle.status === 1 ? 'bg-warning text-white' : detalle.status === 3 ? 'bg-info text-dark' : 'bg-success'" x-text="detalle.status_label"></span></div>

<div class="col-12 mb-2">
<div class="alert text-center" :class="detalle.status === 2 ? 'alert-success' : (detalle.status === 0 && detalle.periodo_vencido ? 'alert-danger' : detalle.status === 1 ? 'alert-warning' : detalle.status === 3 ? 'alert-info' : 'alert-warning')" role="alert">
<template x-if="detalle.status === 2">
<span><i class="ti ti-circle-check me-1"></i> La Solicitud de Cheque ha sido Autorizada</span>
</template>
<template x-if="detalle.status === 0 && detalle.periodo_vencido">
<span><i class="ti ti-alert-triangle me-1"></i> La solicitud no pudo ser firmada para su autorización; es necesario generar una nueva solicitud</span>
</template>
<template x-if="detalle.status === 0 && !detalle.periodo_vencido">
<span> <i class="ti ti-signature me-1"></i> Hace falta la firma del Visto Bueno (VO.BO)</span>
</template>
<template x-if="detalle.status === 1">
<span> <i class="ti ti-signature me-1"></i> ace falta la firma de Autorización</span>
</template>
</div>
</div>
</div>

<div class="row g-2">

<template x-if="detalle.razonsocial?.trim() && detalle.razonsocial.trim() !== 'Selecciona una opcion...'">
<div class="col-12 mb-2">
<label class="form-label">Razón Social:</label>
<div x-text="detalle.razonsocial"></div>
</div>
</template>

<div class="col-md-4 mb-2">
<label class="form-label">Fecha:</label>
<div x-text="detalle.fecha_formateada"></div>
</div>

<div class="col-md-8 mb-2">
<label class="form-label">Nombre del Beneficiario:</label>
<div x-text="detalle.beneficiario"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Monto / Moneda:</label>
<div>
$<span x-text="formatNum(detalle.monto)"></span>
<span x-text="detalle.moneda"></span>
</div>
</div>

<template x-if="detalle.importe_letra">
<div class="col-md-8 mb-2">
<label class="form-label">Importe con letra:</label>
<div x-text="detalle.importe_letra"></div>
</div>
</template>

<div class="col-md-4 mb-2">
<label class="form-label">No. Factura:</label>
<div x-text="detalle.no_factura"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Correo Electrónico:</label>
<div x-text="detalle.email"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Concepto:</label>
<div x-text="detalle.concepto"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Nombre del Solicitante:</label>
<div x-text="detalle.solicitante"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Teléfono:</label>
<div x-text="detalle.telefono"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Uso del CFDI:</label>
<div x-text="detalle.cfdi"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Método de Pago:</label>
<div x-text="detalle.metodo_pago"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Forma de Pago:</label>
<div x-text="detalle.forma_pago"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Banco:</label>
<div x-text="detalle.banco"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">No. Cuenta:</label>
<div x-text="detalle.no_cuenta"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">No. de Cuenta (CLABE):</label>
<div x-text="detalle.cuenta_clabe"></div>
</div>

<div class="col-md-4 mb-2">
<label class="form-label">Referencia / Convenio:</label>
<div x-text="detalle.referencia"></div>
</div>

<template x-if="detalle.observaciones">
<div class="col-12 mb-2">
<label class="form-label">Observaciones:</label>
<div x-text="detalle.observaciones"></div>
</div>
</template>

</div>

<hr>

<label class="form-label mb-3">Firmas:</label>

<div class="row">

<!-- Card A: ENCARGADO / ELABORÓ -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-3">
<template x-if="getFirma('A')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="getFirma('A').tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<template x-if="getFirma('A').firma_img_url">
<div><img :src="getFirma('A').firma_img_url" class="img-fluid" style="max-height:90px;object-fit:contain;"></div>
</template>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="getFirma('A').usuario_nombre"></h6>
</div>
</div>
</template>
<template x-if="!getFirma('A')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">ELABORÓ / ENCARGADO</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">Sin firma registrada</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>
</div>

<!-- Card B: VOBO -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-3">
<template x-if="getFirma('B')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="getFirma('B').tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>
<small class="text-dark" x-html="getFirma('B').firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="getFirma('B').usuario_nombre"></h6>
</div>
</div>
</template>
<template x-if="!getFirma('B')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">VO.BO.</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Vo.Bo.!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>
</div>

<!-- Card C: AUTORIZACIÓN -->
<div class="col-xl-4 col-lg-6 col-md-6 mb-3">
<template x-if="getFirma('C')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-5"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="getFirma('C').tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>
<small class="text-dark" x-html="getFirma('C').firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="getFirma('C').usuario_nombre"></h6>
</div>
</div>
</template>
<template x-if="!getFirma('C')">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-5"></i>
</div>
<div class="ms-3">
<h6 class="mb-0 text-white">AUTORIZACIÓN</h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Autorización!</h6>
</div>
<div class="card-footer bg-light text-center">
<small class="text-muted">Pendiente de firma electronica</small>
</div>
</div>
</template>
</div>

</div>
<hr>   
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>Nombre del documento</th>
<th class="text-center" width="48px"><i class="ti ti-download text-primary" title="Descargar"></i></th>
</tr>
</thead>
<tbody>
<template x-if="detalle.documentos && detalle.documentos.length > 0">
<template x-for="d in detalle.documentos" :key="d.id">
<tr>
<td x-text="d.nombre"></td>
<td class="text-center"><i class="ti ti-download pointer text-primary" @click.prevent="download('solicitud-cheque', d.documento)" title="Descargar"></i></td>
</tr>
</template>
</template>

<template x-if="!detalle.documentos || detalle.documentos.length === 0">
<tr><td colspan="2" class="text-center text-secondary">No se encontro información</td></tr>
</template>

</tbody>
</table>

</div>
</template>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">
Solicitud de Cheque (#<span x-text="comentarioSolicitudId"></span>)
</p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>

<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
style="min-height: 0; overscroll-behavior: contain;"
x-ref="chatContainer">

<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<div class="chat-list active-chat p-2">
<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-3"
:class="c.esMio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.esMio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-5"></i>
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

<!-- <template x-if="puedeAgregarComentarios"> -->
<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
rows="1"
placeholder="Escribe un comentario..."
style="resize:none;"
x-model="nuevoComentario"
@keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;"
type="button"
@click="agregarComentario()"
:disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario">
<i class="ti ti-send fs-5"></i>
</template>
<template x-if="guardandoComentario">
<span class="spinner-border spinner-border-sm"></span>
</template>
</button>
</div>
</div>
</div>
<!--</template>-->
</div>

<div class="modal fade" id="modalArchivos" tabindex="-1" data-bs-backdrop="static" x-ref="modalArchivos">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title">Documentos (#<span x-text="archivoSolicitudId"></span>)</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<!-- <template x-if="puedeAgregarDocumentos"> -->
<div class="row g-2">

<div class="col-12" x-ref="tipoDocumentoWrapper">
<label class="form-label">Tipo de documento:</label>
<select class="form-select" x-model="nuevoDocumento.tipo" x-ref="tipoDocumentoSelect">
<option value="">Selecciona una opción...</option>
<template x-for="t in documentTypes" :key="t">
<option x-text="t" :value="t"></option>
</template>
</select>
</div>

<div class="col-12">
<label class="form-label">Archivo:</label>
<input type="file" class="form-control" x-ref="nuevoDocumentoFile">
</div>

<div class="col-12 mt-3 mb-3 text-end">
<button class="btn btn-success" @click="subirDocumento()" :disabled="subiendoDocumento">Guardar</button>
</div>
</div>
<!-- </template> -->

<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>Tipo de documento</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-if="documentos.length > 0">
<template x-for="d in documentos" :key="d.id">
<tr>
<td x-text="d.nombre"></td>
<td><i class="ti ti-download pointer text-primary fs-5" @click.prevent="download('solicitud-cheque', d.documento)"></i></td>
<td><i class="ti ti-trash pointer text-danger fs-5" @click="eliminarDocumento(d.id)"></i></td>
</tr>
</template>
</template>

<template x-if="documentos.length === 0">
<tr><td colspan="3" class="text-center text-secondary">No se encontro información</td></tr>
</template>

</tbody>
</table>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalPagos" tabindex="-1" data-bs-backdrop="static" x-ref="modalPagos">
<div class="modal-dialog modal-md">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title">Comprobantes de Pago (#<span x-text="pagoSolicitudId"></span>)</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<!-- <template x-if="puedeGestionarPagos"> -->
<div class="row g-2">

<div class="col-12">
<label class="form-label">Archivo de pago:</label>
<input type="file" class="form-control" x-ref="nuevoPagoFile">
</div>

<div class="col-12 text-end mt-3 mb-3">
<button class="btn btn-success" @click="subirPago()" :disabled="subiendoPago">Guardar</button>
</div>

</div>
<!-- </template> -->

<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th>Fecha</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download text-primary fs-5"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-5"></i></th>
</tr>
</thead>
<tbody>
<template x-if="pagos.length > 0">
<template x-for="p in pagos" :key="p.id">
<tr>
<td x-text="p.fecha ? formatearFecha(p.fecha) : ''"></td>
<td class="text-center align-middle"><i class="ti ti-download pointer text-primary fs-5" @click.prevent="download('solicitud-cheque', p.documento)"></i></td>
<td class="text-center align-middle"><i class="ti ti-trash pointer text-danger fs-5" @click="eliminarPago(p.id)"></i></td>
</tr>
</template>
</template>

<template x-if="pagos.length === 0">
<tr><td colspan="3" class="text-center text-secondary">No se encontro información</td></tr>
</template>

</tbody>
</table>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

</div>
<?php endif; ?>