document.addEventListener('alpine:init', () => {
Alpine.data('tarjetasForm', () => ({

archivo: null,
razon_social: '',
nombre_usuario: '',
vehiculo: '',
placas: '',
no_unidad: '',
tarjeta: '',
tipo_tarjeta: '',
loading: false,

errors: {
archivo: false,
razon_social: false,
nombre_usuario: false,
vehiculo: false,
placas: false,
no_unidad: false,
tarjeta: false,
tipo_tarjeta: false
},

// CAPTURAR ARCHIVO
handleFile(e) {
this.archivo = e.target.files[0];
this.errors.archivo = false;
},

// VALIDACIÓN
validate() {
this.errors.razon_social = !this.razon_social;
this.errors.nombre_usuario = !this.nombre_usuario;
this.errors.vehiculo = !this.vehiculo;
this.errors.placas = !this.placas;
this.errors.no_unidad = !this.no_unidad;
this.errors.tarjeta = !this.tarjeta;
this.errors.tipo_tarjeta = !this.tipo_tarjeta;

if (this.errors.razon_social || this.errors.nombre_usuario || this.errors.vehiculo || this.errors.placas || this.errors.no_unidad || this.errors.tarjeta || this.errors.tipo_tarjeta) {
this.notify('error', 'Completa los campos obligatorios');
return false;
}

return true;
},

// RESET
resetForm() {
this.archivo = '';
this.razon_social = '';
this.nombre_usuario = '';
this.vehiculo = null;
this.placas = '';
this.no_unidad = '';
this.tarjeta = null;
this.tipo_tarjeta = '';

this.errors = {
archivo: false,
razon_social: false,
nombre_usuario: false,
vehiculo: false,
placas: false,
no_unidad: false,
tarjeta: false,
tipo_tarjeta: false
};

// LIMPIAR INPUT FILE REAL
if (this.$refs.archivo) {
this.$refs.archivo.value = null;
}
},

// SUBMIT
async submit() {

if (!this.validate()) return;

let formData = new FormData();
formData.append('id', 0);
formData.append('archivo', this.archivo);
formData.append('razon_social', this.razon_social);
formData.append('nombre_usuario', this.nombre_usuario);
formData.append('vehiculo', this.vehiculo);
formData.append('placas', this.placas);
formData.append('no_unidad', this.no_unidad);
formData.append('tarjeta', this.tarjeta);
formData.append('tipo_tarjeta', this.tipo_tarjeta);

try {
const res = await this.createAction({
url: '/solicitud-tarjetas/create-reporte',
data: formData,
table: '#table-tarjetas'
});

if (res && res.success) {

const idEstacion = res.idEstacion;
const noSolicitud = res.no_solicitud;
const modalEl = document.getElementById('nuevo');

document.activeElement.blur();
const modal = bootstrap.Modal.getInstance(modalEl);

if (modal) {
modal.hide();
}

setTimeout(() => {
this.resetForm();
window.location.href = `/solicitud-tarjetas/formulario/${idEstacion}/${noSolicitud}`;
}, 300);

}

} catch (error) {
this.notify('error', 'Error al guardar');
}

}

}));
});