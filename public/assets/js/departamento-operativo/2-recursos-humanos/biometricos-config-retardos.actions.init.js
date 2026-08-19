document.addEventListener('alpine:init', function () {

Alpine.data('retardoConfigForm', function () {
return {
retardo: 0,
incidencia: 0,
loadingRi: false,
puedeEditar: false,

init() {
var c = document.getElementById('container');
if (c) {
this.puedeEditar = c.dataset.puedeEditar === 'true';
}
var self = this;
document.addEventListener('retardo-infidencia-loaded', function (e) {
self.retardo = e.detail.retardo || 0;
self.incidencia = e.detail.incidencia || 0;
});
},

async guardarRetardoIncidencia() {
var sel = document.getElementById('module-station-selector-biometricos');
if (!sel || !sel.value) {
this.notify('error', 'Selecciona una estacion');
return;
}
var val = sel.value;
var idEst = 0;
if (val.indexOf('depto_') === 0) {
idEst = parseInt(val.replace('depto_', ''), 10);
} else {
idEst = parseInt(val.replace('estacion_', ''), 10);
}
if (!idEst) {
this.notify('error', 'Selecciona una estacion valida');
return;
}

this.loadingRi = true;
try {
var res = await this.createAction({
url: '/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/update-retardo-incidencia',
data: {
id_estacion: idEst,
retardo: parseInt(this.retardo) || 0,
incidencia: parseInt(this.incidencia) || 0
}
});
} catch (e) {
this.notify('error', 'Error al guardar');
}
this.loadingRi = false;
}
};
});

Alpine.data('horarioForm', function () {
return {
mode: 'create',
id: null,
titulo: '',
horaEntrada: '',
horaSalida: '',
loading: false,

errors: {
titulo: false,
horaEntrada: false,
horaSalida: false
},

getEstacionParam() {
var sel = document.getElementById('module-station-selector-biometricos');
if (sel && sel.value) {
var val = sel.value;
if (val.indexOf('depto_') === 0) return parseInt(val.replace('depto_', ''), 10);
return parseInt(val.replace('estacion_', ''), 10);
}
return 0;
},

openCreateModal() {
this.mode = 'create';
this.id = null;
this.titulo = '';
this.horaEntrada = '';
this.horaSalida = '';
this.errors = { titulo: false, horaEntrada: false, horaSalida: false };

if (this.getEstacionParam() === 0) {
this.notify('error', 'Selecciona una estacion primero');
return;
}

var modalEl = document.getElementById('modalHorario');
if (modalEl) {
var modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

openEdit(data) {
this.mode = 'edit';
this.id = data.id;
this.titulo = data.titulo || '';
this.horaEntrada = data.hora_entrada || '';
this.horaSalida = data.hora_salida || '';
this.errors = { titulo: false, horaEntrada: false, horaSalida: false };

var modalEl = document.getElementById('modalHorario');
if (modalEl) {
var modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

validate() {
this.errors.titulo = !this.titulo.trim();
this.errors.horaEntrada = !this.horaEntrada;
this.errors.horaSalida = !this.horaSalida;

if (this.errors.titulo) {
this.notify('error', 'Ingresa el titulo del horario');
return false;
}
if (this.errors.horaEntrada) {
this.notify('error', 'Selecciona la hora de entrada');
return false;
}
if (this.errors.horaSalida) {
this.notify('error', 'Selecciona la hora de salida');
return false;
}
return true;
},

resetForm() {
this.mode = 'create';
this.id = null;
this.titulo = '';
this.horaEntrada = '';
this.horaSalida = '';
this.errors = { titulo: false, horaEntrada: false, horaSalida: false };
},

resetModal() {
var modalEl = document.getElementById('modalHorario');
modalEl.addEventListener('hidden.bs.modal', function () {
document.body.focus();
}, { once: true });

if (document.activeElement) {
document.activeElement.blur();
}

var modal = bootstrap.Modal.getInstance(modalEl);
if (modal) {
modal.hide();
}
},

async submit() {
if (!this.validate()) return;

var idEst = this.getEstacionParam();
if (!idEst) {
this.notify('error', 'Selecciona una estacion');
return;
}

var url = this.mode === 'create'
? '/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/create-horario'
: '/departamento-operativo/recursos-humanos/biometricos/configuracion/retardo-horarios-incidencias/update-horario';

var payload = {
id: this.id,
id_estacion: idEst,
titulo: this.titulo,
hora_entrada: this.horaEntrada,
hora_salida: this.horaSalida
};

try {
var res = await this.createAction({
url: url,
data: payload,
table: '#table-horarios'
});

if (res && res.success) {
this.resetModal();
}
} catch (error) {
this.notify('error', 'Error al guardar');
}
}
};
});

});
