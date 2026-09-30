<div class="container-fluid mt-3 pb-4" x-data="{ ...actions(), ...proveedorCrearComponent() }">
    
<div class="text-end mb-3">
<button type="button" class="btn btn-success" @click="guardarProveedor()" :disabled="guardando">
<span x-show="!guardando"><i class="ti ti-check me-1"></i> Guardar</span>
<span x-show="guardando" style="display: none;"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
</button>
</div>


<!---------- INFORMACION GENERAL ---------->
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-info-circle me-2"></i> INFORMACIÓN GENERAL
</h5>
</div>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* Fecha:</label>
<input type="date" class="form-control" x-model="form.Fecha" :style="errors.Fecha ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Fecha = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Razón Social:</label>
<input type="text" class="form-control" x-model="form.RazonSocial" :style="errors.RazonSocial ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.RazonSocial = false">
</div>


<div class="col-md-6">
<label class="form-label mb-1">* RFC:</label>
<input type="text" class="form-control text-uppercase" x-model="form.RFC" :style="errors.RFC ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.RFC = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Correo Electrónico:</label>
<input type="email" class="form-control" x-model="form.Email" :style="errors.Email ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Email = false">
</div>


<div class="col-12">
<label class="form-label mb-1">* Actividad Económica:</label>
<input type="text" class="form-control" x-model="form.ActividadEco" :style="errors.ActividadEco ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.ActividadEco = false">
</div>

</div>
</div>
</div>


<!---------- DATOS DE CONTACTO Y PAGO ---------->
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-address-book me-2"></i> DATOS DE CONTACTO Y PAGO
</h5>
</div>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* Ciudad:</label>
<input type="text" class="form-control" x-model="form.Ciudad" :style="errors.Ciudad ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Ciudad = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* Dirección:</label>
<input type="text" class="form-control" x-model="form.Direccion" :style="errors.Direccion ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Direccion = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* Teléfono 1:</label>
<input type="tel" class="form-control" x-model="form.Telefono1" :style="errors.Telefono1 ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Telefono1 = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">Teléfono 2 <small>(Opcional)</small>:</label>
<input type="tel" class="form-control" x-model="form.Telefono2">
</div>
<div class="col-md-12">
<label class="form-label mb-1">* Nombre del Beneficiario:</label>
<input type="text" class="form-control" x-model="form.Beneficiario" :style="errors.Beneficiario ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Beneficiario = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* Banco:</label>
<input type="text" class="form-control" x-model="form.Banco" :style="errors.Banco ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Banco = false">
</div>
<div class="col-md-6">
<label class="form-label mb-1">* Método de Pago:</label>
<select class="form-select" x-model="form.Metodopago" :style="errors.Metodopago ? 'border: 2px solid #A52525 !important;' : ''" @change="errors.Metodopago = false">
<option value="">Seleccione una opción...</option>
<option value="PUE Pago en una sola exhibición">PUE Pago en una sola exhibición</option>
<option value="PPD Pago en parcialidades o diferido">PPD Pago en parcialidades o diferido</option>
</select>
</div>
<div class="col-md-5">
<label class="form-label mb-1">* Uso del CFDI:</label>
<select class="form-select" x-model="form.CFDI" :style="errors.CFDI ? 'border: 2px solid #A52525 !important;' : ''" @change="errors.CFDI = false">
<option value="">Seleccione una opción...</option>
<option value="G01 Adquisicion de Mercancias">G01 Adquisición de Mercancías</option>
<option value="G02 Devoluciones, Descuentos o Bonificaciones">G02 Devoluciones, Descuentos o Bonificaciones</option>
<option value="G03 Gastos en General">G03 Gastos en General</option>
<option value="I01 Construcciones">I01 Construcciones</option>
<option value="I02 Mobiliario y Equipo de Oficina por Inversiones">I02 Mobiliario y Equipo de Oficina por Inversiones</option>
<option value="I03 Equipo de Transporte">I03 Equipo de Transporte</option>
<option value="I04 Equipo de Computo y Accesorios">I04 Equipo de Cómputo y Accesorios</option>
<option value="I05 Dados, Troqueles, Moldes, Matrices y Herramental">I05 Dados, Troqueles, Moldes, Matrices y Herramental</option>
<option value="I06 Comunicaciones Telefonicas">I06 Comunicaciones Telefónicas</option>
<option value="I07 Comunicaciones Satelitales">I07 Comunicaciones Satelitales</option>
<option value="I08 Otra Maquinaria y Equipo">I08 Otra Maquinaria y Equipo</option>
<option value="P01 Por Definir">P01 Por Definir</option>
</select>
</div>

<div class="col-md-2">
<label class="form-label mb-1">* Moneda:</label>
<select class="form-select" x-model="form.Moneda">
<option value="MXN">MXN</option>
<option value="USD">USD</option>
</select>
</div>
<div class="col-md-5">
<label class="form-label mb-1">* Forma de Pago:</label>
<select class="form-select" x-model="form.FormaPago" :style="errors.FormaPago ? 'border: 2px solid #A52525 !important;' : ''" @change="errors.FormaPago = false">
<option value="">Seleccione una opción...</option>
<option value="01 Efectivo">01 Efectivo</option>
<option value="02 Cheque nominativo">02 Cheque nominativo</option>
<option value="03 Transferencia electrónica de fondos">03 Transferencia electrónica de fondos</option>
<option value="04 Tarjeta de crédito">04 Tarjeta de crédito</option>
<option value="05 Monedero electrónico">05 Monedero electrónico</option>
<option value="06 Dinero electrónico">06 Dinero electrónico</option>
<option value="08 Vales de despensa">08 Vales de despensa</option>
<option value="28 Tarjeta de débito">28 Tarjeta de débito</option>
<option value="99 Por definir">99 Por definir</option>
</select>
</div>
<div class="col-12">
<label class="form-label mb-1">* Productos o Servicios Ofrecidos:</label>
<textarea class="form-control" rows="4" x-model="form.Descripcion" :style="errors.Descripcion ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.Descripcion = false"></textarea>
</div>
</div>

</div>
</div>

<!---------- INFORMACION GENERAL ---------->
<div class="card">
<div class="card-header text-bg-primary">
<div class="d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-file-description me-2"></i> Documentación Adjunta
</h5>
</div>
</div>
<div class="card-body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label mb-1">* Constancia de Situación Fiscal (PDF):</label>
<input type="file" class="form-control" id="constanciaFile" :style="errors.ConstanciaS ? 'border: 2px solid #A52525 !important;' : ''" @change="form.ConstanciaS = $event.target.files[0] || null; errors.ConstanciaS = false" accept=".pdf">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de última actualización:</label>
<input type="date" class="form-control" x-model="form.FechaConstancia" :style="errors.FechaConstancia ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.FechaConstancia = false">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Carátula Bancaria (PDF):</label>
<input type="file" class="form-control" id="caratulaFile" :style="errors.CaratulaB ? 'border: 2px solid #A52525 !important;' : ''" @change="form.CaratulaB = $event.target.files[0] || null; errors.CaratulaB = false" accept=".pdf">
</div>

<div class="col-md-6">
<label class="form-label mb-1">* Fecha de última actualización:</label>
<input type="date" class="form-control" x-model="form.FechaCaratula" :style="errors.FechaCaratula ? 'border: 2px solid #A52525 !important;' : ''" @input="errors.FechaCaratula = false">
</div>
</div>
</div>
</div>

</div>
