document.addEventListener('alpine:init', () => {

Alpine.data('solicitudValesComponent', () => ({

loading: false,

notify(type, message) {
Notify[type](message);
},

showAlert(icon, title, text) {
Swal.fire({ icon, title, text, timer: 2000, showConfirmButton: false });
},

handleResponse(response, table) {
const { success, message } = response.data;
this.showAlert(success ? 'success' : 'error', success ? 'Correcto' : 'Error', message);
this.notify(success ? 'success' : 'error', message);
if (success && table) {
$(table).DataTable().ajax.reload(null, false);
}
},

async deleteAction({ url, id, name, table }) {
if (this.loading) return;
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: `El registro: ${name} será eliminado`,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;
this.loading = true;
try {
const response = await axios.post(url, { id });
this.handleResponse(response, table);
return response.data;
} catch (err) {
const mensaje = err.response?.data?.message || 'Error al eliminar';
this.showAlert('error', 'Error', mensaje);
this.notify('error', mensaje);
} finally {
this.loading = false;
}
},

download(tipo, archivo) {
if (!archivo) { this.notify('error', 'Archivo no disponible'); return; }
window.open(`/download?tipo=${tipo}&file=${encodeURIComponent(archivo)}`, '_blank');
},

            comentarios: [],
            nuevoComentario: '',
            guardandoComentario: false,
            comentarioIdActual: null,
            idUsuario: 0,

detalle: {},
documentos: [],
documentosModal: [],
documentoForm: { nombre: 'VALE' },
subiendoDocumento: false,

init() {
const c = document.getElementById('container');
if (c) {
this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
}

window.abrirDetalle = (id) => this.abrirDetalle(id);
window.abrirComentarios = (id) => this.abrirComentarios(id);
window.abrirDocumentacion = (id) => this.abrirDocumentacion(id);
window.confirmarEliminar = (id, name) => this.confirmarEliminar(id, name);
window.downloadFile = (archivo) => this.download('solicitud-vales', archivo);

document.addEventListener('ver-detalle', (e) => { this.abrirDetalle(e.detail.id); });
document.addEventListener('abrir-comentarios', (e) => { this.abrirComentarios(e.detail.id); });
document.addEventListener('ver-documentacion', (e) => { this.abrirDocumentacion(e.detail.id); });
document.addEventListener('eliminar-solicitud', (e) => { this.confirmarEliminar(e.detail.id, e.detail.name); });
},

formatearFecha(fecha) {
if (!fecha) return '';
var d = new Date(fecha);
var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
return d.getDate() + ' de ' + meses[d.getMonth()] + ' del ' + d.getFullYear();
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id) {
this.comentarioIdActual = id;
this.nuevoComentario = '';
this.comentarios = [];

try {
const resp = await fetch('/departamento-operativo/corporativo/solicitud-vales/get-comentarios?id=' + id);
const json = await resp.json();
if (json.success) {
this.comentarios = (json.data || []).map(c => ({
...c,
esMio: c.esPropio,
usuario_nombre: c.usuario_nombre || 'Sistema',
fecha_formateada: c.fecha_hora || ''
}));
this.scrollChatToBottom();
}
} catch (e) {
console.error('Error cargando comentarios:', e);
}

bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasComentarios')).show();
},

async abrirDetalle(id) {
this.comentarioIdActual = id;
try {
const res = await axios.get('/departamento-operativo/corporativo/solicitud-vales/get-documentos', { params: { id } });
if (res.data.success) {
this.documentos = res.data.data;
}
const res2 = await axios.get('/departamento-operativo/corporativo/solicitud-vales/get-comentarios', { params: { id } });
if (res2.data.success) {
this.comentarios = res2.data.data;
}
} catch (e) {
this.documentos = [];
this.comentarios = [];
}

const row = window.tablaSolicitudVales ? window.tablaSolicitudVales.row(function (idx, data) {
return data.id === id;
}).data() : null;

if (row) {
this.detalle = row;
} else {
this.detalle = { folio: '', fecha: '', hora: '', monto: 0, moneda: 'MXN', concepto: '',
solicitante: '', id_estacion: 0, estacion_nombre: '', cuenta: '',
autorizado_por: '', metodo_autorizacion: '', observaciones: '' };
}

new bootstrap.Modal(document.getElementById('modalDetalle')).show();
},

async abrirDocumentacion(id) {
this.comentarioIdActual = id;
try {
const res = await axios.get('/departamento-operativo/corporativo/solicitud-vales/get-documentos', { params: { id } });
if (res.data.success) {
this.documentosModal = res.data.data;
}
} catch (e) {
this.documentosModal = [];
}

new bootstrap.Modal(document.getElementById('modalDocumentos')).show();
},

confirmarEliminar(id, name) {
this.deleteAction({
url: '/departamento-operativo/corporativo/solicitud-vales/delete',
id: id,
name: name,
table: '#tabla-solicitud-vales'
});
},

async agregarComentario() {
if (this.guardandoComentario) return;
if (!this.nuevoComentario.trim()) return;
if (!this.comentarioIdActual) return;

this.guardandoComentario = true;
const solicitudId = this.comentarioIdActual;

try {
const resp = await fetch('/departamento-operativo/corporativo/solicitud-vales/add-comentario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: solicitudId, comentario: this.nuevoComentario })
});
const json = await resp.json();

if (json.success) {
this.nuevoComentario = '';
const resp2 = await fetch('/departamento-operativo/corporativo/solicitud-vales/get-comentarios?id=' + solicitudId);
const json2 = await resp2.json();
if (json2.success) {
this.comentarios = (json2.data || []).map(c => ({
...c,
esMio: c.esPropio,
usuario_nombre: c.usuario_nombre || 'Sistema',
fecha_formateada: c.fecha_hora || ''
}));
this.scrollChatToBottom();
}
const dt = window.tablaSolicitudVales;
if (dt) {
dt.rows().every(function () {
const d = this.data();
if (d.id === solicitudId) {
d.total_comentarios = (d.total_comentarios || 0) + 1;
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

async agregarDocumento() {
if (!this.comentarioIdActual) return;
const fileInput = document.getElementById('fileDocumento');
if (!fileInput || !fileInput.files[0]) {
this.notify('error', 'Debe seleccionar un archivo');
return;
}

this.subiendoDocumento = true;
const formData = new FormData();
formData.append('id', this.comentarioIdActual);
formData.append('nombre', this.documentoForm.nombre);
formData.append('archivo', fileInput.files[0]);

try {
const res = await axios.post('/departamento-operativo/corporativo/solicitud-vales/add-documento', formData);

if (res.data.success) {
fileInput.value = '';
const res2 = await axios.get('/departamento-operativo/corporativo/solicitud-vales/get-documentos', { params: { id: this.comentarioIdActual } });
if (res2.data.success) {
this.documentosModal = res2.data.data;
}
this.notify('success', 'Documento agregado');
} else {
this.notify('error', res.data.message || 'Error');
}
} catch (e) {
this.notify('error', 'Error al subir documento');
} finally {
this.subiendoDocumento = false;
}
},

async eliminarDocumento(id) {
const res = await this.deleteAction({
url: '/departamento-operativo/corporativo/solicitud-vales/delete-documento',
id: id,
name: 'Documento #' + id
});
if (res?.success) {
const res2 = await axios.get('/departamento-operativo/corporativo/solicitud-vales/get-documentos', { params: { id: this.comentarioIdActual } });
if (res2.data.success) {
this.documentosModal = res2.data.data;
}
}
},
}));
});

