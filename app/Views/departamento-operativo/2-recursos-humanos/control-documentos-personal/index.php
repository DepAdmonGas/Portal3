<div id="container" class="mt-4 mb-5"
data-id-estacion="<?= $idEstacion ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
data-nombre-puesto="<?= $nombrePuesto ?>"
data-module-station-key="control-documentos-personal"
x-data="{ ...actions(), ...controlDocsComponent() }">

<!--
<div x-show="!haSeleccionado" x-cloak>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación o departamento del menú superior para poder visualizar el control de documentos del personal.
</div>
</div>
-->

<div x-show="haSeleccionado" x-cloak>
<div class="row">

<!---------- BOTON DE ACCION ---------->
<template x-if="puedeCrear">
<div class="col-12 mb-3">
<div class="dropdown float-end">
<button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-tools"></i>
</button>
<ul class="dropdown-menu">
<li><a class="dropdown-item pointer" @click="abrirModalAgregar()"><i class="ti ti-plus me-1"></i> Agregar Personal</a></li>
<li><a class="dropdown-item pointer" href="/departamento-operativo/recursos-humanos/control-documentos-personal-excel"><i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel</a></li>
<li><a class="dropdown-item pointer" href="/departamento-operativo/recursos-humanos/control-documentos-personal-kpi/<?= date('Y') ?>"><i class="ti ti-chart-column me-1"></i> Evaluacion Personal (KPIs)</a></li>
</ul>
</div>
</div>
</template>

<!---------- ABREVIATURAS DE DOCUMENTACION ---------->
<div class="col-12 mb-3">
<div class="card">

<div class="card-header text-bg-primary">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white"><i class="ti ti-files me-2"></i>ABREVIATURAS DE DOCUMENTACIÓN</h5>
</div>
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped align-middle mb-0">

<tbody>
<tr>
<td width="48px" class="text-center"><span class="badge rounded-pill text-bg-primary">RP</span></td>
<td>Requisición del Personal</td>

<td width="48px" class="text-center"><span class="badge rounded-pill text-bg-primary">CD</span></td>
<td>Comprobante de Domicilio</td>

<td width="48px" class="text-center"><span class="badge rounded-pill text-bg-primary">CURP</span></td>
<td>Clave Única de Registro de Población</td>
</tr>

<tr>
<td class="text-center"><span class="badge rounded-pill text-bg-primary">CV</span></td>
<td>Curriculum Vitae</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">CAI</span></td>
<td>Comprobante de Afiliación al IMSS</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">ARI</span></td>
<td>Aviso de Retención de Infonavit</td>
</tr>

<tr>
<td class="text-center"><span class="badge rounded-pill text-bg-primary">IO</span></td>
<td>Identificación Oficial</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">CE</span></td>
<td>Comprobante de Estudios</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">CSF</span></td>
<td>Constancia de Situación Fiscal</td>
</tr>

<tr>
<td class="text-center"><span class="badge rounded-pill text-bg-primary">AN</span></td>
<td>Acta de Nacimiento</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">CR</span></td>
<td>Cartas de Recomendación</td>

<td class="text-center"><span class="badge rounded-pill text-bg-primary">CANP</span></td>
<td>Carta de Antecedentes No Penales</td>
</tr>
</tbody>
</table>
</div>

</div>
</div>
</div>

<!---------- TABLA DE PERSONAL ACTIVO ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white">
<i class="ti ti-users me-2"></i>
PERSONAL ACTIVO
</h5>
</div>
</div>

<div class="card-body">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-control-docs" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>
</div>


<!---------- TABLA DE PERSONAL INACTIVO ---------->
<div x-show="esMultiestacion" x-cloak>
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center">
<h5 class="mb-0 text-white"><i class="ti ti-user-off me-2"></i>PERSONAL NO ACTIVO</h5>
</div>
</div>

<div class="card-body">
<div class="datatables">
<div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
<table id="tabla-control-docs-inactivos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<tbody></tbody>
</table>
</div>
</div>
</div>
</div>

</div>
</div>

<!---------- ---------->

</div>
</div>


<!---------- MODAL AGREGAR PERSONAL ---------->
<div class="modal fade" id="modalAgregarPersonal" x-ref="modalAgregarPersonal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title" x-text="nombreEstacionActual ? (modoForm === 'agregar' ? 'Agregar Personal (' + nombreEstacionActual + ')' : 'Editar Personal (' + nombreEstacionActual + ')') : (modoForm === 'agregar' ? 'Agregar Personal' : 'Editar Personal')"></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<div class="row">

<!-- DOCUMENTACION DESCRIPCION -->
<div class="col-12 mb-3" x-show="modoForm === 'agregar'">
<div class="border rounded p-3">
<h6 class="fw-semibold text-primary mb-3"><i class="ti ti-alert-circle me-2"></i>Documentación para colaboradores nuevos</h6>

<div class="alert alert-warning d-flex align-items-start mb-4">
<i class="ti ti-info-circle fs-4 me-2"></i>
<div>Esta documentación aplica únicamente para <strong>colaboradores de nuevo ingreso o de contratación reciente</strong>.</div>
</div>

<div class="d-flex flex-column gap-3">

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">1</span>
<span>Requisición de personal.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">2</span>
<span>Solicitud de empleo (a puño y letra, únicamente despachadores) y/o Curriculum Vitae.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">3</span>
<span>Identificación oficial vigente (INE o pasaporte).</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">4</span>
<span>Acta de nacimiento certificada.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">5</span>
<span>Comprobante de domicilio (teléfono, agua o predial, con antigüedad máxima de tres meses).</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">6</span>
<span>Comprobante de afiliación al IMSS (en caso de no contar con él, presentar afiliación).</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">7</span>
<span>Último comprobante de estudios.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">8</span>
<span>Cartas de recomendación de los últimos empleos (en hoja membretada con dirección y teléfono).</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">9</span>
<span>Clave Única de Registro de Población (CURP).</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">10</span>
<span>Aviso de retención de Infonavit.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center"
style="width:28px;height:28px;">11</span>
<span>Constancia de Situación Fiscal (CSF) con homoclave.</span>
</div>

<div class="d-grid" style="grid-template-columns:40px 1fr; align-items:center;">
<span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">12</span>
<span>Carta de antecedentes no penales (solo para despachadores).</span>
</div>

</div>

</div>
</div>


<!-- INFORMACION GENERAL -->
<div class="col-12 mb-3">
<div class="border rounded p-3">
<h6 class="fw-semibold text-primary mb-4"><i class="ti ti-building me-2"></i>Información General</h6>

<div class="row">

<div class="col-lg-7 mb-3" x-show="esMultiestacion && !idEstacionActual">
<label class="form-label">* Estación / Departamento:</label>
<div class="select2-modal-field is-select2-pending" x-ref="estacionWrapper" :class="errors.id_estacion ? 'is-invalid' : ''">
<select class="form-select" x-ref="estacionSelect" data-width="100%">
<option value="">Selecciona una opción...</option>
<template x-for="e in estaciones" :key="e.id">
<option :value="e.id" x-text="e.nombre + (e.tipo === 'departamento' ? ' (Depto.)' : '')"></option>
</template>
</select>
</div>
</div>

<div class="col-lg-5 mb-3">
<label class="form-label">* Fecha ingreso:</label>
<input type="date" class="form-control" x-model="form.fecha_ingreso" :style="errors.fecha_ingreso ? 'border:2px solid #A52525' : ''">
</div>

<div class="col-lg-4 mb-0" x-show="puedeCrear || esMultiestacion || modoForm === 'editar'">
<label class="form-label">* No. Colaborador:</label>
<input type="number" class="form-control" x-model="form.no_colaborador">
</div>

</div>

</div>
</div>

<!-- INFORMACION DEL COLABORADOR -->
<div class="col-12 mb-3">
<div class="border rounded p-3">
<h6 class="fw-semibold text-primary mb-4"><i class="ti ti-user me-2"></i>Información del Colaborador</h6>

<div class="row">

<div class="col-12 mb-3">
<label class="form-label">* Nombre Completo:</label>
<input type="text" class="form-control" x-model="form.nombre_completo" :style="errors.nombre_completo ? 'border:2px solid #A52525' : ''">
</div>

<div class="col-lg-8 mb-3">
<label class="form-label">* Puesto:</label>
<div class="select2-modal-field is-select2-pending" x-ref="puestoWrapper" :class="errors.puesto ? 'is-invalid' : ''">
<select class="form-select" x-ref="puestoSelect" data-width="100%">
<option value="">Selecciona una opción...</option>
<template x-for="p in puestos" :key="p.id">
<option :value="p.id" x-text="p.puesto"></option>
</template>
</select>
</div>
</div>

<div class="col-lg-4 mb-3" x-show="puedeCrear || esMultiestacion || modoForm === 'editar'">
<label class="form-label">Salario Diario:</label>
<div class="input-group">
<span class="input-group-text">$</span>
<input type="number" step="0.01" class="form-control" x-model="form.sd">
</div>
</div>

</div>

</div>
</div>

<!-- DOCUMENTACION -->
<div class="col-12">
<div class="border rounded p-3">

<div class="col-12 mb-4">
<h6 class="fw-semibold text-primary mb-4"><i class="ti ti-file-text me-2"></i>Documentación</h6>
<div class="row g-3">
<template x-for="(info, campo) in documentTypes" :key="campo">
<div :class="info.fullWidth ? 'col-12' : 'col-xl-6 col-lg-6 col-md-12'">
<label class="form-label fw-semibold" x-text="info.label + ':'"></label>

<div class="input-group">
<template x-if="modoForm === 'editar' && form.documentos[campo]">
<button type="button" class="btn btn-outline-success" @click="downloadDocumento(campo, form.documentos[campo])" title="Descargar archivo actual">
<i class="ti ti-download me-1"></i>Descargar
</button>
</template>

<input type="file" class="form-control" :ref="'doc_' + campo" :name="'doc_' + campo" accept=".pdf,.jpg,.jpeg,.png">
</div>

</div>
</template>
</div>
</div>

</div>
</div>

</div>
</div>

<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarPersonal()" :disabled="guardando">
<template x-if="guardando"><span class="spinner-border spinner-border-sm me-1"></span></template>
<span x-text="guardando ? 'Guardando...' : (modoForm === 'agregar' ? 'Guardar' : 'Editar')"></span>
</button>
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
<p class="mb-0 text-white opacity-75">Personal (<span x-text="comentarioPersonalNombre"></span>)</p>
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

<!---------- MODAL BAJA DEL PERSONAL ---------->
<div class="modal fade" id="modalBajaPersonal" x-ref="modalBajaPersonal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Baja de Personal (<span x-text="bajaPersonalNombre"></span>)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">* Fecha de baja:</label>
<input type="date" class="form-control" x-model="bajaForm.fecha_baja">
</div>
<div class="col-md-6">
<label class="form-label">* Motivo:</label>
<input type="text" class="form-control" x-model="bajaForm.motivo" list="listaMotivosBaja" placeholder="Selecciona o escribe un motivo...">
<datalist id="listaMotivosBaja">
<option value="Renuncia voluntaria">
<option value="Terminacion de contrato">
<option value="Mala practica">
<option value="Reduccion de personal">
<option value="Otro">
</datalist>
</div>
<div class="col-12">
<label class="form-label">Detalle:</label>
<textarea class="form-control" rows="3" x-model="bajaForm.detalle"></textarea>
</div>
<div class="col-12">
<label class="form-label">Acta de hechos:</label>
<input type="file" class="form-control" x-ref="fileActaHechos" accept=".pdf,.jpg,.jpeg,.png">
</div>
<div class="col-12">
<label class="form-label">Carta de renuncia:</label>
<input type="file" class="form-control" x-ref="fileCartaRenuncia" accept=".pdf,.jpg,.jpeg,.png">
</div>
<div class="col-12">
<label class="form-label">Finiquito:</label>
<input type="file" class="form-control" x-ref="fileFiniquito" accept=".pdf,.jpg,.jpeg,.png">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-success" @click="guardarBaja()" :disabled="guardando">Guardar</button>
</div>
</div>
</div>
</div>

<!---------- MODAL ACCESO PERSONAL ---------->
<div class="modal fade" id="modalAccesoPersonal" x-ref="modalAccesoPersonal" tabindex="-1" data-bs-backdrop="static">
<div class="modal-dialog modal-md">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Acceso del Personal (<span x-text="accesoPersonalNombre"></span>)</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body p-4">

<!-- Encabezado -->
<div class="text-center mb-4">
<div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width:90px;height:90px; background:linear-gradient(135deg,#4F8CFF,#1E5EFF);">
<i class="ti ti-user-check text-white" style="font-size:45px;"></i>
</div>

<h4 class="fw-bold mb-1">Control de Acceso</h4>
<small class="text-muted" x-text="accesoSoloLectura ? 'Consulta de acceso del colaborador (solo lectura)' : 'Consulta y administración del acceso del colaborador'"></small>
</div>

<!-- Contenedor -->
<div class="border rounded-4 overflow-hidden shadow-sm bg-white">

<!-- Huella Digital -->
<div class="d-flex align-items-center justify-content-between p-3">

<div class="d-flex align-items-center">
<div class="rounded-3  bg-opacity-10  d-flex align-items-center justify-content-center me-3" 
:class="accesoData.huella ? 'bg-primary text-primary' : 'bg-danger text-danger'"
style="width:52px;height:52px;">
<i class="ti ti-fingerprint fs-5"></i>
</div>

<div>
<div class="fw-semibold">Huella Digital</div>
<small class="text-muted">Estado del registro biométrico</small>
</div>
</div>

<span class="badge rounded-pill" :class="accesoData.huella ? 'bg-success' : 'bg-danger'" x-text="accesoData.huella ? 'Registrada' : 'No registrada'"></span>
</div>

<hr class="m-0">

<!-- PIN -->
<div class="p-3" x-show="accesoData.nombre_puesto !== 'Encargado' && accesoData.nombre_puesto !== 'Asistente Administrativo'">
<template x-if="!showEditPin">

<div>

<!-- Primera fila -->
<div class="d-flex justify-content-between align-items-center">

<div class="d-flex align-items-center">
<div class="rounded-3 bg-opacity-10 d-flex align-items-center justify-content-center me-3" 
:class="accesoData.pin ? 'bg-primary text-primary' : 'bg-danger text-danger'" style="width:52px;height:52px;">
<i class="ti ti-lock fs-5"></i>
</div>

<div>
<div class="fw-semibold">PIN de Acceso</div>
<small class="text-muted">Código utilizado para ingresar</small>
</div>
</div>

<span class="badge rounded-pill"
:class="accesoData.pin ? 'bg-success' : 'bg-danger'"
x-text="accesoData.pin ? accesoData.pin : 'No registrado'">
</span>

</div>

</div>
</template>

<!-- Editar -->
<div x-show="showEditPin && !accesoSoloLectura" x-cloak>
<div class="input-group">
<span class="input-group-text bg-light"><i class="ti ti-key text-primary fs-6"></i></span>

<input type="text" class="form-control form-control" placeholder="Nuevo PIN (mínimo 5 caracteres)" x-model="accesoPinInput"
:class="accesoPinError ? 'is-invalid' : ''" @keydown.enter.prevent="editarPin()">

<button type="button" class="btn btn-success" @click="editarPin()" :disabled="accesoGuardando">
<template x-if="accesoGuardando"><span class="spinner-border spinner-border-sm"></span></template>
<template x-if="!accesoGuardando"><i class="ti ti-check"></i></template>
</button>

<button type="button" class="btn btn-outline-secondary" @click="toggleEditPin()">
<i class="ti ti-x"></i>
</button>

</div>

<template x-if="accesoPinError">
<small
class="text-danger d-block mt-2"
x-text="accesoPinError">
</small>
</template>
</div>

</div>
</div>
</div>

<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
<template x-if="!showEditPin && !accesoSoloLectura">
<button type="button" class="btn btn-primary" @click="toggleEditPin()">Editar PIN</button>
</template>
</div>

</div>
</div>
</div>

</div>
