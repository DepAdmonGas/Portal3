<div id="container" class="mt-4 mb-5"
data-id-year="<?= $idYear ?>"
data-current-quincena="<?= $currentQuincena ?>"
data-current-week="<?= $currentWeek ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-id-usuario="<?= $idUsuario ?>"
data-year-mes-template="<?= htmlspecialchars($yearMesTemplate ?? '', ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...diaDobleComponent() }"
x-init="initSection()">

<!-- SECCION: SELECCION DE CARDS -->
<div class="mt-3" id="dd-section-cards">
<div class="row justify-content-center">

<div class="col-lg-4 col-md-6 col-12 mb-3">
<a href="javascript:void(0)" class="text-decoration-none" @click="showEstaciones()">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">
<div class="d-flex flex-row align-items-center">
<div class="icon-container-do">
<h3 class="text-white mb-0"><i class="ti ti-gas-station fs-9"></i></h3>
</div>
<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">Estaciones</h5>
</div>
<!-- 
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0"><i class="ti ti-arrow-right fs-8"></i></h4>
</div>
-->
</div>
</div>
</a>
</div>

<div class="col-lg-4 col-md-6 col-12 mb-3">
<a href="javascript:void(0)" class="text-decoration-none" @click="showDireccion()">
<div class="card body-container-do overflow-hidden card-hover shadow-sm">
<div class="d-flex flex-row align-items-center">
<div class="icon-container-do">
<h3 class="text-white mb-0"><i class="ti ti-briefcase fs-9"></i></h3>
</div>
<div class="p-4 flex-grow-1">
<h5 class="text-white mb-0">Dirección de Operaciones</h5>
</div>
<!-- 
<div class="align-self-center me-4 ms-auto text-end">
<h4 class="text-white mb-0"><i class="ti ti-arrow-right fs-8"></i></h4>
</div>
-->
</div>
</div>
</a>
</div>

</div>
</div>

<!---------- SECCION: ESTACIONES -->
<div id="dd-section-estaciones" style="display:none;">

<div class="row mb-3 align-items-end g-3">
    <!-- Columna Izquierda: Título y Selector -->
    <div class="col-md-6">
        <h4 class="fw-bold mb-2">Estaciones</h4>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm shadow-sm" id="dd-semana-selector" style="max-width: 220px;">
                <option value="">Selecciona una semana...</option>
                <?php foreach ($weeks as $w): ?>
                <option value="<?= $w['numero'] ?>" <?= $w['numero'] == $currentWeek ? 'selected' : '' ?>>
                    Semana <?= $w['numero'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Columna Derecha: Botones de Acción -->
    <div class="col-md-6">
        <div class="d-flex align-items-center justify-content-md-end gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center" @click="showCards()">
                <i class="ti ti-arrow-left me-1"></i> Regresar
            </button>
            <a href="javascript:void(0)" id="btn-dd-pdf-estaciones" class="btn btn-success d-inline-flex align-items-center">
                <i class="ti ti-download me-1"></i> Descargar PDF
            </a>
        </div>
    </div>
</div>


<div class="row">

<!---------- CARD DE ESTACIONES ---------->
<div id="dd-estaciones-content" class="col-12">
<div class="card">
<div class="card-header bg-primary text-white" id="dd-week-title">
<?= htmlspecialchars($weekTitle, ENT_QUOTES, 'UTF-8') ?>
</div>
<div class="card-body pb-3">
<div id="dd-estaciones-loading" class="text-center py-5" style="display:none;">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando información...</p>
</div>
<div>
<div class="datatables">
<div class="table-responsive overflow-x-auto" id="dd-estaciones-tables"></div>
</div>
</div>
</div>
</div>
</div>
</div>

</div>

<!-- SECCION: DIRECCION DE OPERACIONES -->
<div id="dd-section-direccion" style="display:none;">

<div class="row align-items-center">
<div class="col-12 col-md-7">
<h4 class="mb-0">Dirección de Operaciones</h4>
</div>

<div class="col-12 col-md-5 text-start text-md-end mt-2 mt-md-0">
<div class="d-inline-flex align-items-center gap-2">
<button type="button" class="btn btn-outline-danger d-inline-flex align-items-center" @click="showCards()">
<i class="ti ti-arrow-left me-1"></i> Regresar
</button>
 
<?php if ($puedeCrear): ?>
<button type="button" class="btn bg-primary-subtle text-primary d-inline-flex align-items-center" @click="crearReporte()" :disabled="creando">
<template x-if="!creando"><span class="d-inline-flex align-items-center"><i class="ti ti-plus me-1"></i> Nuevo</span></template>
<template x-if="creando"><span class="d-inline-flex align-items-center"><span class="spinner-border spinner-border-sm me-1" role="status"></span> Procesando...</span></template>
</button>
<?php endif; ?>
</div>
</div>
</div>

<div class="datatables">
<div class="table-responsive overflow-x-auto pb-4">
<table id="tabla-dia-doble" class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%">
<tbody></tbody>
</table>
</div>
</div>

</div>

<!-- MODAL DETALLE -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content" id="modalDetalleBody">
</div>
</div>
</div>

<!-- OFFCANVAS COMENTARIOS -->
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
Día Doble (#00<span x-text="comentarioReporteId"></span>)
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

<template x-if="comentarios.length === 0 && !cargandoComentarios">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<template x-if="cargandoComentarios">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 380px;">
<div class="spinner-border text-primary" role="status"></div>
<p class="text-muted mb-0 mt-2">Cargando comentarios...</p>
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
</div>

<!-- MODAL FIRMA -->
<div class="modal fade" id="modalFirma" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl">
<div class="modal-content" id="firmaModalBody">
</div>
</div>
</div>
</div>
