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

const data = new FormData();
data.append('id_year', this.idYear);
data.append('id_mes', this.idMes);
data.append('fecha', this.form.fecha);
data.append('monto', this.form.monto);
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
