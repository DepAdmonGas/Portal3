document.addEventListener('alpine:init', () => {
Alpine.data('modulosPuestosForm', () => ({

modulos: [],
modulo_id: '',

mode: 'create',
editId: null,
loading: false,

errors: {
modulo: false
},

init() {
window.modulosPuestoInstance = this;

this.bindModalSelect2({
modalRef: 'modalNuevo',
selectRef: 'selectModulo',
wrapperRef: 'moduloWrapper',
model: 'modulo_id',
namespace: 'modulosPuestos',
options: {
placeholder: 'Seleccione una opción...'
},
onShown() {
if (!this.modulos.length) {
this.getModulos();
return false;
}
return true;
}
});

this.$watch('modulo_id', value => {
if (value) {
this.errors.modulo = false;
}
});
},

//-- Obtener módulos
async getModulos(currentId = null) {

const id = document.getElementById('container').dataset.id;
let url = `/configuracion/modulos-puestos/modulo/${id}`;
if (currentId) {
url += `/${currentId}`;
}

try {
const res = await fetch(url);
this.modulos = await res.json();

this.$nextTick(() => {

const el = $(this.$refs.selectModulo);

if (el.hasClass("select2-hidden-accessible")) {
el.select2('destroy');
}

this.initModalSelect2({
modalRef: 'modalNuevo',
selectRef: 'selectModulo',
wrapperRef: 'moduloWrapper',
model: 'modulo_id',
namespace: 'modulosPuestos',
options: {
placeholder: 'Seleccione una opción...'
}
});

el.off('change').on('change', (e) => {
this.modulo_id = e.target.value;
});

if (this.mode === 'edit' && currentId) {
this.modulo_id = currentId; 
el.val(currentId).trigger('change');
}

});

} catch (e) {
this.notify('error', 'Error al traer los módulos');
}
},

//-- Reset formulario
resetForm() {
this.modulo_id = '';

const el = $(this.$refs.selectModulo);
el.val(null).trigger('change');
this.$refs.check_leer.checked = false;
this.$refs.check_crear.checked = false;
this.$refs.check_editar.checked = false;
this.$refs.check_eliminar.checked = false;
this.$refs.check_descargar.checked = false;
},

async refreshModulosSelect(currentId = null) {
await this.getModulos(currentId);
},

//-- Abrir modal NUEVO
openNuevo() {
this.mode = 'create';
this.editId = null;

this.resetForm();
this.refreshModulosSelect();

const modal = new bootstrap.Modal(document.getElementById('nuevo'));
modal.show();
},

//-- Abrir modal EDITAR 
async openEditar(id){
try {
const res = await fetch(`/configuracion/modulos-puestos/detalle/${id}`);
const data = await res.json();

if (!data.success) {
this.notify('error', 'No se encontró información');
return;
}

const d = data.detalle;

this.mode = 'edit';
this.editId = d.id;

await this.getModulos(d.modulo_id);
this.modulo_id = d.modulo_id;

const modal = new bootstrap.Modal(document.getElementById('nuevo'));
modal.show();

// 🔥 setear checkboxes
this.$nextTick(() => {
this.$refs.check_leer.checked = d.leer == 1;
this.$refs.check_crear.checked = d.crear == 1;
this.$refs.check_editar.checked = d.editar == 1;
this.$refs.check_eliminar.checked = d.eliminar == 1;
this.$refs.check_descargar.checked = d.descargar == 1;
});

} catch (e) {
this.notify('error', 'Error al cargar detalle');
}
},

//-- Validar
validate() {
Object.keys(this.errors).forEach(k => this.errors[k] = false);

let valid = true;

if (!this.modulo_id) {
this.errors.modulo = true;
valid = false;
}

return valid;
},

//-- Submit
async submit() {

if (!this.validate()) {
this.notify('error', 'El modulo es obligatorio');
return;
}

const idPuesto = document.getElementById('container').dataset.id;
const formData = new FormData();

formData.append('idPuesto', idPuesto);
formData.append('modulo_id', this.modulo_id);

formData.append('leer', this.$refs.check_leer.checked ? '1' : '0');
formData.append('crear', this.$refs.check_crear.checked ? '1' : '0');
formData.append('editar', this.$refs.check_editar.checked ? '1' : '0');
formData.append('eliminar', this.$refs.check_eliminar.checked ? '1' : '0');
formData.append('descargar', this.$refs.check_descargar.checked ? '1' : '0');

let url = '/configuracion/modulos-puestos/create';

if (this.mode === 'edit') {
url = `/configuracion/modulos-puestos/update/${this.editId}`;
}

try {

const res = await this.createAction({
url,
data: formData,
table: '#table-modulos-puestos-configuracion'
});

if (res && res.success) {
await this.refreshModulosSelect(this.modulo_id);
this.resetModal();
}

} catch (error) {

this.notify('error', 'Error al guardar');

}
},

//-- Cerrar modal
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
}

}));
});