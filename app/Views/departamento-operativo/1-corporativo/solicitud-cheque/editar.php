<div x-data="solicitudChequeEditarComponent()" x-init="initForm(<?= htmlspecialchars(json_encode($detalle), ENT_QUOTES, 'UTF-8') ?>)" class="mt-3 mb-4"
data-id-year="<?= $detalle['id_year'] ?>"
data-id-mes="<?= $detalle['id_mes'] ?>"
data-id-solicitud="<?= $detalle['id'] ?>"
data-status="<?= $detalle['status'] ?>"
data-id-estacion="<?= $idEstacion ?>"
data-id-depto="<?= $idDepto ?>">

<div class="row">
<div class="col-12 mb-4">
<button type="button" class="btn btn-success float-end" @click="guardar()" :disabled="guardando">
<span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
</button>
</div>
</div>

<div class="col-12 mb-3">
<div class="card">
<div class="card-body">
<div class="row g-2">
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
<label class="form-label">* Fecha:</label>
<input type="date" class="form-control rounded-0" x-model="form.fecha">
</div>

<template x-if="idEstacion == 8">
<div class="col-xl-8 col-lg-8 col-md-12 col-sm-12 mb-3">
<label class="form-label">* Razón Social:</label>
<select class="form-select rounded-0" x-model="form.razonsocial">
<option value="">Selecciona una opcion...</option>
<option>ADMINISTRADORA DE GASOLINERAS INTERLOMAS</option>
<option>ADMINISTRADORA DE GASOLINERAS S.A. DE C.V.</option>
<option>ADMINISTRADORA DE GASOLINERAS SAN AGUSTÍN S.A. DE C.V.</option>
<option>GASOMIRA S.A. DE C.V.</option>
<option>GASOLINERA VALLE DE GUADALUPE S.A. DE C.V.</option>
<option>ADMINISTRADORA DE GASOLINERAS ESMEGAS S.A. DE C.V.</option>
<option>ADMINISTRADORA DE GASOLINERAS XOCHIMILCO S.A. DE C.V.</option>
<option>INMOBILIARIA PALO SOLO S.A. DE C.V.</option>
<option>INMOBILIARIA VALLE DE HUIXQUILUCAN, S.A. DE C.V.</option>
<option>ADMINISTRADORA DE GASOLINERIAS BOSQUE REAL S.A. DE C.V.</option>
<option>BIENES RAÍCES SALTE, S.A. DE C.V.</option>
<option>ARRENDATARIA DE COPOPRIEDADES LEO, S.A. DE C.V.</option>
<option>INMOBILIARIA TOMASIN, S.A. DE C.V.</option>
<option>OPERACIÓN SERVICIO Y MANTENIMIENTO DE PERSONAL S.A. DE C.V.</option>
<option>FIDEICOMISO DE ADMINISTRACIÓN No. 2176/2016</option>
<option>BANCA MIFEL, S.A., FIDEICOMISO 2176/2016</option>
<option>DE VILLASANTE HERBERT RODRIGO EMILIO, Y COPS.</option>
<option>AURELIO QUINZAÑOS SUAREZ Y COPROPIETARIOS</option>
<option>AURELIO QUINZAÑOS SUAREZ</option>
</select>
</div>
</template>

<template x-if="idEstacion == 14">
<div class="col-xl-8 col-lg-8 col-md-12 col-sm-12 mb-3">
<label class="form-label">* Razón Social:</label>
<select class="form-select rounded-0" x-model="form.depto">
<option value="">Selecciona una opcion...</option>
<option value="23">BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016</option>
</select>
</div>
</template>
</div>

<div class="row">
<div class="col-xl-5 col-lg-5 col-md-5 col-sm-12 mb-3">
<label class="form-label">* Nombre del Beneficiario:</label>
<input type="text" class="form-control rounded-0" x-model="form.beneficiario">
</div>

<div class="col-xl-5 col-lg-5 col-md-5 col-sm-12 mb-3">
<label class="form-label">* Monto:</label>
<input type="number" min="0" step="0.01" class="form-control rounded-0" x-model="form.monto">
</div>

<div class="col-xl-2 col-lg-2 col-md-2 col-sm-12 mb-3">
<label class="form-label">* Moneda:</label>
<select class="form-select rounded-0" x-model="form.moneda">
<option>MXN</option>
<option>USD</option>
</select>
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Factura No.:</label>
<input type="text" class="form-control rounded-0" x-model="form.no_factura">
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Correo Eléctronico:</label>
<input type="email" class="form-control rounded-0" x-model="form.email">
</div>
</div>

<div class="row">
<div class="col-12 mb-3">
<label class="form-label">* Concepto:</label>
<textarea class="form-control rounded-0" rows="3" x-model="form.concepto"></textarea>
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Nombre del solicitante:</label>
<input type="text" class="form-control rounded-0" x-model="form.solicitante">
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Teléfono:</label>
<input type="text" class="form-control rounded-0" x-model="form.telefono">
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Uso del CDFI:</label>
<select class="form-select rounded-0" x-model="form.cfdi">
<option value="">Selecciona una opción...</option>
<option value="G01 Adquisicion de Mercancias">G01 Adquisicion de Mercancias</option>
<option value="G02 Devoluciones, Descuentos o Bonificaciones">G02 Devoluciones, Descuentos o Bonificaciones</option>
<option value="G03 Gastos en General">G03 Gastos en General</option>
<option value="I01 Construcciones">I01 Construcciones</option>
<option value="I02 Mobiliario y Equipo de Oficina por Inversiones">I02 Mobiliario y Equipo de Oficina por Inversiones</option>
<option value="I03 Equipo de Transporte">I03 Equipo de Transporte</option>
<option value="I04 Equipo de Computo y Accesorios">I04 Equipo de Computo y Accesorios</option>
<option value="I05 Dados, Troqueles, Moldes, Matrices y Herramental">I05 Dados, Troqueles, Moldes, Matrices y Herramental</option>
<option value="I06 Comunicaciones Telefonicas">I06 Comunicaciones Telefonicas</option>
<option value="I07 Comunicaciones Satelitales">I07 Comunicaciones Satelitales</option>
<option value="I08 Otra Maquinaria y Equipo">I08 Otra Maquinaria y Equipo</option>
<option value="P01 Por Definir">P01 Por Definir</option>
</select>
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Método de Pago:</label>
<select class="form-select rounded-0" x-model="form.metodo_pago">
<option value="">Selecciona una opcion...</option>
<option>PUE Pago en una sola exhibición</option>
<option>PPD Pago en parcialidades o diferido</option>
</select>
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Banco:</label>
<input type="text" class="form-control rounded-0" x-model="form.banco">
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* No. de Cuenta:</label>
<input type="text" class="form-control rounded-0" x-model="form.no_cuenta">
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* No. de Cuenta (CLABE):</label>
<input type="text" class="form-control rounded-0" x-model="form.cuenta_clabe">
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Referencia / Convenio:</label>
<input type="text" class="form-control rounded-0" x-model="form.referencia">
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
<label class="form-label">* Forma de Pago:</label>
<select class="form-select rounded-0" x-model="form.forma_pago">
<option value="">Selecciona una opcion...</option>
<option>01 Efectivo</option>
<option>02 Cheque nominativo</option>
<option>02 Cheque Certificado</option>
<option>03 Transferencia electrónica de fondos</option>
<option>04 Tarjeta de crédito</option>
<option>05 Monedero electrónico</option>
<option>06 Dinero electrónico</option>
<option>08 Vales de despensa</option>
<option>12 Dación en pago</option>
<option>13 Pago por subrogación</option>
<option>14 Pago por consignación</option>
<option>15 Condonación</option>
<option>17 Compensación</option>
<option>23 Novación</option>
<option>24 Confusión</option>
<option>25 Remisión de deuda</option>
<option>26 Prescripción o caducidad</option>
<option>27 A satisfacción del acreedor</option>
<option>28 Tarjeta de débito</option>
<option>29 Tarjeta de servicios</option>
<option>30 Aplicación de anticipos</option>
<option>31 Intermediario pagos</option>
<option>99 Por definir</option>
</select>
</div>

</div>

<hr>

<div class="row">
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Presupuesto:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_0">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Factura (PDF):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_1">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Factura (XML):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_2">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Caratula Bancaria:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_3">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Constancia de Situación:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_4">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Prefactura (PDF):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_5">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Orden de Servicio:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_6">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Orden de Compra:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_7">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Orden de Mantenimiento:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_8">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Póliza de Garantía:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_9">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Prorrateo:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_10">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Reembolso de Caja Chica:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_11">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Cotización:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_12">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Nota de Credito (PDF):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_13">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Nota de Credito (XOCHIMILCO):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_14">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Contrato:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_15">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Complemento de Pago (PDF):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_16">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Complemento de Pago (XML):</label>
<input type="file" class="form-control rounded-0" x-ref="doc_17">
</div>
<div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
<label class="form-label">Opinión de Cumplimiento:</label>
<input type="file" class="form-control rounded-0" x-ref="doc_18">
</div>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card h-100 border">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="ti ti-eye me-2"></i>OBSERVACIONES</h5>
</div>
</div>
<div class="card-body p-0">
<textarea class="form-control border-0 rounded-0 p-4" x-model="form.observaciones" style="height:190px; resize:none;" placeholder="Escribe aquí tu comentario..."></textarea>
</div>
</div>
</div>

<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
<div class="card h-100">
<div class="card-header text-bg-primary">
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
<h5 class="mb-0 text-white"><i class="fa-solid fa-signature me-2"></i>FIRMA DEL ENCARGADO</h5>
<button type="button" class="btn btn-danger btn-sm" @click="limpiarFirma()"><i class="ti ti-eraser me-1"></i> Limpiar firma</button>
</div>
</div>
<div class="card-body p-0">
<div id="signature-pad" class="signature-pad border-0">
<div class="signature-pad--body">
<canvas id="canvas" style="width:100%; height:200px; cursor:crosshair;"></canvas>
</div>
<input type="hidden" name="base64" id="base64" value="">
</div>
</div>
</div>
</div>
</div>
</div>