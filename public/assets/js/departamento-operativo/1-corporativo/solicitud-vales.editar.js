document.addEventListener('alpine:init', () => {
Alpine.data('solicitudValesEditarComponent', () => ({
guardando: false,
id: 0,
idYear: parseInt(document.querySelector('[data-id-year]')?.dataset.idYear || 0),
idMes: parseInt(document.querySelector('[data-id-mes]')?.dataset.idMes || 0),
mostrarCuenta: document.querySelector('[data-mostrar-cuenta]')?.dataset.mostrarCuenta === 'true',
estaciones: JSON.parse(document.querySelector('[data-estaciones]')?.dataset.estaciones || '[]'),

init() {
const detalle = JSON.parse(document.querySelector('[data-detalle]')?.dataset.detalle || '{}');
this.id = detalle.id || 0;
this.form.fecha = detalle.fecha || '';
this.form.monto = detalle.monto ?? '';
this.form.moneda = detalle.moneda || 'MXN';
this.form.concepto = detalle.concepto || '';
this.form.solicitante = detalle.solicitante || '';
this.form.departamento = String(detalle.depto || '');
    this.form.cuenta = detalle.cuenta || '';
    this.form.autorizado_por = detalle.autorizado_por || '';
    this.form.metodo_autorizacion = detalle.metodo_autorizacion || '';
    this.form.observaciones = detalle.observaciones || '';

    this.$nextTick(() => {
        this.form.estacion = detalle.id_estacion || '';
        this.toggleCuentaCampos();
    });
},

form: {
fecha: '',
monto: '',
moneda: 'MXN',
concepto: '',
solicitante: '',
departamento: '',
estacion: '',
cuenta: '',
autorizado_por: '',
metodo_autorizacion: '',
observaciones: '',
},
errors: {},

toggleCuentaCampos() {
const estacionEl = this.$refs.estacionSelect;
const cuentaEl = this.$refs.cuentaInput;
if (!estacionEl || !cuentaEl) return;
if (this.form.estacion !== '') {
this.form.cuenta = '';
cuentaEl.disabled = true;
estacionEl.disabled = false;
} else if (this.form.cuenta !== '') {
this.form.estacion = '';
estacionEl.disabled = true;
cuentaEl.disabled = false;
} else {
estacionEl.disabled = false;
cuentaEl.disabled = false;
}
},

validar() {
this.errors = {};
let valido = true;
const campos = [
{ key: 'fecha', label: 'Fecha' },
{ key: 'monto', label: 'Monto' },
{ key: 'concepto', label: 'Concepto' },
{ key: 'solicitante', label: 'Nombre del solicitante' },
{ key: 'autorizado_por', label: 'Autorizado por' },
{ key: 'metodo_autorizacion', label: 'Método de autorización' },
];
for (const c of campos) {
if (!this.form[c.key] || (c.key === 'monto' && parseFloat(this.form.monto) <= 0)) {
this.errors[c.key] = true;
if (window.Notify) Notify.error('* ' + c.label + ' requerido');
valido = false;
}
}
if (this.mostrarCuenta) {
if (!this.form.estacion && !this.form.cuenta) {
this.errors.estacion = true;
this.errors.cuenta = true;
if (window.Notify) Notify.error('* Estación o Cuenta requerido');
valido = false;
}
}
return valido;
},

async guardar() {
if (!this.validar()) return;
this.guardando = true;

try {
const payload = {
id: this.id,
fecha: this.form.fecha,
monto: this.form.monto,
moneda: this.form.moneda,
concepto: this.form.concepto,
solicitante: this.form.solicitante,
departamento: this.form.departamento,
autorizado_por: this.form.autorizado_por,
metodo_autorizacion: this.form.metodo_autorizacion,
observaciones: this.form.observaciones,
};
if (this.mostrarCuenta) {
if (this.form.estacion) {
payload.estacion = this.form.estacion;
payload.cuenta = '';
} else if (this.form.cuenta) {
payload.estacion = '';
payload.cuenta = this.form.cuenta;
} else {
payload.estacion = '';
payload.cuenta = '';
}
}
const response = await axios({
method: 'POST',
url: '/departamento-operativo/corporativo/solicitud-vales/edit',
data: payload,
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
});
const res = response.data;
if (res.success) {
Swal.fire({
icon: 'success',
title: 'Correcto',
text: 'Solicitud de Vale actualizada correctamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
window.location.href = '/departamento-operativo/corporativo/solicitud-vales/' + this.idYear + '/' + this.idMes;
});
} else {
if (window.Notify) Notify.error(res.message || 'Error');
this.guardando = false;
}
} catch (err) {
console.error('ERROR:', err);
const mensaje = err.response?.data?.message || err.message || 'Error en la solicitud';
if (window.Notify) Notify.error(mensaje);
this.guardando = false;
}
},
}));
});
