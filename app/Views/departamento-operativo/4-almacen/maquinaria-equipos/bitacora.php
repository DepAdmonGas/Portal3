<style>
/* El dropdown de acciones debe salir por encima del modal: los contenedores
   con scroll (.table-responsive / .modal-body) recortan el menú. */
#modalDiaBitacora .table-responsive { overflow: visible; }
#modalDiaBitacora .modal-body { overflow-y: visible; }
#modalDiaBitacora .dropdown-menu { z-index: 1080; }
</style>

<?php
/**
 * Bitácora de Maquinaria y Equipos — Almacén
 *
 * Layout: calendario FullCalendar (izquierda) + panel del día (derecha) y
 * modales de Detalle/Checklist/Firmas, Nuevo mantenimiento y Token.
 * Toda la lógica vive en Alpine (bitacora.calendario.js + bitacora.actions.js).
 * El calendario replica el diseño de /sasisopa/calendario, /sgm/calendario y
 * mantenimiento-preventivo/calendario (calender-sidebar + totales).
 *
 * @var string $title
 * @var int    $idEquipo
 * @var array  $equipo
 * @var string $moduleStationKey
 * @var bool   $esUsuarioEstacion
 */

$baseUrl = '/departamento-operativo/almacen/maquinaria-equipos-bitacora';
$rutaPdfBase = $baseUrl . '/pdf';
?>

<div id="container"
class="pb-4"
data-base-url="<?= $baseUrl ?>"
data-pdf-base="<?= $rutaPdfBase ?>"
data-id-equipo="<?= (int)$idEquipo ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-puede-crear="<?= !empty($puedeCrear) ? 'true' : 'false' ?>"
data-es-usuario-estacion="<?= !empty($esUsuarioEstacion) ? 'true' : 'false' ?>"
data-equipo="<?= htmlspecialchars(json_encode($equipo ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...bitacoraActions(), ...bitacoraCalendario() }"
x-init="initBitacora()">

<div class="row mt-3">

<div class="col-12">

<template x-if="esUsuarioEstacion">
<div class="row">
<div class="col-12">
<a class="btn bg-primary-subtle text-primary mb-3 float-end" :href="urlNuevo()">
<i class="ti ti-plus me-1"></i> Nuevo
</a>
</div>
</div>
</template>

<!------------ CARD INFORMACION ------------>
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-info-circle"></i> Informacion (Maquinaria / Equipo)</h4>
</div>

<div class="card-body">
<div class="row">
<div class="col-md-3">
<label class="form-label mb-1">Maquinaria:</label>
<div x-text="equipo.maquinaria || ''"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Descripción:</label>
<div x-text="equipo.descripcion || 'Equipo'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Marca:</label>
<div x-text="equipo.marca || 'Sin Información'"></div>
</div>

<div class="col-md-3">
<label class="form-label mb-1">Modelo:</label>
<div x-text="equipo.modelo || 'Sin Información'"></div>
</div>

</div>
</div>

</div>

</div>


<!------------ CARD CALENDARIO ------------>
<div class="col-12">
<div class="calender-sidebar app-calendar mt-3">

<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-calendar"></i> Calendario</h4>
</div>

<div class="card-body">

<div class="d-flex justify-content-between align-items-center">

<!-- Totales (Lado Izquierdo) -->
<div class="d-flex align-items-center">
<div class="border-end pe-3">
<h6 class="text-muted fw-normal mb-1">Pendientes</h6>
<b class="text-danger fs-5" x-text="totales.pendientes"></b>
</div>
<div class="ms-5 border-end pe-3">
<h6 class="text-muted fw-normal mb-1">Finalizados</h6>
<b class="text-success fs-5" x-text="totales.finalizados"></b>
</div>
<div class="ms-5">
<h6 class="text-muted fw-normal mb-1">Total</h6>
<b class="fs-5" x-text="totales.total"></b>
</div>
</div>

</div>

<div class="text-capitalize" id="calendar"></div>
</div>
</div>
</div>

</div>
</div>

<!-- ================= MODAL: REGISTROS DEL DÍA ================= -->

<div class="modal fade" id="modalDiaBitacora" tabindex="-1" aria-labelledby="modalDiaLabel" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-calendar-month fs-7 me-1"></i>
<h4 class="modal-title text-white" id="modalDiaLabel" x-text="fechaSeleccionada"></h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="table-responsive">
<table class="table table-striped table-bordered mt-1 mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Folio</th>
<th class="text-center align-middle">Frecuencia</th>
<th class="text-center align-middle">Tipo</th>
<th class="text-center align-middle">Encargado</th>
<th class="text-center align-middle"><i class="ti ti-writing fs-7"></i></th>
<th class="text-center align-middle"><i class="ti ti-message fs-7"></i></th>
<th class="text-center align-middle">Estatus</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th>
</tr>
</thead>
<tbody>

<!-- Registros -->
<template x-for="(item, index) in actividadesDia" :key="item.id">
<tr>
<td class="text-center align-middle"><strong x-text="'#' + item.orden_label"></strong></td>
<td class="text-center align-middle"><span x-text="item.frecuencia_label"></span></td>

<td class="text-center align-middle">
<span class="badge text-capitalize" :class="item.tipo_mantenimiento === 1 ? 'bg-primary' : 'bg-warning text-dark'" x-text="item.tipo_label"></span>
</td>

<td class="text-center align-middle" x-text="item.creador_nombre"></td>

<!-- Firma: misma etapa del flujo A→B→C que Solicitud de Cheques; lleva a la
     pantalla del registro en modo firma (como el legacy, pantalla completa). -->
<td class="text-center align-middle">
<a class="firma-link" :href="urlFirma(item.id)"
:title="'Firmas: ' + (item.firma_a ? 'A' : '') + (item.firma_b ? ' B' : '') + (item.firma_c ? ' C' : '')">
<i class="ti ti-writing fs-7" :class="item.estatus === 2 ? 'text-success' : (item.firma_a ? 'text-primary' : 'text-dark')"></i>
</a>
</td>

<!-- Comentarios: offcanvas tipo chat (patrón de solicitud-cheque) -->
<td class="text-center align-middle position-relative">
<a href="javascript:void(0)" class="btn-comentarios position-relative d-inline-flex align-items-center justify-content-center"
@click="abrirComentarios(item)">
<i class="ti ti-message fs-7"></i>
<span class="badge-historico position-absolute top-0 start-100 translate-middle" x-show="item.comentarios">
<span x-text="item.comentarios"></span>
</span>
</a>
</td>

<td class="text-center align-middle">
<span class="badge" :class="claseEstatus(item.estatus)" x-text="item.estatus_label"></span>
<span class="badge bg-warning text-dark ms-1" x-show="!item.secuencia_ok" title="Debes finalizar el registro anterior">
<i class="ti ti-lock"></i>
</span>
</td>

<td class="text-center align-middle">
<div class="dropdown dropstart">
<a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones"><i class="ti ti-dots-vertical fs-6"></i></a>
<div class="dropdown-menu">

<a class="dropdown-item pointer" :href="urlDetalle(item.id)">
<i class="ti ti-eye me-1"></i> Detalle
</a>

<a class="dropdown-item pointer" :class="item.acciones.mantenimiento ? '' : ' disabled'"
:href="item.acciones.mantenimiento ? urlMantenimiento(item.id) : 'javascript:void(0)'">
<i class="ti ti-clipboard-list me-1"></i> Mantenimiento
</a>

<a class="dropdown-item pointer" :class="item.acciones.editar ? '' : ' disabled'"
@click="item.acciones.editar && abrirEditar(item)">
<i class="ti ti-pencil me-1"></i> Editar
</a>

<a class="dropdown-item pointer" @click="abrirEvidencias(item)">
<i class="ti ti-paperclip me-1"></i> Evidencias
</a>

<a class="dropdown-item pointer" :class="item.acciones.pdf ? '' : ' disabled'"
:href="item.acciones.pdf ? (pdfBase + '/registro/' + item.id) : 'javascript:void(0)'"
:target="item.acciones.pdf ? '_blank' : '_self'">
<i class="ti ti-file-type-pdf me-1"></i> Descargar PDF
</a>

<a class="dropdown-item pointer" :class="item.acciones.pdf_general ? '' : ' disabled'"
:href="item.acciones.pdf_general ? (pdfBase + '/general/' + item.id_mantenimiento) : 'javascript:void(0)'"
:target="item.acciones.pdf_general ? '_blank' : '_self'">
<i class="ti ti-files me-1"></i> Descargar PDF general
</a>

<a class="dropdown-item pointer" :class="item.acciones.eliminar ? '' : ' disabled'"
@click="item.acciones.eliminar && eliminarRegistro(item)">
<i class="ti ti-trash me-1"></i> Eliminar
</a>

</div>
</div>
</td>
</tr>
</template>

<!-- Sin registros -->
<template x-if="actividadesDia.length === 0">
<tr>
<td colspan="8" class="text-center text-muted py-5">
No existen registros para este día.
<div class="mt-2" x-show="esUsuarioEstacion">
<a class="btn btn-sm bg-primary-subtle text-primary" :href="urlNuevo()">
<i class="ti ti-plus me-1"></i> Crear mantenimiento
</a>
</div>
</td>
</tr>
</template>

</tbody>
</table>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
</div>

</div>
</div>
</div>

<!-- ================= MODAL: EVIDENCIAS ================= -->
<div class="modal fade" id="modalEvidenciasBitacora" tabindex="-1" aria-labelledby="modalEvidenciasLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-paperclip fs-7 me-1"></i>
<h4 class="modal-title text-white" id="modalEvidenciasLabel">
Evidencias (#0<span x-text="evidenciaRegistroLabel"></span>)
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="col-12">
    <label class="form-label mb-1">* Evidencia:</label>
    <div class="input-group">
        <input type="file" class="form-control" id="inputEvidenciaModal"
        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
        <button type="button" class="btn btn-success" @click="subirEvidenciaModal()" :disabled="subiendo">
            <i class="ti ti-check me-1"></i> Guardar
        </button>
    </div>
</div>


<div class="row">
<template x-for="e in evidencias" :key="e.id">
<div class="col-md-6 mt-4">
    <div class="card h-100 shadow-sm border bg-light position-relative">
        
        <!-- Badge redondo en la esquina superior derecha -->
        <div class="position-absolute top-0 end-0 p-2" style="z-index: 2;">
            <span class="badge bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center p-2" style="width: 32px; height: 32px;">
                <i :class="(e.es_pdf == 1 || e.es_pdf === true) ? 'ti ti-file-type-pdf text-danger' : 'ti ti-photo text-primary'" class="fs-6"></i>
            </span>
        </div>

        <!-- Vista previa exclusiva: Imagen o PDF dentro del rectángulo de 140px -->
        <a :href="e.url + '&view=1'" target="_blank" class="text-decoration-none overflow-hidden d-block position-relative" style="height: 140px;">
            <!-- Si NO es PDF, muestra la imagen -->
            <img x-show="!(e.es_pdf == 1 || e.es_pdf === true)" :src="e.url + '&view=1'" class="w-100 h-100" style="object-fit: cover;" alt="">
            
            <!-- Si SÍ es PDF, muestra el bloque de PDF -->
            <div x-show="e.es_pdf == 1 || e.es_pdf === true" class="text-center p-4 bg-white d-flex align-items-center justify-content-center h-100 w-100">
                <i class="ti ti-file-type-pdf text-danger display-4"></i>
            </div>
        </a>

        <!-- Cuerpo de la tarjeta -->
        <div class="card-body border-top pb-2 bg-white">
            <div>
                <a :href="e.url + '&view=1'" target="_blank" class="text-dark text-decoration-none">
                    <p class="small fw-bold text-truncate mb-0" :title="e.nombre_original" x-text="e.nombre_original"></p>
                </a>
                <span class="small text-muted d-block text-truncate">
                    <i class="ti ti-user me-1"></i><span x-text="e.subido_por_nombre"></span>
                </span>
            </div>
        </div>

        <!-- Pie de tarjeta con botones de acción al 50% -->
        <div class="card-footer bg-white">
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-1" :href="e.url" target="_blank" title="Descargar">
                    <i class="ti ti-download"></i> <span class="">Descargar</span>
                </a>
                
                <button class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-1" @click.prevent="eliminarEvidencia(e)" title="Eliminar">
                    <i class="ti ti-trash"></i> <span class="">Eliminar</span>
                </button>
            </div>
        </div>

    </div>
</div>
</template>

<div class="col-12 text-center py-5" x-show="evidencias.length === 0">
<small class="text-muted">No se han subido evidencias</small>
</div>
</div>

</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
<button type="button" class="btn bg-primary text-white" @click="regresarAlDia()">
<i class="ti ti-arrow-left me-1"></i> Regresar
</button>
</div>

</div>
</div>
</div>

<!-- ================= MODAL: EDITAR (costo) ================= -->
<div class="modal fade" id="modalEditarBitacora" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary text-white">
<i class="ti ti-pencil fs-7 me-1"></i>
<h4 class="modal-title text-white" id="modalEditarLabel">
Editar costo (#0<span x-text="editarRegistroLabel"></span>)
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="alert alert-warning py-2" x-show="editarReg.estatus === 2">
<i class="ti ti-lock me-1"></i> Este registro está Finalizado; no se puede modificar su costo.
</div>
<div class="alert alert-warning py-2" x-show="editarReg.estatus !== 2 && !editarReg.es_elaborador">
<i class="ti ti-lock me-1"></i> Solo el usuario que elaboró el registro puede editarlo.
</div>
<div class="alert alert-warning py-2" x-show="editarReg.estatus !== 2 && editarReg.es_elaborador && !editarReg.secuencia_ok">
<i class="ti ti-lock me-1"></i> Debes finalizar el registro anterior de esta solicitud antes de editar este.
</div>
<div class="alert alert-secondary py-2" x-show="editarReg.estatus !== 2 && editarReg.es_elaborador && editarReg.secuencia_ok && editarReg.bloqueado">
<i class="ti ti-lock me-1"></i> Este registro ya cuenta con la firma de quien elabora; ya no se puede modificar su costo.
</div>

<div x-show="editarReg.puede_editar_costo">
<div class="mb-1 form-label">* Costo del mantenimiento:</div>
<input type="number" step="0.01" min="0" class="form-control" x-model="costoEdicion">
</div>

</div>

<div class="modal-footer" x-show="editarReg.puede_editar_costo">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
<i class="ti ti-x me-1"></i> Cerrar
</button>
<button type="button" class="btn bg-primary text-white" @click="regresarAlDia()">
<i class="ti ti-arrow-left me-1"></i> Regresar
</button>
<button type="button" class="btn btn-success" @click="guardarCostoModal()" :disabled="guardando">
<span class="spinner-border spinner-border-sm me-1" x-show="guardando"></span>
<i class="ti ti-check me-1" x-show="!guardando"></i> Guardar
</button>
</div>

</div>
</div>
</div>

<!-- ================= OFFCANVAS: COMENTARIOS (patrón solicitud-cheque) ================= -->
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentariosBitacora" style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">
Registro (No. <span x-text="comentarioRegistroLabel"></span>)
</p>
</div>
</div>
<div class="d-flex align-items-center gap-2">
<button type="button" class="btn btn-sm bg-white text-primary" @click="regresarAlDia()">
<i class="ti ti-arrow-left me-1"></i> Regresar
</button>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>
</div>

<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;">

<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<div class="chat-list active-chat p-2">
<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-3" :class="c.esMio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.esMio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-5"></i>
</div>
</div>
<div>
<h6 class="fw-semibold mb-1" x-text="c.nombre_usuario || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_label || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.esMio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_label || ''"></div>
<div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</template>
</div>
</template>
</div>
</div>
</div>
</div>

<div class="px-3 py-3 border-top bg-white flex-shrink-0">
<div class="d-flex align-items-center gap-2">
<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1"
placeholder="Escribe un comentario..." style="resize:none;"
x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;" type="button"
@click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>

</div>
