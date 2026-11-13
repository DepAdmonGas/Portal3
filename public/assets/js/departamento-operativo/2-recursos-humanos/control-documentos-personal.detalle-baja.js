function detalleBajaComponent() {
var c = document.getElementById('detalle-baja-container');
var bajaData = c ? JSON.parse(c.dataset.baja || '{}') : {};
return {
baja: bajaData,
idBaja: bajaData.id || 0,
idPersonal: bajaData.id_personal || 0,
puedeEditarBaja: c?.dataset.puedeEditarBaja === 'true',
puedeEliminarBaja: c?.dataset.puedeEliminarBaja === 'true',
puedeDescargarBaja: c?.dataset.puedeDescargarBaja === 'true',

archivosBaja: bajaData.archivos_baja || [],
selectedArchivoBajaId: null,
bajaForm: { descripcion: '' },
bajaErrors: { descripcion: false, archivo: false },
subiendoArchivo: false,

comentarios: bajaData.comentarios || [],
nuevoComentario: '',
guardandoComentario: false,

init() {
this.scrollChatToBottom();
const modal = document.getElementById('modalSubirArchivoBaja');
modal?.addEventListener('hidden.bs.modal', () => {
this.bajaForm = { descripcion: '' };
this.bajaErrors = { descripcion: false, archivo: false };
if (this.$refs.bajaFileInputModal) this.$refs.bajaFileInputModal.value = '';
});
},

scrollChatToBottom() {
this.$nextTick(() => {
const el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async subirArchivoBaja() {
if (this.subiendoArchivo) return;
this.bajaErrors = { descripcion: false, archivo: false };

const fileInput = this.$refs.bajaFileInputModal;
const descripcion = String(this.bajaForm.descripcion || '').trim();
const tieneArchivo = !!(fileInput && fileInput.files && fileInput.files.length);
if (!descripcion || !tieneArchivo) {
this.bajaErrors.descripcion = !descripcion;
this.bajaErrors.archivo = !tieneArchivo;
if (window.Notify) Notify.error('Completa los campos obligatorios.');
return;
}

this.subiendoArchivo = true;
try {
const fd = new FormData();
fd.append('id_baja', this.idBaja);
fd.append('descripcion', descripcion);
fd.append('archivo', fileInput.files[0]);

const resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/upload-baja-archivo', {
method: 'POST',
body: fd
});
const json = await resp.json();
if (json.success) {
this.bajaForm.descripcion = '';
this.bajaErrors = { descripcion: false, archivo: false };
fileInput.value = '';
if (window.Notify) Notify.success(json.message || 'Archivo subido correctamente.');
await this.recargarArchivosBaja();
const modal = bootstrap.Modal.getInstance(document.getElementById('modalSubirArchivoBaja'));
if (modal) modal.hide();
} else {
if (window.Notify) Notify.error(json.message || 'Error al subir archivo.');
}
} catch (e) {
if (window.Notify) Notify.error('Error al subir archivo.');
} finally {
this.subiendoArchivo = false;
}
},

async recargarArchivosBaja() {
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-archivos-baja?id_baja=' + this.idBaja);
const json = await resp.json();
if (json.success) {
this.archivosBaja = json.data || [];
}
} catch (e) {
console.error('Error recargando archivos:', e);
}
},

async agregarComentario() {
if (this.guardandoComentario) return;
if (!this.nuevoComentario.trim()) return;
this.guardandoComentario = true;
this.scrollChatToBottom();
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/add-comentario-baja', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_baja: this.idBaja, comentario: this.nuevoComentario })
});
const json = await resp.json();
if (json.success) {
this.nuevoComentario = '';
await this.recargarComentarios();
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar comentario.');
}
} catch (e) {
if (window.Notify) Notify.error('Error al agregar comentario.');
} finally {
this.guardandoComentario = false;
}
},

async recargarComentarios() {
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-comentarios-baja?id_baja=' + this.idBaja);
const json = await resp.json();
if (json.success) {
this.comentarios = json.data || [];
this.scrollChatToBottom();
}
} catch (e) {
console.error('Error recargando comentarios:', e);
}
}
};
}
