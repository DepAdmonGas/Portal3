document.addEventListener('alpine:init', () => {

Alpine.data('embarquesComponent', () => ({
guardando: false,
editando: false,
editandoId: null,
errores: {},

form: {
fecha: new Date().toISOString().substring(0, 10),
embarque: '',
producto: '',
documentocv: '',
importef: '',
precio_litro: '',
merma: '',
tad: '',
nom_transporte: '',
chofer: '',
unidad: '',
},

limpiarError(field) {
if (this.errores[field]) this.errores[field] = false;
},

formatearCantidad(event, field) {
const input = event.target;
const raw = input.value;
const cursor = input.selectionStart ?? raw.length;
const digitsBeforeCursor = raw.slice(0, cursor).replace(/[^0-9]/g, '').length;
const decimalBeforeCursor = raw.slice(0, cursor).includes('.');
let normalized = raw.replace(/,/g, '').replace(/[^0-9.-]/g, '');
const negative = normalized.startsWith('-');
normalized = normalized.replace(/-/g, '');
const pieces = normalized.split('.');
normalized = pieces[0] + (pieces.length > 1 ? '.' + pieces.slice(1).join('') : '');

if (!normalized) {
this.form[field] = negative ? '-' : '';
input.value = this.form[field];
input.setSelectionRange(input.value.length, input.value.length);
return;
}

const [whole = '', ...decimals] = normalized.split('.');
const grouped = (whole.replace(/^0+(?=\d)/, '') || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const formatted = (negative ? '-' : '') + grouped + (normalized.includes('.') ? '.' + decimals.join('') : '');
this.form[field] = formatted;
input.value = formatted;

let digitsSeen = 0;
let position = input.value.length;
if (digitsBeforeCursor === 0) {
position = input.value.startsWith('-') ? 1 : 0;
input.setSelectionRange(position, position);
return;
}
for (let index = 0; index < input.value.length; index += 1) {
if (/\d/.test(input.value[index])) digitsSeen += 1;
if (digitsSeen >= digitsBeforeCursor) {
position = index + 1;
break;
}
}
const decimalPosition = input.value.indexOf('.');
if (decimalBeforeCursor && decimalPosition !== -1) position = Math.max(position, decimalPosition + 1);
input.setSelectionRange(position, position);
},

formatearCantidadExistente(value) {
if (value === null || value === undefined || value === '') return '';
const amount = Number(String(value).replace(/,/g, ''));
return Number.isFinite(amount) ? amount.toLocaleString('en-US', { maximumFractionDigits: 20 }) : '';
},

valorCantidad(value) {
const normalized = String(value ?? '').replace(/,/g, '').trim();
if (!normalized || normalized === '-' || normalized === '.' || normalized === '-.') return '';
const amount = Number(normalized);
return Number.isFinite(amount) ? String(amount) : '';
},

validarFormulario() {
const archivoDocumento = this.$refs.documento?.files?.length > 0;
this.errores = {
fecha: !String(this.form.fecha ?? '').trim(),
embarque: !String(this.form.embarque ?? '').trim(),
producto: !String(this.form.producto ?? '').trim(),
documento: !this.editando && !archivoDocumento,
chofer: !String(this.form.chofer ?? '').trim(),
unidad: !String(this.form.unidad ?? '').trim(),
};

if (Object.values(this.errores).some(Boolean)) {
if (window.Notify) Notify.error('Completa los campos obligatorios.');
return false;
}
return true;
},

comentarios: [],
comentarioEmbarqueId: null,
detalleComentarioEmbarque: null,
nuevoComentario: '',
guardandoComentario: false,
puedeAgregarComentarios: false,
idUsuario: 0,

formatearFecha(fecha) {
if (!fecha) return '';
var d = new Date(fecha);
var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
return d.getDate() + ' de ' + meses[d.getMonth()] + ' del ' + d.getFullYear();
},

init() {
const c = document.getElementById('embarques-content');
if (!c) return;

this.puedeAgregarComentarios = c.dataset.puedeAgregarComentarios === 'true';
this.idUsuario = parseInt(c.dataset.idUsuario) || 0;

this.loadCatalogos();

document.addEventListener('abrir-comentarios', (e) => {
this.abrirComentarios(e.detail.id);
});

document.addEventListener('editar-embarque', (e) => {
this.abrirModalEditar(e.detail.id);
});

document.addEventListener('eliminar-embarque', (e) => {
this.confirmarEliminar(e.detail.id);
});
},

cambiarYearMes(year, mes) {
window.location.href = '/departamento-operativo/embarques/' + year + '/' + mes;
},

onEmbarqueChange() {
if (this.form.embarque === '' || this.form.embarque === 'Pemex') {
this.form.merma = '';
this.form.nom_transporte = '';
}
},

async loadCatalogos() {
try {
const resp = await fetch('/departamento-operativo/embarques/catalogos');
const json = await resp.json();
['choferes', 'unidades', 'transportes'].forEach(key => {
if (!json[key]) return;
const refMap = { choferes: 'choferSelect', unidades: 'unidadSelect', transportes: 'transporteSelect' };
const sel = this.$refs[refMap[key]];
if (!sel) return;
json[key].forEach(v => {
const opt = document.createElement('option');
opt.value = v;
opt.textContent = v;
sel.appendChild(opt);
});
});
} catch (e) {
console.error('Error cargando catálogos:', e);
}
},

abrirModalAgregar() {
this.editando = false;
this.editandoId = null;
this.resetForm();
this.limpiarArchivos();
this.$nextTick(() => this.initSelect2());
new bootstrap.Modal(document.getElementById('modalEmbarque')).show();
},

async abrirModalEditar(id) {
this.errores = {};
this.limpiarArchivos();
const c = document.getElementById('embarques-content');
const resp = await fetch('/departamento-operativo/embarques/data/' + c.dataset.idYear + '/' + c.dataset.idMes);
const json = await resp.json();
if (!json.success) { if (window.Notify) Notify.error('Error al obtener datos'); return; }

const row = json.data.find(r => r.id === id);
if (!row) { if (window.Notify) Notify.error('Registro no encontrado'); return; }

this.editando = true;
this.editandoId = row.id;
this.form = {
fecha: row.fecha_raw || '',
embarque: row.embarque || '',
producto: row.producto || '',
documentocv: row.documentocv || '',
importef: this.formatearCantidadExistente(row.importef),
precio_litro: this.formatearCantidadExistente(row.precio_litro),
merma: this.formatearCantidadExistente(row.merma),
tad: row.tad || '',
nom_transporte: row.nom_transporte || '',
chofer: row.chofer || '',
unidad: row.unidad || '',
};

this.$nextTick(() => this.initSelect2());
new bootstrap.Modal(document.getElementById('modalEmbarque')).show();
},

resetForm() {
this.errores = {};
this.form = {
fecha: new Date().toISOString().substring(0, 10),
embarque: '',
producto: '',
documentocv: '',
importef: '',
precio_litro: '',
merma: '',
tad: '',
nom_transporte: '',
chofer: '',
unidad: '',
};
},

limpiarArchivos() {
['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'].forEach(ref => {
const input = this.$refs[ref];
if (input) input.value = '';
});
},

initSelect2() {
const modal = document.getElementById('modalEmbarque');
if (!modal) return;

const self = this;
const selects = [
{ ref: 'choferSelect', key: 'chofer' },
{ ref: 'unidadSelect', key: 'unidad' },
{ ref: 'transporteSelect', key: 'nom_transporte' },
];

selects.forEach(item => {
const $sel = $(self.$refs[item.ref]);
if (!$sel || !$sel.length) return;

$sel.off('change.emb').on('change.emb', function () {
self.form[item.key] = $(this).val() || '';
self.limpiarError(item.key);
});

if ($sel.hasClass('select2-hidden-accessible')) {
$sel.val(self.form[item.key] || '').trigger('change');
return;
}

const $wrap = $(self.$refs[item.ref]).parent();
$sel.select2({
dropdownParent: $wrap,
width: '100%',
tags: true,
createTag: (params) => {
const term = (params.term || '').toUpperCase();
return term ? { id: term, text: term } : null;
},
insertTag: (data, tag) => data.push(tag)
});

$sel.val(self.form[item.key] || '').trigger('change');
});
},

async guardar() {
if (!this.validarFormulario()) return;

this.guardando = true;
try {
const c = document.getElementById('embarques-content');
const fd = new FormData();
const endpoint = this.editando
? '/departamento-operativo/embarques/update'
: '/departamento-operativo/embarques/store';

if (this.editando) {
fd.append('id', this.editandoId);
} else {
fd.append('id_mes', c.dataset.idMesDb || '');
}

const fields = ['fecha', 'embarque', 'producto', 'documentocv', 'importef', 'precio_litro', 'merma', 'tad', 'nom_transporte', 'chofer', 'unidad'];
for (const f of fields) {
const value = ['importef', 'precio_litro', 'merma'].includes(f)
? this.valorCantidad(this.form[f])
: this.form[f];
fd.append(f, value ?? '');
}

const fileRefs = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
for (const ff of fileRefs) {
const input = this.$refs[ff];
if (input && input.files && input.files[0]) {
fd.append(ff, input.files[0]);
}
}

const resp = await fetch(endpoint, { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
const modal = bootstrap.Modal.getInstance(document.getElementById('modalEmbarque'));
if (modal) modal.hide();
if (window.Notify) Notify.success(json.message);
const dt = $('#tabla-embarques').DataTable();
if (dt) dt.ajax.reload(null, false);

this.editando = false;
this.editandoId = null;
this.resetForm();
this.limpiarArchivos();
this.$nextTick(() => this.initSelect2());
} else {
if (Array.isArray(json.missing_fields)) {
json.missing_fields.forEach(field => { this.errores[field] = true; });
}
if (window.Notify) Notify.error(json.message || 'Error al guardar');
}
} catch (e) {
console.error('Error al guardar:', e);
if (window.Notify) Notify.error('Error al guardar');
} finally {
this.guardando = false;
}
},

async confirmarEliminar(id) {
await this.deleteAction({
url: '/departamento-operativo/embarques/delete',
id: id,
name: 'Embarque #' + id,
table: '#tabla-embarques'
});
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id) {
this.comentarioEmbarqueId = id;
this.detalleComentarioEmbarque = null;
this.nuevoComentario = '';
this.comentarios = [];

try {
const resp = await fetch('/departamento-operativo/embarques/comentarios?id_embarque=' + id);
const json = await resp.json();
if (json.success) {
this.detalleComentarioEmbarque = json.detalle || null;
this.comentarios = (json.comentarios || []).map(c => ({
...c,
esMio: c.id_usuario === this.idUsuario,
usuario_nombre: c.usuario?.nombre || 'Sistema',
fecha_formateada: this.formatearFecha(c.fecha_hora)
}));
this.scrollChatToBottom();
}
} catch (e) {
console.error('Error cargando comentarios:', e);
}

bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('modalComentarios')).show();
},

async agregarComentario() {
if (this.guardandoComentario) return;
if (!this.nuevoComentario.trim()) return;
if (!this.comentarioEmbarqueId) return;

this.guardandoComentario = true;

try {
const fd = new FormData();
fd.append('id_embarque', this.comentarioEmbarqueId);
fd.append('comentario', this.nuevoComentario);

const resp = await fetch('/departamento-operativo/embarques/store-comentario', {
method: 'POST',
body: fd
});
const json = await resp.json();

                if (json.success) {
                    this.nuevoComentario = '';
                    const resp2 = await fetch('/departamento-operativo/embarques/comentarios?id_embarque=' + this.comentarioEmbarqueId);
                    const json2 = await resp2.json();
                    if (json2.success) {
                        this.detalleComentarioEmbarque = json2.detalle || this.detalleComentarioEmbarque;
                        this.comentarios = (json2.comentarios || []).map(c => ({
                            ...c,
                            esMio: c.id_usuario === this.idUsuario,
                            usuario_nombre: c.usuario?.nombre || 'Sistema',
                            fecha_formateada: this.formatearFecha(c.fecha_hora)
                        }));
                        this.scrollChatToBottom();
                    }
                    const dt = $('#tabla-embarques').DataTable();
                    if (dt) {
                        const self = this;
                        dt.rows().every(function () {
                            const d = this.data();
                            if (d.id === self.comentarioEmbarqueId) {
                                d.num_comentarios = (d.num_comentarios || 0) + 1;
                                this.invalidate();
                                dt.draw(false);
                                return false;
                            }
                        });
                    }
                } else {
if (window.Notify) Notify.error(json.message || 'Error al agregar comentario');
}
} catch (e) {
console.error('Error al agregar comentario:', e);
if (window.Notify) Notify.error('Error al agregar comentario');
} finally {
this.guardandoComentario = false;
}
},

abrirAnalisisCompras() {
const c = document.getElementById('embarques-content');
window.location.href = '/departamento-operativo/analisis-compra/' + c.dataset.idYear + '/' + c.dataset.idMes;
},
}));

});
