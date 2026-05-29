document.addEventListener('alpine:init', () => {

Alpine.data('controlVolumetricoComponent', () => ({
idMesDb: null,
idYear: null,
idMes: null,
idEstacion: null,
estado: 0,
multiestacion: false,
tipoPuesto: '',
loading: true,

productos: [],
aceites: { piezas: 0, volumetrico: 0, contables: 0, diferencia: 0 },
totales: { dato3: 0, dato4: 0, dato5: 0, dato6: 0, dato7: 0, dato8: 0, dato9: 0, dato10: 0, dato11: 0, dato12: 0, dato13: 0, dato14: 0, dif2: 0, dif3: 0, dif4: 0, dif5: 0, dif6: 0, dif7: 0 },
documentos: [],
prefijos: [],
prefijoTotals: { sum_gasolina: 0, sum_rentas: 0, sum_sodexo: 0, sum_autolavado: 0, sum_gtotal: 0 },
granTotal: 0,
comentarios: [],

nuevoComentario: '',
guardandoComentario: false,
subiendoDocumento: false,

init() {
const c = document.getElementById('container');
if (!c) return;
this.idMesDb = parseInt(c.dataset.idMesDb);
this.idYear = parseInt(c.dataset.idYear);
this.idMes = parseInt(c.dataset.idMes);
this.idEstacion = parseInt(c.dataset.idEstacion);
this.estado = parseInt(c.dataset.estado);
this.multiestacion = c.dataset.multiestacion === 'true';
this.tipoPuesto = c.dataset.tipoPuesto || '';
this.cargarDatos();
},

async cargarDatos() {
this.loading = true;
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/data?id_mes=' + this.idMesDb);
const json = await resp.json();
if (json.success) {
this.estado = json.estado;
this.productos = json.data.productos || [];
this.aceites = json.data.aceites || { piezas: 0, volumetrico: 0, contables: 0, diferencia: 0 };
this.totales = json.data.totales || this.totales;
this.documentos = json.documentos || [];
this.prefijos = json.prefijos || [];
this.comentarios = json.comentarios || [];
this.scrollChatToBottom();
this.recalcularTotales();
}
} catch (e) {
console.error('Error cargando datos:', e);
} finally {
this.loading = false;
}
},

recalcularTotales() {
let s3 = 0, s4 = 0, s5 = 0, s6 = 0, s7 = 0, s8 = 0, s9 = 0, s10 = 0, s11 = 0, s12 = 0, s13 = 0, s14 = 0;
for (const p of this.productos) {
s3 += p.dato3; s4 += p.dato4;
s5 += p.dato5; s6 += p.dato6;
s7 += p.dato7; s8 += p.dato8;
s9 += p.dato9; s10 += p.dato10;
s11 += p.dato11; s12 += p.dato12;
s13 += p.dato13; s14 += p.dato14;
}
this.totales = {
dato3: s3, dato4: s4, dato5: s5, dato6: s6,
dato7: s7, dato8: s8, dato9: s9, dato10: s10,
dato11: s11, dato12: s12, dato13: s13, dato14: s14,
dif2: s3 - s4, dif3: s5 - s6, dif4: s7 - s8,
dif5: s9 - s10, dif6: s11 - s12, dif7: s13 - s14,
};

this.aceites.diferencia = this.aceites.volumetrico - this.aceites.contables;

this.recalcularPrefijoTotals();
this.recalcularGranTotal();
},

recalcularPrefijoTotals() {
let sg = 0, sr = 0, ss = 0, sa = 0, sgt = 0;
for (const p of this.prefijos) {
const total = (p.serie !== 'K' && p.serie !== 'CP') ? (parseFloat(p.total) || 0) : 0;
const gas = (p.serie !== 'RL' && p.serie !== 'S' && p.serie !== 'K' && p.serie !== 'CP' && p.serie !== 'CA') ? (parseFloat(p.total) || 0) : 0;
sg += gas;
sr += (p.serie === 'RL') ? (parseFloat(p.total) || 0) : 0;
ss += (p.serie === 'S') ? (parseFloat(p.total) || 0) : 0;
sa += (p.serie === 'AL') ? (parseFloat(p.total) || 0) : 0;
sgt += total;
}
this.prefijoTotals = { sum_gasolina: sg, sum_rentas: sr, sum_sodexo: ss, sum_autolavado: sa, sum_gtotal: sgt };
},

recalcularGranTotal() {
let s9 = 0;
for (const p of this.productos) s9 += p.dato9;
this.granTotal = s9 - this.prefijoTotals.sum_gasolina;
},

getProductColor(producto) {
if (producto === 'G SUPER') return '#76bd1d';
if (producto === 'G PREMIUM') return '#e21683';
if (producto === 'G DIESEL') return '#000000';
return '#6c757d';
},

diffColor(val) {
return (typeof val === 'number' && val >= 0) ? '' : 'text-danger';
},

paramColor(val) {
return (typeof val === 'number' && val >= 1.5) ? 'text-danger' : '';
},

formatNum(n) {
return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

formatDisplay(n) {
return this.formatNum(n);
},

formatInt(n) {
return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
},

get anexosOpciones() {
const base = [
'Tirilla de inventarios',
'Control de despachos',
'Control volumétrico',
'Acuse de recepción controles volumétricos',
'Acuse de aceptación controles volumétricos',
'Jarreo',
'UUID CV',
'UUID SAT',
'Compras de Combustible',
'Ventas',
];
const puedeVerExtra = this.tipoPuesto === 'Contabilidad' || this.tipoPuesto === 'Dirección de operaciones' || (this.tipoPuesto === 'Encargado' && [1, 2, 3, 4, 5, 6, 7, 14].includes(this.idEstacion));
if (puedeVerExtra) {
return [...base, 'Opinión de cumplimiento', 'Reporte de facturas canceladas'];
}
return base;
},

getFileIcon(documento) {
const ext = (documento || '').split('.').pop().toLowerCase();
if (ext === 'xml') return 'ti ti-file-type-xml text-primary';
if (ext === 'pdf') return 'ti ti-file-type-pdf text-danger';
if (ext === 'xlsx' || ext === 'xls') return 'ti ti-file-spreadsheet text-success';
if (ext === 'zip') return 'ti ti-file-zip text-warning';
return 'ti ti-file text-muted';
},

async editarResumen(id, campo, value) {
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/editar-resumen', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, campo, valor: value })
});
const json = await resp.json();
if (!json.success && window.Notify) {
Notify.error(json.message || 'Error al guardar');
}
this.recalcularTotales();
} catch (e) {
console.error('Error al editar resumen:', e);
}
},

async editarComentarioResumen(id) {
const p = this.productos.find(x => x.id === id);
if (!p) return;
try {
await fetch('/departamento-operativo/control-volumetrico/editar-comentario-resumen', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, comentario: p.comentario })
});
} catch (e) {
console.error('Error al editar comentario:', e);
}
},

async editarAceite() {
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/editar-aceite', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_mes: this.idMesDb, valor: this.aceites.volumetrico })
});
const json = await resp.json();
if (!json.success && window.Notify) {
Notify.error(json.message || 'Error al guardar aceite');
}
this.aceites.diferencia = this.aceites.volumetrico - this.aceites.contables;
} catch (e) {
console.error('Error al editar aceite:', e);
}
},

async editarPrefijo(id) {
const p = this.prefijos.find(x => x.id === id);
if (!p) return;
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/editar-prefijo', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, total: p.total })
});
const json = await resp.json();
if (!json.success && window.Notify) {
Notify.error(json.message || 'Error al guardar prefijo');
}
this.recalcularTotales();
} catch (e) {
console.error('Error al editar prefijo:', e);
}
},

async agregarComentario() {
if (!this.nuevoComentario.trim()) return;
this.guardandoComentario = true;
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/agregar-comentario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_mes: this.idMesDb, comentario: this.nuevoComentario })
});
const json = await resp.json();
if (json.success) {
this.nuevoComentario = '';
await this.recargarComentarios();
if (window.Notify) Notify.success('Comentario agregado');
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar comentario');
}
} catch (e) {
if (window.Notify) Notify.error('Error al agregar comentario');
} finally {
this.guardandoComentario = false;
}
},

abrirModalDocumento() {
this.subiendoDocumento = false;
const modalEl = document.getElementById('modalDocumento');
const fechaEl = document.getElementById('docFecha');
if (fechaEl) fechaEl.value = new Date().toISOString().substring(0, 10);
const fileEl = document.getElementById('docFile');
if (fileEl) fileEl.value = '';

this.$nextTick(() => {
const $select = $('#docAnexos');
if ($select.length) {
if ($select.hasClass('select2-hidden-accessible')) {
$select.select2('destroy');
}
$select.select2({
dropdownParent: $('#modalDocumento .modal-content'),
width: '100%',
placeholder: 'Seleccionar...',
allowClear: true,
});
}
const wr = this.$refs.anexosWrapper;
if (wr) wr.classList.remove('is-select2-pending');
});

const modal = new bootstrap.Modal(modalEl);
modal.show();

if (!modalEl._select2Cleanup) {
modalEl._select2Cleanup = true;
modalEl.addEventListener('hidden.bs.modal', () => {
const wr = this.$refs.anexosWrapper;
if (wr) wr.classList.add('is-select2-pending');
});
}
},

async recargarDocumentos() {
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/documentos-list?id_mes=' + this.idMesDb);
const json = await resp.json();
if (json.success) {
this.documentos = json.documentos || [];
}
} catch (e) {
console.error('Error recargando documentos:', e);
}
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async recargarComentarios() {
try {
const resp = await fetch('/departamento-operativo/control-volumetrico/comentarios-list?id_mes=' + this.idMesDb);
const json = await resp.json();
if (json.success) {
this.comentarios = json.comentarios || [];
this.scrollChatToBottom();
}
} catch (e) {
console.error('Error recargando comentarios:', e);
}
},

async guardarDocumento() {
const fecha = document.getElementById('docFecha').value;
const $select = $('#docAnexos');
const anexos = $select.val() || '';
const fileInput = document.getElementById('docFile');

if (!fecha) { if (window.Notify) Notify.error('* Agregar fecha'); return; }
if (!anexos) { if (window.Notify) Notify.error('* Agregar Anexo'); return; }
if (!fileInput || !fileInput.files[0]) { if (window.Notify) Notify.error('* Agregar Documento'); return; }

this.subiendoDocumento = true;
const fd = new FormData();
fd.append('id_mes', this.idMesDb);
fd.append('fecha', fecha);
fd.append('anexos', anexos);
fd.append('documento', fileInput.files[0]);

try {
const resp = await fetch('/departamento-operativo/control-volumetrico/subir-documento', {
method: 'POST',
body: fd
});
const json = await resp.json();
if (json.success) {
bootstrap.Modal.getInstance(document.getElementById('modalDocumento')).hide();
if (typeof Swal !== 'undefined') {
Swal.fire({
icon: 'success',
title: 'Control Volumétrico',
text: 'Registro agregado exitosamente.',
timer: 2000,
showConfirmButton: false
});
} else if (window.Notify) {
Notify.success('Registro agregado exitosamente.');
}
await this.recargarDocumentos();
} else {
if (typeof Swal !== 'undefined') {
Swal.fire({
icon: 'error',
title: 'Error',
text: json.message || 'Error al subir el archivo.'
});
} else if (window.Notify) {
Notify.error(json.message || 'Error al subir el archivo.');
}
}
} catch (e) {
console.error('Error uploading:', e);
if (window.Notify) Notify.error('Error al subir el archivo.');
} finally {
this.subiendoDocumento = false;
}
},

}));
});
