<?php
$formato = (int)$datos['formato'];
?>

<div class="mt-4 mb-5" x-data="{ ...actions(), ...formatosFormComponent() }"
data-formato="<?= $formato ?>"
data-es-edicion="<?= $esEdicion ? 'true' : 'false' ?>"
data-id-localidad="<?= (int)($datos['id_localidad'] ?? 0) ?>"
data-detalle-id="<?= (int)($datos['detalle_id'] ?? 0) ?>"
data-localidad="<?= htmlspecialchars((string)($datos['nombre_localidad'] ?? '')) ?>"
data-formatos='<?= $datos['formatos_data'] ?? '{}' ?>'>

<div class="row">

<template x-if="esMultiempleado">
<div class="col-12">
<div class="float-end mb-4">
<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModal">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
</div>
</template>

</div>

<div class="row align-items-stretch">

<div class="col-md-8 d-flex">
<div class="card w-100" id="card-formato">

<div class="card-body">

<div class="row">

<div class="col-12 mb-4 text-end">
<div class="">Formato: <span class="text-primary" x-text="cabecera.codigo || ''"></span></div>
<div class="">No. de control: <span class="text-primary" x-text="esEdicion ? (cabecera.no_control || '—') : '—'"></span></div>
<div class="" x-text="'Huixquilucan, Edo. de México a ' + (cabecera.fecha || '')"></div>
</div>

<div class="col-12 mb-4">
<div class="" x-text="cabecera.dirigido_a || ''"></div>
<template x-if="formato === 7">
<p class="mb-0">
<span x-text="cabecera.intro || ''"></span>
<span>, correspondiente al periodo de</span>
<select name="periodo" class="form-select d-inline-block ms-2" style="width: 140px;" @change="valores.periodo = Number($event.target.value)">
<option value="">Selecciona un periodo...</option>
<template x-for="anio in periodos" :key="anio">
<option :value="anio" x-text="anio" :selected="Number(valores.periodo) === Number(anio)"></option>
</template>
</select>
</p>
</template>
<template x-if="formato !== 7">
<p class="mb-0" x-text="cabecera.intro || ''"></p>
</template>
</div>

</div>

<!---------- FORMATOS MULTI-EMPLEADO (1 A 5) ---------->
<template x-if="esMultiempleado">
<div class="table-responsive mb-3">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

<thead>
<tr>
<template x-for="(col, ci) in columnas" :key="'col-' + ci">
<th :class="ci === 1 ? 'text-start' : 'text-center'" x-html="col"></th>
</template>
</tr>
</thead>

<tbody>
<template x-if="filas.length === 0">
<tr>
<td :colspan="colspanTabla" class="text-center text-muted py-4">
<i class="ti ti-users-off me-1"></i> No se encontró información para mostrar
</td>
</tr>
</template>
<template x-for="(fila, i) in filas" :key="'fila-' + i">
<tr>
<td class="text-center" x-text="i + 1"></td>
<td x-show="formato === 1" x-text="fila.nombre || '—'"></td>
<td class="text-center" x-show="formato === 1" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 1" x-text="puestoNombre(fila.puesto)"></td>
<td class="text-center" x-show="formato === 1" x-text="fila.fecha_ingreso || '—'"></td>
<td class="text-center" x-show="formato === 1" x-text="fila.sd !== '' && fila.sd != null ? '$' + formatNum(fila.sd) : '—'"></td>
<td class="text-center" x-show="formato === 1">
<i class="ti ti-file-text text-primary fs-5 pointer" @click="verArchivosEmpleado(fila)" title="Ver documentos del empleado"></i>
</td>
<td x-show="formato === 2" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 2" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.fecha_baja || '—'"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.motivo || '—'"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.detalle || '—'"></td>
<td x-show="formato === 3" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 3" x-text="fila.dias_falta || '—'"></td>
<td class="text-center" x-show="formato === 3" x-text="estacionNombre(fila.id_estacion)"></td>
<td x-show="formato === 4" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 4" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 4" x-text="estacionNombre(fila.id_estacion_cambio)"></td>
<td class="text-center" x-show="formato === 4" x-text="fila.fecha || '—'"></td>
<td x-show="formato === 5" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 5" x-text="puestoPersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.salario_actual !== '' && fila.salario_actual != null ? '$' + formatNum(fila.salario_actual) : '—'"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.salario_ajustado !== '' && fila.salario_ajustado != null ? '$' + formatNum(fila.salario_ajustado) : '—'"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.fecha_aplicacion || '—'"></td>
<td class="text-center">
<span x-data="actions()">
<i
class="ti ti-trash text-danger fs-6 pointer"
@click="
deleteAction({
url: '/departamento-operativo/recursos-humanos/formatos/eliminar-fila',
id: detalleId,
name: formato === 1 ? (fila.nombre || '—') : nombrePersonal(fila.id_personal),
table: null,
data: {
formato: formato,
fila_id: fila.id
}
}).then(r => {
if (r?.success) {
filas.splice(i, 1);
}
})
"
title="Quitar empleado"
></i>
</span>
</td>
</tr>
</template>
</tbody>
</table>
</div>

<template x-if="filas.length === 0">
<div class="alert alert-warning text-center mb-3" role="alert">
<i class="ti ti-alert-triangle me-1"></i> ¡Aun no es posible finalizar! Se debe de agregar el registro del personal.
</div>
</template>
</template>

<!---------- FORMATOS DE UN EMPLEADO (6 Y 7) ---------->
<template x-if="formato === 6">
<div class="col-12">
<div class="table-responsive mb-3">
<table class="table table-bordered mb-0 align-middle">

<tr>
<th class=" bg-primary text-white text-center">Área o Departamento</th>
<th class=" bg-primary text-white text-center">Nombre del empleado</th>
<th class=" bg-primary text-white text-center">Número de días a disfrutar</th>
</tr>

<tr>
<td class="text-center align-middle" x-text="localidad || '—'"></td>
<td class="text-center align-middle p-0">
<div class="select2-modal-field is-select2-pending " x-ref="f6PersonalWrapper">
<select name="id_personal" x-ref="f6PersonalSelect" class="form-select border-0 p-3 w-100 h-100">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo" :selected="Number(valores.id_personal) === Number(p.id)"></option>
</template>
</select>
</div>
</td>
<td class="text-center align-middle p-0">
<input type="number" min="1" name="num_dias" class="form-control text-center p-3 border-0" :value="valores.num_dias || ''">
</td>
</tr>

<tr>
<th class="bg-primary text-white text-center">Del:</th>
<th class="bg-primary text-white text-center">Al:</th>
<th class="bg-primary text-white text-center">Regresando el:</th>
</tr>
<tr>
<td class="text-center align-middle p-0">
<input type="date" name="fecha_inicio" class="form-control text-center p-3 border-0" :value="valores.fecha_inicio || ''">
</td>
<td class="text-center align-middle p-0">
<input type="date" name="fecha_termino" class="form-control text-center p-3 border-0" :value="valores.fecha_termino || ''">
</td>
<td class="text-center align-middle p-0">
<input type="date" name="fecha_regreso" class="form-control text-center p-3 border-0" :value="valores.fecha_regreso || ''">
</td>
</tr>
<tr>
<th class="bg-primary text-white" colspan="3">Observaciones:</th>
</tr>
<tr>
<td colspan="3" class="p-0">
<textarea name="observaciones" class="form-control border-0" rows="5" x-text="valores.observaciones || ''"></textarea>
</td>
</tr>
</table>
</div>
</div>
</template>

<template x-if="formato === 7">
<div class="col-12">
<div class="table-responsive mb-3">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Nombre del empleado</th>
<th class="text-center align-middle">Fecha de ingreso</th>
<th class="text-center align-middle">Estación / Departamento</th>
</tr>
</thead>
<tbody>
<tr>
<td class="text-center align-middle">
<div class="select2-modal-field is-select2-pending" x-ref="f7PersonalWrapper">
<select name="id_personal" class="form-select" x-ref="f7PersonalSelect">
<option value="">Selecciona un colaborador...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo" :selected="Number(valores.id_personal) === Number(p.id)"></option>
</template>
</select>
</div>
</td>
<td class="text-center align-middle" x-text="fechaIngresoSeleccionado() || '—'"></td>
<td class="text-center align-middle" x-text="localidad || '—'"></td>
</tr>
</tbody>
</table>
</div>
</div>
</template>

<!---------- FIRMA DE QUIEN ELABORA (SIGNATURE PAD) ---------->
<div class="col-12 text-center mt-4"><p>Sin más por el momento quedo de usted.</p><hr></div>

<div class="float-end">
<button type="button" class="btn btn-success" @click="guardar()" :disabled="guardando"><i class="ti ti-check me-1"></i> <?= $esEdicion ? 'Guardar' : 'Actualizar' ?></button>
</div>

</div>

</div>
</div>

<div class="col-md-4 d-flex">
<div class="card w-100">

<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white">
<i class="fa-solid fa-signature me-2"></i>
FIRMA DE QUIEN ELABORA
</h5>

<button type="button"
class="btn btn-danger btn-sm"
@click="limpiarFirma()">
<i class="ti ti-eraser me-1"></i>
Limpiar firma
</button>
</div>
</div>

<div class="card-body p-0 d-flex">

<div id="signature-pad"
class="signature-pad border-0 w-100 d-flex">

<div class="signature-pad--body w-100 d-flex">

<canvas
id="canvas"
style="
width: 100%;
height: 100%;
min-height: 0;
display: block;
cursor: crosshair;
touch-action: none;
">
</canvas>

</div>

<input
type="hidden"
name="firma_elaboro"
id="firma_elaboro"
value="">

</div>

</div>

</div>
</div>

</div>

<template x-if="esMultiempleado">
<!---------- MODAL AGREGAR EMPLEADO (FORMATOS 1 A 5) ---------->
<div class="modal fade" id="modalAgregarFila" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-user-plus"></i>
<span x-text="formato === 1 ? 'Personal' : 'Empleado'"></span>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">

<div class="row" x-show="formato === 1" data-formato-fila="1">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Nombre completo:</label>
<input type="text" class="form-control" x-model="fila.nombre" data-campo="nombre">
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Puesto:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f1PuestoWrapper">
<select class="form-select" x-ref="f1PuestoSelect" data-campo="puesto">
<option value="">Selecciona un puesto...</option>
<template x-for="p in puestos" :key="p.id">
<option :value="p.id" x-text="p.puesto"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Fecha de alta:</label>
<input type="date" class="form-control" x-model="fila.fecha_ingreso" data-campo="fecha_ingreso">
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Salario diario:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="fila.sd" data-campo="sd">
</div>
<div class="col-12 mb-3">
<div class="alert alert-primary mb-0" role="alert">Documentación a anexar</div>
</div>
<template x-for="arch in archivosAlta" :key="arch.campo">
<div class="col-md-6 mb-3" x-show="arch.campo !== 'c_antecedentes' || fila.puesto == 4">
<label class="form-label fw-semibold" x-text="(arch.campo === 'a_infonavit' ? '' : '* ') + arch.label + (arch.campo === 'a_infonavit' ? ' (Opcional)' : '')"></label>
<input type="file" class="form-control" :data-campo="arch.campo" @change="fila.archivos[arch.campo] = $event.target.files[0] || null">
</div>
</template>
</div>

<div class="row" x-show="formato === 2" data-formato-fila="2">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Nombre del empleado:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f2PersonalWrapper">
<select class="form-select" x-ref="f2PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Fecha de aplicación de baja:</label>
<input type="date" class="form-control" x-model="fila.fecha_baja" data-campo="fecha_baja">
</div>
<div class="col-12 mb-3">
<label class="form-label fw-semibold">* Causa:</label>
<input type="text" class="form-control" list="motivosBaja" x-model="fila.motivo" data-campo="motivo">
<datalist id="motivosBaja">
<template x-for="m in motivos" :key="m">
<option :value="m"></option>
</template>
</datalist>
</div>
<div class="col-12 mb-3">
<label class="form-label fw-semibold">* Motivo:</label>
<textarea class="form-control" rows="3" x-model="fila.detalle"></textarea>
</div>
</div>

<div class="row" x-show="formato === 3" data-formato-fila="3">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Nombre del empleado:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f3PersonalWrapper">
<select class="form-select" x-ref="f3PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Día faltante:</label>
<input type="date" class="form-control" x-model="fila.dias_falta" data-campo="dias_falta">
</div>
</div>

<div class="row" x-show="formato === 4" data-formato-fila="4">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Nombre del empleado:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f4PersonalWrapper">
<select class="form-select" x-ref="f4PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Cambio a:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f4EstacionWrapper">
<select class="form-select" x-ref="f4EstacionSelect" data-campo="id_estacion_cambio">
<option value="">Selecciona una localidad...</option>
<template x-for="e in estacionesCambio" :key="e.id">
<option :value="e.id" x-text="e.nombre"></option>
</template>
</select>
</div>
</div>
<div class="col-12 mb-3">
<label class="form-label fw-semibold">* Fecha de aplicación:</label>
<input type="date" class="form-control" x-model="fila.fecha" data-campo="fecha">
</div>
</div>

<div class="row" x-show="formato === 5" data-formato-fila="5">
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Nombre del empleado:</label>
<div class="select2-modal-field is-select2-pending" x-ref="f5PersonalWrapper">
<select class="form-select" x-ref="f5PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" :data-sd="p.sd" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Salario actual:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="fila.salario_actual" data-campo="salario_actual" disabled>
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Ajuste a:</label>
<input type="number" step="0.01" min="0" class="form-control" x-model="fila.salario_ajustado" data-campo="salario_ajustado">
</div>
<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">* Aplicar a parte del:</label>
<input type="date" class="form-control" x-model="fila.fecha_aplicacion" data-campo="fecha_aplicacion">
</div>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cancelar</button>
<button type="button" class="btn btn-success" @click="agregarFila()" :disabled="guardandoFila">
<template x-if="guardandoFila"><span class="spinner-border spinner-border-sm me-1"></span></template>
<template x-if="!guardandoFila"><i class="ti ti-check me-1"></i></template>
Guardar
</button>
</div>
</div>
</div>
</div>
</template>

<template x-if="formato === 1">
<!---------- MODAL ARCHIVOS POR EMPLEADO (FORMATO 1) ---------->
<div class="modal fade" id="modalArchivosEmpleado" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-file-text me-1"></i> Documentación de <span class="fw-bold" x-text="archivosEmpleadoNombre || '—'"></span></h5>
</h4>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="modal-body">
<template x-if="archivosEmpleado.length === 0">
<div class="text-center text-muted py-4">
<i class="ti ti-files-off"></i>
<p class="mb-0 mt-2">El empleado no tiene archivos adjuntos</p>
</div>
</template>
<template x-if="archivosEmpleado.length > 0">
<div class="table-responsive">

</template>
</div>
<div class="modal-footer">
<button class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x"></i> Cerrar</button>
</div>
</div>
</div>
</div>
</template>

</div>
