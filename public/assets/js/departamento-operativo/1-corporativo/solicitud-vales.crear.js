document.addEventListener('alpine:init', () => {
Alpine.data('solicitudValesCrearComponent', () => ({
guardando: false,
idYear: parseInt(document.querySelector('[data-id-year]')?.dataset.idYear || 0),
idMes: parseInt(document.querySelector('[data-id-mes]')?.dataset.idMes || 0),
mostrarCuenta: document.querySelector('[data-mostrar-cuenta]')?.dataset.mostrarCuenta === 'true',
estaciones: JSON.parse(document.querySelector('[data-estaciones]')?.dataset.estaciones || '[]'),

form: {
fecha: new Date().toISOString().substring(0, 10),
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
const campos = [
'fecha', 'monto', 'moneda', 'concepto', 'solicitante', 'departamento',
'autorizado_por', 'metodo_autorizacion', 'observaciones',
];
for (const campo of campos) {
const valor = campo === 'monto' ? this.normalizarMonto(this.form.monto) : this.form[campo];
if (!String(valor ?? '').trim() || (campo === 'monto' && (!Number.isFinite(Number(valor)) || Number(valor) <= 0))) {
this.errors[campo] = true;
}
}
if (this.mostrarCuenta) {
if (!this.form.estacion && !this.form.cuenta) {
this.errors.estacion = true;
this.errors.cuenta = true;
}
}
const valido = Object.keys(this.errors).length === 0;
if (!valido && window.Notify) Notify.error('Completa los campos obligatorios.');
return valido;
},

normalizarMonto(valor) {
return String(valor ?? '').replace(/,/g, '').trim();
},

formatearMonto(event) {
const input = event.target;
const posicion = input.selectionStart ?? input.value.length;
const digitosAntes = input.value.slice(0, posicion).replace(/[^0-9]/g, '').length;
let valor = input.value.replace(/[^0-9.]/g, '');
const punto = valor.indexOf('.');
if (punto !== -1) valor = valor.slice(0, punto + 1) + valor.slice(punto + 1).replace(/\./g, '').slice(0, 2);
const partes = valor.split('.');
partes[0] = (partes[0] || '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const formateado = partes.join('.');
this.form.monto = formateado;
input.value = formateado;
let nuevaPosicion = 0;
let vistos = 0;
while (nuevaPosicion < formateado.length && vistos < digitosAntes) {
if (/\d/.test(formateado[nuevaPosicion])) vistos++;
nuevaPosicion++;
}
input.setSelectionRange(nuevaPosicion, nuevaPosicion);
},

async guardar() {
if (!this.validar()) return;
this.guardando = true;

const data = new FormData();
data.append('id_year', this.idYear);
data.append('id_mes', this.idMes);
data.append('fecha', this.form.fecha);
data.append('monto', this.normalizarMonto(this.form.monto));
data.append('moneda', this.form.moneda);
data.append('concepto', this.form.concepto);
data.append('solicitante', this.form.solicitante);
data.append('departamento', this.form.departamento);
data.append('autorizado_por', this.form.autorizado_por);
data.append('metodo_autorizacion', this.form.metodo_autorizacion);
data.append('observaciones', this.form.observaciones);

if (this.mostrarCuenta) {
if (this.form.estacion) {
data.append('estacion', this.form.estacion);
data.append('cuenta', '');
} else if (this.form.cuenta) {
data.append('estacion', '');
data.append('cuenta', this.form.cuenta);
}
}

const vale = this.$refs.fileVale.files[0];
const recibo = this.$refs.fileRecibo.files[0];
const factura = this.$refs.fileFactura.files[0];
const pdf = this.$refs.filePDF.files[0];
const xml = this.$refs.fileXML.files[0];

if (vale) data.append('doc_vale', vale, vale.name);
if (recibo) data.append('doc_recibo', recibo, recibo.name);
if (factura) data.append('doc_factura', factura, factura.name);
if (pdf) data.append('doc_pdf', pdf, pdf.name);
if (xml) data.append('doc_xml', xml, xml.name);

try {
const response = await axios({
method: 'POST',
url: '/departamento-operativo/corporativo/solicitud-vales/add',
data: data,
headers: { 'Content-Type': 'multipart/form-data' },
});
const res = response.data;
if (res.success) {
Swal.fire({
icon: 'success',
title: 'Correcto',
text: 'Solicitud de Vale creada correctamente',
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
