document.addEventListener('alpine:init', () => {
Alpine.data('solicitudChequeEditarComponent', () => ({
guardando: false,
signaturePad: null,
idEstacion: parseInt(document.querySelector('[data-id-estacion]')?.dataset.idEstacion || 0),

form: {
fecha: '',
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
depto: '',
},

initForm(data) {
if (data) {
this.form.fecha = data.fecha || '';
this.form.beneficiario = data.beneficiario || '';
this.form.monto = data.monto || '';
this.form.moneda = data.moneda || 'MXN';
this.form.no_factura = data.no_factura || '';
this.form.email = data.email || '';
this.form.concepto = data.concepto || '';
this.form.solicitante = data.solicitante || '';
this.form.telefono = data.telefono || '';
this.form.cfdi = data.cfdi || '';
this.form.metodo_pago = data.metodo_pago || '';
this.form.forma_pago = data.forma_pago || '';
this.form.banco = data.banco || '';
this.form.no_cuenta = data.no_cuenta || '';
this.form.cuenta_clabe = data.cuenta_clabe || '';
this.form.referencia = data.referencia || '';
this.form.observaciones = data.observaciones || '';
this.form.razonsocial = data.razonsocial || '';
this.form.depto = data.depto || '';
}
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
if (this.signaturePad) this.signaturePad.clear();
},

async guardar() {
if (!this.form.fecha) { alertify?.error('Falta ingresar la fecha'); return; }
if (!this.form.beneficiario) { alertify?.error('Falta ingresar el beneficiario'); return; }
if (!this.form.monto) { alertify?.error('Falta ingresar el monto'); return; }
if (!this.form.no_factura) { alertify?.error('Falta ingresar el No. de factura'); return; }
if (!this.form.email) { alertify?.error('Falta ingresar el correo electrónico'); return; }
if (!this.form.concepto) { alertify?.error('Falta ingresar el concepto'); return; }
if (!this.form.solicitante) { alertify?.error('Falta ingresar el solicitante'); return; }
if (!this.form.telefono) { alertify?.error('Falta ingresar el teléfono'); return; }
if (!this.form.cfdi) { alertify?.error('Falta ingresar el CFDI'); return; }
if (!this.form.metodo_pago) { alertify?.error('Falta ingresar el método de pago'); return; }
if (!this.form.forma_pago) { alertify?.error('Falta ingresar la forma de pago'); return; }
if (!this.form.banco) { alertify?.error('Falta ingresar el banco'); return; }
if (!this.form.no_cuenta) { alertify?.error('Falta ingresar el No. de cuenta'); return; }
if (!this.form.cuenta_clabe) { alertify?.error('Falta ingresar la cuenta CLABE'); return; }
if (!this.form.referencia) { alertify?.error('Falta ingresar la referencia'); return; }

this.guardando = true;
try {
const c = document.querySelector('[data-id-solicitud]');
const idSolicitud = parseInt(c.dataset.idSolicitud);
const idYear = parseInt(c.dataset.idYear);
const idMes = parseInt(c.dataset.idMes);

const fd = new FormData();
fd.append('id', idSolicitud);
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

if (this.signaturePad && !this.signaturePad.isEmpty()) {
fd.append('firma_base64', this.signaturePad.toDataURL());
}

for (let i = 0; i < 19; i++) {
const input = this.$refs['doc_' + i];
if (input && input.files && input.files[0]) {
fd.append('doc_' + i, input.files[0]);
}
}

const resp = await fetch('/departamento-operativo/solicitud-cheque/update', { method: 'POST', body: fd });
const json = await resp.json();

    if (json.success) {
        Swal.fire({
            icon: 'success',
            title: 'Solicitud actualizada',
            text: 'La solicitud de cheque se ha actualizado exitosamente.',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = '/departamento-operativo/solicitud-cheque/' + idYear + '/' + idMes;
        });
    } else {
        alertify?.error(json.message || 'Error al actualizar');
    }
} catch (e) {
console.error('Error al guardar:', e);
alertify?.error('Error al actualizar la solicitud');
} finally {
this.guardando = false;
}
},
}));
});
