<div class="mt-3 mb-3"
x-data="solicitudValesCrearComponent()"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-id-estacion="<?= $idEstacion ?>"
data-mostrar-cuenta="<?= $mostrarCuenta ? 'true' : 'false' ?>"
data-estaciones='<?= $estacionesJson ?>'>
<div class="row">
<div class="col-12 mb-3">
<div class="card">
<div class="card-body">
<div class="row g-3">
<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Fecha:</label>
<input type="date" class="form-control rounded-0" x-model="form.fecha" :class="{'is-invalid': errors.fecha}">
</div>

<div class="col-xl-5 col-lg-5 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Monto:</label>
<input type="number" min="0" step="0.01" class="form-control rounded-0" x-model="form.monto" :class="{'is-invalid': errors.monto}">
</div>

<div class="col-xl-2 col-lg-2 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Moneda:</label>
<select class="form-select rounded-0" x-model="form.moneda">
<option>MXN</option>
<option>USD</option>
</select>
</div>

<div class="col-12 mb-2">
<label class="form-label">* Concepto:</label>
<textarea class="form-control rounded-0" rows="3" x-model="form.concepto" :class="{'is-invalid': errors.concepto}"></textarea>
</div>

<div class="col-xl-8 col-lg-8 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Nombre del solicitante:</label>
<input type="text" class="form-control rounded-0" x-model="form.solicitante" :class="{'is-invalid': errors.solicitante}">
</div>

<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Departamento:</label>
<select class="form-select rounded-0" x-model="form.departamento">
<option value="">Selecciona una opción...</option>
<option value="2">Sistemas</option>
<option value="4">Comercializadora</option>
<option value="5">Gestoria</option>
<option value="8">Mantenimiento</option>
<option value="13">Dirección de operaciones</option>
<option value="15">Departamento Jurídico</option>
</select>
</div>

<template x-if="mostrarCuenta">
<div class="col-12">
<hr>
<h5 class="mt-3 mb-3">CARGO A CUENTA:</h5>
<div class="row g-3">
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Estación:</label>
                    <select class="form-select rounded-0" x-model="form.estacion" x-ref="estacionSelect" @change="toggleCuentaCampos">
<option value="">Selecciona una opción...</option>
<template x-for="est in estaciones" :key="est.id">
<option :value="est.id" x-text="est.nombre"></option>
</template>
</select>
</div>
<div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-2">
<label class="form-label">* Cuenta:</label>
<input class="form-control rounded-0" type="text" list="listaCuentas" x-model="form.cuenta" x-ref="cuentaInput" @input="toggleCuentaCampos">
<datalist id="listaCuentas">
<option value="Club deportivo">
<option value="Gasera">
<option value="Ecatepec">
<option value="Escuela wingate">
<option value="Sabino">
<option value="Acueducto">
<option value="G500 Corp">
<option value="Aguascalientes">
<option value="Verificentro">
<option value="Castorena">
<option value="Conflicto zona 2">
<option value="Conflicto zona 3-Oropeza">
<option value="Terrenos zona 1">
<option value="Rancho">
<option value="AQS">
<option value="Pozo el mirador">
<option value="Chaparral">
<option value="Pozo el estímulo">
<option value="Remolques">
<option value="Honorarios">
<option value="Fraccionadores">
<option value="MP">
</datalist>
</div>
</div>
</div>
</template>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-2 mt-2">
<label class="form-label">* Autorizado por:</label>
<input type="text" class="form-control rounded-0" x-model="form.autorizado_por" :class="{'is-invalid': errors.autorizado_por}">
</div>

<div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-2 mt-2">
<label class="form-label">* Metodo de autorización:</label>
<select class="form-select rounded-0" x-model="form.metodo_autorizacion" :class="{'is-invalid': errors.metodo_autorizacion}">
<option value="">Selecciona una opción...</option>
<option>Personal</option>
<option>Telefónica</option>
</select>
</div>

<div class="col-12 mb-2">
<label class="form-label">* Observaciones:</label>
<textarea class="form-control rounded-0" rows="2" x-model="form.observaciones"></textarea>
</div>

<div class="col-12">
<hr>
<h5 class="mt-3 mb-3">DOCUMENTACIÓN:</h5>
<div class="row g-3">
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
<label class="form-label">VALE:</label>
<input type="file" class="form-control rounded-0" x-ref="fileVale">
</div>
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
<label class="form-label">RECIBO:</label>
<input type="file" class="form-control rounded-0" x-ref="fileRecibo">
</div>
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
<label class="form-label">FACTURA:</label>
<input type="file" class="form-control rounded-0" x-ref="fileFactura">
</div>
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
    <label class="form-label">PDF:</label>
<input type="file" class="form-control rounded-0" x-ref="filePDF">
</div>
<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2">
<label class="form-label">XML:</label>
<input type="file" class="form-control rounded-0" x-ref="fileXML">
</div>
</div>
</div>

<div class="col-12 mt-3">
<button type="button" class="btn btn-labeled2 btn-success float-end" @click="guardar()" :disabled="guardando">
 Guardar
</button>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

