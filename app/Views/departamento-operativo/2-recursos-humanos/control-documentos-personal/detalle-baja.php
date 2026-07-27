<div id="detalle-baja-container"
class="mt-4 mb-5"
data-baja="<?= htmlspecialchars(json_encode($baja), ENT_QUOTES) ?>"
x-data="{ ...actions(), ...detalleBajaComponent() }"
x-init="init()"
x-cloak>

<div class="row">

<!---------- COLUMNA IZQUIERDA (col-8) ---------->
<div class="col-xl-8 col-lg-7 col-12">

<!---------- INFORMACIÓN PERSONAL ---------->
<div class="card mb-4">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white"><i class="ti ti-user me-2"></i>INFORMACIÓN PERSONAL</h5>
</div>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">No. de Colaborador:</label>
<div class="mt-1"><?= htmlspecialchars($baja['no_colaborador']) ?></div>
</div>
<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Nombre Completo:</label>
<div class="mt-1"><?= htmlspecialchars($baja['nombre_completo']) ?></div>
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Puesto:</label>
<div class="mt-1"><?= htmlspecialchars($baja['puesto']) ?></div>
</div>
<div class="col-xl-2 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Fecha de Ingreso:</label>
<div class="mt-1"><?= htmlspecialchars($baja['fecha_ingreso']) ?></div>
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Estación / Departamento:</label>
<div class="mt-1"><?= htmlspecialchars($baja['nombre_estacion']) ?></div>
</div>
</div>
</div>
</div>

<!---------- INFORMACIÓN DE BAJA ---------->
<div class="card mb-4">
<div class="card-header text-bg-danger">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white"><i class="ti ti-user-off me-2"></i>INFORMACIÓN DE BAJA</h5>
</div>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Fecha de Baja:</label>
<div class="fw-semibold mt-1"><?= htmlspecialchars($baja['fecha_baja']) ?></div>
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Motivo:</label>
<div class="fw-semibold mt-1"><?= htmlspecialchars($baja['motivo']) ?></div>
</div>
<div class="col-xl-6 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Detalle:</label>
<div class="fw-semibold mt-1"><?= htmlspecialchars($baja['detalle']) ?: 'S/I' ?></div>
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Proceso de Baja:</label>
<div class="fw-semibold mt-1"><?= htmlspecialchars($baja['proceso']) ?: 'Pendiente' ?></div>
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
<label class="form-label">Estatus:</label>
<div class="mt-1"><span class="badge <?= $baja['badge_class'] ?>"><?= $baja['badge_label'] ?></span></div>
</div>
<div class="col-xl-6 col-lg-4 col-md-6 col-sm-12" x-show="baja.has_solucion" x-cloak>
<label class="form-label">Solución:</label>
<div class="fw-semibold mt-1" x-text="baja.solucion || ''"></div>
</div>
</div>
</div>
</div>

<!---------- DOCUMENTACIÓN DEL PERSONAL ---------->
<div class="card mb-4">
<div class="card-header text-bg-info">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white"><i class="ti ti-files me-2"></i>DOCUMENTACIÓN DEL PERSONAL</h5>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered table-striped mb-0 text-nowrap align-middle">
<thead>
<tr class="text-center">
<th class="align-middle">Documentos Personales</th>
<th class="align-middle">Identificación Oficial</th>
<th class="align-middle">CURP</th>
<th class="align-middle">RFC</th>
<th class="align-middle">NSS</th>
<th class="align-middle">Contrato</th>
</tr>
</thead>
<tbody class="text-center">
<tr>
<td class="align-middle">
<i class="ti ti-download fs-5 text-primary pointer" title="Documentos Personales" x-show="baja.has_docs_archivo" @click="download('docs-personal-documentos', '<?= htmlspecialchars($baja['documentos_archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_docs_archivo"></i>
</td>
<td class="align-middle">
<i class="ti ti-download" title="<?= htmlspecialchars($baja['documentos']['ine']['label']) ?>" x-show="baja.has_ine" @click="download('docs-personal-ine', '<?= htmlspecialchars($baja['documentos']['ine']['archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_ine"></i>
</td>
<td class="align-middle">
<i class="ti ti-download fs-5 text-primary pointer" title="<?= htmlspecialchars($baja['documentos']['curp']['label']) ?>" x-show="baja.has_curp" @click="download('docs-personal-curp', '<?= htmlspecialchars($baja['documentos']['curp']['archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_curp"></i>
</td>
<td class="align-middle">
<i class="ti ti-download fs-5 text-primary pointer" title="<?= htmlspecialchars($baja['documentos']['rfc']['label']) ?>" x-show="baja.has_rfc" @click="download('docs-personal-rfc', '<?= htmlspecialchars($baja['documentos']['rfc']['archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_rfc"></i>
</td>
<td class="align-middle">
<i class="ti ti-download" title="<?= htmlspecialchars($baja['documentos']['nss']['label']) ?>" x-show="baja.has_nss" @click="download('docs-personal-nss', '<?= htmlspecialchars($baja['documentos']['nss']['archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_nss"></i>
</td>
<td class="align-middle">
<i class="ti ti-download" title="<?= htmlspecialchars($baja['documentos']['contrato']['label']) ?>" x-show="baja.has_contrato" @click="download('docs-personal-contrato', '<?= htmlspecialchars($baja['documentos']['contrato']['archivo']) ?>')"></i>
<i class="ti ti-file-off text-muted fs-5" x-show="!baja.has_contrato"></i>
</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>

<!---------- ARCHIVOS DE BAJA ---------->
<div class="card mb-4">
<div class="card-header bg-primary">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white"><i class="ti ti-paperclip me-2"></i>ARCHIVOS DE BAJA</h5>
<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSubirArchivoBaja">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered table-striped mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="align-middle">Descripción</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-download fs-5 text-primary"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash fs-5 text-danger"></i></th>
</tr>
</thead>
<tbody class="text-center">
<template x-if="archivosBaja.length === 0">
<tr>
<td colspan="3" class="text-center text-primary py-3">No se encontró información</td>
</tr>
</template>
<template x-for="a in archivosBaja" :key="a.id">
<tr>
<td class="align-middle text-start" x-text="a.descripcion"></td>
<td class="align-middle">
<i class="ti ti-download fs-5 text-primary pointer" title="Descargar" @click="download('docs-personal-baja', a.archivo)"></i>
</td>
<td class="align-middle">
<i class="ti ti-trash fs-5 text-danger pointer" @click="deleteAction({ url: '/departamento-operativo/recursos-humanos/control-documentos-personal/delete-baja-archivo', id: a.id, name: a.descripcion }).then(() => recargarArchivosBaja())" title="Eliminar"></i>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>

</div>

<!---------- COLUMNA DERECHA — COMENTARIOS ---------->
<div class="col-xl-4 col-lg-5 col-12">

<div class="card overflow-hidden">
<div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary">
<div class="hstack gap-3">

<div class="position-relative">
<div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
<i class="ti ti-message-circle text-primary fs-7"></i>
</div>

<span class="position-absolute bottom-0 end-0 p-2 badge rounded-pill bg-success"><span class="visually-hidden">online</span></span>
</div>

<div>
<h5 class="mb-1 text-white">COMENTARIOS</h5>
<p class="mb-0 text-white opacity-75">Conversaci&oacute;n activa</p>
</div>

</div>
</div>

<!-- BODY -->
<div class="d-flex parent-chat-box">

<div class="chat-box w-100">

<!-- LISTA -->
<div class="chat-box-inner p-3" style="max-height: 650px; overflow-y: auto;" x-ref="chatContainer">

<!-- SIN COMENTARIOS -->
<template x-if="comentarios.length === 0">
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 650px;">
<i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
<p class="text-muted mb-0 fs-5">Sin comentarios</p>
</div>
</template>

<!-- COMENTARIOS -->
<div class="chat-list active-chat p-2">

<template x-for="c in comentarios" :key="c.id">
<div class="d-flex mb-4" :class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">

<!-- MENSAJES OTROS -->
<template x-if="!c.esPropio">
<div class="d-flex gap-3 align-items-start">

<!-- ICONO -->
<div class="flex-shrink-0">
<div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
<i class="ti ti-user text-white fs-6"></i>
</div>
</div>

<!-- CONTENIDO -->
<div>
<h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
<div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
<div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"> </div>
</div>

</div>
</template>

<!-- MIS MENSAJES -->
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

<!-- FOOTER -->
<div class="px-3 py-3 border-top chat-send-message-footer bg-white">
<div class="d-flex align-items-center gap-2">

<div class="flex-grow-1">
<textarea class="form-control border-0 bg-light rounded-pill px-3 py-2" rows="1" placeholder="Escribe un comentario..." style="resize:none;"
x-model="nuevoComentario" @keydown.enter.prevent="agregarComentario()"></textarea>
</div>

<div class="flex-shrink-0">
<button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px;"
type="button" @click="agregarComentario()" :disabled="guardandoComentario || !nuevoComentario.trim()">
<template x-if="!guardandoComentario"><i class="ti ti-send fs-5"></i></template>
<template x-if="guardandoComentario"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>

</div>
</div>

</div>
</div>

</div>
</div>

</div>
<!---------- MODAL SUBIR ARCHIVO BAJA ---------->
<div class="modal fade" id="modalSubirArchivoBaja" tabindex="-1" aria-labelledby="modalSubirArchivoBajaLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="modalSubirArchivoBajaLabel">Nuevo archivo de baja</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">* Descripción:</label>
<input type="text" class="form-control" list="listaDescBajaModal"
x-model="bajaForm.descripcion">
<datalist id="listaDescBajaModal">
<option>Acta de hechos</option>
<option>Carta de Renuncia</option>
<option>Finiquito</option>
</datalist>
</div>
<div class="mb-3">
<label class="form-label">* Archivo:</label>
<input type="file" class="form-control" x-ref="bajaFileInputModal">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success"
@click="subirArchivoBaja()"
:disabled="subiendoArchivo || !bajaForm.descripcion.trim()">
<template x-if="!subiendoArchivo"><span> Guardar</span></template>
<template x-if="subiendoArchivo"><span class="spinner-border spinner-border-sm"></span></template>
</button>
</div>
</div>
</div>
</div>

</div>
