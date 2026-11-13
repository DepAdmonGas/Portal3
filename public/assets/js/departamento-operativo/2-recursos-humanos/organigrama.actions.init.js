document.addEventListener('alpine:init', () => {

Alpine.data('organigramaComponent', () => ({

loading: false,
guardando: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
esEncargado: false,
idEstacionActual: 0,
imagenUrl: '',
versionActual: 0,
plantilla: [],
selectedPlantillaId: null,
selectedEmpresaFila: null,
resultadosPersonal: {},
busquedasPersonal: {},
solicitudesPersonal: {},
stationInfo: null,
stationInfoEstaciones: [1, 2, 3, 4, 5, 6, 7, 14],

form: {
observaciones: '',
},
documentoPlantillaId: 0,
documentoTipo: 'perfil',
documentoModo: 'agregar',
errors: {},

init() {
var c = document.getElementById('container');
if (!c) return;

this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.esEncargado = c.dataset.esEncargado === 'true';
this.idEstacionActual = parseInt(c.dataset.idEstacion || '0');

document.addEventListener('org:ver-imagen', (e) => {
this.verImagen(e.detail.archivo, e.detail.version || 0);
});

document.addEventListener('org:eliminar', (e) => {
this.confirmarEliminar(e.detail.id, e.detail.version);
});

document.addEventListener('org:estacion-cambio', () => {
this.idEstacionActual = this.obtenerEstacionActual();
this.imagenUrl = '';
this.versionActual = 0;
this.selectedPlantillaId = null;
this.selectedEmpresaFila = null;
this.cargarPlantilla();
this.cargarStationInfo();

Alpine.nextTick(() => {
var dt = window.tablaOrganigrama;
if (dt && this.idEstacionActual) dt.columns.adjust();
});
});

// For multiestacion users, also read the selector value (may have context restored)
var sel = document.getElementById('module-station-selector-organigrama');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) this.idEstacionActual = parseInt(p[1]);
}

if (this.idEstacionActual) {
this.cargarPlantilla();
this.cargarStationInfo();
}
},

obtenerEstacionActual() {
const container = document.getElementById('container');
if (!container) return 0;
var sel = document.getElementById('module-station-selector-organigrama');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(container.dataset.idEstacion || '0');
},

seleccionarFilaPlantilla(id) {
this.selectedPlantillaId = id;
},

seleccionarFilaEmpresa(campo) {
this.selectedEmpresaFila = campo;
},

verImagen(archivo, version) {
if (!archivo) {
this.notify('error', 'Archivo no disponible');
return;
}
this.imagenUrl = '/download?tipo=organigrama&file=' + encodeURIComponent(archivo) + '&view=1';
this.versionActual = version || 0;
},

abrirModalAgregar() {
this.form.observaciones = '';
this.errors = {};
if (this.$refs.fileArchivo) this.$refs.fileArchivo.value = '';
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarOrganigrama')).show();
},

async guardarOrganigrama() {
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) {
this.notify('error', 'Selecciona una estación primero');
return;
}

var archivo = this.$refs.fileArchivo ? this.$refs.fileArchivo.files[0] : null;
if (!archivo) {
this.errors.archivo = true;
this.notify('error', 'Completa los campos obligatorios.');
return;
}

var ext = archivo.name.split('.').pop().toLowerCase();
if (['jpg', 'jpeg', 'png'].includes(ext) === false) {
this.errors.archivo = true;
this.notify('error', 'Solo se permiten imágenes JPG/PNG');
return;
}

this.guardando = true;
try {
var data = new FormData();
data.append('id_estacion', idEstacion);
data.append('archivo', archivo, archivo.name);
data.append('observaciones', this.form.observaciones);

var resp = await axios({
method: 'POST',
url: '/departamento-operativo/recursos-humanos/organigrama/add',
data: data,
headers: { 'Content-Type': 'multipart/form-data' }
});

if (resp.data.success) {
bootstrap.Modal.getInstance(document.getElementById('modalAgregarOrganigrama'))?.hide();
window._orgVersionActual = null;
}
this.handleResponse(resp, '#tabla-organigrama-versions');
} catch (err) {
if (err.response?.data?.errors) this.errors = err.response.data.errors;
this.notify('error', err.response?.data?.message || 'Error al guardar');
} finally {
this.guardando = false;
}
},

async confirmarEliminar(id, version) {
var response = await this.deleteAction({
url: '/departamento-operativo/recursos-humanos/organigrama/delete',
id: id,
name: 'Versión ' + version,
table: null
});
if (!response?.success) return;

// Al borrar la versión activa, no dejes su imagen en la previsualización.
this.imagenUrl = '';
this.versionActual = 0;
window._orgVersionActual = null;
window._orgSeleccionandoPosteriorEliminar = true;

var tabla = window.tablaOrganigrama;
if (!tabla) {
window._orgSeleccionandoPosteriorEliminar = false;
return;
}

await new Promise((resolve) => {
tabla.ajax.reload(() => {
var filas = tabla.rows().data().toArray();
var cercana = filas.reduce((mejor, fila) => {
if (!mejor) return fila;
return Math.abs(Number(fila.version) - Number(version)) < Math.abs(Number(mejor.version) - Number(version))
? fila
: mejor;
}, null);

if (cercana) {
document.dispatchEvent(new CustomEvent('org:seleccionar-version', { detail: { id: cercana.id } }));
if (cercana.archivo && cercana.archivo_disponible) {
document.dispatchEvent(new CustomEvent('org:ver-imagen', {
detail: { archivo: cercana.archivo, version: cercana.version }
}));
} else {
this.imagenUrl = '';
this.versionActual = 0;
window._orgVersionActual = null;
}
}

window._orgSeleccionandoPosteriorEliminar = false;
resolve();
}, false);
});
},

showAlert(icon, title, text) {
Swal.fire({ icon: icon, title: title, text: text, timer: 2000, showConfirmButton: false });
},

notify(type, message) {
if (window.Notify) Notify[type](message);
},

async cargarPlantilla() {
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) {
this.plantilla = [];
return;
}

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/get-plantilla?id_estacion=' + idEstacion);
var json = await resp.json();
if (json.success) {
this.plantilla = (json.data || []).map(function(r) {
return { ...r, idEstacion: idEstacion };
});
this.selectedPlantillaId = null;
this.resultadosPersonal = {};
} else {
this.plantilla = [];
}
} catch (e) {
console.error('Error cargando plantilla:', e);
this.plantilla = [];
}
},

async agregarFilaPlantilla() {
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) return;

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/add-plantilla', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_estacion: idEstacion })
});
var json = await resp.json();
if (json.success) {
this.plantilla.push({
id: json.id,
id_usuario: 0,
nombre: '',
nombre_completo: '',
descripcion: '',
documento_perfil: '',
documento_contrato: '',
status: 0,
idEstacion: idEstacion
});
this.notify('success', 'Fila agregada correctamente.');
} else {
this.notify('error', json.message || 'Error al agregar fila.');
}
} catch (e) {
this.notify('error', 'Error al agregar fila.');
}
},

async actualizarCampoPlantilla(id, campo, valor, row) {
if (row) row._savingField = campo;
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-plantilla', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'X-Portal3-Skip-Action-Loader': '1' },
body: JSON.stringify({ id: id, campo: campo, valor: valor })
});
var json = await resp.json();
if (json.success) {
if (row) {
row._savedField = campo;
setTimeout(() => { if (row._savedField === campo) row._savedField = ''; }, 2200);
}
} else {
this.notify('error', json.message || 'Error al guardar el cambio.');
}
} catch (e) {
this.notify('error', 'Error al guardar el cambio.');
} finally {
if (row) row._savingField = '';
}
},

async buscarPersonal(idEstacion, idx, event) {
var query = (event?.target?.value || '').trim();
if (!idEstacion) {
this.resultadosPersonal[idx] = [];
return;
}

// La versión anterior mostraba el catálogo de la estación al abrir el campo
// y también al borrar la búsqueda; después permite filtrar o escribir libremente.

var solicitud = (this.solicitudesPersonal[idx] || 0) + 1;
this.solicitudesPersonal[idx] = solicitud;
this.busquedasPersonal[idx] = query;

try {
var params = new URLSearchParams({ id_estacion: String(idEstacion), query: query });
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/search-personal?' + params.toString());
var json = await resp.json();
// Evita que una respuesta anterior reemplace los resultados de una búsqueda
// más reciente o de otra estación seleccionada mientras se escribía.
if (solicitud !== this.solicitudesPersonal[idx]
|| this.busquedasPersonal[idx] !== query
|| String(idEstacion) !== String(this.obtenerEstacionActual())) return;
if (json.success) this.resultadosPersonal[idx] = json.data || [];
} catch (e) {
console.error('Error buscando personal:', e);
}
},

async asignarNombrePlantilla(row, idx) {
row._savingField = 'nombre';
var valor = (row.nombre_completo || '').trim();
var persona = (this.resultadosPersonal[idx] || []).find((p) => p.nombre === valor);
var datalist = document.getElementById('personal-list-' + idx);
if (!persona && datalist) {
var option = Array.from(datalist.options).find((opt) => opt.value === valor);
if (option && option.dataset.id) persona = { id: option.dataset.id, nombre: option.value };
}

try {
var idUsuario = persona ? parseInt(persona.id, 10) : 0;
var nombrePersonalizado = idUsuario ? '' : valor;
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-plantilla-usuario', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'X-Portal3-Skip-Action-Loader': '1' },
body: JSON.stringify({ id: row.id, id_usuario: idUsuario, nombre: nombrePersonalizado })
});
var json = await resp.json();
if (json.success) {
row.id_usuario = idUsuario;
row.nombre = nombrePersonalizado;
row.nombre_completo = persona ? persona.nombre : nombrePersonalizado;
row._savedField = 'nombre';
setTimeout(() => { if (row._savedField === 'nombre') row._savedField = ''; }, 2200);
} else {
this.notify('error', json.message || 'Error al guardar el cambio.');
}
} catch (e) {
this.notify('error', 'Error al guardar el cambio.');
} finally {
row._savingField = '';
}
},

async eliminarFilaPlantilla(id) {
var res = await this.deleteAction({
url: '/departamento-operativo/recursos-humanos/organigrama/delete-plantilla',
id: id,
name: id,
table: null
});
if (res && res.success) {
this.plantilla = this.plantilla.filter(function(r) { return r.id !== id; });
if (this.selectedPlantillaId === id) this.selectedPlantillaId = null;
}
},

abrirModalDocumento(idPlantilla, tipo, editar) {
this.errors = {};
this.documentoPlantillaId = idPlantilla;
this.documentoTipo = tipo;
this.documentoModo = editar ? 'editar' : 'agregar';
if (this.$refs.fileDocumento) this.$refs.fileDocumento.value = '';
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumento')).show();
},

async guardarDocumento() {
var archivo = this.$refs.fileDocumento ? this.$refs.fileDocumento.files[0] : null;
if (!archivo) {
this.errors.documento = true;
this.notify('error', 'Completa los campos obligatorios.');
return;
}

if (archivo.type !== 'application/pdf' && archivo.name.split('.').pop().toLowerCase() !== 'pdf') {
this.errors.documento = true;
this.notify('error', 'Solo se permiten archivos PDF');
return;
}

this.guardando = true;
try {
var data = new FormData();
data.append('id_plantilla', this.documentoPlantillaId);
data.append('tipo', this.documentoTipo);
data.append('archivo', archivo, archivo.name);

var resp = await axios({
method: 'POST',
url: '/departamento-operativo/recursos-humanos/organigrama/upload-documento',
data: data,
headers: { 'Content-Type': 'multipart/form-data' }
});

if (resp.data.success) {
this.notify('success', 'Documento editado correctamente');
bootstrap.Modal.getInstance(document.getElementById('modalDocumento'))?.hide();
this.cargarPlantilla();
} else {
this.notify('error', resp.data.message || 'Error');
}
} catch (e) {
if (e.response?.data?.errors) this.errors = e.response.data.errors;
this.notify('error', e.response?.data?.message || 'Error al subir documento');
} finally {
this.guardando = false;
}
},

async eliminarDocumento(idPlantilla, tipo) {
var nombre = tipo === 'perfil' ? 'Perfil' : 'Contrato';
var res = await this.deleteAction({
url: '/departamento-operativo/recursos-humanos/organigrama/delete-documento?tipo=' + tipo,
id: idPlantilla,
name: nombre + ' del #' + idPlantilla,
table: null
});
if (res && res.success) {
this.cargarPlantilla();
}
},

downloadDocumento(archivo) {
if (!archivo) return;
window.open('/download?tipo=organigrama-documentos&file=' + encodeURIComponent(archivo), '_blank');
},

async cargarStationInfo() {
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion || this.stationInfoEstaciones.indexOf(idEstacion) === -1) {
this.stationInfo = null;
return;
}

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/get-station-info?id_estacion=' + idEstacion);
var json = await resp.json();
if (json.success && json.data) {
this.stationInfo = json.data;
} else {
this.stationInfo = null;
}
} catch (e) {
console.error('Error cargando info estacion:', e);
this.stationInfo = null;
}
},

async actualizarStationInfo(campo, valor) {
var idEstacion = this.obtenerEstacionActual();
if (!idEstacion) return;
this.stationInfo._savingField = campo;

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-station-info', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'X-Portal3-Skip-Action-Loader': '1' },
body: JSON.stringify({ id_estacion: idEstacion, campo: campo, valor: valor })
});
var json = await resp.json();
if (json.success) {
this.stationInfo._savedField = campo;
setTimeout(() => {
if (this.stationInfo && this.stationInfo._savedField === campo) this.stationInfo._savedField = '';
}, 2200);
} else {
this.notify('error', json.message || 'Error al guardar');
}
} catch (e) {
this.notify('error', 'Error al guardar');
} finally {
if (this.stationInfo) this.stationInfo._savingField = '';
}
},

}));
});
