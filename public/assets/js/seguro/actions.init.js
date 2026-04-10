document.addEventListener('alpine:init', () => {

Alpine.data('polizaForm', () => ({
init() {
window.polizaSeguroInstance = this;
},

mode: 'create',
id: null,
poliza: null,
loading: false,

errors: {
poliza: false
},

// CAPTURAR POLIZA
handleFile(e) {
this.poliza = e.target.files[0];
this.errors.poliza = false;
},

// VALIDACIÓN
validate() {
this.errors.poliza = !this.poliza;

if (this.errors.poliza) {
this.notify('error', 'Debes de subir un archivo');
return false;
}

return true;
},

// RESET
resetForm() {
this.poliza = null;

this.errors = {
poliza: false
};

// LIMPIAR INPUT FILE REAL
if (this.$refs.poliza) {
this.$refs.poliza.value = null;
}
},

// SUBMIT
async submit() {

if (!this.validate()) return;

let formData = new FormData();
formData.append('id', 0);
formData.append('poliza', this.poliza);

try {
const res = await this.createAction({
url: '/seguro/create-poliza-seguro',
data: formData,
table: '#table-poliza'
});


if (res && res.success) {
const modaPoliza = document.getElementById('modalPolizaSeguro');

document.activeElement.blur();

modaPoliza.addEventListener('hidden.bs.modal',() => {
this.resetForm();
},
{ once: true }
);

const modal = bootstrap.Modal.getInstance(modaPoliza);

if (modal) {
modal.hide();
}

}

} catch (error) {
this.notify('error', 'Error al guardar');
}

}

}));


Alpine.data('coberturaForm', () => ({
init() {
window.coberturaSeguroInstance = this;
},

mode: 'create',
id: null,
cobertura: null,
loading: false,

errors: {
cobertura: false
},

// CAPTURAR POLIZA
handleFile(e) {
this.cobertura = e.target.files[0];
this.errors.poliza = false;
},

// VALIDACIÓN
validate() {
this.errors.cobertura = !this.cobertura;

if (this.errors.cobertura) {
this.notify('error', 'Debes de subir un archivo');
return false;
}

return true;
},

// RESET
resetForm() {
this.cobertura = null;

this.errors = {
cobertura: false
};

// LIMPIAR INPUT FILE REAL
if (this.$refs.cobertura) {
this.$refs.cobertura.value = null;
}
},

// SUBMIT
async submit() {

if (!this.validate()) return;

let formData = new FormData();
formData.append('id', 0);
formData.append('cobertura', this.cobertura);

try {
const res = await this.createAction({
url: '/seguro/create-cobertura-poliza-seguro',
data: formData,
table: '#table-poliza-cobertura'
});



if (res && res.success) {

const modalCobertura = document.getElementById('modalPolizaCobertura');

document.activeElement.blur();

modalCobertura.addEventListener('hidden.bs.modal',() => {
this.resetForm();
},
{ once: true }
);

const modal = bootstrap.Modal.getInstance(modalCobertura);

if (modal) {
modal.hide();
}

}

} catch (error) {
this.notify('error', 'Error al guardar');
}

}

}));


});
 