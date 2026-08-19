<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-acceso="<?= $puedeAcceso ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-puede-firmar="<?= $puedeFirmar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-nombre-puesto="<?= $nombrePuesto ?>"
data-module-station-key="formatos"
x-data="{ ...actions(), ...formatosComponent() }">

<div class="row">

<!---------- ENCABEZADO: BADGE PENDIENTES (IZQ) + FORMATOS (DER) ---------->
<div class="col-12 mb-3">
<div id="fmt-tools-header" class="d-flex align-items-center justify-content-between mb-2 gap-2 mb-3">

<div id="fmt-pending-wrapper" class="d-flex align-items-center gap-1">
<span id="formatos-pendientes-badge" class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
<i class="ti ti-alert-circle fs-4"></i>
<span>Pendientes: <span id="formatos-pendientes-total">0</span></span>
</span>
</div>

<div id="fmt-tools-anchor" class="d-flex">
<template x-if="puedeAcceso && hayContexto">
<div class="dropdown d-inline-block" id="formatos-dropdown-wrap">
<button id="formatos-dropdown-btn" type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-tools me-1"></i> Formatos
</button>
<ul class="dropdown-menu dropdown-menu-end">
<li><h6 class="dropdown-header">Nuevo formato</h6></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(1)"><i class="ti ti-plus me-1"></i> 1. Alta de personal</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(2)"><i class="ti ti-plus me-1"></i> 2. Baja de personal</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(3)"><i class="ti ti-plus me-1"></i> 3. Falta de personal</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(4)"><i class="ti ti-plus me-1"></i> 4. Reestructuración de personal</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(5)"><i class="ti ti-plus me-1"></i> 5. Ajuste Salarial</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(6)"><i class="ti ti-plus me-1"></i> 6. Formato de Vacaciones</a></li>
<li><a class="dropdown-item pointer" @click="abrirFormulario(7)"><i class="ti ti-plus me-1"></i> 7. Solicitud de Prima Vacacional</a></li>
<li><hr class="dropdown-divider"></li>
<li><h6 class="dropdown-header">Descargar plantillas</h6></li>
<li><a class="dropdown-item pointer" href="/download?tipo=lista-formatos&amp;file=RH-REN-VOL-08.docx"><i class="ti ti-file-text me-1"></i> Formato de renuncia voluntaria</a></li>
</ul>
</div>
</template>
</div>

</div>
</div>

<!---------- TABLA DE FORMATOS ---------->
<div class="col-12">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-formatos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<!---------- MODAL DETALLE ---------->
<div class="modal fade" id="modalDetalleFormato" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-xl">
<div class="modal-content">
<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2" id="modalDetalleFormatoTitle">Detalle del formato</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body" id="modalDetalleFormatoBody"></div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cerrar</button>
</div>
</div>
</div>
</div>

<!---------- MODAL VER ARCHIVO / EVIDENCIA ---------->
<div class="modal fade" id="modalVerArchivo" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-xl">
<div class="modal-content">
<div class="modal-header bg-light">
<h5 class="modal-title" id="modalVerArchivoTitle">Archivo</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
<span id="modalVerArchivoNombre" class="text-muted small text-truncate"></span>
<button type="button" id="modalVerArchivoDescargar" class="btn btn-sm btn-outline-primary">
<i class="ti ti-download me-1"></i> Descargar
</button>
</div>
<iframe id="modalVerArchivoFrame" src="" style="width:100%;height:70vh;border:1px solid #dee2e6;border-radius:.375rem;background:#f8f9fa;"></iframe>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>
</div>
</div>
</div>

<!---------- MODAL COMENTARIOS ---------->
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
<p class="mb-0 text-white opacity-75"><span x-text="comentarioFormatoNombre"></span></p>
</div>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
</div>

<div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
<div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
<div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height: 0; overscroll-behavior: contain;" x-ref="chatContainer">

<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<div class="chat-list active-chat p-2">
<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-3" :class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">
<template x-if="!c.esPropio">
<div class="d-flex gap-3 align-items-start">
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-5"></i>
</div>
</div>
<div>
<h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
</div>
</div>
</template>
<template x-if="c.esPropio">
<div class="d-flex flex-column align-items-end">
<div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_hora || ''"></div>
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
<textarea class="form-control border-0 bg-light rounded-pill"
rows="1" placeholder="Escribe un comentario..."
style="resize:none;"
x-model="nuevoComentario"
@keydown.enter.prevent="agregarComentario()"></textarea>
</div>
<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:44px; height:44px;" type="button"
@click="agregarComentario()"
:disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>

</div>
