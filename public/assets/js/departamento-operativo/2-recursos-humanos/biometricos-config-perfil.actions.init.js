document.addEventListener('alpine:init', function () {

Alpine.data('perfilForm', function () {

function validatePasswordStrength(password) {
var matchedCase = [
'[A-Z]',
'[a-z]',
'[0-9]'
];

if (password.length < 8) return 0;

var ctr = 0;
for (var i = 0; i < matchedCase.length; i++) {
if (new RegExp(matchedCase[i]).test(password)) ctr++;
}
return ctr;
}

function getStationId() {
var c = document.getElementById('container');
var moduleKey = c ? c.dataset.moduleStationKey || 'biometricos' : 'biometricos';
var sel = document.getElementById('module-station-selector-' + moduleKey);
if (sel && sel.value) {
var val = sel.value;
if (val.indexOf('depto_') === 0) return parseInt(val.replace('depto_', ''), 10);
return parseInt(val.replace('estacion_', ''), 10);
}
return 0;
}

return {
mode: 'create',
id: null,
usuario: '',
password: '',
validaPassword: '',
loading: false,

errors: {
usuario: false,
password: false,
passwordsMatch: true
},

passwordBorderClass: '',

onPasswordInput: function () {
var strength = validatePasswordStrength(this.password);
if (this.password.length === 0) {
this.passwordBorderClass = '';
} else if (strength === 3) {
this.passwordBorderClass = 'border border-2 border-success';
} else {
this.passwordBorderClass = 'border border-2 border-danger';
}
},

openCreateModal: function () {
this.mode = 'create';
this.id = null;
this.usuario = '';
this.password = '';
this.validaPassword = '';
this.errors = { usuario: false, password: false, passwordsMatch: true };
this.passwordBorderClass = '';
var modalEl = document.getElementById('modalPerfil');
if (modalEl) {
var modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

openEdit: function (data) {
this.mode = 'edit';
this.id = data.id;
this.usuario = data.usuario || '';
this.password = '';
this.validaPassword = '';
this.errors = { usuario: false, password: false, passwordsMatch: true };
this.passwordBorderClass = '';
var modalEl = document.getElementById('modalPerfil');
if (modalEl) {
var modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

validate: function () {
this.errors.usuario = !this.usuario.trim();

if (this.errors.usuario) {
this.notify('error', 'Ingresa el usuario');
return false;
}

var hasPassword = this.password.trim() !== '';

if (this.mode === 'create') {
if (!hasPassword) {
this.notify('error', 'Ingresa la contraseña');
return false;
}

if (validatePasswordStrength(this.password) < 3) {
this.notify('error', 'La contraseña debe tener al menos 8 caracteres, 1 mayúscula, 1 minúscula y 1 dígito');
return false;
}

if (this.password !== this.validaPassword) {
this.notify('error', 'Las contraseñas no coinciden');
return false;
}
} else {
if (hasPassword) {
if (validatePasswordStrength(this.password) < 3) {
this.notify('error', 'La contraseña debe tener al menos 8 caracteres, 1 mayúscula, 1 minúscula y 1 dígito');
return false;
}

if (this.password !== this.validaPassword) {
this.notify('error', 'Las contraseñas no coinciden');
return false;
}
}
}

return true;
},

resetForm: function () {
this.mode = 'create';
this.id = null;
this.usuario = '';
this.password = '';
this.validaPassword = '';
this.errors = { usuario: false, password: false, passwordsMatch: true };
this.passwordBorderClass = '';
},

resetModal: function () {
var self = this;
var modalEl = document.getElementById('modalPerfil');
modalEl.addEventListener('hidden.bs.modal', function () {
self.resetForm();
document.body.focus();
}, { once: true });
if (document.activeElement) {
document.activeElement.blur();
}
var modal = bootstrap.Modal.getInstance(modalEl);
if (modal) modal.hide();
},

submit: function () {
var self = this;
if (!this.validate()) return;

var stationId = getStationId();

if (this.mode === 'create' && stationId <= 0) {
this.notify('error', 'Selecciona una estación antes de agregar un perfil');
return;
}

var url = this.mode === 'create'
? '/departamento-operativo/recursos-humanos/biometricos/configuracion/perfil/create'
: '/departamento-operativo/recursos-humanos/biometricos/configuracion/perfil/update';

var payload = {
id: this.id,
id_estacion: stationId,
usuario: this.usuario,
password: this.password
};

this.createAction({
url: url,
data: payload,
table: '#table-perfil'
}).then(function (res) {
if (res && res.success) {
self.resetModal();
}
});
}

};
});

});
