<div id="container" class="mt-4 mb-5"
data-id-usuario="<?= $registro['id_usuario'] ?>"
data-id-estacion="<?= $registro['id_estacion'] ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? 'formato-descarga-merma', ENT_QUOTES, 'UTF-8') ?>"
data-adjuntos='<?= htmlspecialchars(json_encode($adjuntos ?? []), ENT_QUOTES, 'UTF-8') ?>'
data-registro='<?= htmlspecialchars(json_encode($registro, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
x-data="{ ...actions(), ...mermaFormEditar() }">

<style>
.form-check.is-invalid .form-check-input { border-color: var(--bs-danger); box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, .25); }
.form-check.is-invalid .form-check-label { color: var(--bs-danger); }
</style>

<div class="row">

<div class="col-12">
    <div class="d-flex justify-content-end align-items-center mb-3">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success d-inline-flex align-items-center gap-1" :disabled="guardando" @click="guardarEditar()">
                <span x-show="guardando" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <i class="ti ti-check"></i> Actualizar
            </button>
        </div>
    </div>
</div>

<!---------- CARD DATOS GENERALES ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DATOS GENERALES</h4>
</div>

<div class="card-body">

<div class="row g-3 mb-4">
<div class="col-12 col-md-3">
<label class="form-label">Estación de descarga:</label>
<div class=""><?= htmlspecialchars($registro['estacion']) ?></div>
</div>
<div class="col-12 col-md-3">
<label class="form-label">Responsable de la estación:</label>
<div class=""><?= htmlspecialchars($registro['responsable']) ?></div>
</div>
<div class="col-12 col-md-6">
<label class="form-label">Fecha y hora de la descarga de full: <span class="text-danger">*</span></label>
<div class="row g-2">
<div class="col-12 col-md-6">
<input type="date" class="form-control" x-model="form.fecha_llegada" required>
</div>
<div class="col-12 col-md-6">
<input type="time" class="form-control" x-model="form.hora_llegada" required>
</div>
</div>
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-12 col-md-6">
<label class="form-label">Producto recibido: <span class="text-danger">*</span></label>
<select class="form-select" x-model="form.producto" required>
<option value=""></option>
<option value="87 oct">87 oct</option>
<option value="91 oct">91 oct</option>
<option value="Diesel">Diesel</option>
</select>
</div>

<div class="col-12 col-md-6">
<label class="form-label">Número de factura o remisión: <span class="text-danger">*</span></label>
<input type="text" class="form-control" x-model="form.no_factura_remision" placeholder="Nº de factura o remisión">
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-12 col-md-3">
<label class="form-label">Litros: <span class="text-danger">*</span></label>
<input type="number" step="any" min="0" class="form-control" x-model.number="form.litros" @input="calcularMerma()">
</div>
<div class="col-12 col-md-3">
<label class="form-label">Precio por litro: <span class="text-danger">*</span></label>
<input type="number" step="any" min="0" class="form-control" x-model.number="form.precio_litro">
</div>
<div class="col-12 col-md-3">
<label class="form-label">Cuenta litros: <span class="text-danger">*</span></label>
<input type="number" step="any" min="0" class="form-control" x-model.number="form.cuenta_litros" @input="calcularMerma()">
</div>
<div class="col-12 col-md-3">
<label class="form-label">Merma (Lts):</label>
<div class="fw-bold text-success fs-5" x-text="isFinite(form.merma) ? Number(form.merma).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00'"></div>
</div>
</div>

<div class="row g-3 mb-3">
<div class="col-12 col-md-3">
<label class="form-label">Unidad: <span class="text-danger">*</span></label>
<input type="text" class="form-control" x-model="form.unidad" placeholder="Ej. Camión, Auto-tanque, etc.">
</div>
<div class="col-12 col-md-6">
<label class="form-label">Nombre del operador de la unidad: <span class="text-danger">*</span></label>
<input type="text" class="form-control" x-model="form.operador" placeholder="Nombre del operador">
</div>
<div class="col-12 col-md-3">
<label class="form-label">Compañia a la que pertenece el transportista: <span class="text-danger">*</span></label>
<input type="text" class="form-control" x-model="form.transportista" placeholder="Compañía">
</div>
</div>

<div class="row g-3">

<div class="col-12 col-12">
<label class="form-label">Factura o Nota de Remisión: <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_no_factura">
</div>

<div class="col-12 col-md-6 text-center">
<label class="form-label mt-2">Sellos Alterados: <span class="text-danger">*</span></label>
<div class="d-flex justify-content-center gap-4">
<div class="form-check">
<input class="form-check-input" type="radio" value="Si" id="sellosSi" x-model="form.sellos">
<label class="form-check-label" for="sellosSi">SI</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" value="No" id="sellosNo" x-model="form.sellos">
<label class="form-check-label" for="sellosNo">NO</label>
</div>
</div>
</div>

<div class="col-12 col-md-6 text-center">
<label class="form-label mt-2">Se detuvo la venta durante la descarga: <span class="text-danger">*</span></label>
<div class="d-flex justify-content-center gap-4">
<div class="form-check">
<input class="form-check-input" type="radio" value="Si" id="detuvoSi" x-model="form.detuvo_venta">
<label class="form-check-label" for="detuvoSi">SI</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" value="No" id="detuvoNo" x-model="form.detuvo_venta">
<label class="form-check-label" for="detuvoNo">NO</label>
</div>
</div>
</div>

</div>
</div>

</div>
</div>

<!---------- DOCUMENTACION ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4 class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DOCUMENTACIÓN</h4>
</div>

<div class="card-body">

<div class="row g-3 mb-4">
<div class="col-12 col-md-4">
<label class="form-label">Reporte de inventario inicial (con fecha y hora): <span class="text-danger">*</span></label>
<template x-if="adjuntos.inventario_inicial && adjuntos.inventario_inicial.url">
<div class="mb-1">
<a :href="adjuntos.inventario_inicial.url" target="_blank" class="text-decoration-none small">
<i class="ti ti-file me-1"></i><span x-text="adjuntos.inventario_inicial.archivo"></span>
</a>
</div>
</template>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_inventario_inicial">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Medida Nice: <span class="text-danger">*</span></label>
<template x-if="adjuntos.nice && adjuntos.nice.url">
<div class="mb-1">
<a :href="adjuntos.nice.url" target="_blank" class="text-decoration-none small">
<i class="ti ti-file me-1"></i><span x-text="adjuntos.nice.archivo"></span>
</a>
</div>
</template>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_nice">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Reporte de inventario final (con fecha y hora): <span class="text-danger">*</span></label>
<template x-if="adjuntos.inventario_final && adjuntos.inventario_final.url">
<div class="mb-1">
<a :href="adjuntos.inventario_final.url" target="_blank" class="text-decoration-none small">
<i class="ti ti-file me-1"></i><span x-text="adjuntos.inventario_final.archivo"></span>
</a>
</div>
</template>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_inventario_final">
</div>
</div>
<div class="row g-3 mb-4">
<div class="col-12 col-md-4">
<label class="form-label">Metro contador (Temperatura Normal): <span class="text-danger">*</span></label>
<template x-if="adjuntos.metro_contador && adjuntos.metro_contador.url">
<div class="mb-1">
<a :href="adjuntos.metro_contador.url" target="_blank" class="text-decoration-none small">
<i class="ti ti-file me-1"></i><span x-text="adjuntos.metro_contador.archivo"></span>
</a>
</div>
</template>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_metro_contador">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Metro contador (a 20 °C): <span class="text-danger">*</span></label>
<template x-if="adjuntos.metro_contador20 && adjuntos.metro_contador20.url">
<div class="mb-1">
<a :href="adjuntos.metro_contador20.url" target="_blank" class="text-decoration-none small">
<i class="ti ti-file me-1"></i><span x-text="adjuntos.metro_contador20.archivo"></span>
</a>
</div>
</template>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_metro_contador20">
</div>
</div>

</div>
</div>
</div>

</div>
</div>
