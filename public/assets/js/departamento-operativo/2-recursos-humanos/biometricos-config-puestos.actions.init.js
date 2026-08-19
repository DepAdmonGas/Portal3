document.addEventListener('alpine:init', () => {

Alpine.data('puestoForm', () => ({

mode: 'create',
id: null,
nombre: '',
loading: false,

errors: {
nombre: false
},

openEdit(data) {
this.mode = 'edit';
this.id = data.id;
this.nombre = data.puesto ?? '';

const modalEl = document.getElementById('nuevo');
if (modalEl) {
const modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

validate() {
this.errors.nombre = !this.nombre.trim();

if (this.errors.nombre) {
this.notify('error', 'Ingresa el nombre del puesto');
return false;
}

return true;
},

resetForm() {
this.mode = 'create';
this.id = null;
this.nombre = '';
this.errors = { nombre: false };
},

resetModal() {
const modalEl = document.getElementById('nuevo');

modalEl.addEventListener('hidden.bs.modal', () => {
this.resetForm();
document.body.focus();
}, { once: true });

if (document.activeElement) {
document.activeElement.blur();
}

const modal = bootstrap.Modal.getInstance(modalEl);
if (modal) {
modal.hide();
}
},

async submit() {
if (!this.validate()) return;

let url = this.mode === 'create'
? '/departamento-operativo/recursos-humanos/biometricos/configuracion/puestos/create'
: '/departamento-operativo/recursos-humanos/biometricos/configuracion/puestos/update';

let payload = {
id: this.id,
nombre: this.nombre
};

try {
const res = await this.createAction({
url,
data: payload,
table: '#table-puestos'
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
