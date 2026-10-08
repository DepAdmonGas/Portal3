<style>
/* Elimina el fondo y borde del contenedor principal de Select2 dentro de esta píldora */
.d-inline-flex .select2-container--bootstrap-5 .select2-selection,
.d-inline-flex .select2-container .select2-selection {
background-color: transparent !important;
border: none !important;
box-shadow: none !important;
}

/* Asegura que el contenedor de Select2 ocupe el espacio flexible dentro de la píldora */
.d-inline-flex .select2-container {
flex-grow: 1;
width: auto !important;
}
</style>

<div id="container" class="mt-3 mb-3"
data-id-year="<?= $idYear ?>"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-module-station-key="<?= $moduleStationKey ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-es-mexdesa="<?= $esMexdesa ? 'true' : 'false' ?>"
data-es-director="<?= $esDirector ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...recibosNominaComponent() }">

<!-- MENSAJE SI NO HAY ESTACIÓN SELECCIONADA -->
<template x-if="!idEstacionActual">
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Selecciona una estación o departamento del menú superior.
</div>
</template>


<div x-show="idEstacionActual" style="display: none;">

<div class="row align-items-center mb-3">

<!---------- COLUMNA DEL SELECT (Ocupa 100% en móvil y 50% en escritorio) ---------->
<div class="col-10 mb-2 mb-md-0">
<!-- Contenedor en forma de píldora que abarca todo el ancho de su columna -->
<div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 px-md-4 py-2 gap-1 w-75">
<!-- Etiqueta con ícono y texto dinámico (Semana / Quincena) -->
<span class="text-muted fw-semibold text-nowrap d-flex align-items-center gap-1">
<i class="ti ti-filter fs-4 text-primary"></i>
<span x-text="(esSemanal ? 'Semana:' : 'Quincena:')"></span>
</span>

<select id="select-periodo" class="form-select form-select-sm border-0 bg-transparent fw-semibold w-auto flex-grow-1"></select>
</div>
</div>

<!---------- COLUMNA DEL DROPDOWN DE ACCIONES ---------->
<div class="col-2 text-md-end d-flex align-items-center justify-content-end">
<div class="dropdown">
<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="ti ti-dots-vertical fs-4"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end shadow-sm">
<li x-show="puedeCrear">
<a class="dropdown-item pointer" href="javascript:void(0)" @click="abrirModalAgregarPersonal()">
<i class="ti ti-user-plus me-1"></i> Nuevo personal
</a>
</li>
<li>
<a class="dropdown-item pointer" href="javascript:void(0)" @click="abrirModalAcusePeriodo()">
<i class="ti ti-file-upload me-1"></i> Acuse de Nómina
</a>
</li>
<li x-show="esMexdesa">
<a class="dropdown-item pointer" href="javascript:void(0)" @click="abrirModalMexdesa()">
<i class="ti ti-upload me-1"></i> Subir Recibos de Nómina
</a>
</li>
<li x-show="esMexdesa && resumenInfo && resumenInfo.es_ultimo_periodo">
<a class="dropdown-item pointer" href="javascript:void(0)" @click="abrirModalAguinaldo()">
<i class="ti ti-gift me-1"></i> Subir Recibos de Nómina (Aguinaldos)
</a>
</li>

<template x-if="esDirector">
<li>
<hr class="dropdown-divider">
</li>
</template>
<template x-if="esDirector">
<li>
<h6 class="dropdown-header">Dirección de Operaciones</h6>
</li>
</template>

<li x-show="esDirector">
<a class="dropdown-item" :href="'/departamento-operativo/recursos-humanos/recibos-nomina-revision/' + idYear + '/' + (new Date().getMonth() + 1)">
<i class="ti ti-eye me-2"></i>Revisión
</a>
</li>
<li x-show="esDirector">
<a class="dropdown-item" :href="'/departamento-operativo/recursos-humanos/recibos-nomina-evaluacion/' + idYear + '/' + (new Date().getMonth() + 1)">
<i class="ti ti-chart-line me-2"></i>Evaluación (KPI's)
</a>
</li>
</ul>
</div>
</div>

</div>


<!---------- TABLA DE LOS RECIBOS DE NOMINA "SEMANAL / QUINCENAL" ---------->
<div class="row g-3">

<div class="col-12">
<div class="card">

<div class="card-header bg-primary d-flex justify-content-between align-items-center">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-calendar-event text-white fs-6"></i>
<h5 class="mb-0 text-white d-flex flex-column" x-show="periodoActualInfo">
<span x-text="(esSemanal ? 'Semana ' : 'Quincena ') + (periodoActualInfo ? periodoActualInfo.numero : '')"></span>
<small class="text-white mt-1" x-text="periodoActualInfo ? (' del ' + periodoActualInfo.inicio + ' al ' + periodoActualInfo.fin) : ''"></small>
</h5>
</div>
</div>

<div class="card-body">

<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
        
<!-- ESTADO DE LA ACTIVIDAD (Lado Izquierdo) -->
<template x-if="resumenInfo">
<div class="d-flex align-items-center gap-2 flex-wrap">
<!-- La actividad ya fue finalizada por la estación -->
<template x-if="resumenInfo.finalizado_estacion">
<span class="badge rounded-pill bg-success py-2 px-3">La actividad fue finalizada.</span>
</template>

<!-- Falta que MEXDESA suba y finalice sus recibos (solo multiestacion) -->
<template x-if="!resumenInfo.finalizado_estacion && !resumenInfo.puede_finalizar_estacion && resumenInfo.finalizado_mexdesa === false">
<span class="badge rounded-pill bg-danger text-white py-2 px-3">Aún no se han sido subidos los recibos de nómina.</span>
</template>

<!-- Todo completo y el usuario pertenece a esta estación/departamento: el botón queda habilitado -->
<template x-if="!resumenInfo.finalizado_estacion && resumenInfo.puede_finalizar_estacion && resumenInfo.es_localidad_propia">
<button type="button" class="btn btn-success d-flex align-items-center gap-1" @click="finalizarActividad()">
<i class="ti ti-check fs-5"></i> Finalizar actividad
</button>
</template>

<!-- Falta información en los registros del periodo -->
<template x-if="!resumenInfo.finalizado_estacion && !resumenInfo.puede_finalizar_estacion && resumenInfo.finalizado_mexdesa !== false">
<span class="badge rounded-pill bg-warning text-dark py-2 px-3">No es posible finalizar la actividad, se debe de agregar toda la información.</span>
</template>
</div>
</template>

<!-- RECIBOS DE NÓMINA (Lado Derecho) -->
<div class="d-flex align-items-center gap-2 flex-wrap">

<!-- Último período: dropdown con recibos de nómina y de aguinaldo (paridad legacy) -->
<div class="dropdown" style="display: none;" x-show="resumenInfo && resumenInfo.es_ultimo_periodo && puedeDescargar">
<button class="btn btn-success dropdown-toggle" type="button" id="dropdownDescargasNomina" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-download me-1"></i> Descargar información
</button>
<ul class="dropdown-menu dropdown-menu-end shadow-sm">
<li>
<a class="dropdown-item" :class="resumenInfo && resumenInfo.doc_nomina_acuse ? 'pointer' : 'disabled'" :href="resumenInfo && resumenInfo.doc_nomina_acuse ? '/download?tipo=recibos-mexdesa&file=' + encodeURIComponent(resumenInfo.doc_nomina_acuse) : null" :aria-disabled="!(resumenInfo && resumenInfo.doc_nomina_acuse)">
<i class="ti ti-file-text me-1"></i> Descargar Recibos de Nómina del Personal
</a>
</li>
<li>
<a class="dropdown-item" :class="resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.doc ? 'pointer' : 'disabled'" :href="resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.doc ? '/download?tipo=recibos-mexdesa&file=' + encodeURIComponent(resumenInfo.aguinaldo.doc) : null" :aria-disabled="!(resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.doc)">
<i class="ti ti-gift me-1"></i> Descargar Recibos de Aguinaldo del Personal
</a>
</li>
</ul>
</div>

<!-- Períodos que no son el último: botón directo de recibos de nómina -->
<template x-if="resumenInfo && !resumenInfo.es_ultimo_periodo && resumenInfo.doc_nomina_acuse && puedeDescargar">
<a :href="'/download?tipo=recibos-mexdesa&file=' + encodeURIComponent(resumenInfo.doc_nomina_acuse)" class="btn btn-success" download>
<i class="ti ti-download me-1"></i> Descargar Recibos de Nómina
</a>
</template>
<template x-if="resumenInfo && resumenInfo.doc_nomina_acuse && !puedeDescargar">
<span class="badge rounded-pill bg-warning py-2 px-3">Recibos de Nómina disponibles</span>
</template>
<template x-if="resumenInfo && !resumenInfo.doc_nomina_acuse && !resumenInfo.es_ultimo_periodo">
<span class="badge rounded-pill bg-danger py-2 px-3">Recibos de Nómina No Disponibles</span>
</template>
</div>

</div>


<!---------- TABLA RECIBOS DE NOMINA "SEMANAL / QUINCENAL" ---------->
<div class="table-responsive pb-4">
<table id="tabla-recibos-nomina" class="table table-striped table-bordered mb-0 text-nowrap align-middle w-100">
<thead>
<tr>
<th class="text-center align-middle" width="96px">#</th>
<th class="text-center align-middle" >No. Colaborador</th>
<th class="text-start align-middle">Nombre del personal</th>
<th class="text-center align-middle">Puesto</th>
<th class="text-end align-middle">Importe</th>
<th class="text-center align-middle" >Prima Vacacional</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-text text-primary fs-7" title="Recibo Acuse"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-signature text-success fs-8" title="Recibo Firmado"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-gift text-warning fs-7" title="Aguinaldo"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-file-check text-dark fs-7" title="Original"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-message fs-7" title="Comentarios"></i></th>
<th class="text-center align-middle" width="100px">Estatus</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6" title="Acciones"></i></th>
</tr>
</thead>
<tbody></tbody>
<tfoot class="table-dark">
<tr>
<th colspan="4" class="text-end text-white">Importe Total:</th>
<th class="text-end text-white" id="tfoot-total-importe">$0.00</th>
<th colspan="8"></th>
</tr>
</tfoot>
</table>
</div>
</div>

</div>
</div>
</div>


</div>

<!---------- MODAL EDITAR INFORMACIÓN ---------->
<div class="modal fade" id="modalEditarNomina" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">

<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-user"></i>
<span x-text="colaboradorEditando ? colaboradorEditando.nombre_completo : 'Editar'"></span>
</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body pb-0">
<div x-show="!esMexdesa">
<div class="mb-3">
<label class="form-label mb-1">* Importe Total:</label>
<input type="number" step="0.01" class="form-control" x-model="formEdicion.importe">
</div>

<div class="mb-3">
<label class="form-label mb-1">* Recibo de Nómina (PDF):</label>
<input type="file" class="form-control" x-ref="docNominaFile" accept=".pdf,.jpg,.jpeg,.png">
</div>

<div class="mb-3">
<label class="form-label mb-1">* Recibo Firmado (PDF):</label>
<input type="file" class="form-control" x-ref="docNominaFirmaFile" accept=".pdf,.jpg,.jpeg,.png">
</div>

<template x-if="resumenInfo && resumenInfo.es_ultimo_periodo">
<div class="mb-3">
<label class="form-label mb-1">* Recibo de Nomina (Aguinaldo):</label>
<input type="file" class="form-control" x-ref="docAguinaldoFile" accept=".pdf,.jpg,.jpeg,.png">
</div>
</template>
</div>

<div class="mb-3" x-show="esMexdesa">
<label class="form-label mb-1">¿Se recibió el recibo de nómina original (firmado)?:</label>
<div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="radOriginal" value="1" x-model="formEdicion.original">
<label class="form-check-label">Si</label>
</div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="radOriginal" value="0" x-model="formEdicion.original">
<label class="form-check-label">No</label>
</div>
</div>
</div>

<div class="mb-3" x-show="esDirector && puedeEditarPrima">
<label class="form-label">¿Se realizó el pago de prima vacacional?:</label>
<div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="radPrima" value="2" x-model="formEdicion.prima_vacacional">
<label class="form-check-label">Si</label>
</div>
<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="radPrima" value="0" x-model="formEdicion.prima_vacacional">
<label class="form-check-label">No</label>
</div>
<div class="form-check form-check-inline" x-show="formEdicion.prima_vacacional === 1">
<input class="form-check-input" type="radio" name="radPrima" value="1" checked disabled>
<label class="form-check-label">No se realizó el pago</label>
</div>
</div>

</div>
</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarEdicion()" :disabled="guardandoEdicion"><i class="ti ti-check me-1"></i> Guardar</button>
</div>
</div>
</div>
</div>

<!-- Offcanvas Comentarios -->
<div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="modalComentarios" x-ref="modalComentarios"
style="width: 480px; max-height: 100dvh; overflow: hidden;">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
<div class="hstack gap-3">
<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>
<span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success">
<span class="visually-hidden">online</span>
</span>
</div>
<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">
Recibo de Nómina #<span x-text="comentarioIdActual"></span>
</p>
</div>
</div>
<button type="button"
class="btn-close btn-close-white"
data-bs-dismiss="offcanvas"></button>
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

<div class="d-flex mb-4"
:class="c.esMio ? 'justify-content-end' : 'justify-content-start'">

<template x-if="!c.esMio">
<div class="d-flex gap-3 align-items-start">

<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center"
style="width:45px; height:45px;">
<i class="ti ti-user fs-6 text-white"></i>
</div>
</div>

<div>
<h6 class="fw-semibold mb-1"
x-text="c.nombre_usuario || 'Usuario'"></h6>

<div class="fs-3 text-muted mb-1"
x-text="c.fecha_formateada || ''"></div>

<div class="p-3 text-bg-success rounded-3 text-white mt-2"
style="max-width: 420px;"
x-text="c.comentario"></div>
</div>

</div>
</template>

<template x-if="c.esMio">
<div class="d-flex flex-column align-items-end">

<div class="fs-3 text-muted mb-1 text-end"
x-text="c.fecha_formateada || ''"></div>

<div class="p-3 bg-primary text-white rounded-3 mt-2"
style="max-width: 420px;"
x-text="c.comentario"></div>

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

<!---------- MODAL AGREGAR PERSONAL ---------->
<div class="modal fade" id="modalAgregarPersonal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white"><i class="ti ti-user-plus"></i> Nuevo personal a la nómina</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body pb-0">

<template x-if="personalFaltanteCargando">
<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
</template>

<template x-if="!personalFaltanteCargando">
<div>
<label class="form-label mb-1">* Nombre del personal:</label>
<select id="select-personal-agregar" class="form-select" multiple></select>
<small class="text-muted mt-1">Selecciona uno o varios colaboradores para agregarlos al periodo actual.</small>

<template x-if="!personalFaltante.length">
<div class="alert alert-secondary text-center text-muted mb-0">No hay personal disponible por agregar.</div>
</template>
</div>
</template>

</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarPersonal()" :disabled="guardandoPersonal"><i class="ti ti-check"></i> Guardar</button>
</div>
</div>
</div>
</div>

<!-- MODAL ACUSE DE NÓMINA (PERIODO) -->
<div class="modal fade" id="modalAcusePeriodo" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white">Acuse de nómina <small class="text-white" x-text="resumenInfo ? '(del ' + resumenInfo.rango_fechas + ')' : ''"></small></h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body pb-0">
<!-- Spinner de carga -->
<template x-if="acusesCargando">
<div class="text-center py-3">
<div class="spinner-border text-primary" role="status"></div>
</div>
</template>

<!-- Sección para subir documento -->
<div x-show="puedeEditar || puedeCrear">
<label class="form-label">* Documento:</label>
<input type="file" class="form-control mb-3" x-ref="archivoAcusePeriodo" accept=".pdf,.jpg,.jpeg,.png">
</div>


<!-- Tabla de acuses -->
<div class="table-responsive mt-3" x-show="!acusesCargando">
<table class="table table-bordered table-striped">
<thead>
<tr>
<th class="text-center" width="48px">#</th>
<th class="text-center">Fecha</th>
<th class="text-center" width="48px"><i class="ti ti-download text-primary fs-6"></i></th>
<th class="text-center" width="48px" x-show="puedeEliminar"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<!-- Mensaje cuando no hay información -->
<template x-if="listaAcuses.length === 0">
<tr>
<td colspan="5" class="text-center text-primary">
No se encontro información
</td>
</tr>
</template>

<!-- Listado de elementos -->
<template x-for="(a, index) in listaAcuses" :key="a.id">
<tr>
<td class="align-middle text-center fw-bolder" x-text="index + 1"></td>
<td class="align-middle text-center" x-text="a.fecha"></td>

<!-- Botón Descargar -->
<td class="text-center align-middle" width="36">
<a :href="'/download?tipo=recibos-nomina-acuse&file=' + encodeURIComponent(a.archivo)" target="_blank" title="Descargar">
<i class="ti ti-download text-primary fs-6"></i>
</a>
</td>

<!-- Botón Eliminar -->
<td class="text-center align-middle" width="36" x-show="puedeEliminar">
<i class="ti ti-trash text-danger fs-6 pointer"
   @click="deleteAction({ url: baseUrl + '/acuses/eliminar', id: a.id, name: 'acuse de nómina', table: null })
             .then(r => { if (r && r.success) abrirModalAcusePeriodo(); })"></i>
</td>
</tr>
</template>
</tbody>
</table>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarAcusePeriodo()" :disabled="subiendoAcusePeriodo" x-show="puedeEditar || puedeCrear"><i class="ti ti-check"></i> Guardar</button>
</div>
</div>
</div>
</div>

<!---------- MODAL RECIBOS DE NÓMINA (MEXDESA) ---------->
<div class="modal fade" id="modalMexdesa" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content"> 
<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white"><i class="ti ti-upload me-1"></i> Subir Recibos de Nómina <small x-text="resumenInfo ? '(del ' + resumenInfo.rango_fechas + ')' : ''"></small></h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body pb-0">

<div x-show="esMexdesa && resumenInfo && !resumenInfo.finalizado_mexdesa">
<label class="form-label">* Recibos de Nómina (PDF):</label>
<input type="file" class="form-control mb-3" x-ref="archivoAcuseMexdesa" accept=".pdf">
</div>

<!-- Estado: Recibos subidos correctamente -->
<template x-if="resumenInfo && resumenInfo.doc_nomina_acuse">
<div class="alert alert-success border-0 bg-success bg-opacity-10 d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3 rounded-3 gap-3 shadow-sm">
<div class="d-flex align-items-center gap-3">
<i class="ti ti-file-check fs-7 text-success flex-shrink-0"></i>
<div>
<span class="fw-semibold d-block text-success">Recibos de nómina subidos</span>
<span class="small text-muted">El archivo de acuse está listo para su descarga.</span>
</div>
</div>
<a :href="'/download?tipo=recibos-mexdesa&file=' + encodeURIComponent(resumenInfo.doc_nomina_acuse)" 
target="_blank" 
class="btn btn-success text-nowrap align-self-start align-self-md-center shadow-sm">
<i class="ti ti-download me-1"></i>Descargar PDF
</a>
</div>
</template>

<!-- Estado: Pendiente de carga -->
<template x-if="resumenInfo && !resumenInfo.doc_nomina_acuse">
<div class="alert alert-warning border-0 bg-warning bg-opacity-10 d-flex align-items-center p-3 rounded-3 gap-3 shadow-sm">
<i class="ti ti-alert-circle fs-7 text-warning flex-shrink-0"></i>
<div>
<span class="fw-semibold d-block text-warning-emphasis">Recibos pendientes</span>
<span class="small text-muted">Aún no se han subido los recibos de nómina del personal para este periodo.</span>
</div>
</div>
</template>

<template x-if="resumenInfo && resumenInfo.finalizado_mexdesa">
<div class="alert alert-light border shadow-sm d-flex align-items-center p-3 rounded-3 gap-2">
<i class="ti ti-circle-check text-success fs-7 flex-shrink-0"></i>
<div>
<strong class="text-dark">Actividad finalizada:</strong> 
<span class="text-muted">la estación puede capturar los recibos de nómina.</span>
</div>
</div>
</template>
</div>

<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cerrar</button>
<button type="button" class="btn btn-success" @click="guardarAcuseMexdesa()" :disabled="subiendoAcuseMexdesa" x-show="esMexdesa && resumenInfo && !resumenInfo.finalizado_mexdesa">
<i class="ti ti-check me-1"></i> Guardar
</button>
<button type="button" class="btn btn-primary" @click="finalizarMexdesa()" x-show="esMexdesa && resumenInfo && resumenInfo.doc_nomina_acuse && !resumenInfo.finalizado_mexdesa">
<i class="ti ti-circle-check me-1"></i> Finalizar
</button>
</div>
</div>
</div>
</div>

<!-- MODAL AGUINALDOS -->
<div class="modal fade" id="modalAguinaldo" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header modal-colored-header bg-primary">
<h5 class="modal-title text-white"><i class="ti ti-gift me-1"></i> Recibos de Aguinaldo <small x-text="resumenInfo ? '(del ' + resumenInfo.rango_fechas + ')' : ''"></small></h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body pb-0">

<div x-show="esMexdesa && !(resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.status === 1)">
<label class="form-label">* Subir PDF de recibos de aguinaldo:</label>
<input type="file" class="form-control mb-3" x-ref="archivoAguinaldo" accept=".pdf,.jpg,.jpeg,.png">
</div>

<!-- Estado: Recibos subidos correctamente -->
<template x-if="resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.doc">
<div class="alert alert-success border-0 bg-success bg-opacity-10 d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3 rounded-3 gap-3 shadow-sm mb-3">
<div class="d-flex align-items-center gap-3">
<i class="ti ti-file-check fs-7 text-success flex-shrink-0"></i>
<div>
<span class="fw-semibold d-block text-success">Recibos de aguinaldo subidos</span>
<span class="small text-muted">El archivo está listo para su descarga.</span>
</div>
</div>
<a :href="'/download?tipo=recibos-mexdesa&file=' + encodeURIComponent(resumenInfo.aguinaldo.doc)" target="_blank" class="btn btn-success text-nowrap align-self-start align-self-md-center shadow-sm">
<i class="ti ti-download me-1"></i>Descargar PDF
</a>
</div>
</template>

<!-- Estado: Pendiente de carga -->
<template x-if="resumenInfo && (!resumenInfo.aguinaldo || !resumenInfo.aguinaldo.doc)">
<div class="alert alert-warning border-0 bg-warning bg-opacity-10 d-flex align-items-center p-3 rounded-3 gap-3 shadow-sm mb-3">
<i class="ti ti-alert-circle fs-7 text-warning flex-shrink-0"></i>
<div>
<span class="fw-semibold d-block text-warning-emphasis">Recibos pendientes</span>
<span class="small text-muted">Aún no se han subido los recibos de aguinaldo del personal.</span>
</div>
</div>
</template>

<!-- Estado: Finalizado -->
<template x-if="resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.status === 1">
<div class="alert alert-light border shadow-sm d-flex align-items-center p-3 rounded-3 gap-2 mb-3">
<i class="ti ti-circle-check text-success fs-7 flex-shrink-0"></i>
<div>
<strong class="text-dark">Actividad finalizada:</strong> 
<span class="text-muted">los recibos de aguinaldo han sido completados.</span>
</div>
</div>
</template>

</div>
<div class="modal-footer">
<template x-if="resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.status === 1">
<span class="badge rounded-pill bg-success py-2 px-3">Aguinaldos finalizados</span>
</template>
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cerrar</button>
<button type="button" class="btn btn-success" @click="guardarAguinaldo()" :disabled="subiendoAguinaldo" x-show="esMexdesa && !(resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.status === 1)">
<i class="ti ti-upload me-1"></i> Subir / Reemplazar documento
</button>
<button type="button" class="btn btn-primary" @click="finalizarAguinaldoOp()" x-show="esMexdesa && resumenInfo && resumenInfo.aguinaldo && resumenInfo.aguinaldo.doc && resumenInfo.aguinaldo.status === 0">
<i class="ti ti-circle-check me-1"></i> Finalizar
</button>
</div>
</div>
</div>
</div>

</div>