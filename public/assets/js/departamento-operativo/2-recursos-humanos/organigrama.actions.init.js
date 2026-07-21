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
resultadosPersonal: {},
stationInfo: null,
stationInfoEstaciones: [1, 2, 3, 4, 5, 6, 7, 14],

form: {
observaciones: '',
},
documentoPlantillaId: 0,
documentoTipo: 'perfil',
documentoModo: 'agregar',

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
this.notify('error', '* Selecciona una imagen');
return;
}

var ext = archivo.name.split('.').pop().toLowerCase();
if (['jpg', 'jpeg', 'png'].includes(ext) === false) {
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
this.notify('error', 'Error al guardar');
} finally {
this.guardando = false;
}
},

confirmarEliminar(id, version) {
window._orgVersionActual = null;
this.deleteAction({
url: '/departamento-operativo/recursos-humanos/organigrama/delete',
id: id,
name: 'Versión ' + version,
table: '#tabla-organigrama-versions'
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

async actualizarCampoPlantilla(id, campo, valor) {
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-plantilla', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: id, campo: campo, valor: valor })
});
var json = await resp.json();
if (json.success) {
this.notify('success', 'Campo modificado exitosamente.');
} else {
this.notify('error', json.message || 'Error al guardar el cambio.');
}
} catch (e) {
this.notify('error', 'Error al guardar el cambio.');
}
},

async buscarPersonal(idEstacion, idx, event) {
var query = event.target.value;
if (query.length < 1) {
this.resultadosPersonal[idx] = [];
return;
}

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/search-personal?id_estacion=' + idEstacion + '&query=' + encodeURIComponent(query));
var json = await resp.json();
if (json.success) {
this.resultadosPersonal[idx] = json.data || [];
}
} catch (e) {
console.error('Error buscando personal:', e);
}
},

async asignarNombrePlantilla(row, idx) {
var datalist = document.getElementById('personal-list-' + idx);
if (!datalist) return;
var options = datalist.querySelectorAll('option');
var match = null;
for (var opt of options) {
if (opt.value === row.nombre_completo) {
match = opt;
break;
}
}

try {
var resp;
if (match) {
var idUsuario = parseInt(match.dataset.id);
resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-plantilla-usuario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: row.id, id_usuario: idUsuario, nombre: '' })
});
} else {
resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-plantilla-usuario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: row.id, id_usuario: 0, nombre: row.nombre_completo })
});
}
var json = await resp.json();
if (json.success) {
this.notify('success', 'Campo modificado exitosamente.');
}
} catch (e) {
this.notify('error', 'Error al guardar el cambio.');
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
}
},

abrirModalDocumento(idPlantilla, tipo, editar) {
this.documentoPlantillaId = idPlantilla;
this.documentoTipo = tipo;
this.documentoModo = editar ? 'editar' : 'agregar';
if (this.$refs.fileDocumento) this.$refs.fileDocumento.value = '';
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumento')).show();
},

async guardarDocumento() {
var archivo = this.$refs.fileDocumento ? this.$refs.fileDocumento.files[0] : null;
if (!archivo) {
this.notify('error', 'Selecciona un archivo PDF');
return;
}

if (archivo.type !== 'application/pdf') {
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
this.notify('error', 'Error al subir documento');
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

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/organigrama/update-station-info', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_estacion: idEstacion, campo: campo, valor: valor })
});
var json = await resp.json();
if (json.success) {
this.notify('success', json.message || 'Guardado');
} else {
this.notify('error', json.message || 'Error al guardar');
}
} catch (e) {
this.notify('error', 'Error al guardar');
}
},

}));
});