<div id="container" class="mt-4 mb-5"
data-solicitud='<?= htmlspecialchars(json_encode($solicitud, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-puede-firmar="<?= $puedeFirmarVoBo ? 'true' : 'false' ?>"
data-es-encargado="<?= $esEncargado ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-module-station-key="pedido-aditivo"
x-data="{ ...actions(), ...pedidoAditivoPedidoComponent() }">

<div class="row">

<!---------- BOTÓN FINALIZAR / REGRESAR ---------->
<div class="col-12 mb-3 d-flex justify-content-end">
    <template x-if="solicitudActual.status === 0" x-cloak>
        <button type="button" class="btn btn-success" @click="finalizarSolicitud()" :disabled="finalizando">
            <i class="ti ti-check me-1"></i> Finalizar
        </button>
    </template>
</div>

<!---------- ENCABEZADO ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-1"></i> DETALLE ORDEN DE COMPRA</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-3"><div class="form-label mb-1">Folio:</div><div class="" x-text="solicitudActual.id ? '# 00' + solicitudActual.id : '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Orden de compra:</div><div class="" x-text="solicitudActual.orden_compra || '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Nombre del personal:</div><div class="" x-text="solicitudActual.personal || '—'"></div></div>
<div class="col-md-3"><div class="form-label mb-1">Puesto:</div><div class="" x-text="solicitudActual.puesto || '—'"></div></div>
</div>
</div>
</div>
</div>

<!---------- DATOS EDITABLES (status 0) ---------->
<div class="col-12 mb-3" x-show="solicitudActual.status === 0" x-cloak>
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-settings me-1"></i> DATOS DE LA SOLICITUD</h5>
</div>

<div class="card-body">
<div class="row g-3">
<div class="col-md-4">
<div class="mb-1 form-label">Fecha:</div>
<div class="fw-semibold" x-text="solicitudActual.fecha || 'Sin información'"></div>
</div>
<div class="col-md-4">
<div class="mb-1 form-label">Para:</div>
<div class="fw-semibold" x-text="solicitudActual.para || 'Sin información'"></div>
</div>
<div class="col-md-4">
<div class="mb-1 form-label">Fecha de entrega:</div>
<input type="date" class="form-control" x-model="form.fecha_entrega" @change="guardarDatos()">
</div>
<div class="col-md-12">
<div class="mb-1 form-label">Comentarios:</div>
<textarea class="form-control" rows="5" x-model="form.comentarios" placeholder="Ingresa aqui tus comentarios..." @change="guardarDatos()"></textarea>
</div>
</div>
</div>
</div>
</div>

<!---------- ESTATUS (status > 0) ---------->
<div class="col-12 mb-3" x-show="solicitudActual.status > 0" x-cloak>
<div class="card">
<div class="card-body d-flex align-items-center gap-2 flex-wrap">
<span class="form-label mb-0">Estatus:</span>
<span class="badge fs-6" :class="statusBadgeClass(solicitudActual.status)" x-text="solicitudActual.status_label"></span>
<span class="text-secondary small ms-2" x-show="solicitudActual.status === 1">Solicitud finalizada, en espera de la firma de Autorización.</span>
<span class="text-secondary small ms-2" x-show="solicitudActual.status === 2">Solicitud autorizada.</span>
</div>
</div>
</div>

<!---------- TAMBOS ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-flask me-1"></i> TAMBOS</h5>

<div class="d-flex justify-content-end">
<template x-if="puedeEditar && solicitudActual.status === 0" x-cloak>
<button type="button" class="btn bg-success text-white" @click="modalAgregarTambo()">
<i class="ti ti-plus me-1"></i> Nuevo tambo
</button>
</template>
</div>
</div>

<div class="card-body p-0">
<div class="table-responsive mb-0">
<table class="table table-bordered table-striped align-middle text-nowrap mb-0">
<thead>
<tr>
<th class="text-center align-middle" width="96px">#</th>
<th class="text-center align-middle">Cantidad de tambores</th>
<th class="text-center align-middle">Nombre del producto</th>
<th class="text-center align-middle">Nombre del aditivo</th>
<th class="text-center align-middle">Kilogramo por tambor</th>
<th class="text-center align-middle">Total de kilos</th>
<th class="text-center align-middle" width="48px" x-show="solicitudActual.status === 0"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="it in solicitudActual.tambos" :key="it.id">
<template x-if="solicitudActual.status === 0">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="p-0">
<input type="number" min="1" class="form-control form-control border-0 p-3 text-center align-middle" :value="it.cantidad" @change="editarTamboCantidad(it.id, $event.target.value)">
</td>
<td class="p-0">
<select class="form-control form-control border-0 p-3 text-center align-middle" x-model="it.producto" @change="cambiarProducto(it)">
<option value="GASOLINA">GASOLINA</option>
<option value="DIESEL">DIESEL</option>
</select>
</td>
<td class="p-0 text-center align-middle" x-text="it.aditivo"></td>
<td class="p-0 text-center align-middle" x-text="it.kilogramo"></td>
<td class="text-center align-middle" x-text="it.cantidad * it.kilogramo"></td>
<td class="text-center">
<i class="ti ti-trash text-danger pointer fs-6" @click="eliminarTambo(it.id)"></i>
</td>
</tr>
</template>
<template x-if="solicitudActual.status > 0">
<tr>
<td class="text-center align-middle" x-text="it.num"></td>
<td class="text-center align-middle" x-text="it.cantidad"></td>
<td class="text-center align-middle" x-text="it.producto"></td>
<td class="text-center align-middle" x-text="it.aditivo"></td>
<td class="text-center align-middle" x-text="it.kilogramo"></td>
<td class="text-center align-middle" x-text="it.cantidad * it.kilogramo"></td>
</tr>
</template>
</template>
<tr x-show="!solicitudActual.tambos.length">
<td colspan="7" class="text-center text-primary">No se encontró información</td>
</tr>
</tbody>
<tfoot x-show="solicitudActual.tambos.length">
<tr class="table-dark">
<td colspan="1" class="text-center align-middle fw-semibold text-white">Totales</td>
<td class="text-center align-middle fw-semibold text-white" x-text="solicitudActual.total_tambos"></td>
<td colspan="2"></td>
<td class="text-center align-middle fw-semibold text-white" x-text="solicitudActual.tambos.reduce((acc, it) => acc + Number(it.kilogramo || 0), 0)"></td>
<td class="text-center align-middle fw-semibold text-white" x-text="solicitudActual.total_kilogramos"></td>
<td x-show="solicitudActual.status === 0"></td>
</tr>
</tfoot>
</table>
</div>
</div>

</div>
</div>

<!---------- FIRMAS REGISTRADAS (status > 0) ---------->
<div class="col-12 mt-4" x-show="solicitudActual.status > 0" x-cloak>
<div class="row g-3">

<div class="col-12">
<template x-if="solicitudActual.firmas && solicitudActual.firmas.B">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-user-check fs-6"></i>
</div>
<div class="ms-3 overflow-hidden">
<h6 class="mb-0 text-white" x-text="solicitudActual.firmas.B.tipo_label"></h6>
</div>
</div>
</div>
<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
<small class="text-dark" x-text="solicitudActual.firmas.B.firma_texto || ''"></small>
</div>
<div class="card-footer bg-light text-center">
<h6 class="mb-0 fw-semibold text-truncate" x-text="solicitudActual.firmas.B.usuario_nombre"></h6>
<small class="text-muted" x-text="solicitudActual.firmas.B.fecha || ''"></small>
</div>
</div>
</template>
<template x-if="!solicitudActual.firmas || !solicitudActual.firmas.B">
<div class="card border h-100">
<div class="card-header bg-primary text-white py-3 border-0">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
<i class="ti ti-clock-hour-4 fs-6"></i>
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
<small class="text-muted">Pendiente de firma electrónica</small>
</div>
</div>
</template>
</div>

</div>
</div>

</div>

<!---------- MODAL AGREGAR TAMBO (solo status 0) ---------->
<div class="modal fade" id="modalAgregarTambo" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white"><i class="ti ti-flask me-1"></i> Nuevo tambo a la solicitud</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="row g-3">

<div class="col-12">
<div class="mb-1 form-label">* Producto:</div>
<select class="form-select" x-model="nuevoTambo.producto">
<option value="">Selecciona una opción...</option>
<option value="GASOLINA">GASOLINA</option>
<option value="DIESEL">DIESEL</option>
</select>
</div>

<div class="col-12">
<div class="mb-1 form-label">* Cantidad de tambos:</div>
<input type="number" min="1" class="form-control" x-model.number="nuevoTambo.cantidad">
</div>

</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="agregarTambo()" :disabled="guardandoTambo"><i class="ti ti-check me-1"></i> Guardar</button>
</div>
</div>
</div>
</div>

</div>