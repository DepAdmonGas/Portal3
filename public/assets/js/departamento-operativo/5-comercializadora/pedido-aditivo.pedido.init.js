document.addEventListener('alpine:init', () => {

Alpine.data('pedidoAditivoPedidoComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esEncargado: false,
signaturePad: null,
firmaVacia: true,
validationErrors: {},
resizeFirma: null,
reintentosFirma: 0,

solicitudActual: null,
form: { fecha: '', para: '', fecha_entrega: '', comentarios: '' },
nuevoTambo: { producto: '', cantidad: 1 },
finalizando: false,
guardandoDatos: false,
repetirGuardado: false,
guardandoTambo: false,

BASE: '/departamento-operativo/comercializadora/pedido-aditivo',

ADITIVOS: {
GASOLINA: { aditivo: 'HITEC 6590C Drum', kilogramo: 185 },
DIESEL: { aditivo: 'HITEC 4133G Drum', kilogramo: 180 }
},

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeFirmar = c.dataset.puedeFirmar === 'true';
this.esEncargado = c.dataset.esEncargado === 'true';

if (c.dataset.solicitud) {
try { this.solicitudActual = JSON.parse(c.dataset.solicitud); } catch (e) { console.error('Error parseando solicitud:', e); }
}

window.pedidoAditivoPedidoComponentInstance = this;

this.sincronizarForm();
this.initSignaturePad();
},

invalid(field) { return this.validationErrors[field] === false; },

validateFields(fields) {
this.validationErrors = fields;
const valid = Object.values(fields).every(Boolean);
if (!valid && window.Notify) Notify.error('Completa los campos obligatorios.');
return valid;
},

clearField(field) { this.validationErrors[field] = true; },

positiveQuantity(value) {
const normalized = String(value ?? '').replace(/,/g, '');
return /^\d+$/.test(normalized) && Number.isSafeInteger(Number(normalized))
&& Number(normalized) > 0 && Number(normalized) <= 2147483647;
},

formatNumericInput(event, row, field) {
const input = event.target;
const raw = input.value;
const cursor = input.selectionStart ?? raw.length;
const digitsBeforeCursor = raw.slice(0, cursor).replace(/,/g, '').replace(/\D/g, '').length;
const normalized = raw.replace(/,/g, '').replace(/\D/g, '');
const formatted = normalized.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
row[field] = normalized;
input.value = formatted;
let nextCursor = 0;
let numericChars = 0;
while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
if (/\d/.test(formatted[nextCursor])) numericChars++;
nextCursor++;
}
input.setSelectionRange(nextCursor, nextCursor);
if (this.positiveQuantity(normalized)) this.clearField(field);
},

formatInputNumber(value) {
return String(value ?? '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
},

displayQuantity(value) {
return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
},

initSignaturePad() {
this.$nextTick(() => requestAnimationFrame(() => {
if (Number(this.solicitudActual?.status) !== 0 || !(this.esEncargado || this.puedeEditar)) return;
if (!window.SignaturePad) { console.error('SignaturePad no disponible'); return; }
const canvas = document.querySelector('#signature-pad-aditivo');
if (!canvas || canvas.getBoundingClientRect().width <= 0 || canvas.getBoundingClientRect().height <= 0) {
if (this.reintentosFirma++ < 10) setTimeout(() => this.initSignaturePad(), 100);
return;
}
this.reintentosFirma = 0;
this.signaturePad?.off();
this.signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)', minWidth: 1, maxWidth: 2.5, penColor: '#000000', onEnd: () => { this.firmaVacia = this.signaturePad.isEmpty(); if (!this.firmaVacia) this.clearField('firma'); } });
this.redimensionarCanvasFirma();
this.resizeFirma = () => this.redimensionarCanvasFirma();
window.addEventListener('resize', this.resizeFirma);
this.firmaVacia = this.signaturePad.isEmpty();
}));
},

redimensionarCanvasFirma() {
const canvas = document.getElementById('signature-pad-aditivo');
if (!canvas) return;
const width = canvas.getBoundingClientRect().width;
const height = canvas.getBoundingClientRect().height;
if (width <= 0 || height <= 0) return;
const data = this.signaturePad && !this.signaturePad.isEmpty() ? this.signaturePad.toData() : null;
const ratio = Math.max(window.devicePixelRatio || 1, 1);
canvas.width = Math.round(width * ratio);
canvas.height = Math.round(height * ratio);
canvas.getContext('2d').scale(ratio, ratio);
if (data) this.signaturePad.fromData(data);
},

limpiarFirma() { if (this.signaturePad) this.signaturePad.clear(); this.firmaVacia = true; },

destroy() { if (this.resizeFirma) window.removeEventListener('resize', this.resizeFirma); this.signaturePad?.off(); },

sincronizarForm() {
if (!this.solicitudActual) return;
this.form = {
fecha: this.solicitudActual.fecha_iso || '',
para: this.solicitudActual.para || '',
fecha_entrega: this.solicitudActual.fecha_entrega_iso || '',
comentarios: this.solicitudActual.comentarios || ''
};
},

async cargarSolicitud() {
if (!this.solicitudActual) return;
try {
const resp = await axios.get(this.BASE + '/detalle?id=' + this.solicitudActual.id);
const json = resp.data;
if (json.success && json.solicitud) {
this.solicitudActual = json.solicitud;
if (json.solicitud.status === 0) {
this.sincronizarForm();
}
}
} catch (e) {
console.error('Error cargando solicitud:', e);
}
},

statusBadgeClass(status) {
if (status === 0) return 'bg-danger';
if (status === 1) return 'bg-warning-subtle text-warning-emphasis';
return 'bg-success-subtle text-success-emphasis';
},

/* ================= DATOS ================= */

async guardarDatos() {
if (this.guardandoDatos) { this.repetirGuardado = true; return; }

if (this.form.fecha_entrega) this.clearField('fecha_entrega');

if (!this.form.fecha) {
if (window.Notify) Notify.error('La fecha es obligatoria');
return;
}
if (!this.form.para) {
if (window.Notify) Notify.error('Debe seleccionar la razón social');
return;
}

this.guardandoDatos = true;
try {
const fd = new FormData();
fd.append('id', this.solicitudActual.id);
fd.append('fecha', this.form.fecha);
fd.append('para', this.form.para);
fd.append('fecha_entrega', this.form.fecha_entrega || '');
fd.append('comentarios', this.form.comentarios || '');
const resp = await axios.post(this.BASE + '/guardar-datos', fd, {
headers: { 'X-Portal3-Skip-Action-Loader': '1' }
});
const json = resp.data;
if (json.success) {
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al guardar los datos');
}
} catch (e) {
console.error('Error guardando datos:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al guardar los datos');
} finally {
this.guardandoDatos = false;
if (this.repetirGuardado) {
this.repetirGuardado = false;
this.guardarDatos();
}
}
},

/* ================= TAMBOS ================= */

modalAgregarTambo() {
this.nuevoTambo = { producto: '', cantidad: 1 };
const m = document.getElementById('modalAgregarTambo');
bootstrap.Modal.getOrCreateInstance(m).show();
},

async agregarTambo() {
if (this.guardandoTambo) return;

const producto = this.nuevoTambo.producto || '';
const cantidad = String(this.nuevoTambo.cantidad || '').trim();
if (!this.validateFields({ producto: !!producto, cantidad: this.positiveQuantity(cantidad) })) return;

this.guardandoTambo = true;
try {
const fd = new FormData();
fd.append('id_reporte', this.solicitudActual.id);
fd.append('producto', producto);
fd.append('cantidad', cantidad);
const resp = await axios.post(this.BASE + '/agregar-tambo', fd);
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarTambo');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.nuevoTambo = { producto: '', cantidad: 1 };
if (window.Notify) Notify.success(json.message || 'Tambo agregado correctamente');
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el tambo');
}
} catch (e) {
console.error('Error agregando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al agregar el tambo');
} finally {
this.guardandoTambo = false;
}
},

cambiarProducto(it) {
const item = this.ADITIVOS[it.producto];
if (!item) return;
it.aditivo = item.aditivo;
it.kilogramo = item.kilogramo;
this.editarTamboRow(it);
},

editarTamboCantidad(id, valor) {
const cantidad = String(valor || '').trim();
if (!this.positiveQuantity(cantidad)) {
 this.validationErrors['cantidad_' + id] = false;
 if (window.Notify) Notify.error('Completa los campos obligatorios.');
this.cargarSolicitud();
return;
}
this.clearField('cantidad_' + id);
const it = (this.solicitudActual.tambos || []).find(function (t) { return t.id === id; });
if (!it) return;
it.cantidad = cantidad;
this.editarTamboRow(it);
},

async editarTamboRow(it) {
try {
const fd = new FormData();
fd.append('id', it.id);
fd.append('cantidad', it.cantidad);
fd.append('producto', it.producto || '');
const resp = await axios.post(this.BASE + '/editar-tambo', fd, {
headers: { 'X-Portal3-Skip-Action-Loader': '1' }
});
const json = resp.data;
if (!json.success) {
if (window.Notify) Notify.error(json.message || 'Error al actualizar el tambo');
}
await this.cargarSolicitud();
} catch (e) {
console.error('Error editando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al actualizar el tambo');
}
},

async eliminarTambo(id) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El tambo será eliminado de la solicitud',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;

try {
const resp = await axios.post(this.BASE + '/eliminar-tambo', { id: id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Tambo eliminado correctamente');
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el tambo');
}
} catch (e) {
console.error('Error eliminando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el tambo');
}
},

/* ================= FINALIZAR ================= */

async finalizarSolicitud() {
if (this.finalizando) return;

this.firmaVacia = !this.signaturePad || this.signaturePad.isEmpty();
if (!this.validateFields({
fecha_entrega: !!this.form.fecha_entrega,
productos: !!(this.solicitudActual.tambos && this.solicitudActual.tambos.length),
firma: !this.firmaVacia
})) return;

const confirm = await Swal.fire({
title: '¿Finalizar solicitud?',
text: 'La solicitud pasará a espera de la firma de Autorización',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar'
});
if (!confirm.isConfirmed) return;

this.finalizando = true;
try {
const resp = await axios.post(this.BASE + '/finalizar', { id: this.solicitudActual.id, firma_base64: this.signaturePad.toDataURL('image/png') });
const json = resp.data;
if (json.success) {
Swal.fire({
icon: 'success',
title: 'Solicitud finalizada',
text: json.message || 'Solicitud finalizada correctamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
window.location.reload();
});
} else {
if (window.Notify) Notify.error(json.message || 'Error al finalizar la solicitud');
}
} catch (e) {
console.error('Error finalizando solicitud:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al finalizar la solicitud');
} finally {
this.finalizando = false;
}
},

}));

});
