<div id="container" class="mt-4 mb-5"
data-id-usuario="<?= $idUsuario ?>"
data-id-estacion="<?= $idEstacion ?>"
data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
x-data="{ ...actions(), ...mermaForm() }">

<style>
.form-check.is-invalid .form-check-input { border-color: var(--bs-danger); box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, .25); }
.form-check.is-invalid .form-check-label { color: var(--bs-danger); }
.signature-pad-wrapper.is-invalid { border-color: var(--bs-danger) !important; }
</style>

<div class="row">

<!---------- BOTON DE FINALIZAR ---------->
<div class="col-12">
<div class="d-flex justify-content-end mb-3">
<button type="button" class="btn btn-success d-inline-flex align-items-center gap-1" :disabled="guardando" @click="guardarNuevo()">
<!-- Spinner cuando guarda -->
<span x-show="guardando" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>

<i class="ti ti-check"></i>
Finalizar

</button>
</div>
</div>

<!---------- CARD DATOS GENERALES ---------->
<div class="col-12">
<div class="card">

<div class="card-header bg-primary">
<h4  class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DATOS GENERALES</h4>
</div>

<div class="card-body">

<div class="row g-3 mb-4">
<div class="col-12 col-md-3">
<label class="form-label">Estación de descarga:</label>
<div class=""><?= htmlspecialchars($nombreEstacion) ?></div>
</div>
<div class="col-12 col-md-3">
<label class="form-label">Responsable de la estación:</label>
<div class=""><?= htmlspecialchars($nombreResponsable) ?></div>
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
<h4  class="mb-0 text-white card-title"><i class="ti ti-file-text"></i> DOCUMENTACIÓN</h4>
</div>

<div class="card-body">

<div class="row g-3 mb-4">
<div class="col-12 col-md-4">
<label class="form-label">Reporte de inventario inicial (con fecha y hora): <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_inventario_inicial">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Medida Nice: <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_nice">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Reporte de inventario final (con fecha y hora): <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_inventario_final">
</div>
</div>
<div class="row g-3 mb-4">
<div class="col-12 col-md-4">
<label class="form-label">Metro contador (Temperatura Normal): <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_metro_contador">
</div>
<div class="col-12 col-md-4">
<label class="form-label">Metro contador (a 20 °C): <span class="text-danger">*</span></label>
<input type="file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" x-ref="file_metro_contador20">
</div>
</div>

</div>
</div>
</div>


<!---------- CARD DE FIRMAS ---------->
<div class="col-12">
<div class="row g-3">

<!-- Card Firma Encargado -->
<div class="col-12 col-md-6">
<div class="card border-0 bg-white">

<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
<i class="ti ti-signature  fs-6"></i>
</div>
<div class="ms-3"><h5 class="mb-0 text-white">FIRMA DEL ENCARGADO</h5></div>
</div>
</div>
</div>
<div class="card-body p-3">
<!-- Pad con borde punteado en los 4 lados -->
<div class="signature-pad-wrapper" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
<div class="signature-pad--body">
<canvas id="canvasFirmaEncargado" style="width: 100%; height: 280px; display: block;"></canvas>
</div>
</div>
<input type="hidden" x-model="form.firma_encargado">
</div>

<!-- Botón completamente incorporado a lo ancho y pegado con distancia mínima (mt-2) -->
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;" @click="limpiarFirma('encargado')">
<i class="ti ti-eraser me-1"></i> Repetir firma
</button>

</div>
</div>

<!-- Card Firma Operador -->
<div class="col-12 col-md-6">
<div class="card border-0 bg-white">

<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center">
<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
<i class="ti ti-signature  fs-6"></i>
</div>
<div class="ms-3"><h5 class="mb-0 text-white">FIRMA DEL OPERADOR</h5></div>
</div>
</div>
</div>

<div class="card-body p-3">
<!-- Pad con borde punteado en los 4 lados -->
<div class="signature-pad-wrapper" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
<div class="signature-pad--body">
<canvas id="canvasFirmaOperador" style="width: 100%; height: 280px; display: block;"></canvas>
</div>
</div>
<input type="hidden" x-model="form.firma_operador">
</div>

<!-- Botón completamente incorporado a lo ancho y pegado con distancia mínima (mt-2) -->
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;" @click="limpiarFirma('operador')">
<i class="ti ti-eraser me-1"></i> Repetir firma
</button>

</div>
</div>

</div>
</div>

</div>
</div>