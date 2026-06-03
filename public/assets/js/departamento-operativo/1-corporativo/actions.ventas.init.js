document.addEventListener('alpine:init', () => {

Alpine.data('ventasComponent', () => ({
idDia: null,
idYear: null,
idMes: null,
estado: 0,
multiestacion: false,
esSuperviso: false,
esVoBo: false,

ventas_dia: [],
ventas_dia_otros: [],
prosegur: [],
tarjetas_cb: [],
controlgas: [],
pago_clientes: [],
aceites: [],
documentos: [],
observaciones: '',
firmas: [],

signaturePad: null,
aceptoTerminos: false,
firmando: false,

nuevoDocumento: { nombre: '', file: null },
subiendoDocumento: false,

enviandoToken: false,
tokenGenerado: '',
tokenInput: '',
tokenError: '',

/* ---------- COMPUTED: totales locales ---------- */
get totales1234() {
const t1 = this.prosegur.reduce((s, p) => s + (parseFloat(p.importe) || 0), 0);
const t2 = this.tarjetas_cb.reduce((s, t) => s + (parseFloat(t.baucher) || 0), 0);
const t3 = this.controlgas.reduce((s, c) => s + (parseFloat(c.consumo) || 0), 0);
return { total1: t1, total2: t2, total3: t3, cTotal: t1 + t2 + t3 };
},

get totales_ventas() {
let subTL = 0, subJ = 0, subTLit = 0, subImp = 0;
for (const v of this.ventas_dia) {
const l = parseFloat(v.litros) || 0;
const j = parseFloat(v.jarras) || 0;
const pl = parseFloat(v.precio_litro) || 0;
const tl = l - j;
subTL += l;
subJ += j;
subTLit += tl;
subImp += tl * pl;
}
const sumO = this.ventas_dia_otros.reduce((s, o) => s + (parseFloat(o.importe) || 0), 0);
return { subTLitros: subTL, subJarras: subJ, subTotalLitros: subTLit, subImporteTotal: subImp, sumOtros: sumO, totalNeto: subImp + sumO };
},

get totales_aceites() {
let totC = 0, totP = 0;
for (const a of this.aceites) {
const c = parseInt(a.cantidad) || 0;
const p = parseFloat(a.precio_unitario) || 0;
totC += c;
totP += c * p;
}
return { totalCantidad: totC, totalPrecio: totP };
},

get total_pago_clientes() {
return this.pago_clientes.reduce((s, p) => s + (parseFloat(p.importe) || 0), 0);
},

get pago_total() {
return this.controlgas.reduce((s, c) => s + (parseFloat(c.pago) || 0), 0);
},

get diferenciaBC() {
return this.totales1234.cTotal - this.totales_ventas.totalNeto;
},

get difPagoClientes() {
return this.pago_total - this.total_pago_clientes;
},

get firmasElaboro() {
return this.firmas.find(f => f.detalle === 'Elaboró') || null;
},

get firmasSuperviso() {
return this.firmas.find(f => f.detalle === 'Superviso') || null;
},

get firmasVoBo() {
return this.firmas.find(f => f.detalle === 'VoBo') || null;
},

/* ---------- INIT ---------- */
init() {
const c = document.getElementById('container');
if (!c) return;
this.idDia = parseInt(c.dataset.idDia);
this.idYear = parseInt(c.dataset.idYear);
this.idMes = parseInt(c.dataset.idMes);
this.estado = parseInt(c.dataset.estado);
this.multiestacion = c.dataset.multiestacion === 'true';
this.esSuperviso = c.dataset.esSuperviso === 'true';
this.esVoBo = c.dataset.esVoBo === 'true';
this.loadData();
if (!this.multiestacion) setTimeout(() => this.initSignaturePad(), 500);
},

initSignaturePad() {
const w = document.getElementById('signature-pad');
const cv = w ? w.querySelector('canvas') : null;
if (cv && typeof SignaturePad !== 'undefined') {
this.signaturePad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
this._resizeCanvas();
window.addEventListener('resize', () => this._resizeCanvas());
}
},

_resizeCanvas() {
const w = document.getElementById('signature-pad');
const cv = w ? w.querySelector('canvas') : null;
if (!cv) return;
const r = Math.max(window.devicePixelRatio || 1, 1);
cv.width = cv.offsetWidth * r;
cv.height = cv.offsetHeight * r;
cv.getContext('2d').scale(r, r);
if (this.signaturePad) this.signaturePad.clear();
},

/* ---------- CARGA INICIAL ---------- */
loadData() {
axios.get('/departamento-operativo/ventas/data/' + this.idDia)
.then(r => {
if (!r.data.success) return;
this.ventas_dia = r.data.ventas_dia || [];
this.ventas_dia_otros = r.data.ventas_dia_otros || [];
this.prosegur = r.data.prosegur || [];
this.tarjetas_cb = r.data.tarjetas_cb || [];
this.controlgas = r.data.controlgas || [];
this.pago_clientes = r.data.pago_clientes || [];
this.aceites = r.data.aceites || [];
this.documentos = r.data.documentos || [];
this.observaciones = r.data.observaciones || '';
this.firmas = r.data.firmas || [];
this.estado = r.data.estado;
}).catch(() => {});
},



calcTotalLitros(v) {
return (parseFloat(v.litros) || 0) - (parseFloat(v.jarras) || 0);
},

calcImporteTotal(v) {
const tl = (parseFloat(v.litros) || 0) - (parseFloat(v.jarras) || 0);
return tl * (parseFloat(v.precio_litro) || 0);
},

calcAceiteImporte(a) {
return (parseInt(a.cantidad) || 0) * (parseFloat(a.precio_unitario) || 0);
},



/* ---------- INLINE EDIT - guardado silencioso sin loadData ---------- */
_edit(url, data) {
axios.put(url, data, { headers: { 'Content-Type': 'application/json' } }).catch(() => {});
},

editVenta(id, field, value) {
this._edit('/departamento-operativo/ventas/editar-venta', { id, field, value });
},

editVentaOtros(id, value) {
this._edit('/departamento-operativo/ventas/editar-venta-otros', { id, value });
},

editProsegur(id, field, value) {
this._edit('/departamento-operativo/ventas/editar-prosegur', { id, field, value });
},

editTarjeta(id, value) {
this._edit('/departamento-operativo/ventas/editar-tarjeta', { id, value });
},

editControlgas(id, field, value) {
this._edit('/departamento-operativo/ventas/editar-controlgas', { id, field, value });
},

editPagoCliente(id, field, value) {
this._edit('/departamento-operativo/ventas/editar-pago-cliente', { id, field, value });
},

editAceite(id, field, value) {
axios.put('/departamento-operativo/ventas/editar-aceite', { id, field, value }, {
headers: { 'Content-Type': 'application/json' }
}).then(r => {
if (r.data.success && r.data.ventas_dia_otros) {
const idx = this.ventas_dia_otros.findIndex(o => o.concepto === '4 ACEITES Y LUBRICANTES');
if (idx !== -1) this.ventas_dia_otros[idx] = r.data.ventas_dia_otros;
if (r.data.aceites) this.aceites = r.data.aceites;
}
}).catch(() => {});
},

editObservaciones() {
if (this._obsTimeout) clearTimeout(this._obsTimeout);
this._obsTimeout = setTimeout(() => {
axios.put('/departamento-operativo/ventas/editar-observaciones', {
id: this.idDia, observaciones: this.observaciones
}, { headers: { 'Content-Type': 'application/json' } }).catch(() => {});
}, 500);
},

/* ---------- NUEVA VENTA ---------- */
newVenta() {
axios.post('/departamento-operativo/ventas/' + this.idDia + '/nueva-venta', {}, {
headers: { 'Content-Type': 'application/json' }
}).then(r => {
if (r.data.success) {
this.ventas_dia.push(r.data.data);
}
}).catch(() => {});
},

/* ---------- DOCUMENTOS ---------- */
abrirModalDocumento() {
this.nuevoDocumento = { nombre: '', file: null };
this.subiendoDocumento = false;
new bootstrap.Modal(document.getElementById('modalDocumento')).show();
},

guardarDocumento() {
if (!this.nuevoDocumento.nombre) { if (window.Notify && window.Notify.error) window.Notify.error('Selecciona un tipo de documento'); return; }
if (!this.nuevoDocumento.file) { if (window.Notify && window.Notify.error) window.Notify.error('Selecciona un archivo'); return; }
this.subiendoDocumento = true;
const fd = new FormData();
fd.append('NombreDocumento', this.nuevoDocumento.nombre);
fd.append('Documento_file', this.nuevoDocumento.file);
axios.post('/departamento-operativo/ventas/' + this.idDia + '/subir-documento', fd).then(r => {
this.subiendoDocumento = false;
if (r.data.success) {
this.loadData();
bootstrap.Modal.getInstance(document.getElementById('modalDocumento')).hide();
if (window.Notify && window.Notify.success) window.Notify.success('Documento agregado exitosamente');
}
}).catch(() => { this.subiendoDocumento = false; if (window.Notify && window.Notify.error) window.Notify.error('Error al subir el documento'); });
},

/* ---------- TOKEN / FIRMA ADMIN ---------- */
crearToken(method) {
this.tokenError = '';
this.enviandoToken = true;
axios.post('/departamento-operativo/ventas/crear-token', { id: this.idDia, method }, {
headers: { 'Content-Type': 'application/json' }
}).then(r => {
this.enviandoToken = false;
if (r.data.success) {
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: 'success', title: 'Token enviado', text: r.data.message || 'Token enviado correctamente', timer: 2000, showConfirmButton: false });
} else if (window.Notify && window.Notify.success) {
window.Notify.success(r.data.message || 'Token enviado correctamente');
}
} else {
this.tokenError = r.data.message || 'Error';
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: 'error', title: 'Error', text: this.tokenError });
} else if (window.Notify && window.Notify.error) {
window.Notify.error(this.tokenError);
}
}
}).catch(() => { this.tokenError = 'Error al generar token'; if (window.Notify && window.Notify.error) window.Notify.error('Error al generar token'); })
.finally(() => { this.enviandoToken = false; });
},

firmarConToken() {
if (!this.tokenInput) { this.tokenError = 'Ingresa el token de seguridad'; return; }
this.tokenError = '';
this.enviandoToken = true;
axios.post('/departamento-operativo/ventas/firmar-token', { id: this.idDia, token: this.tokenInput }, {
headers: { 'Content-Type': 'application/json' }
}).then(r => {
this.enviandoToken = false;
if (r.data.success) {
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: 'success', title: 'Firma registrada', text: 'Firma registrada correctamente', timer: 1500, showConfirmButton: false });
} else if (window.Notify && window.Notify.success) {
window.Notify.success('Firma registrada correctamente');
}
setTimeout(() => location.reload(), 1200);
} else {
this.tokenError = r.data.message || 'Token inválido';
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: 'error', title: 'Error', text: this.tokenError });
} else if (window.Notify && window.Notify.error) {
window.Notify.error(this.tokenError);
}
}
}).catch(() => { this.tokenError = 'Error al validar el token'; if (window.Notify && window.Notify.error) window.Notify.error('Error al validar el token'); })
.finally(() => { this.enviandoToken = false; });
},

/* ---------- FIRMA / FINALIZAR ---------- */
abrirModalFirma() {
if (!this.aceptoTerminos) {
if (window.Notify && window.Notify.error) window.Notify.error('Debes aceptar los terminos del Corte Diario');
window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
return;
}
if (this.signaturePad && this.signaturePad.isEmpty()) {
if (window.Notify && window.Notify.error) window.Notify.error('Falta firma');
window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
return;
}
if (typeof Swal !== 'undefined') {
Swal.fire({
title: '¿Finalizar ventas?',
text: 'Se registrará tu firma y no podrás realizar cambios posteriores.',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33',
reverseButtons: true
}).then(result => {
if (result.isConfirmed) this.finalizarVentas();
});
}
},

finalizarVentas() {
this.firmando = true;
Swal.fire({
title: 'Finalizando...',
text: 'Procesando la solicitud, por favor espera.',
allowOutsideClick: false,
allowEscapeKey: false,
showConfirmButton: false,
didOpen: () => Swal.showLoading()
});
const cv = document.getElementById('canvas');
axios.post('/departamento-operativo/ventas/firmar', { id: this.idDia, base64: cv ? cv.toDataURL() : '' }, {
headers: { 'Content-Type': 'application/json' }
}).then(r => {
this.firmando = false;
Swal.close();
if (r.data.success) {
Swal.fire({ icon: 'success', title: 'Venta finalizada', text: 'Venta finalizada correctamente', timer: 1500, showConfirmButton: false });
setTimeout(() => location.reload(), 1200);
} else {
Swal.fire({ icon: 'error', title: 'Error', text: 'Error al firmar el Corte Diario' });
}
}).catch(() => {
this.firmando = false;
Swal.close();
Swal.fire({ icon: 'error', title: 'Error', text: 'Error al firmar el Corte Diario' });
});
},

limpiarFirma() {
if (this.signaturePad) this.signaturePad.clear();
this._resizeCanvas();
},

downloadPdf() {
window.location = '/departamento-operativo/ventas/' + this.idYear + '/' + this.idMes + '/' + this.idDia + '/pdf';
}
}));
});

