document.addEventListener('alpine:init', () => {

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
formData.append('id', 0);
formData.append('clave', this.clave);
formData.append('nombre_g', this.nombre_g);
formData.append('foto', this.foto);

try {
const res = await this.createAction({
url: '/solicitud-gafetes/create-reporte',
data: formData,
table: '#table-gafetes'
});

if (res && res.success) {

const idEstacion = res.idEstacion;
const noReporte = res.no_reporte;
const modalEl = document.getElementById('nuevo');

document.activeElement.blur();

const modal = bootstrap.Modal.getInstance(modalEl);

if (modal) {
modal.hide();
}
 
setTimeout(() => {
this.resetForm();
window.location.href = `/solicitud-gafetes/formulario/${idEstacion}/${noReporte}`;
}, 300);
}

} catch (error) {
this.notify('error', 'Error al guardar');
}
}

}));

});
