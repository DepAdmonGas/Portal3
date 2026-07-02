document.addEventListener('alpine:init', () => {
Alpine.data('solicitudChequeCrearComponent', () => ({
guardando: false,
signaturePad: null,
idEstacion: parseInt(document.querySelector('[data-id-estacion]')?.dataset.idEstacion || 0),
idDepto: parseInt(document.querySelector('[data-id-depto]')?.dataset.idDepto || 0),

form: {
fecha: new Date().toISOString().substring(0, 10),
beneficiario: '',
monto: '',
moneda: 'MXN',
no_factura: '',
email: '',
concepto: '',
solicitante: '',
telefono: '',
cfdi: '',
metodo_pago: '',
forma_pago: '',
banco: '',
no_cuenta: '',
cuenta_clabe: '',
referencia: '',
observaciones: '',
razonsocial: '',
depto: document.querySelector('[data-id-depto]')?.dataset.idDepto || '',
},

init() {
this.$nextTick(() => {
this.iniciarSignaturePad();
this._posicionarBadge();
});
},

_posicionarBadge() {
var switcheo = document.querySelector('span.mb-1.badge.rounded-pill.text-bg-info');
if (switcheo && switcheo.id !== 'contextBadge') switcheo.style.display = 'none';
},

iniciarSignaturePad() {
var w = document.getElementById('signature-pad');
var cv = w ? w.querySelector('canvas') : null;
if (cv && typeof SignaturePad !== 'undefined') {
this.signaturePad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
this._redimensionarCanvas();
window.addEventListener('resize', () => this._redimensionarCanvas());
}
},

_redimensionarCanvas() {
var w = document.getElementById('signature-pad');
var cv = w ? w.querySelector('canvas') : null;
if (!cv) return;
var r = Math.max(window.devicePixelRatio || 1, 1);
cv.width = cv.offsetWidth * r;
cv.height = cv.offsetHeight * r;
cv.getContext('2d').scale(r, r);
if (this.signaturePad) this.signaturePad.clear();
},

limpiarFirma() {
if (this.signaturePad) {
this.signaturePad.clear();
}
},

_notify(type, message) {
if (window.Notify) Notify.show(type, message);
},

async guardar() {
if (!this.form.fecha) { this._notify('error', 'Falta ingresar la fecha'); return; }
if (!this.form.beneficiario) { this._notify('error', 'Falta ingresar el beneficiario'); return; }
if (!this.form.monto) { this._notify('error', 'Falta ingresar el monto'); return; }
if (!this.form.no_factura) { this._notify('error', 'Falta ingresar el No. de factura'); return; }
if (!this.form.email) { this._notify('error', 'Falta ingresar el correo electrónico'); return; }
if (!this.form.concepto) { this._notify('error', 'Falta ingresar el concepto'); return; }
if (!this.form.solicitante) { this._notify('error', 'Falta ingresar el solicitante'); return; }
if (!this.form.telefono) { this._notify('error', 'Falta ingresar el teléfono'); return; }
if (!this.form.cfdi) { this._notify('error', 'Falta ingresar el CFDI'); return; }
if (!this.form.metodo_pago) { this._notify('error', 'Falta ingresar el método de pago'); return; }
if (!this.form.forma_pago) { this._notify('error', 'Falta ingresar la forma de pago'); return; }
if (!this.form.banco) { this._notify('error', 'Falta ingresar el banco'); return; }
if (!this.form.no_cuenta) { this._notify('error', 'Falta ingresar el No. de cuenta'); return; }
if (!this.form.cuenta_clabe) { this._notify('error', 'Falta ingresar la cuenta CLABE'); return; }
if (!this.form.referencia) { this._notify('error', 'Falta ingresar la referencia'); return; }

if (!this.signaturePad || this.signaturePad.isEmpty()) {
this._notify('error', 'Falta ingresar la firma');
return;
}

this.guardando = true;
try {
const c = document.querySelector('[data-id-year]');
const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);

const fd = new FormData();
fd.append('id_year', idYear);
fd.append('id_mes', idMes);
fd.append('id_estacion', this.idEstacion || 0);
fd.append('fecha', this.form.fecha);
fd.append('beneficiario', this.form.beneficiario);
fd.append('monto', this.form.monto);
fd.append('moneda', this.form.moneda);
fd.append('no_factura', this.form.no_factura);
fd.append('email', this.form.email);
fd.append('concepto', this.form.concepto);
fd.append('solicitante', this.form.solicitante);
fd.append('telefono', this.form.telefono);
fd.append('cfdi', this.form.cfdi);
fd.append('metodo_pago', this.form.metodo_pago);
fd.append('forma_pago', this.form.forma_pago);
fd.append('banco', this.form.banco);
fd.append('no_cuenta', this.form.no_cuenta);
fd.append('cuenta_clabe', this.form.cuenta_clabe);
fd.append('referencia', this.form.referencia);
fd.append('observaciones', this.form.observaciones);
fd.append('razonsocial', this.form.razonsocial);
fd.append('depto', this.form.depto);
fd.append('firma_base64', this.signaturePad.toDataURL());

for (let i = 0; i < 19; i++) {
const input = this.$refs['doc_' + i];
if (input && input.files && input.files[0]) {
fd.append('doc_' + i, input.files[0]);
}
}

const resp = await fetch('/departamento-operativo/solicitud-cheque/store', { method: 'POST', body: fd });
const json = await resp.json();

    if (json.success) {
        Swal.fire({
            icon: 'success',
            title: 'Solicitud creada',
            text: 'La solicitud de cheque se ha creado exitosamente.',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = '/departamento-operativo/solicitud-cheque/' + idYear + '/' + idMes;
        });
    } else {
        this._notify('error', json.message || 'Error al crear la solicitud');
    }
} catch (e) {
console.error('Error al guardar:', e);
this._notify('error', 'Error al crear la solicitud');
} finally {
this.guardando = false;
}
},
}));
});
