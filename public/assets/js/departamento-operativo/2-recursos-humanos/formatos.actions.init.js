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

descargarPdf(id, formato) {
window.open('/departamento-operativo/recursos-humanos/formatos/pdf/' + id, '_blank');
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
if (window.Notify) Notify.success('Comentario agregado correctamente.');
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
if (title) title.textContent = d.formato_nombre + ' (# 00' + d.id + ')';

const esc = this._esc.bind(this);
let html = '';

html += '<div class="text-end mb-3"><span class="badge ' + (d.status === 0 ? 'bg-danger' : (d.status >= 3 ? 'bg-success' : 'bg-warning text-white')) + '">' + esc(d.status_label) + '</span></div>';


const fA = firmas.find(function (f) { return f.tipo_firma === 'A'; });
const fB = firmas.find(function (f) { return f.tipo_firma === 'B'; });
const fC = firmas.find(function (f) { return f.tipo_firma === 'C'; });
const fD = firmas.find(function (f) { return f.tipo_firma === 'D'; });

if (!fA) {
html += '<div class="alert alert-warning border-0 text-center py-3"><i class="ti ti-signature fs-6 me-1"></i></i>Hace falta la firma de quien elabora</div>';
}
if (!fB) {
html += '<div class="alert alert-warning border-0 text-center py-3"><i class="ti ti-signature fs-6 me-1"></i></i>Hace falta la firma del Visto Bueno (VO.BO)</div>';
}
if (fB && !fC) {
html += '<div class="alert alert-warning border-0 text-center py-3"><i class="ti ti-signature fs-6 me-1"></i></i>Hace falta la firma del Visto Autorización</div>';
}
if (fC && !fD) {
html += '<div class="alert alert-warning border-0 text-center py-3"><i class="ti ti-signature fs-6 me-1"></i></i>Hace falta la firma de Verificación</div>';
}

html += '<div class="row">'
+ '<div class="col-12 mb-4 text-end">'
+ '<div>Formato: <span class="text-primary">' + esc(d.codigo_formato || '') + '</span></div>'
+ '<div>No. de control: <span class="text-primary">' + esc(d.no_control || '—') + '</span></div>'
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
html += '<h6 class="fw-semibold mb-2">Archivos: </h6>';
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
html += '    <tr>';
html += '      <td class="text-start">' + esc(arch.label) + '</td>';
html += '      <td class="text-center">';
html += '        <i class="ti ti-file-text text-primary pointer fs-6" style="cursor: pointer;" onclick="formatosComponentInstance.download(\'formatos-alta\',\'' + archivo + '\')"></i>';
html += '      </td>';
html += '    </tr>';
});

html += '  </tbody>';
html += '</table>';
html += '</div>';
}

html += '<h6 class="fw-semibold mb-2">Firmas: </h6>';
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
if ((t === 'A' || t === 'D') && f.firma_img_url) {
html += '      <div><img src="' + f.firma_img_url + '" onerror="this.style.display=\'none\'" class="img-fluid" style="max-height:90px;object-fit:contain;"></div>';
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
html += '      <i class="ti ti-signature-off text-muted mb-3" style="font-size:80px; line-height: 1;"></i>';
html += '      <h6 class="text-muted mb-0">Sin firma registrada</h6>';
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
