document.addEventListener('alpine:init', () => {

Alpine.data('embarquesComponent', () => ({
guardando: false,
editando: false,
editandoId: null,

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

comentarios: [],
comentarioEmbarqueId: null,
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
const c = document.getElementById('container');
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
this.$nextTick(() => this.initSelect2());
new bootstrap.Modal(document.getElementById('modalEmbarque')).show();
},

async abrirModalEditar(id) {
const c = document.getElementById('container');
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
importef: row.importef ?? '',
precio_litro: row.precio_litro ?? '',
merma: row.merma ?? '',
tad: row.tad || '',
nom_transporte: row.nom_transporte || '',
chofer: row.chofer || '',
unidad: row.unidad || '',
};

this.$nextTick(() => this.initSelect2());
new bootstrap.Modal(document.getElementById('modalEmbarque')).show();
},

resetForm() {
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
$sel.off('change.emb').on('change.emb', function () {
self.form[item.key] = $(this).val() || '';
});
});
},

async guardar() {
if (!this.form.fecha) { if (window.Notify) Notify.error('* Fecha requerida'); return; }
if (!this.form.embarque) { if (window.Notify) Notify.error('* Embarque requerido'); return; }
if (!this.form.producto) { if (window.Notify) Notify.error('* Producto requerido'); return; }
if (!this.editando && this.$refs.documento && this.$refs.documento.files.length === 0) { if (window.Notify) Notify.error('* Documento requerido'); return; }
if (!this.form.chofer) { if (window.Notify) Notify.error('* Chofer requerido'); return; }
if (!this.form.unidad) { if (window.Notify) Notify.error('* Unidad requerida'); return; }

this.guardando = true;
try {
const c = document.getElementById('container');
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
fd.append(f, this.form[f] ?? '');
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
['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'].forEach(ref => {
const input = this.$refs[ref];
if (input) input.value = '';
});
this.$nextTick(() => this.initSelect2());
} else {
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
this.nuevoComentario = '';
this.comentarios = [];

try {
const resp = await fetch('/departamento-operativo/embarques/comentarios?id_embarque=' + id);
const json = await resp.json();
if (json.success) {
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
                    if (window.Notify) Notify.success('Comentario agregado');
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
const c = document.getElementById('container');
window.location.href = '/departamento-operativo/analisis-compra/' + c.dataset.idYear + '/' + c.dataset.idMes;
},
}));

});
