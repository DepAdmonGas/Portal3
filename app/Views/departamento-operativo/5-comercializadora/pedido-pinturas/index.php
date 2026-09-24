<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-es-encargado="<?= $esEncargado ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-pinturas"
x-data="{ ...actions(), ...pedidoPinturasComponent() }">

<div class="row">

<!---------- ENCABEZADO: BADGE PENDIENTES (IZQ) + ACCIONES (DER) ---------->
<div class="col-12 mb-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">

<div class="d-flex align-items-center gap-1">
<span id="pedidos-pendientes-badge" class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle fs-4"></i>
<span>Pendientes: <span id="pedidos-pendientes-total">0</span></span>
</span>
</div>

<div class="d-flex align-items-center">
<div class="ms-auto">

<template x-if="esMultiestacion">
<a class="btn bg-primary-subtle text-primary" href="/departamento-operativo/comercializadora/pedido-pinturas/catalogo" title="Catálogo">
<i class="ti ti-tools me-1"></i> Catálogo
</a>
</template>

<template x-if="!esMultiestacion && puedeAcceso && hayContexto">
<div class="dropdown dropcenter">
<button type="button" class="btn btn-light dropdown-toggle text-dark" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false" title="Opciones">
<i class="ti ti-dots-vertical fs-6"></i>
</button>
<ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
<li x-show="puedeCrear" x-cloak>
<a class="dropdown-item pointer" href="javascript:void(0)" @click="nuevoPedido()">
<i class="ti ti-plus me-1"></i> Nuevo
</a>
</li>
<li x-show="puedeEditar" x-cloak>
<a class="dropdown-item pointer" href="/departamento-operativo/comercializadora/pedido-pinturas/inventario">
<i class="ti ti-paint me-1"></i> Inventario
</a>
</li>
<li x-show="puedeAcceso" x-cloak>
<a class="dropdown-item pointer" href="/departamento-operativo/comercializadora/pedido-pinturas/reporte">
<i class="ti ti-report me-1"></i> Reporte
</a>
</li>
</ul>
</div>
</template>

</div>
</div>

</div>
</div>

<!---------- TABLA PEDIDOS ---------->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-pedidos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<!---------- MODAL DETALLE PEDIDO ---------->
<div class="modal fade" id="modalDetallePedido" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-eye "></i> Detalle del pedido de pinturas (<span x-text="pedidoActual.id ? '# 00' + pedidoActual.id : ''"></span>)</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">


<div class="row g-3 mb-3">

<template x-if="pedidoActual.nombre_estacion">
<div class="col-md-6"><div class="form-label mb-1">Estacion:</div>
<div x-text="pedidoActual.nombre_estacion"></div>
</div>
</template>

<div class="col-md-6">
<div class="form-label mb-1">Fecha y hora:</div>
<div x-text="pedidoActual.fecha_hora || 'Sin informacion'"></div>
</div>

<div class="col-md-6"><div class="form-label mb-1">Nombre del personal:</div>
<div x-text="pedidoActual.personal || 'Sin informacion'"></div>
</div>

<div class="col-md-6"><div class="form-label mb-1">Puesto:</div>
<div x-text="pedidoActual.puesto || 'Sin informacion'"></div>
</div>

</div>

<div class="table-responsive mb-3">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="96px">#</th>
<th class="text-center align-middle">Unidad</th>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Piezas</th>
<th class="text-center align-middle">¿Para qué?</th>
</tr>
</thead>
<tbody>
<template x-for="it in pedidoActual.detalle" :key="it.id">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.unidad"></td>
<td class="text-center align-middle" x-text="it.producto"></td>
<td class="text-center align-middle" x-text="it.piezas"></td>
<td class="text-center align-middle" x-text="it.para_que || '—'"></td>
</tr>
</template>
<tr x-show="!pedidoActual.detalle.length">
<td colspan="5" class="text-center text-primary">No se encontro informacion</td>
</tr>
</tbody>
</table>
</div>

<div class="row g-3 mb-3">
<div class="col-md-6">
<div class="mb-1 form-label">Observaciones:</div>
<div x-text="pedidoActual.observaciones || 'Sin observaciones'"></div>
</div>
</div>

<!---------- FIRMAS ---------->
<div class="mb-1 form-label">Firmas:</div>
<div class="row g-3">

<div class="col-md-6">
  <!-- YA TIENE FIRMA A -->
  <template x-if="pedidoActual.firmas && pedidoActual.firmas.A">
    <div class="card border h-100">
      <div class="card-header bg-primary text-white py-3 border-0">
        <div class="d-flex align-items-center">
          <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
            <i class="ti ti-user-check fs-6"></i>
          </div>
          <div class="ms-3 overflow-hidden">
            <h6 class="mb-0 text-white" x-text="pedidoActual.firmas.A.tipo_label"></h6>
          </div>
        </div>
      </div>
      <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
        <template x-if="pedidoActual.firmas.A.firma_img_url">
          <img :src="pedidoActual.firmas.A.firma_img_url" class="img-fluid" style="max-height:90px;object-fit:contain;">
        </template>
        <template x-if="!pedidoActual.firmas.A.firma_img_url">
          <i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
          <small class="text-dark" x-text="pedidoActual.firmas.A.firma_texto || ''"></small>
        </template>
      </div>
      <div class="card-footer bg-light text-center">
        <h6 class="mb-0 fw-semibold text-truncate" x-text="pedidoActual.firmas.A.usuario_nombre"></h6>
      </div>
    </div>
  </template>

  <!-- FALTA FIRMA A -->
  <template x-if="!pedidoActual.firmas || !pedidoActual.firmas.A">
    <div class="card border h-100">
      <div class="card-header bg-primary text-white py-3 border-0">
        <div class="d-flex align-items-center">
          <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
            <i class="ti ti-clock-hour-4 fs-6"></i>
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

      <!-- FOOTER AGREGADO CUANDO FALTA LA FIRMA -->
      <div class="card-footer bg-light text-center mt-auto border-top-0">
        <small class="text-muted">Pendiente de firma de elaboración</small>
      </div>
    </div>
  </template>
</div>

<div class="col-md-6">
<div class="card h-100 border">

<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<template x-if="pedidoActual.firmas && pedidoActual.firmas.B">
<i class="ti ti-circle-check fs-6"></i>
</template>
<template x-if="!pedidoActual.firmas || !pedidoActual.firmas.B">
<i class="ti ti-clock-hour-4 fs-6"></i>
</template>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="(pedidoActual.firmas && pedidoActual.firmas.B && pedidoActual.firmas.B.tipo_label) ? pedidoActual.firmas.B.tipo_label : 'VO.BO.'"></h6>
</div>
</div>
</div>

<!-- YA FIRMÓ -->
<template x-if="pedidoActual.firmas && pedidoActual.firmas.B">
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<h6 class="text-dark mb-1">El formato se firmó por un medio electrónico.</h6>
<h6 class="text-dark mb-0"><strong x-text="pedidoActual.firmas.B.fecha || ''"></strong></h6>
</div>
</template>

<!-- NO PUEDE FIRMAR -->
<template x-if="(!pedidoActual.firmas || !pedidoActual.firmas.B) && !esVoBo">
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Vo.Bo.!</h6>
</div>
</template>

<!-- Footer: Cuando YA FIRMÓ -->
<template x-if="pedidoActual.firmas && pedidoActual.firmas.B">
<div class="card-footer bg-light text-center mt-auto">
<div>
<h6 class="mb-0 fw-semibold text-truncate" x-text="pedidoActual.firmas.B.usuario_nombre"></h6>
</div>
</div>
</template>

<!-- CUERPO Y FOOTER: Cuando FALTA LA FIRMA -->
<template x-if="!pedidoActual.firmas || !pedidoActual.firmas.B">
<div class="d-flex flex-column h-100">
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
<h6 class="text-muted mb-0">¡Falta la firma de Vo.Bo.!</h6>
</div>
<div class="card-footer bg-light text-center mt-auto border-top-0">
<!-- Texto dinámico: si es firma A dice elaboración, si no, electrónica -->
<small class="text-muted" x-text="pedidoActual.firmas.B ? 'Pendiente de firma de elaboración' : 'Pendiente de firma electrónica'"></small>
</div>
</div>
</template>

</div>
</div>

</div>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button>
</div>

</div>
</div>
</div>

</div>