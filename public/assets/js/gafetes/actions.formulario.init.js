document.addEventListener('alpine:init', () => {

const containerModal = document.getElementById('nuevo');
const idEstacion = containerModal.dataset.estacion;
const noReporte = containerModal.dataset.reporte;

Alpine.data('gafetesForm', () => ({

clave: '',
nombre_g: '',
foto: null,
loading: false,

errors: {
clave: false,
nombre_g: false,
foto: false
},

// CAPTURAR ARCHIVO
handleFile(e) {
this.foto = e.target.files[0];
this.errors.foto = false;
},

// VALIDACIÓN
validate() {
this.errors.clave = !this.clave;
this.errors.nombre_g = !this.nombre_g;
this.errors.foto = !this.foto;

if (this.errors.clave || this.errors.nombre_g || this.errors.foto) {
this.notify('error', 'Completa los campos obligatorios');
return false;
}

return true;
},


// RESET
resetForm() {
this.clave = '';
this.nombre_g = '';
this.foto = null;

this.errors = {
clave: false,
nombre_g: false,
foto: false
};

// LIMPIAR INPUT FILE REAL
if (this.$refs.foto) {
this.$refs.foto.value = null;
}
},

// SUBMIT
async submit() {

if (!this.validate()) return;

let formData = new FormData();
formData.append('no_reporte', noReporte);
formData.append('idEstacion', idEstacion);
formData.append('clave', this.clave);
formData.append('nombre_g', this.nombre_g);
formData.append('foto', this.foto);

try {
const res = await this.createAction({
url: '/solicitud-gafetes/create-reporte-formulario',
data: formData,
table: '#table-gafetes-formulario'
});

if (res && res.success) {

const modalEl = document.getElementById('nuevo');

//quitar foco (error aria-hidden)
document.activeElement.blur();

//IMPORTANTE: esperar a que cierre
modalEl.addEventListener('hidden.bs.modal', () => {

this.resetForm();

}, { once: true });

const modal = bootstrap.Modal.getInstance(modalEl);

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