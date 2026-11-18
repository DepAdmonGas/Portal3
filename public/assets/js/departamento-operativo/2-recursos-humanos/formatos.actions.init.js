document.addEventListener('error', function (event) {
const img = event.target;
if (!(img instanceof HTMLImageElement) || !img.matches('.firma-registrada-img')) return;
img.classList.add('d-none');
const fallback = document.getElementById(img.dataset.firmaFallback || '');
if (fallback) {
fallback.classList.remove('d-none');
fallback.classList.add('d-flex');
}
}, true);

document.addEventListener('alpine:init', () => {

Alpine.data('formatosComponent', () => ({
puedeAcceso: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esMultiestacion: false,
idEstacion: 0,
idUsuario: 0,
nombrePuesto: '',
hayContexto: false,
comentarios: [],
comentarioFormatoId: null,
comentarioFormatoNombre: '',
detalleComentarioFormato: null,
nuevoComentario: '',
guardandoComentario: false,

init() {
const c = document.getElementById('container');
if (!c) return;
this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeFirmar = c.dataset.puedeFirmar === 'true';
this.esMultiestacion = c.dataset.multiestacion === 'true';
this.idEstacion = parseInt(c.dataset.idEstacion || '0');
this.idUsuario = parseInt(c.dataset.idUsuario || '0');
this.nombrePuesto = c.dataset.nombrePuesto || '';
window.formatosComponentInstance = this;

this.actualizarPendientes();
this.actualizarHayContexto();

document.addEventListener('formatos:estacion-cambio', () => {
this.actualizarPendientes();
this.actualizarHayContexto();
});
},

getSelector() {
return document.getElementById('module-station-selector-formatos');
},

getIdLocalidadContexto() {
const sel = this.getSelector();
if (sel && sel.value) {
const p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return this.idEstacion || 0;
},

esTodasEstaciones() {
const sel = this.getSelector();
return sel && sel.value === '';
},

actualizarHayContexto() {
this.hayContexto = this.getIdLocalidadContexto() > 0 && !this.esTodasEstaciones();
},

abrirFormulario(formato) {
const idLocalidad = this.getIdLocalidadContexto();
if (!idLocalidad || this.esTodasEstaciones()) {
if (window.Notify) Notify.info('Selecciona una estación o departamento específico para crear un formato.');
return;
}
window.location.href = '/departamento-operativo/recursos-humanos/formatos-formulario/' + formato + '/' + idLocalidad;
},

abrirEditar(id, idLocalidad, formato) {
window.location.href = '/departamento-operativo/recursos-humanos/formatos-editar/' + id;
},

abrirFirmar(id, idLocalidad, formato) {
window.location.href = '/departamento-operativo/recursos-humanos/formatos/firmar/' + id;
},

async descargarPdf(id, formato) {
if (window.loader) window.loader.show();

try {
const response = await fetch('/departamento-operativo/recursos-humanos/formatos/pdf/' + id, {
method: 'GET',
credentials: 'same-origin',
headers: { 'X-Requested-With': 'XMLHttpRequest' }
});
const blob = await response.blob();
if (!response.ok) {
let message = (await blob.text()).trim();
try {
const payload = JSON.parse(message);
message = payload.message || message;
} catch (_) {}
throw new Error(message || 'No se pudo descargar el PDF.');
}
if (!(blob.type || response.headers.get('Content-Type') || '').toLowerCase().includes('pdf')) {
throw new Error('El servidor no generó el PDF solicitado.');
}

const disposition = response.headers.get('Content-Disposition') || '';
const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
const plainName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
let filename = 'Formato-' + id + '.pdf';
if (encodedName) {
try { filename = decodeURIComponent(encodedName); } catch (_) { filename = encodedName; }
} else if (plainName) {
filename = plainName;
}

const objectUrl = URL.createObjectURL(blob);
const link = document.createElement('a');
link.href = objectUrl;
link.download = filename;
link.hidden = true;
document.body.appendChild(link);
link.click();
link.remove();
window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
} catch (error) {
window.Notify?.error(error.message || 'No se pudo descargar el PDF.');
} finally {
if (window.loader) window.loader.hide();
}
},

async eliminarFormulario(id, idLocalidad, formato, nombreEmpleado) {
const res = await this.deleteAction({
url: '/departamento-operativo/recursos-humanos/formatos/delete',
id: id,
name: nombreEmpleado || 'Formato #' + id,
table: '#tabla-formatos'
});
if (res && res.success) {
this.actualizarPendientes();
}
},

scrollChatToBottom() {
this.$nextTick(() => {
var el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id, nombre, formato, formatoNombre) {
    this.comentarioFormatoId = id;
    this.detalleComentarioFormato = null;
    this.comentarioFormatoNombre = (formatoNombre || 'Formato') + ' # 00' + id + (nombre ? ' (' + nombre + ')' : '');
this.nuevoComentario = '';
this.comentarios = [];

var self = this;
var offcanvasEl = document.getElementById('modalComentarios');
var oc = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

oc.show();

this.$nextTick(async function() {
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/formatos/comentarios?id=' + id);
const json = await resp.json();
if (json.success) {
self.detalleComentarioFormato = json.detalle || null;
self.comentarios = json.comentarios || [];
self.scrollChatToBottom();
}
} catch (e) {
console.error('Error cargando comentarios:', e);
}
});
},

async agregarComentario() {
if (this.guardandoComentario) return;
if (!this.nuevoComentario.trim()) return;
if (!this.comentarioFormatoId) return;

this.guardandoComentario = true;
const formatoId = this.comentarioFormatoId;
const texto = this.nuevoComentario.trim();

try {
const fd = new FormData();
fd.append('id', formatoId);
fd.append('comentario', texto);

const resp = await fetch('/departamento-operativo/recursos-humanos/formatos/store-comentario', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
this.nuevoComentario = '';
const resp2 = await fetch('/departamento-operativo/recursos-humanos/formatos/comentarios?id=' + formatoId);
const json2 = await resp2.json();
if (json2.success) {
this.detalleComentarioFormato = json2.detalle || this.detalleComentarioFormato;
this.comentarios = json2.comentarios || [];
this.scrollChatToBottom();
}
const dt = window.tablaFormatos;
if (dt) {
dt.rows().every(function () {
const d = this.data();
if (d.id === formatoId) {
d.num_comentarios = (d.num_comentarios || 0) + 1;
this.invalidate();
return false;
}
});
dt.draw(false);
}
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar comentario.');
}
} catch (e) {
console.error('Error al agregar comentario:', e);
if (window.Notify) Notify.error('Error al agregar comentario.');
} finally {
this.guardandoComentario = false;
}
},

_esc(value) {
return String(value == null ? '' : value).replace(/[&<>"']/g, function (m) {
return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
});
},

async abrirDetalle(id, formato) {
const body = document.getElementById('modalDetalleFormatoBody');
if (body) body.innerHTML = '<div class="text-center text-muted py-4"><i class="ti ti-loader-2 fs-5"></i> Cargando...</div>';

try {
const resp = await fetch('/departamento-operativo/recursos-humanos/formatos/detalle?id=' + id + '&formato=' + formato);
const json = await resp.json();
if (!json.success) {
if (window.Notify) Notify.error('Error al cargar detalle');
return;
} 
const d = json.detalle;
const firmas = json.firmas || [];
const title = document.getElementById('modalDetalleFormatoTitle');

if (title) {
    title.innerHTML = `<i class="ti ti-eye me-0"></i>${d.formato_nombre} (# 00${d.id})`;
}
const esc = this._esc.bind(this);
let html = '';

html += '<div class="text-end mb-3"><span class="badge ' + (d.status === 0 ? 'bg-danger' : (d.status >= 3 ? 'bg-success' : 'bg-warning text-white')) + '">' + esc(d.status_label) + '</span></div>';


html += '<div class="row">'
+ '<div class="col-12 mb-4 text-end">'
+ '<div><div class="form-label mb-1">Formato:</div><div class="text-primary">' + esc(d.codigo_formato || '') + '</div></div>'
+ '<div><div class="form-label mb-1">No. de control:</div><div class="text-primary">' + esc(d.no_control || '—') + '</div></div>'
+ '<div>' + esc(d.encabezado_ciudad || '') + '</div>'
+ '</div>'
+ '</div>';

html += '<div class="mb-2 fw-medium">' + esc(d.dirigido_a || '') + '</div>';
html += '<p class="mb-3">' + esc(d.intro || '') + '</p>';

if (d.tabla && d.tabla.headers && d.tabla.headers.length) {
html += '<div class="table-responsive mb-3"><table class="table table-bordered table-striped align-middle text-nowrap">';
html += '<thead><tr>';
(d.tabla.headers || []).forEach(function (h) { html += '<th class="text-center">' + esc(h) + '</th>'; });
html += '</tr></thead><tbody>';
(d.tabla.rows || []).forEach(function (row) {
html += '<tr>';
row.forEach(function (cell) {
const tag = cell.header ? 'th' : 'td';
const colspan = cell.colspan ? ' colspan="' + cell.colspan + '"' : '';
html += '<' + tag + colspan + ' class="text-center">' + esc(cell.value) + '</' + tag + '>';
});
html += '</tr>';
});
html += '</tbody></table></div>';
}

if (d.archivos && d.archivos.length) {
html += '<div class="form-label mb-1">Archivos:</div>';
html += '<div class="table-responsive mb-4">';
html += '<table class="table table-striped table-bordered mb-0 align-middle">';
html += '  <thead>';
html += '    <tr>';
html += '      <th class="text-start">Nombre del documento</th>';
html += '      <th class="text-center" style="width: 80px;"><i class="ti ti-file-text text-primary fs-6"></i></th>';
html += '    </tr>';
html += '  </thead>';
html += '  <tbody>';

d.archivos.forEach(function (arch) {
var archivo = arch.ruta.split('/').pop();
var tipo = /^[a-z0-9-]+$/.test(arch.tipo || '') ? arch.tipo : 'formatos-alta';
html += '    <tr>';
html += '      <td class="text-start">' + esc(arch.label) + '</td>';
html += '      <td class="text-center">';
html += '        <i class="ti ti-file-text text-primary pointer fs-6" style="cursor: pointer;" onclick="formatosComponentInstance.download(\'' + tipo + '\',\'' + archivo + '\')"></i>';
html += '      </td>';
html += '    </tr>';
});

html += '  </tbody>';
html += '</table>';
html += '</div>';
}

html += '<div class="form-label mb-1">Firmas:</div>';
html += '<div class="row">';
const labels = { A: 'ELABORÓ / ENCARGADO', B: 'VO.BO.', C: 'AUTORIZACIÓN', D: 'VERIFICACIÓN' };

['A', 'B', 'C', 'D'].forEach(function (t) {
const f = firmas.find(function (x) { return x.tipo_firma === t; });
const tipoLabel = labels[t] || t;

html += '<div class="col-12 col-md-6 mt-2 mb-3">';
html += '  <div class="card border h-100">';

if (f) {
// --- CON FIRMA ---
html += '    <div class="card-header bg-primary text-white py-3 border-0">';
html += '      <div class="d-flex align-items-center">';
html += '        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">';
html += '          <i class="ti ti-user-check" style="font-size:22px; line-height: 1;"></i>';
html += '        </div>';
html += '        <div class="ms-3 overflow-hidden">';
html += '          <h6 class="mb-0 text-white fw-bold">' + esc(tipoLabel) + '</h6>';
html += '        </div>';
html += '      </div>';
html += '    </div>';

html += '    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
if (t === 'A' || t === 'D') {
if (f.firma_img_disponible && f.firma_img_url) {
const fallbackId = 'firma-detalle-fallback-' + t;
html += '      <div class="d-flex flex-column align-items-center">';
html += '        <img src="' + f.firma_img_url + '" class="img-fluid firma-registrada-img" data-firma-fallback="' + fallbackId + '" style="max-height:90px;object-fit:contain;">';
html += '        <div id="' + fallbackId + '" class="d-none flex-column align-items-center"><i class="ti ti-signature-off text-secondary mb-3" style="font-size:100px;"></i><small class="text-muted">La firma está registrada, pero su imagen no está disponible.</small></div>';
html += '      </div>';
} else {
html += '      <i class="ti ti-signature-off text-secondary mb-3" style="font-size:100px;"></i>';
html += '      <small class="text-muted">La firma está registrada, pero su imagen no está disponible.</small>';
}
}

// Se quita esc() para que f.firma_texto interprete el HTML (<b>, <br>, etc.) sin renderizar etiquetas visibles
if (f.firma_texto) {
html += '          <i class="ti ti-signature text-primary mb-3" style="font-size:80px;"></i>'

html += '      <small class="text-dark mt-2">' + f.firma_texto + '</small>';
}
html += '    </div>';

html += '    <div class="card-footer bg-light text-center py-2">';
html += '      <h6 class="mb-0 fw-semibold text-truncate">' + esc(f.usuario_nombre || f.nombre || '') + '</h6>';
html += '    </div>';

} else {
// --- SIN FIRMA ---
html += '    <div class="card-header bg-primary text-white py-3 border-0">';
html += '      <div class="d-flex align-items-center">';
html += '        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">';
html += '          <i class="ti ti-clock-hour-4" style="font-size:22px; line-height: 1;"></i>';
html += '        </div>';
html += '        <div class="ms-3">';
html += '          <h6 class="mb-0 text-white fw-bold">' + esc(tipoLabel) + '</h6>';
html += '        </div>';
html += '      </div>';
html += '    </div>';

html += '    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
html += '      <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px; line-height: 1;"></i>';
const faltaFirma = {
    A: '¡Falta la firma de quien elaboró!',
    B: '¡Falta la firma de Vo.Bo.!',
    C: '¡Falta la firma de Autorización!',
    D: '¡Falta la firma de Verificación!'
};
html += '      <h6 class="text-muted mb-0">' + esc(faltaFirma[t] || 'Sin firma registrada') + '</h6>';
html += '    </div>';

html += '    <div class="card-footer bg-light text-center py-2">';
html += '      <small class="text-muted">Pendiente de firma electrónica</small>';
html += '    </div>';
}

html += '  </div>';
html += '</div>';
});

html += '</div>';

if (body) body.innerHTML = html;
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleFormato')).show();
} catch (e) {
console.error('Error al cargar detalle:', e);
if (window.Notify) Notify.error('Error al cargar detalle');
}
},

_archivoTipo: '',
_archivoNombre: '',

verArchivo(tipo, archivo) {
this._archivoTipo = tipo;
this._archivoNombre = archivo;

const title = document.getElementById('modalVerArchivoTitle');
const nombreEl = document.getElementById('modalVerArchivoNombre');
const frame = document.getElementById('modalVerArchivoFrame');
const btn = document.getElementById('modalVerArchivoDescargar');

if (title) title.textContent = archivo;
if (nombreEl) nombreEl.textContent = archivo;
if (frame) frame.src = '/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(archivo) + '&view=1';
if (btn) btn.setAttribute('onclick', "formatosComponentInstance.download('" + tipo + "', '" + archivo + "')");

const m = document.getElementById('modalVerArchivo');
if (m) bootstrap.Modal.getOrCreateInstance(m).show();
},

async actualizarPendientes() {
const badge = document.getElementById('formatos-pendientes-badge');
if (!badge) return;
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/formatos/pendientes');
const json = await resp.json();
if (!json.success) return;

const total = parseInt(json.total || 0, 10);
const contexto = parseInt(json.contexto !== undefined ? json.contexto : total, 10);
const totalEl = document.getElementById('formatos-pendientes-total');
if (totalEl) totalEl.textContent = contexto;

const sel = this.getSelector();
if (sel) {
Array.prototype.forEach.call(sel.options, function(opt) {
if (!opt.value) return;
const key = opt.value;
const base = (opt.textContent || '').replace(/\s*\(\d+\)\s*$/, '').trim();
const count = parseInt(json[key] || 0, 10);
opt.textContent = base + ' (' + count + ')';
});
const first = sel.options[0];
if (first && first.value === '') {
const base = (first.textContent || '').replace(/\s*\(\d+\)\s*$/, '').trim();
first.textContent = base + ' (' + total + ')';
}
}
} catch (e) {
console.error('Error actualizando pendientes:', e);
}
},
}));

});
