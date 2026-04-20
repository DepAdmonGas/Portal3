document.addEventListener('alpine:init', () => {

Alpine.data('moduloForm', () => ({

mode: 'create',
idModulo: null,
nombre_modulo: '',
clave: '',
ruta: '',
icono: '',
loading: false,

errors: {
nombre_modulo: false,
clave: false,
ruta: false,
icono: false
},

//---------- MODAL PARA EDITAR EL CONTENIDO ---------//
openEdit(data) {

this.mode = 'edit';
this.idModulo = data.idModulo;
this.nombre_modulo = data.nombre_modulo ?? '';
this.clave = data.clave ?? '';
this.ruta = data.ruta ?? '';
this.icono = data.icono ?? '';

const modalEl = document.getElementById('nuevo');
if (modalEl) {
const modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

//---------- VALIDACIÓN ---------//
validate() {
    
this.errors.nombre_modulo = !this.nombre_modulo;
this.errors.clave = !this.clave;
this.errors.ruta = !this.ruta;

if (this.errors.nombre_modulo || this.errors.clave || this.errors.ruta) {
this.notify('error', 'Completa los campos obligatorios');
return false;
}

return true;
},


//---------- RESET ---------//
resetForm() {
this.mode = 'create';
this.nombre_modulo = '';
this.clave = '';
this.ruta = '';
this.icono = '';

this.errors = {
nombre_modulo: false,
clave: false,
ruta: false,
icono: false
};

},

resetModal(){

const modalEl = document.getElementById('nuevo');

// evento al cerrar completamente
modalEl.addEventListener('hidden.bs.modal', () => {
this.resetForm();
document.body.focus(); // opcional (mejora accesibilidad)
}, { once: true });

// quitar foco ANTES de cerrar
if (document.activeElement) {
document.activeElement.blur();
}

const modal = bootstrap.Modal.getInstance(modalEl);

if (modal) {
modal.hide();
}

},

//---------- SUBMIT ---------//
async submit() {

if (!this.validate()) return;

let payload = {};
let url = '';

if (this.mode === 'create') {

url = '/configuracion/create-modulos';
payload = {
nombre_modulo: this.nombre_modulo,
clave: this.clave,
ruta: this.ruta,
icono: this.icono
};

} else {

url = '/configuracion/update-modulos';
payload = {
idModulo: this.idModulo,
nombre_modulo: this.nombre_modulo,
clave: this.clave,
ruta: this.ruta,
icono: this.icono
};

} 

try {

const res = await this.createAction({
url,
data: payload,
table: '#table-modulos'
});


if (res && res.success) {
this.resetModal();                
}

} catch (error) {
this.notify('error', 'Error al guardar');
}

}

}));

});

