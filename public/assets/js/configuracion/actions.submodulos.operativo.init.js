document.addEventListener('alpine:init', () => {
const idModulo = document.getElementById('container').dataset.id;

Alpine.data('submoduloOperativoForm', () => ({
mode: 'create',
idSubmodulo: null,
nombre_submodulo: '',
clave: '',
ruta: '',
icono: '',
loading: false,

errors: {
nombre_submodulo: false,
clave: false,
ruta: false,
icono: false
},

//---------- MODAL PARA EDITAR EL CONTENIDO ---------//
openEdit(data) {

this.mode = 'edit';
this.idSubmodulo = data.idSubmodulo;
this.nombre_submodulo = data.nombre ?? '';
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
    
this.errors.nombre_submodulo = !this.nombre_submodulo;
this.errors.clave = !this.clave;
this.errors.ruta = !this.ruta;

if (this.errors.nombre_submodulo || this.errors.clave || this.errors.ruta) {
this.notify('error', 'Completa los campos obligatorios');
return false;
}

return true;
},


//---------- RESET ---------//
resetForm() {
this.mode = 'create';
this.nombre_submodulo = '';
this.clave = '';
this.ruta = '';
this.icono = '';

this.errors = {
nombre_submodulo: false,
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

url = '/configuracion/create-submodulos-operativo';
payload = {
idModulo: idModulo,
nombre_submodulo: this.nombre_submodulo,
clave: this.clave,
ruta: this.ruta,
icono: this.icono
};

} else {

url = '/configuracion/update-submodulos-operativo';
payload = {
idSubmodulo: this.idSubmodulo,
nombre_submodulo: this.nombre_submodulo,
clave: this.clave,
ruta: this.ruta,
icono: this.icono
};

} 

try {

const res = await this.createAction({
url,
data: payload,
table: '#table-submodulos-operativo'
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

