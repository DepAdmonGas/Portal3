document.addEventListener('alpine:init', () => {
Alpine.data('solicitudChequeEditarComponent', () => ({
guardando: false,
signaturePad: null,
errores: {},
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

formatMonto(value) {
if (value === null || value === undefined || value === '') return '';
const normalized = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
return normalized.includes('.') ? grouped + '.' + decimals : grouped;
},

formatearMonto(event) {
const input = event.target;
const raw = input.value;
const cursor = input.selectionStart ?? raw.length;
const digitsBeforeCursor = raw.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
const normalized = raw.replace(/,/g, '').replace(/[^0-9.]/g, '');
const parts = normalized.split('.');
const integer = parts.shift() || '';
const decimals = parts.join('');
const formatted = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (normalized.includes('.') ? '.' + decimals : '');
this.form.monto = normalized;
input.value = formatted;
let nextCursor = 0;
let numericChars = 0;
while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
if (/[0-9.]/.test(formatted[nextCursor])) numericChars++;
nextCursor++;
}
input.setSelectionRange(nextCursor, nextCursor);
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
this.errores = {
fecha: !/^\d{4}-\d{2}-\d{2}$/.test(String(this.form.fecha || '').trim()),
beneficiario: !String(this.form.beneficiario || '').trim(),
monto: String(this.form.monto ?? '').trim() === '' || !Number.isFinite(Number(this.form.monto)) || Number(this.form.monto) < 0,
moneda: !String(this.form.moneda || '').trim(),
no_factura: !String(this.form.no_factura || '').trim(),
email: !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(this.form.email || '').trim()),
concepto: !String(this.form.concepto || '').trim(),
solicitante: !String(this.form.solicitante || '').trim(),
telefono: !String(this.form.telefono || '').trim(),
cfdi: !String(this.form.cfdi || '').trim(),
metodo_pago: !String(this.form.metodo_pago || '').trim(),
forma_pago: !String(this.form.forma_pago || '').trim(),
banco: !String(this.form.banco || '').trim(),
no_cuenta: !String(this.form.no_cuenta || '').trim(),
cuenta_clabe: !String(this.form.cuenta_clabe || '').trim(),
referencia: !String(this.form.referencia || '').trim(),
};
if (this.idEstacion === 8) this.errores.razonsocial = !String(this.form.razonsocial || '').trim();
if (this.idEstacion === 14) this.errores.depto = !String(this.form.depto || '').trim();
if (Object.values(this.errores).some(Boolean)) {
if (window.Notify) Notify.error('Completa los campos obligatorios.');
return;
}

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
        if (json.validation) this.errores = { ...this.errores, ...json.validation };
        if (json.message === 'Completa los campos obligatorios.') {
            if (window.Notify) Notify.error(json.message);
        } else {
            alertify?.error(json.message || 'Error al actualizar');
        }
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
