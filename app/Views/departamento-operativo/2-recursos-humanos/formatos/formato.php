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

<div class="row g-0 ms-auto w-100 justify-content-end mb-3 mt-3">
                    <div class="col-12 col-md-auto d-grid ">
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

<div class="card-body pb-1">

<div class="row">

<div class="col-12 mb-4 text-end">
<div><div class="form-label mb-1">Formato:</div><div class="text-primary" x-text="cabecera.codigo || ''"></div></div>
<div><div class="form-label mb-1">No. de control:</div><div class="text-primary" x-text="esEdicion ? (cabecera.no_control || 'Sin información') : 'Sin información'"></div></div>
<div class="" x-text="'Huixquilucan, Edo. de México a ' + (cabecera.fecha || '')"></div>
</div>

<div class="col-12 mb-4">
<div class="" x-text="cabecera.dirigido_a || ''"></div>
<template x-if="formato === 7">
<div class="d-flex flex-wrap align-items-center gap-2 mt-2">
<span x-text="cabecera.intro || ''"></span><br>
<label for="formato-periodo" class="mb-0">Correspondiente al periodo de:</label>
<div class="position-relative d-inline-block">
<select id="formato-periodo" name="periodo" class="form-select"  @change="valores.periodo = Number($event.target.value); campoFormato7Modificado = 'periodo'">
<option value="">Selecciona un periodo...</option>
<template x-for="anio in periodos" :key="anio">
<option :value="anio" x-text="anio" :selected="Number(valores.periodo) === Number(anio)"></option>
</template>
</select>
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-4" x-show="campoFormato7Guardado === 'periodo'" x-cloak aria-label="Cambio guardado"></i>
</div>
</div>
</template>
<template x-if="formato !== 7">
<p class="mb-0" x-text="cabecera.intro || ''"></p>
</template>
</div>

</div>

<!---------- FORMATOS MULTI-EMPLEADO (1 A 5) ---------->
<template x-if="esMultiempleado">
<div class="table-responsive overflow-x-auto overflow-y-hidden mb-3">
<table class="table table-striped table-bordered mb-0 align-middle formato-edicion-seleccionable portal-table portal-table--selectable">

<thead>
<tr>
<template x-for="(col, ci) in columnas" :key="'col-' + ci">
<th :class="ci === 1 ? 'text-start align-middle' : 'text-center align-middle'">
<template x-if="col.includes('ti-file-text')">
<i class="ti ti-file-text text-primary fs-5"></i>
</template>
<template x-if="col.includes('ti-trash')">
<i class="ti ti-trash text-danger fs-5"></i>
</template>
<template x-if="!col.includes('ti-file-text') && !col.includes('ti-trash')">
<span x-text="col"></span>
</template>
</th>
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
<tr class="pointer" :class="{ selected: selectedFilaIndex === i }" @click="selectedFilaIndex = i">
<td class="text-center" x-text="i + 1"></td>
<td x-show="formato === 1" x-text="fila.nombre || 'Sin información'"></td>
<td class="text-center" x-show="formato === 1" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 1" x-text="puestoNombre(fila.puesto)"></td>
<td class="text-center" x-show="formato === 1" x-text="fila.fecha_ingreso || 'Sin información'"></td>
<td class="text-center" x-show="formato === 1" x-text="fila.sd !== '' && fila.sd != null ? '$' + formatNum(fila.sd) : 'Sin información'"></td>
<td class="text-center" x-show="formato === 1">
<i class="ti ti-file-text text-primary fs-5 pointer" @click="verArchivosEmpleado(fila)" title="Ver documentos del empleado"></i>
</td>
<td x-show="formato === 2" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 2" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.fecha_baja || 'Sin información'"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.motivo || 'Sin información'"></td>
<td class="text-center" x-show="formato === 2" x-text="fila.detalle || 'Sin información'"></td>
<td x-show="formato === 3" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 3" x-text="fila.dias_falta || 'Sin información'"></td>
<td class="text-center" x-show="formato === 3" x-text="estacionNombre(fila.id_estacion)"></td>
<td x-show="formato === 4" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 4" x-text="estacionNombre(fila.id_estacion)"></td>
<td class="text-center" x-show="formato === 4" x-text="estacionNombre(fila.id_estacion_cambio)"></td>
<td class="text-center" x-show="formato === 4" x-text="fila.fecha || 'Sin información'"></td>
<td x-show="formato === 5" x-text="nombrePersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 5" x-text="puestoPersonal(fila.id_personal)"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.salario_actual !== '' && fila.salario_actual != null ? '$' + formatNum(fila.salario_actual) : 'Sin información'"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.salario_ajustado !== '' && fila.salario_ajustado != null ? '$' + formatNum(fila.salario_ajustado) : 'Sin información'"></td>
<td class="text-center" x-show="formato === 5" x-text="fila.fecha_aplicacion || 'Sin información'"></td>
<td class="text-center">


<span x-data="actions()">
<i class="ti ti-trash text-danger fs-6 pointer"
@click="deleteAction({
url: '/departamento-operativo/recursos-humanos/formatos/eliminar-fila',
id: detalleId,
name: formato === 1 ? (fila.nombre || 'Sin información') : nombrePersonal(fila.id_personal),
table: null,
data: {
formato: formato,
fila_id: fila.id
}}).then(r => {
if (r?.success) {
filas.splice(i, 1); if (selectedFilaIndex === i) selectedFilaIndex = null; else if (selectedFilaIndex !== null && selectedFilaIndex > i) selectedFilaIndex--;}})"
title="Quitar empleado"></i>
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
<table class="table table-striped table-bordered align-middle" data-portal-table-selection="false">

<tr>
<th class="text-center">Área o Departamento</th>
<th class="text-center">Nombre del empleado</th>
<th class="text-center">Número de días a disfrutar</th>
</tr>

<tr>
<td class="text-center align-middle" x-text="localidad || 'Sin información'"></td>
<td class="text-center align-middle p-0">
    <!-- 1. Quitamos 'select2-form-bordered' y agregamos 'select2-sin-bordes' y 'w-100 h-100' -->
    <div class="select2-modal-field select2-sin-bordes is-select2-pending position-relative w-100 h-100" x-ref="f6PersonalWrapper">
        
        <!-- 2. El select puede quedar limpio, Select2 lo va a ocultar de todos modos -->
        <select name="id_personal" x-ref="f6PersonalSelect" class="form-select w-100 h-100">
            <option value="">Selecciona un empleado...</option>
            <template x-for="p in personal" :key="p.id">
                <option :value="p.id" x-text="p.nombre_completo" :selected="Number(valores.id_personal) === Number(p.id)"></option>
            </template>
        </select>
        
        <i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="autosaveGuardado && campoAutosaveActivo === 'id_personal'" x-cloak aria-label="Cambio guardado"></i>
    </div>
</td>
<td class="text-center align-middle p-0">
<div class="position-relative">
<input type="text" inputmode="numeric" name="num_dias" class="form-control text-center p-3 pe-5 border-0" :value="formatearNumeroEntrada(valores.num_dias || '', false)" @input="actualizarNumeroCampo($event.target, false)">
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="autosaveGuardado && campoAutosaveActivo === 'num_dias'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
</tr>

<tr>
<th class="text-center">Del:</th>
<th class="text-center">Al:</th>
<th class="text-center">Regresando el:</th>
</tr>
<tr>
<td class="text-center align-middle p-0">
<div class="position-relative">
<input type="date" name="fecha_inicio" class="form-control text-center p-3 border-0" :value="valores.fecha_inicio || ''">
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="autosaveGuardado && campoAutosaveActivo === 'fecha_inicio'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
<td class="text-center align-middle p-0">
<div class="position-relative">
<input type="date" name="fecha_termino" class="form-control text-center p-3 border-0" :value="valores.fecha_termino || ''">
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="autosaveGuardado && campoAutosaveActivo === 'fecha_termino'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
<td class="text-center align-middle p-0">
<div class="position-relative">
<input type="date" name="fecha_regreso" class="form-control text-center p-3 border-0" :value="valores.fecha_regreso || ''">
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="autosaveGuardado && campoAutosaveActivo === 'fecha_regreso'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
</tr>
<tr>
<th  colspan="3">Observaciones:</th>
</tr>
<tr>
<td colspan="3" class="p-0">
<div class="position-relative">
<textarea name="observaciones" class="form-control border-0 pe-5" placeholder="Escriba sus observaciones aqui" rows="5" x-text="valores.observaciones || ''"></textarea>
<i class="ti ti-check text-success position-absolute top-0 end-0 mt-3 me-3" x-show="autosaveGuardado && campoAutosaveActivo === 'observaciones'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
</tr>
</table>
</div>
</div>
</template>

<template x-if="formato === 7">
<div class="col-12">
<div class="table-responsive mb-3">
<table class="table table-striped table-bordered mb-0 align-middle formato-prima-vacacional" data-portal-table-selection="false">
<thead>
<tr>
<th class="text-center align-middle" style="width: 40%;">Nombre del empleado</th>
<th class="text-center align-middle" style="width: 30%;">Fecha de ingreso</th>
<th class="text-center align-middle" style="width: 30%;">Estación / Departamento</th>

</tr>
</thead>
<tbody>
<tr>
<td class="text-center align-middle p-0">
<!-- Se agrega w-100 y h-100 para que el div ocupe toda la celda -->
<div class="select2-modal-field position-relative w-100 h-100" x-ref="f7PersonalWrapper">

<!-- Agregamos: border-0, shadow-none, w-100 -->
<select name="id_personal" class="form-select p-3 bg-transparent border-0 shadow-none w-100" x-ref="f7PersonalSelect">
<option value="">Selecciona un colaborador...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo" :selected="Number(valores.id_personal) === Number(p.id)"></option>
</template>
</select>

<!-- El ícono se mantiene igual. me-5 (48px) está perfecto para no chocar con la flecha del select -->
<i class="ti ti-check text-success position-absolute top-50 end-0 translate-middle-y me-5" x-show="campoFormato7Guardado === 'id_personal'" x-cloak aria-label="Cambio guardado"></i>
</div>
</td>
<td class="text-center align-middle" x-text="fechaIngresoSeleccionado() || 'Sin información'"></td>
<td class="text-center align-middle" x-text="localidad || 'Sin información'"></td>
</tr>
</tbody>
</table>
</div>
</div>
</template>

<!---------- FIRMA DE QUIEN ELABORA (SIGNATURE PAD) ---------->
<div class="col-12 text-center mt-4"><p>Sin más por el momento quedo de usted.</p></div>
</div>

<div class="card-footer">
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <div class="col-12 col-md-auto d-grid">
                      <button type="button" class="btn btn-success float-end" @click="guardar()" :disabled="guardando"><i class="ti ti-check me-1"></i> <?= $esEdicion ? 'Guardar' : 'Actualizar' ?></button>
                    </div>
    </div>

</div>

</div>
</div>

<div class="col-md-4 d-flex">
<div class="card border-0 bg-white w-100 d-flex flex-column">

    <!-- Encabezado con ícono circular -->
    <div class="card-header text-bg-primary py-3 border-0 flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
                                            <i class="ti ti-signature fs-6"></i>
                </div>
                <div class="ms-3">
                    <h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Cuerpo adaptable que ocupa todo el alto restante -->
    <div class="card-body p-3 flex-fill d-flex flex-column">
        <div id="signature-pad" class="signature-pad-wrapper flex-fill d-flex" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
            <div class="signature-pad--body w-100 h-100 d-flex">
                <canvas 
                    id="canvas" 
                    style="
                        width: 100%; 
                        height: 100%; 
                        min-height: 0; 
                        display: block; 
                        touch-action: none;
                    ">
                </canvas>
            </div>
        </div>
        <input type="hidden" name="firma_elaboro" id="firma_elaboro" value="">
    </div>

    <!-- Botón fijo a lo ancho en la base -->
    <button 
        type="button" 
        class="btn bg-danger-subtle text-danger w-100 rounded-top-0 flex-shrink-0" 
        style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;" 
        @click="limpiarFirma()">
        <i class="ti ti-eraser me-1"></i> Limpiar firma
    </button>

</div>

</div>

<template x-if="esMultiempleado">


<!---------- MODAL AGREGAR EMPLEADO (FORMATOS 1 A 5) ---------->
<div class="modal fade" id="modalAgregarFila" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"  data-bs-keyboard="false">
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
<label class="form-label mb-1">* Nombre completo:</label>
<input type="text" class="form-control" x-model="fila.nombre" data-campo="nombre">
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Puesto:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f1PuestoWrapper">
<select class="form-select" x-ref="f1PuestoSelect" data-campo="puesto">
<option value="">Selecciona un puesto...</option>
<template x-for="p in puestos" :key="p.id">
<option :value="p.id" x-text="p.puesto"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Fecha de alta:</label>
<input type="date" class="form-control" x-model="fila.fecha_ingreso" data-campo="fecha_ingreso">
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Salario diario:</label>
<input type="text" inputmode="decimal" class="form-control" :value="formatearNumeroEntrada(fila.sd, true)" @input="actualizarNumeroFila('sd', $event.target, true)" data-campo="sd">
</div>
<div class="col-12 mb-3">
<div class="alert alert-primary mb-0" role="alert">Documentación a anexar</div>
</div>
<template x-for="arch in archivosAlta" :key="arch.campo">
<div class="col-md-6 mb-3" x-show="arch.campo !== 'c_antecedentes' || fila.puesto == 4">
<label class="form-label mb-1" x-text="(arch.campo === 'a_infonavit' ? '' : '* ') + arch.label + (arch.campo === 'a_infonavit' ? ' (Opcional)' : '')"></label>
<input type="file" class="form-control" :data-campo="arch.campo" @change="fila.archivos[arch.campo] = $event.target.files[0] || null">
</div>
</template>
</div>

<div class="row" x-show="formato === 2" data-formato-fila="2">
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Nombre del empleado:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f2PersonalWrapper">
<select class="form-select" x-ref="f2PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Fecha de aplicación de baja:</label>
<input type="date" class="form-control" x-model="fila.fecha_baja" data-campo="fecha_baja">
</div>
<div class="col-12 mb-3">
<label class="form-label mb-1">* Causa:</label>
<input type="text" class="form-control" list="motivosBaja" x-model="fila.motivo" data-campo="motivo">
<datalist id="motivosBaja">
<template x-for="m in motivos" :key="m">
<option :value="m"></option>
</template>
</datalist>
</div>
<div class="col-12 mb-3">
<label class="form-label mb-1">* Motivo:</label>
<textarea class="form-control" rows="3" x-model="fila.detalle"></textarea>
</div>
</div>

<div class="row" x-show="formato === 3" data-formato-fila="3">
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Nombre del empleado:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f3PersonalWrapper">
<select class="form-select" x-ref="f3PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Día faltante:</label>
<input type="date" class="form-control" x-model="fila.dias_falta" data-campo="dias_falta">
</div>
</div>

<div class="row" x-show="formato === 4" data-formato-fila="4">

<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Nombre del empleado:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f4PersonalWrapper">
<select class="form-select" x-ref="f4PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>


<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Cambio a:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f4EstacionWrapper">
<select class="form-select" x-ref="f4EstacionSelect" data-campo="id_estacion_cambio">
<option value="">Selecciona una localidad...</option>
<template x-for="e in estacionesCambio" :key="e.id">
<option :value="e.id" x-text="e.nombre"></option>
</template>
</select>
</div>
</div>
<div class="col-12 mb-3">
<label class="form-label mb-1">* Fecha de aplicación:</label>
<input type="date" class="form-control" x-model="fila.fecha" data-campo="fecha">
</div>
</div>

<div class="row" x-show="formato === 5" data-formato-fila="5">
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Nombre del empleado:</label>
<div class="select2-modal-field select2-form-bordered is-select2-pending" x-ref="f5PersonalWrapper">
<select class="form-select" x-ref="f5PersonalSelect" data-campo="id_personal">
<option value="">Selecciona un empleado...</option>
<template x-for="p in personal" :key="p.id">
<option :value="p.id" :data-sd="p.sd" x-text="p.nombre_completo"></option>
</template>
</select>
</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Salario actual:</label>
<input type="text" inputmode="decimal" class="form-control" :value="formatearNumeroEntrada(fila.salario_actual, true)" data-campo="salario_actual" disabled>
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Ajuste a:</label>
<input type="text" inputmode="decimal" class="form-control" :value="formatearNumeroEntrada(fila.salario_ajustado, true)" @input="actualizarNumeroFila('salario_ajustado', $event.target, true)" data-campo="salario_ajustado">
</div>
<div class="col-md-6 mb-3">
<label class="form-label mb-1">* Aplicar a parte del:</label>
<input type="date" class="form-control" x-model="fila.fecha_aplicacion" data-campo="fecha_aplicacion">
</div>
</div>

</div>
<div class="modal-footer">
<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" 
class="btn bg-danger-subtle text-danger" 
data-bs-dismiss="modal">
<i class="ti ti-x"></i> 
Cancelar
</button>
                    </div>
                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button"
 class="btn btn-success"
  @click="agregarFila()" 
  :disabled="guardandoFila">
<template x-if="guardandoFila">
    <span class="spinner-border spinner-border-sm me-1">
    </span>
</template>
<template x-if="!guardandoFila">
    <i class="ti ti-check me-1"></i>
</template>
Guardar
</button>
                    </div>
                  </div>





</div>
</div>
</div>
</div>
</template>

<template x-if="formato === 1">
<!---------- MODAL ARCHIVOS POR EMPLEADO (FORMATO 1) ---------->
<div class="modal fade" id="modalArchivosEmpleado" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"  data-bs-keyboard="false">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">

<div class="modal-header bg-primary">
<h4 class="modal-title text-white d-flex align-items-center gap-2">
<i class="ti ti-file-text me-1"></i> Documentación de <span class="fw-bold" x-text="archivosEmpleadoNombre || 'Sin información'"></span></h5>
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
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th>Documento</th>
<th><i class="ti ti-download text-primary fs-6"></i>
</th>
</tr>
</thead>
<tbody>
<template x-for="(documento, indice) in archivosEmpleado" :key="documento.campo + '-' + indice">
<tr>
<td>
<div class="d-flex align-items-center justify-content-between gap-3">
<span x-text="documento.label"></span>
<i x-show="documento.disponible" class="ti ti-download text-primary fs-6 pointer" role="button" tabindex="0" title="Descargar documento" @click="descargarArchivoEmpleado(documento)" @keydown.enter.prevent="descargarArchivoEmpleado(documento)"></i>
<i x-show="!documento.disponible" class="ti ti-file-off text-muted fs-6" title="Archivo no disponible"></i>
</div>
</td>
</tr>
</template>
</tbody>
</table>
</div>
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
