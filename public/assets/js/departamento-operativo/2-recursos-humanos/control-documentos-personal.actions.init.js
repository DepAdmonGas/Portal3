document.addEventListener('alpine:init', () => {

Alpine.data('controlDocsComponent', () => ({

loading: false,
guardando: false,
guardandoComentario: false,
idEstacionActual: 0,
haSeleccionado: true,
esMultiestacion: false,
puedeCrear: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
nombreEstacionActual: '',
baselineBusy: false,
baselinePreview: null,
baselineImportResult: null,
baselineError: '',
baselineAllowedWorkplaceIds: [],
baselineDefaultWorkplaceId: 0,
baselineWorkplaceId: 0,
baselineEffectiveFromUtc: '',
baselineReasonText: '',
baselineSelectedWorkerIds: [],
confirmBaselineRoster: false,

modoForm: 'agregar',
personalId: 0,
form: {
nombre_completo: '',
puesto: '',
no_colaborador: '',
fecha_ingreso: '',
sd: 0,
id_estacion: '',
documentos: {}
},
errors: {
id_estacion: false,
fecha_ingreso: false,
nombre_completo: false,
puesto: false,
},
puestos: [],
estaciones: [],

comentarioPersonalId: 0,
comentarioPersonalNombre: '',
comentarios: [],
nuevoComentario: '',

bajaPersonalId: 0,
bajaPersonalNombre: '',
bajaForm: {
fecha_baja: '',
motivo: '',
detalle: ''
},

accesoPersonalId: 0,
accesoPersonalNombre: '',
accesoData: { huella: '', pin: 0, nombre_puesto: '' },
accesoPinInput: '',
accesoPinError: '',
showEditPin: false,
accesoGuardando: false,
accesoSoloLectura: false,

documentTypes: {
requisicion:      { label: 'Requisicion del Personal' },
curriculum:       { label: 'Solicitud de empleo y/o Curriculum Vitae (CV)' },
ine:              { label: 'Identificacion Oficial (vigente, elector o pasaporte)' },
acta_nacimiento:  { label: 'Acta de Nacimiento (certificada)' },
c_domicilio:      { label: 'Comprobante de Domicilio', fullWidth: true },
nss:              { label: 'Comprobante de Afiliacion IMSS', fullWidth: true },
c_recomendacion:  { label: 'Cartas de Recomendacion de los ultimos empleos', fullWidth: true },
c_estudios:       { label: 'Ultimo Comprobante de Estudios' },
curp:             { label: 'Clave Unica de Registro de Poblacion (CURP)' },
a_infonavit:      { label: 'Aviso de Retencion de Infonavit' },
rfc:              { label: 'Constancia de Situacion Fiscal (CSF) con homoclave' },
c_antecedentes:   { label: 'Carta de Antecedentes No Penales (solo despachadores)' },
contrato:         { label: 'Contrato' }
},

init() {
var c = document.getElementById('container');
if (!c) return;

this.puedeCrear = c.dataset.puedeCrear === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.esMultiestacion = c.dataset.multiestacion === 'true';
this.idEstacionActual = parseInt(c.dataset.idEstacion || '0');
try {
this.baselineAllowedWorkplaceIds = JSON.parse(c.dataset.baselineWorkplaceIds || '[]').map(Number).filter(Number.isInteger);
} catch (e) {
this.baselineAllowedWorkplaceIds = [];
}
this.baselineDefaultWorkplaceId = parseInt(c.dataset.baselineCurrentWorkplaceId || '0');

this.cargarPuestos();
this.cargarEstaciones();

this.bindModalSelect2({
modalRef: 'modalAgregarPersonal',
selectRef: 'estacionSelect',
wrapperRef: 'estacionWrapper',
model: 'form.id_estacion',
options: { placeholder: 'Selecciona una estación/departamento...' },
namespace: 'cdEstacion'
});

this.bindModalSelect2({
modalRef: 'modalAgregarPersonal',
selectRef: 'puestoSelect',
wrapperRef: 'puestoWrapper',
model: 'form.puesto',
options: { placeholder: 'Selecciona un puesto...' },
namespace: 'cdPuesto'
});

var self = this;
document.getElementById('modalAgregarPersonal').addEventListener('hidden.bs.modal', function() {
self.modoForm = 'agregar';
self.personalId = 0;
self.nombreEstacionActual = '';
self.actualizarNombreEstacion();
self.form = {
nombre_completo: '', puesto: '', no_colaborador: '',
fecha_ingreso: '', sd: 0, id_estacion: self.idEstacionActual || '',
documentos: {}
};
self.clearErrors();
});

this.$watch('form.puesto', (value) => {
this.onPuestoChange();
});

document.addEventListener('cd:estacion-cambio', () => {
this.idEstacionActual = this.obtenerEstacionActual();
this.haSeleccionado = true;
this.actualizarNombreEstacion();
});

var sel = document.getElementById('module-station-selector-control-documentos-personal');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) this.idEstacionActual = parseInt(p[1]);
this.haSeleccionado = true;
} else if (parseInt(c.dataset.idEstacion || '0') > 0) {
this.haSeleccionado = true;
}
this.actualizarNombreEstacion();

window.controlDocsComponentInstance = this;
},

selectedBaselineWorkplaceId() {
var selector = document.getElementById('module-station-selector-control-documentos-personal');
if (selector && selector.value) {
if (!selector.value.startsWith('estacion_')) return 0;
var selected = parseInt(selector.value.slice('estacion_'.length));
return Number.isInteger(selected) ? selected : 0;
}
return this.baselineDefaultWorkplaceId;
},

canRunChecadorBaseline() {
var workplaceId = this.selectedBaselineWorkplaceId();
return this.puedeEditar && workplaceId > 0 && this.baselineAllowedWorkplaceIds.includes(workplaceId);
},

clearBaselinePreview() {
this.baselinePreview = null;
this.baselineImportResult = null;
this.baselineError = '';
this.baselineSelectedWorkerIds = [];
this.confirmBaselineRoster = false;
},

abrirChecadorBaseline() {
if (!this.canRunChecadorBaseline()) return;
this.baselineWorkplaceId = this.selectedBaselineWorkplaceId();
this.baselineEffectiveFromUtc = this.baselineWorkplaceId === 1 ? '2026-11-06T06:00:00Z' : '';
this.baselineReasonText = '';
this.clearBaselinePreview();
bootstrap.Modal.getOrCreateInstance(document.getElementById('checadorMembershipBaselineModal')).show();
},

baselineSelectionPreviewMatches() {
if (!this.baselinePreview || !this.baselinePreview.requires_worker_selection) return true;
var selected = (this.baselineSelectedWorkerIds || []).map(Number).filter(Number.isInteger).sort(function(a, b) { return a - b; });
var previewed = (this.baselinePreview.selected_worker_ids || []).map(Number).filter(Number.isInteger).sort(function(a, b) { return a - b; });
return JSON.stringify(selected) === JSON.stringify(previewed);
},

onBaselineWorkerSelectionChange() {
this.confirmBaselineRoster = false;
this.baselineImportResult = null;
},

async previewChecadorBaseline() {
if (this.baselineBusy || !this.canRunChecadorBaseline() || this.baselineWorkplaceId !== this.selectedBaselineWorkplaceId()) return;
var selectedIds = this.baselineWorkplaceId === 9 && this.baselineSelectedWorkerIds.length
? this.baselineSelectedWorkerIds.map(Number).filter(Number.isInteger).sort(function(a, b) { return a - b; })
: null;
this.baselineBusy = true;
this.baselinePreview = null;
this.baselineImportResult = null;
this.baselineError = '';
this.confirmBaselineRoster = false;
try {
var payload = {
workplace_id: this.baselineWorkplaceId,
valid_from_utc: this.baselineEffectiveFromUtc
};
if (this.baselineWorkplaceId === 9) {
payload.reason = this.baselineReasonText.trim();
if (selectedIds !== null) payload.selected_worker_ids = selectedIds;
}
var response = await axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-preview', payload);
this.baselinePreview = response.data.data || null;
this.baselineSelectedWorkerIds = (this.baselinePreview?.selected_worker_ids || []).map(Number).filter(Number.isInteger);
this.baselineError = response.data.success ? '' : (response.data.message || 'La vista previa no está disponible.');
} catch (error) {
var payload = error && error.response ? error.response.data : null;
if (payload && payload.data) this.baselinePreview = payload.data;
this.baselineSelectedWorkerIds = (this.baselinePreview?.selected_worker_ids || []).map(Number).filter(Number.isInteger);
if (!this.baselinePreview) {
this.baselineError = payload && payload.message ? payload.message : 'No se pudo completar la vista previa autorizada.';
this.notify('error', this.baselineError);
}
} finally {
this.baselineBusy = false;
}
},

async importChecadorBaseline() {
if (this.baselineBusy || !this.canRunChecadorBaseline()
    || !this.baselinePreview || !this.baselinePreview.ready_to_import
    || !this.baselineSelectionPreviewMatches()
    || (!this.baselinePreview.is_interlomas && !this.confirmBaselineRoster)) return;
var confirmationText = this.baselinePreview.is_interlomas
? 'Se registrarán exactamente los 41 trabajadores autorizados de Interlomas desde 2026-11-06 06:00 UTC. No se crearán horarios.'
: (this.baselinePreview.requires_worker_selection
? 'Se registrarán únicamente los IDs ' + (this.baselinePreview.selected_worker_ids || []).join(', ') + ' de ' + (this.baselinePreview.locality_reference?.locality || 'la estación seleccionada') + ' desde ' + this.baselinePreview.valid_from_utc + '. Motivo: ' + (this.baselinePreview.reason || '') + '. No se crearán horarios.'
: 'Se registrará únicamente el conjunto mostrado para ' + (this.baselinePreview.locality_reference?.locality || 'la estación seleccionada') + ' desde ' + this.baselinePreview.valid_from_utc + '. No se crearán horarios.');
var confirmation = await Swal.fire({
icon: 'warning',
title: 'Confirmar incorporación inicial',
text: confirmationText,
showCancelButton: true,
confirmButtonText: 'Registrar membresías',
cancelButtonText: 'Cancelar',
reverseButtons: true
});
if (!confirmation.isConfirmed) return;

this.baselineBusy = true;
this.baselineError = '';
try {
var payload = {
workplace_id: this.baselinePreview.workplace_id,
candidate_count: this.baselinePreview.candidate_count,
candidate_fingerprint: this.baselinePreview.candidate_fingerprint,
source_fingerprint: this.baselinePreview.source_fingerprint,
valid_from_utc: this.baselinePreview.valid_from_utc,
confirm_roster: this.confirmBaselineRoster
};
if (this.baselinePreview.requires_worker_selection) {
payload.selected_worker_ids = this.baselinePreview.selected_worker_ids;
payload.full_candidate_fingerprint = this.baselinePreview.full_candidate_fingerprint;
}
var response = await axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-import', payload);
this.baselineImportResult = response.data || null;
this.notify('success', response.data.message || 'La importación fue verificada.');
} catch (error) {
var payload = error && error.response ? error.response.data : null;
this.baselineError = payload && payload.message ? payload.message : 'No se confirmó la importación. Genera una vista previa nueva y verifica el historial.';
this.notify('error', this.baselineError);
} finally {
this.baselineBusy = false;
}
},

obtenerEstacionActual() {
var sel = document.getElementById('module-station-selector-control-documentos-personal');
if (sel && sel.value) {
var p = sel.value.split('_');
if (p.length === 2 && p[1]) return parseInt(p[1]);
}
return parseInt(document.getElementById('container').dataset.idEstacion || '0');
},

actualizarNombreEstacion() {
var id = this.idEstacionActual;
if (!id) {
this.nombreEstacionActual = '';
return;
}
var found = this.estaciones.find(function(e) { return e.id == id; });
this.nombreEstacionActual = found ? found.nombre : '';
},

notify(type, message) {
if (window.Notify) Notify[type](message);
},

async cargarPuestos() {
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-puestos');
var json = await resp.json();
if (json.success) this.puestos = json.data || [];
} catch (e) {
console.error('Error cargando puestos:', e);
}
},

async cargarEstaciones() {
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-estaciones');
var json = await resp.json();
if (json.success) this.estaciones = json.data || [];
} catch (e) {
console.error('Error cargando estaciones:', e);
}
},

clearErrors() {
this.errors = { id_estacion: false, fecha_ingreso: false, nombre_completo: false, puesto: false };
},

abrirModalAgregar() {
this.clearErrors();
this.modoForm = 'agregar';
this.personalId = 0;
this.nombreEstacionActual = '';
this.actualizarNombreEstacion();
this.form = {
nombre_completo: '', puesto: '', no_colaborador: '',
fecha_ingreso: '', sd: 0, id_estacion: this.idEstacionActual || '',
documentos: {}
};
this.resetFileInputs();
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarPersonal')).show();
},

async abrirEditar(id) {
this.clearErrors();
this.modoForm = 'editar';
this.personalId = id;
var personalData = null;
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-personal-by-id?id=' + id);
var json = await resp.json();
if (json.success && json.data) {
personalData = json.data;
}
} catch (e) {
this.notify('error', 'Error al cargar datos');
return;
}
if (!personalData) return;

var maxWait = 50;
while (maxWait-- > 0 && (!this.estaciones.length || !this.puestos.length)) {
await new Promise(function(r) { setTimeout(r, 100); });
}

this.form = {
nombre_completo: personalData.nombre_completo,
puesto: String(personalData.puesto),
no_colaborador: personalData.no_colaborador,
fecha_ingreso: personalData.fecha_ingreso,
sd: personalData.sd,
id_estacion: String(personalData.id_estacion),
documentos: personalData.documentos || {}
};
this.nombreEstacionActual = personalData.nombre_estacion || '';

this.resetFileInputs();

var self = this;
var eid = String(personalData.id_estacion);
var pid = String(personalData.puesto);
var modalEl = document.getElementById('modalAgregarPersonal');

function onEditShown() {
modalEl.removeEventListener('shown.bs.modal', onEditShown);
self.$nextTick(function() {
if (self.$refs.estacionSelect) {
var $e = $(self.$refs.estacionSelect);
if ($e.hasClass('select2-hidden-accessible') && eid) {
$e.val(eid).trigger('change.select2');
}
}
if (self.$refs.puestoSelect) {
var $p = $(self.$refs.puestoSelect);
if ($p.hasClass('select2-hidden-accessible') && pid) {
$p.val(pid).trigger('change.select2');
}
}
self.$nextTick(function() {
if (self.$refs.estacionSelect) {
var $e2 = $(self.$refs.estacionSelect);
if ($e2.hasClass('select2-hidden-accessible') && eid) {
$e2.val(eid).trigger('change.select2');
}
}
if (self.$refs.puestoSelect) {
var $p2 = $(self.$refs.puestoSelect);
if ($p2.hasClass('select2-hidden-accessible') && pid) {
$p2.val(pid).trigger('change.select2');
}
}
});
});
}
modalEl.addEventListener('shown.bs.modal', onEditShown);
bootstrap.Modal.getOrCreateInstance(modalEl).show();
},

resetFileInputs() {
var inputs = document.querySelectorAll('#modalAgregarPersonal input[type="file"][name^="doc_"]');
inputs.forEach(function(input) { input.value = ''; });
},

onPuestoChange() {
var puesto = this.form.puesto;
var puestoObj = this.puestos.find(function(p) { return p.id == puesto; });
var nombrePuesto = puestoObj ? puestoObj.puesto : '';
var cartasPenales = document.getElementById('Cartas_Penales');
if (cartasPenales) {
cartasPenales.style.display = (nombrePuesto === 'Despachador') ? 'block' : 'none';
}
},

async guardarPersonal() {
if (this.guardando) return;
this.clearErrors();
var hasError = false;

var estacionAsignada = this.form.id_estacion || this.idEstacionActual;
if (!estacionAsignada) {
this.errors.id_estacion = true;
hasError = true;
}
if (!this.form.fecha_ingreso) {
this.errors.fecha_ingreso = true;
hasError = true;
}
if (!this.form.nombre_completo) {
this.errors.nombre_completo = true;
hasError = true;
}
if (!this.form.puesto) {
this.errors.puesto = true;
hasError = true;
}

if (hasError) {
this.notify('error', '* Completa los campos obligatorios');
return;
}

this.guardando = true;
try {
var data = new FormData();
data.append('nombre_completo', this.form.nombre_completo);
data.append('puesto', this.form.puesto);
data.append('no_colaborador', this.form.no_colaborador);
data.append('fecha_ingreso', this.form.fecha_ingreso);
data.append('sd', this.form.sd);
data.append('id_estacion', this.form.id_estacion || this.idEstacionActual);

var fileInputs = document.querySelectorAll('#modalAgregarPersonal input[type="file"][name^="doc_"]');
fileInputs.forEach(function(input) {
if (input.files && input.files[0]) {
data.append(input.name, input.files[0]);
}
});

var url = this.modoForm === 'agregar'
? '/departamento-operativo/recursos-humanos/control-documentos-personal/add'
: '/departamento-operativo/recursos-humanos/control-documentos-personal/edit';

var requestHeaders = {};
if (this.modoForm === 'agregar') {
    try {
        requestHeaders['Idempotency-Key'] = await this.idempotencyKeyFor(data);
    } catch (keyError) {
        var keyMessage = keyError && String(keyError.message || '').includes('still pending')
            ? 'Hay un alta pendiente. Reintenta con los datos y documentos originales antes de iniciar otra.'
            : 'No se pudo preparar una clave segura para el alta. Actualiza el navegador e inténtalo de nuevo.';
        this.notify('error', keyMessage);
        return;
    }
}

if (this.modoForm === 'editar') {
data.append('id', this.personalId);
}

var resp = await axios({
method: 'POST',
url: url,
data: data,
headers: requestHeaders
});

this.handleResponse(resp, '#tabla-control-docs');
if (resp.data.success) {
if (this.modoForm === 'agregar') {
    try { sessionStorage.removeItem('portal3:control-docs:direct-create-operation'); } catch (e) {}
}
bootstrap.Modal.getInstance(document.getElementById('modalAgregarPersonal'))?.hide();
if (window.tablaControlDocs) window.tablaControlDocs.ajax.reload(null, false);
if (window.tablaControlDocsInactivos) window.tablaControlDocsInactivos.ajax.reload(null, false);
}
} catch (err) {
var status = err && err.response ? err.response.status : 0;
var serverMessage = err && err.response && err.response.data ? err.response.data.message : '';
if (this.modoForm === 'agregar' && status >= 400 && status < 500 && status !== 409) {
    try { sessionStorage.removeItem('portal3:control-docs:direct-create-operation'); } catch (e) {}
}
this.notify('error', serverMessage || (status === 409
    ? 'La clave de esta operación ya se usó con otros datos. Recupera la solicitud original antes de iniciar otra alta.'
    : (status === 403 ? 'No tienes autorización para esta localidad.' : 'Error al guardar')));
} finally {
this.guardando = false;
}
},

async idempotencyKeyFor(formData) {
    if (!window.crypto || !window.crypto.subtle || !window.crypto.getRandomValues) {
        throw new Error('Secure browser crypto is required.');
    }
    var payload = [];
    for (var [key, value] of formData.entries()) {
        if (value instanceof File) {
            if (value.size > 0) {
                var fileDigest = await window.crypto.subtle.digest('SHA-256', await value.arrayBuffer());
                var fileFingerprint = Array.from(new Uint8Array(fileDigest)).map(function(byte) {
                    return byte.toString(16).padStart(2, '0');
                }).join('');
                payload.push([key, value.name, value.size, value.lastModified, fileFingerprint]);
            }
        } else {
            payload.push([key, String(value)]);
        }
    }
    payload.sort(function(a, b) { return String(a[0]).localeCompare(String(b[0])); });
    var bytes = new TextEncoder().encode(JSON.stringify(payload));
    var digest = await window.crypto.subtle.digest('SHA-256', bytes);
    var fingerprint = Array.from(new Uint8Array(digest)).map(function(value) {
        return value.toString(16).padStart(2, '0');
    }).join('');
    var storageKey = 'portal3:control-docs:direct-create-operation';
    var saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (e) {}
    if (saved && typeof saved.key === 'string') {
        if (saved.fingerprint !== fingerprint) {
            throw new Error('A direct-create operation is still pending; retry its original payload before changing it.');
        }
        return saved.key;
    }

    var bytesKey = new Uint8Array(16);
    window.crypto.getRandomValues(bytesKey);
    bytesKey[6] = (bytesKey[6] & 0x0f) | 0x40;
    bytesKey[8] = (bytesKey[8] & 0x3f) | 0x80;
    var hex = Array.from(bytesKey).map(function(value) { return value.toString(16).padStart(2, '0'); }).join('');
    var key = hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    sessionStorage.setItem(storageKey, JSON.stringify({ key: key, fingerprint: fingerprint }));
    return key;
},

confirmarEliminar(id, nombre) {
this.deleteAction({
url: '/departamento-operativo/recursos-humanos/control-documentos-personal/delete',
id: id,
name: nombre,
table: '#tabla-control-docs'
}).then(() => {
if (window.tablaControlDocsInactivos) window.tablaControlDocsInactivos.ajax.reload(null, false);
});
},

scrollChatToBottom() {
this.$nextTick(() => {
var el = this.$refs.chatContainer;
if (el) el.scrollTop = el.scrollHeight;
});
},

async abrirComentarios(id, nombre) {
this.comentarioPersonalId = id;
this.comentarioPersonalNombre = nombre;
this.nuevoComentario = '';
this.comentarios = [];

var self = this;
var offcanvasEl = document.getElementById('modalComentarios');
var oc = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

oc.show();

this.$nextTick(async function() {
try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-comentarios?id_personal=' + id);
var json = await resp.json();
if (json.success) {
self.comentarios = json.data || [];
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
if (!this.comentarioPersonalId) return;

this.guardandoComentario = true;
var personalId = this.comentarioPersonalId;

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/add-comentario', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_personal: personalId, comentario: this.nuevoComentario })
});
var json = await resp.json();

if (json.success) {
this.nuevoComentario = '';
var resp2 = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-comentarios?id_personal=' + personalId);
var json2 = await resp2.json();
if (json2.success) {
this.comentarios = json2.data || [];
this.scrollChatToBottom();
}
var dt = window.tablaControlDocs;
if (dt) {
dt.rows().every(function() {
var d = this.data();
if (d.id === personalId) {
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

abrirBaja(id, nombre) {
this.bajaPersonalId = id;
this.bajaPersonalNombre = nombre;
this.bajaForm = { fecha_baja: '', motivo: '', detalle: '' };
bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBajaPersonal')).show();
},

async guardarBaja() {
if (!this.bajaForm.fecha_baja || !this.bajaForm.motivo) {
this.notify('error', '* Completa los campos obligatorios');
return;
}

this.guardando = true;
try {
var data = new FormData();
data.append('id_personal', this.bajaPersonalId);
data.append('fecha_baja', this.bajaForm.fecha_baja);
data.append('motivo', this.bajaForm.motivo);
data.append('detalle', this.bajaForm.detalle);

if (this.$refs.fileActaHechos && this.$refs.fileActaHechos.files[0]) {
data.append('acta_hechos', this.$refs.fileActaHechos.files[0]);
}
if (this.$refs.fileCartaRenuncia && this.$refs.fileCartaRenuncia.files[0]) {
data.append('carta_renuncia', this.$refs.fileCartaRenuncia.files[0]);
}
if (this.$refs.fileFiniquito && this.$refs.fileFiniquito.files[0]) {
data.append('finiquito', this.$refs.fileFiniquito.files[0]);
}

var resp = await axios({
method: 'POST',
url: '/departamento-operativo/recursos-humanos/control-documentos-personal/add-baja',
data: data
});

this.handleResponse(resp);
if (resp.data.success) {
bootstrap.Modal.getInstance(document.getElementById('modalBajaPersonal'))?.hide();
if (window.tablaControlDocs) window.tablaControlDocs.ajax.reload(null, false);
if (window.tablaControlDocsInactivos) window.tablaControlDocsInactivos.ajax.reload(null, false);
}
} catch (err) {
this.notify('error', 'Error al registrar baja');
} finally {
this.guardando = false;
}
},

abrirDetalleBaja(idBaja) {
window.location.href = '/departamento-operativo/recursos-humanos/control-documentos-personal/detalle-baja/' + idBaja;
},

async abrirAcceso(id, nombre, soloLectura) {
this.accesoPersonalId = id;
this.accesoPersonalNombre = nombre;
this.accesoSoloLectura = !!soloLectura;
this.accesoData = { huella: '', pin: 0, nombre_puesto: '' };
this.accesoPinInput = '';
this.accesoPinError = '';
this.showEditPin = false;
this.accesoGuardando = false;

bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAccesoPersonal')).show();

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/get-acceso?id_personal=' + id);
var json = await resp.json();
if (json.success && json.data) {
this.accesoData = json.data;
}
} catch (e) {
this.notify('error', 'Error al cargar datos de acceso');
}
},

toggleEditPin() {
if (this.accesoSoloLectura) return;
this.showEditPin = !this.showEditPin;
this.accesoPinInput = this.showEditPin ? (this.accesoData.pin || '') : '';
this.accesoPinError = '';
},

async editarPin() {
if (this.accesoSoloLectura) return;
if (this.accesoGuardando) return;

var pin = (this.accesoPinInput || '').trim();
if (pin.length < 5) {
this.accesoPinError = 'El PIN debe contener al menos 5 caracteres.';
return;
}

this.accesoGuardando = true;
this.accesoPinError = '';

try {
var resp = await fetch('/departamento-operativo/recursos-humanos/control-documentos-personal/editar-pin', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id_personal: this.accesoPersonalId, pin: pin })
});
var json = await resp.json();

if (json.success) {
this.accesoData.pin = parseInt(pin);
this.showEditPin = false;
this.accesoPinInput = '';
if (window.Notify) Notify.success(json.message || 'PIN actualizado correctamente.');
} else {
this.accesoPinError = json.message || 'Error al actualizar PIN.';
}
} catch (e) {
this.accesoPinError = 'Error al conectar con el servidor.';
} finally {
this.accesoGuardando = false;
}
},

downloadDocumento(campo, archivo) {
if (!archivo) return;
var tipoMap = {
requisicion: 'docs-personal-requisicion',
curriculum: 'docs-personal-curriculum',
ine: 'docs-personal-ine',
acta_nacimiento: 'docs-personal-acta-nacimiento',
c_domicilio: 'docs-personal-c-domicilio',
nss: 'docs-personal-nss',
c_estudios: 'docs-personal-c-estudios',
c_recomendacion: 'docs-personal-c-recomendacion',
curp: 'docs-personal-curp',
a_infonavit: 'docs-personal-a-infonavit',
rfc: 'docs-personal-rfc',
c_antecedentes: 'docs-personal-c-antecedentes',
contrato: 'docs-personal-contrato',
documentos: 'docs-personal-documentos'
};
var tipo = tipoMap[campo] || 'docs-personal-' + campo;
window.open('/download?tipo=' + tipo + '&file=' + encodeURIComponent(archivo), '_blank');
},

}));
});
