document.addEventListener('alpine:init', () => {

Alpine.data('formatosFormComponent', () => ({
formato: 0,
esEdicion: false,
esMultiempleado: false,
idLocalidad: 0,
detalleId: 0,
localidad: '',
personal: [],
puestos: [],
estaciones: [],
estacionesCambio: [],
motivos: [],
archivosAlta: [],
columnas: [],
colspanTabla: 1,
periodos: [],
valores: {},
cabecera: {},
filas: [],
fila: {},
archivosEmpleado: [],
archivosEmpleadoNombre: '',
signaturePad: null,
guardandoFila: false,
guardando: false,

init() {
const c = this.$el;
this.formato = parseInt(c.dataset.formato || '0');
this.esEdicion = c.dataset.esEdicion === 'true';
this.esMultiempleado = this.formato <= 5;
this.idLocalidad = parseInt(c.dataset.idLocalidad || '0');
this.detalleId = parseInt(c.dataset.detalleId || '0');
this.localidad = c.dataset.localidad || '';

try {
const raw = c.dataset.formatos;
const data = raw ? JSON.parse(raw) : {};
this.personal = data.personal || [];
this.puestos = data.puestos || [];
this.estaciones = data.estaciones || [];
this.estacionesCambio = data.estacionesCambio || [];
this.motivos = data.motivos || [];
this.archivosAlta = data.archivosAlta || [];
this.columnas = data.columnas || [];
this.colspanTabla = this.columnas.length;
this.periodos = data.periodos || [];
this.valores = data.valores || {};
this.cabecera = data.cabecera || {};
this.filas = data.detalleRows || [];
} catch (err) {
console.error('Error al leer datos del formato:', err);
}

this.$watch('fila.id_personal', (value) => {
if (this.formato !== 5) return;
const p = this.personal.find(x => x.id === Number(value));
if (p && p.sd !== undefined && p.sd !== null && p.sd !== '') {
this.fila.salario_actual = p.sd;
}
});

this.initSelect2();

this.$nextTick(() => this.initSignaturePad());
},

initSelect2() {
if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;

this.$nextTick(() => {
const bindModal = (selectRef, wrapperRef, model, options, formatoFila, namespace) => {
this.bindModalSelect2({
modalRef: 'modalAgregarFila',
selectRef,
wrapperRef,
model,
options,
namespace,
onShown: function () { return this.formato === formatoFila; }
});
};

bindModal('f1PuestoSelect', 'f1PuestoWrapper', 'fila.puesto',
{ placeholder: 'Selecciona un puesto...' }, 1, 'fmtF1Puesto');
bindModal('f2PersonalSelect', 'f2PersonalWrapper', 'fila.id_personal',
{ placeholder: 'Selecciona un empleado...' }, 2, 'fmtF2Personal');
bindModal('f3PersonalSelect', 'f3PersonalWrapper', 'fila.id_personal',
{ placeholder: 'Selecciona un empleado...' }, 3, 'fmtF3Personal');
bindModal('f4PersonalSelect', 'f4PersonalWrapper', 'fila.id_personal',
{ placeholder: 'Selecciona un empleado...' }, 4, 'fmtF4Personal');
bindModal('f4EstacionSelect', 'f4EstacionWrapper', 'fila.id_estacion_cambio',
{ placeholder: 'Selecciona una localidad...' }, 4, 'fmtF4Estacion');
bindModal('f5PersonalSelect', 'f5PersonalWrapper', 'fila.id_personal',
{ placeholder: 'Selecciona un empleado...' }, 5, 'fmtF5Personal');

if (this.formato === 6) {
this.initModalSelect2({
selectRef: 'f6PersonalSelect',
wrapperRef: 'f6PersonalWrapper',
model: 'valores.id_personal',
options: { placeholder: 'Selecciona un empleado...' },
namespace: 'fmtF6Personal'
});
}
if (this.formato === 7) {
this.initModalSelect2({
selectRef: 'f7PersonalSelect',
wrapperRef: 'f7PersonalWrapper',
model: 'valores.id_personal',
options: { placeholder: 'Selecciona un colaborador...' },
namespace: 'fmtF7Personal'
});
}
});
},

nuevaFila() {
const base = {};
if (this.formato === 1) {
base.archivos = {};
base.sd = '';
}
if (this.formato === 5) {
base.salario_actual = '';
}
return base;
},

abrirModal() {
this.fila = this.nuevaFila();
const m = document.getElementById('modalAgregarFila');
if (!m) return;
const body = m.querySelector('.modal-body');
if (body) {
body.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(function (el) {
if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
else el.value = '';
});
}
this.limpiarInvalidosModal();
bootstrap.Modal.getOrCreateInstance(m).show();
},

cerrarModal() {
const m = document.getElementById('modalAgregarFila');
if (m) bootstrap.Modal.getOrCreateInstance(m).hide();
},

initSignaturePad() {
const w = document.getElementById('signature-pad');
const cv = w ? w.querySelector('canvas') : null;
if (cv && typeof SignaturePad !== 'undefined') {
this.signaturePad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
this._resizeCanvas();
window.addEventListener('resize', () => this._resizeCanvas());
}
},

_resizeCanvas() {
const w = document.getElementById('signature-pad');
const cv = w ? w.querySelector('canvas') : null;
if (!cv) return;
const r = Math.max(window.devicePixelRatio || 1, 1);
cv.width = cv.offsetWidth * r;
cv.height = cv.offsetHeight * r;
cv.getContext('2d').scale(r, r);
if (this.signaturePad) this.signaturePad.clear();
},

limpiarFirma() {
if (this.signaturePad) this.signaturePad.clear();
},

limpiarInvalidosModal() {
const m = document.getElementById('modalAgregarFila');
if (!m) return;
m.querySelectorAll('.is-invalid').forEach(function (el) {
el.classList.remove('is-invalid');
});
},

marcarInvalidoModal(campo) {
const m = document.getElementById('modalAgregarFila');
if (!m) return;
const fila = m.querySelector('.row[data-formato-fila="' + this.formato + '"]');
const el = fila ? fila.querySelector('[data-campo="' + campo + '"]') : null;
if (el) {
const wrapper = el.closest('.select2-modal-field') || el;
wrapper.classList.add('is-invalid');
}
},

filaValida() {
this.limpiarInvalidosModal();
const f = this.fila;
const faltantes = [];
const marcar = (campo) => this.marcarInvalidoModal(campo);

if (this.formato === 1) {
if (!f.nombre) { faltantes.push('• Nombre completo'); marcar('nombre'); }
if (!f.puesto) { faltantes.push('• Puesto'); marcar('puesto'); }
if (!f.fecha_ingreso) { faltantes.push('• Fecha de alta'); marcar('fecha_ingreso'); }
if (f.sd === '' || f.sd === null) { faltantes.push('• Salario diario'); marcar('sd'); }

const docsObligatorios = ['curriculum', 'ine', 'acta_nacimiento', 'nss', 'c_domicilio', 'c_estudios', 'c_recomendacion', 'curp', 'rfc'];
if (Number(f.puesto) === 4) docsObligatorios.push('c_antecedentes');
const docsFaltantes = [];
docsObligatorios.forEach((campo) => {
const tieneArchivo = !!(f.archivos && f.archivos[campo]);
const tieneExistente = !!(f._existe && f._existe[campo]);
if (!tieneArchivo && !tieneExistente) {
const meta = this.archivosAlta.find(a => a.campo === campo);
docsFaltantes.push('• ' + (meta ? meta.label : campo));
marcar(campo);
}
});
if (docsFaltantes.length) {
faltantes.push('Documentación obligatoria:');
faltantes.push(docsFaltantes.join('<br>'));
}
} else if (this.formato === 2) {
if (!f.id_personal) { faltantes.push('• Nombre del empleado'); marcar('id_personal'); }
if (!f.fecha_baja) { faltantes.push('• Fecha de aplicación de baja'); marcar('fecha_baja'); }
if (!f.motivo) { faltantes.push('• Causa de la baja'); marcar('motivo'); }
} else if (this.formato === 3) {
if (!f.id_personal) { faltantes.push('• Nombre del colaborador'); marcar('id_personal'); }
if (!f.dias_falta) { faltantes.push('• Día faltante'); marcar('dias_falta'); }
} else if (this.formato === 4) {
if (!f.id_personal) { faltantes.push('• Nombre del empleado'); marcar('id_personal'); }
if (!f.id_estacion_cambio) { faltantes.push('• Cambio a'); marcar('id_estacion_cambio'); }
if (!f.fecha) { faltantes.push('• Fecha de aplicación'); marcar('fecha'); }
} else if (this.formato === 5) {
if (!f.id_personal) { faltantes.push('• Nombre del empleado'); marcar('id_personal'); }
if (f.salario_actual === '' || f.salario_actual === null) { faltantes.push('• Salario actual'); marcar('salario_actual'); }
if (f.salario_ajustado === '' || f.salario_ajustado === null) { faltantes.push('• Ajuste a'); marcar('salario_ajustado'); }
if (!f.fecha_aplicacion) { faltantes.push('• Aplicar a partir del'); marcar('fecha_aplicacion'); }
}

if (faltantes.length === 0) return '';
return 'Completa los campos obligatorios:<br>' + faltantes.join('<br>');
},

async agregarFila() {
if (this.guardandoFila) return;

const error = this.filaValida();
if (error) {
if (window.Notify) Notify.error(error);
return;
}

const id = String(this.detalleId || '');

const fd = new FormData();
fd.append('formato', this.formato);
fd.append('id', id);

Object.keys(this.fila).forEach((k) => {
if (k === 'archivos') return;
let val = this.fila[k];
if (val === null || val === undefined) val = '';
fd.append(k, val);
});

if (this.formato === 1 && this.fila.archivos) {
Object.keys(this.fila.archivos).forEach((campo) => {
const file = this.fila.archivos[campo];
if (file) fd.append('archivo_' + campo, file);
});
} 

this.guardandoFila = true;
if (window.loader) window.loader.show();
try {
const resp = await fetch('/departamento-operativo/recursos-humanos/formatos/agregar-fila', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
this.filas.push(json.fila || this.fila);
this.cerrarModal();
this.showAlert('success', 'Correcto', json.message || 'Empleado agregado correctamente');
if (window.Notify) Notify.success(json.message || 'Empleado agregado correctamente');
} else {
this.showAlert('error', 'Error', json.message || 'Error al agregar el empleado');
if (window.Notify) Notify.error(json.message || 'Error al agregar el empleado');
}
} catch (err) {
console.error('Error al agregar empleado:', err);
this.showAlert('error', 'Error', 'Error al agregar el empleado');
if (window.Notify) Notify.error('Error al agregar el empleado');
} finally {
this.guardandoFila = false;
if (window.loader) window.loader.hide();
}
},

nombrePersonal(id) {
const p = this.personal.find(x => x.id === Number(id));
return p ? p.nombre_completo : '—';
},

puestoNombre(id) {
const p = this.puestos.find(x => x.id === Number(id));
return p ? p.puesto : '—';
},

puestoPersonal(id) {
const p = this.personal.find(x => x.id === Number(id));
if (p && p.puesto) {
const pu = this.puestos.find(x => x.id === Number(p.puesto));
if (pu) return pu.puesto;
}
return '—';
},

estacionNombre(id) {
const e = this.estaciones.find(x => x.id === Number(id));
return e ? e.nombre : '—';
},

fechaIngresoSeleccionado() {
const p = this.personal.find(x => x.id === Number(this.valores.id_personal));
return p ? (p.fecha_ingreso || '') : '';
},

archivosCount(fila) {
let n = 0;
if (fila.archivos) {
Object.keys(fila.archivos).forEach(function (k) {
if (fila.archivos[k]) n++;
});
}
if (fila._existe) {
Object.keys(fila._existe).forEach(function (k) {
if (fila._existe[k]) n++;
});
}
return n;
},

verArchivosEmpleado(fila) {
const meta = {};
(this.archivosAlta || []).forEach(function (a) { meta[a.campo] = a.label; });
const list = [];
if (fila._existe) {
Object.keys(fila._existe).forEach((campo) => {
const ruta = fila._existe[campo];
if (!ruta) return;
list.push({ campo: campo, label: meta[campo] || campo, archivo: ruta.split('/').pop(), ruta: ruta });
});
}
if (fila.archivos) {
Object.keys(fila.archivos).forEach((campo) => {
const file = fila.archivos[campo];
if (!file) return;
list.push({ campo: campo, label: meta[campo] || campo, archivo: file.name || campo, ruta: '' });
});
}
this.archivosEmpleado = list;
this.archivosEmpleadoNombre = fila.nombre || 'Empleado';
const m = document.getElementById('modalArchivosEmpleado');
if (m) bootstrap.Modal.getOrCreateInstance(m).show();
},

descargarArchivoEmpleado(item) {
if (!item.ruta) {
if (window.Notify) Notify.error('El archivo estará disponible al guardar el empleado');
return;
}
this.download('formatos-alta', item.archivo);
},

validarFormatoSimple() {
this.$el.querySelectorAll('.is-invalid').forEach(function (el) {
el.classList.remove('is-invalid');
});
const valor = (nombre) => {
const el = document.querySelector('[name="' + nombre + '"]');
return el ? el.value : '';
};
const marcar = (nombre) => {
const el = document.querySelector('[name="' + nombre + '"]');
if (!el) return;
const wrapper = el.closest('.select2-modal-field') || el;
wrapper.classList.add('is-invalid');
};
const faltantes = [];

if (this.formato === 6) {
if (!valor('id_personal')) { faltantes.push('• Empleado'); marcar('id_personal'); }
if (Number(valor('num_dias')) <= 0) { faltantes.push('• Número de días a disfrutar'); marcar('num_dias'); }
if (!valor('fecha_inicio')) { faltantes.push('• Fecha inicial (Del)'); marcar('fecha_inicio'); }
if (!valor('fecha_termino')) { faltantes.push('• Fecha final (Al)'); marcar('fecha_termino'); }
if (!valor('fecha_regreso')) { faltantes.push('• Fecha de regreso'); marcar('fecha_regreso'); }
} else if (this.formato === 7) {
if (!valor('id_personal')) { faltantes.push('• Colaborador'); marcar('id_personal'); }
if (Number(valor('periodo')) <= 0) { faltantes.push('• Periodo'); marcar('periodo'); }
}

if (faltantes.length === 0) return '';
return 'Completa los campos obligatorios:<br>' + faltantes.join('<br>');
},

async guardar() {
if (this.guardando) return;

if (this.formato <= 5 && this.filas.length === 0) {
if (window.Notify) Notify.error('Agrega al menos un empleado al formato');
return;
}

if (this.formato >= 6) {
const error = this.validarFormatoSimple();
if (error) {
if (window.Notify) Notify.error(error);
return;
}
}

if (!this.signaturePad || this.signaturePad.isEmpty()) {
if (window.Notify) Notify.error('Es obligatorio capturar la firma de quien elabora');
return;
}

this.guardando = true;

const url = '/departamento-operativo/recursos-humanos/formatos/update';

const fd = new FormData();
fd.append('formato', this.formato);
fd.append('id', this.detalleId);
fd.append('id_localidad', this.idLocalidad);

if (this.formato >= 6) {
const campo = (name) => {
const el = document.querySelector('#card-formato [name="' + name + '"]');
return el ? el.value : '';
};
fd.append('id_personal', campo('id_personal'));
if (this.formato === 6) {
fd.append('num_dias', campo('num_dias'));
fd.append('fecha_inicio', campo('fecha_inicio'));
fd.append('fecha_termino', campo('fecha_termino'));
fd.append('fecha_regreso', campo('fecha_regreso'));
fd.append('observaciones', campo('observaciones'));
}
if (this.formato === 7) {
fd.append('periodo', this.valores.periodo || '');
}
}

if (this.signaturePad && !this.signaturePad.isEmpty()) {
fd.append('firma_elaboro', this.signaturePad.toDataURL());
}

if (window.loader) window.loader.show();
try {
const resp = await fetch(url, { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
this.showAlert('success', 'Correcto', json.message || 'Formato guardado correctamente');
if (window.Notify) Notify.success(json.message || 'Formato guardado');
setTimeout(() => {
window.location.href = '/departamento-operativo/recursos-humanos/formatos';
}, 1500);
} else {
this.showAlert('error', 'Error', json.message || 'Error al guardar el formato');
if (window.Notify) Notify.error(json.message || 'Error al guardar el formato');
}
} catch (err) {
console.error('Error al guardar formato:', err);
this.showAlert('error', 'Error', 'Error al guardar el formato');
if (window.Notify) Notify.error('Error al guardar el formato');
} finally {
if (window.loader) window.loader.hide();
this.guardando = false;
}
}
}));

});
