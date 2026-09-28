document.addEventListener('alpine:init', () => {

Alpine.data('pedidoAditivoPedidoComponent', () => ({

puedeAcceso: false,
puedeEditar: false,
puedeEliminar: false,
puedeDescargar: false,
puedeFirmar: false,
esEncargado: false,

solicitudActual: null,
form: { fecha: '', para: '', fecha_entrega: '', comentarios: '' },
nuevoTambo: { producto: '', cantidad: 1 },
finalizando: false,
guardandoDatos: false,
repetirGuardado: false,
guardandoTambo: false,

BASE: '/departamento-operativo/comercializadora/pedido-aditivo',

ADITIVOS: {
GASOLINA: { aditivo: 'HITEC 6590C Drum', kilogramo: 185 },
DIESEL: { aditivo: 'HITEC 4133G Drum', kilogramo: 180 }
},

init() {
const c = document.getElementById('container');
if (!c) return;

this.puedeAcceso = c.dataset.puedeAcceso === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeEliminar = c.dataset.puedeEliminar === 'true';
this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeFirmar = c.dataset.puedeFirmar === 'true';
this.esEncargado = c.dataset.esEncargado === 'true';

if (c.dataset.solicitud) {
try { this.solicitudActual = JSON.parse(c.dataset.solicitud); } catch (e) { console.error('Error parseando solicitud:', e); }
}

window.pedidoAditivoPedidoComponentInstance = this;

this.sincronizarForm();
},

sincronizarForm() {
if (!this.solicitudActual) return;
this.form = {
fecha: this.solicitudActual.fecha_iso || '',
para: this.solicitudActual.para || '',
fecha_entrega: this.solicitudActual.fecha_entrega_iso || '',
comentarios: this.solicitudActual.comentarios || ''
};
},

async cargarSolicitud() {
if (!this.solicitudActual) return;
try {
const resp = await axios.get(this.BASE + '/detalle?id=' + this.solicitudActual.id);
const json = resp.data;
if (json.success && json.solicitud) {
this.solicitudActual = json.solicitud;
if (json.solicitud.status === 0) {
this.sincronizarForm();
}
}
} catch (e) {
console.error('Error cargando solicitud:', e);
}
},

statusBadgeClass(status) {
if (status === 0) return 'bg-danger';
if (status === 1) return 'bg-warning-subtle text-warning-emphasis';
return 'bg-success-subtle text-success-emphasis';
},

/* ================= DATOS ================= */

async guardarDatos() {
if (this.guardandoDatos) { this.repetirGuardado = true; return; }

if (!this.form.fecha) {
if (window.Notify) Notify.error('La fecha es obligatoria');
return;
}
if (!this.form.para) {
if (window.Notify) Notify.error('Debe seleccionar la razón social');
return;
}

this.guardandoDatos = true;
try {
const fd = new FormData();
fd.append('id', this.solicitudActual.id);
fd.append('fecha', this.form.fecha);
fd.append('para', this.form.para);
fd.append('fecha_entrega', this.form.fecha_entrega || '');
fd.append('comentarios', this.form.comentarios || '');
const resp = await axios.post(this.BASE + '/guardar-datos', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Datos guardados correctamente');
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al guardar los datos');
}
} catch (e) {
console.error('Error guardando datos:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al guardar los datos');
} finally {
this.guardandoDatos = false;
if (this.repetirGuardado) {
this.repetirGuardado = false;
this.guardarDatos();
}
}
},

/* ================= TAMBOS ================= */

modalAgregarTambo() {
this.nuevoTambo = { producto: '', cantidad: 1 };
const m = document.getElementById('modalAgregarTambo');
bootstrap.Modal.getOrCreateInstance(m).show();
},

async agregarTambo() {
if (this.guardandoTambo) return;

const producto = this.nuevoTambo.producto || '';
const cantidad = parseInt(this.nuevoTambo.cantidad || '0', 10);

if (!producto) {
if (window.Notify) Notify.error('Selecciona un producto');
return;
}
if (!cantidad || cantidad <= 0) {
if (window.Notify) Notify.error('Ingresa una cantidad de tambos válida');
return;
}

this.guardandoTambo = true;
try {
const fd = new FormData();
fd.append('id_reporte', this.solicitudActual.id);
fd.append('producto', producto);
fd.append('cantidad', cantidad);
const resp = await axios.post(this.BASE + '/agregar-tambo', fd);
const json = resp.data;
if (json.success) {
const m = document.getElementById('modalAgregarTambo');
bootstrap.Modal.getOrCreateInstance(m).hide();
this.nuevoTambo = { producto: '', cantidad: 1 };
if (window.Notify) Notify.success(json.message || 'Tambo agregado correctamente');
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al agregar el tambo');
}
} catch (e) {
console.error('Error agregando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al agregar el tambo');
} finally {
this.guardandoTambo = false;
}
},

cambiarProducto(it) {
const item = this.ADITIVOS[it.producto];
if (!item) return;
it.aditivo = item.aditivo;
it.kilogramo = item.kilogramo;
this.editarTamboRow(it);
},

editarTamboCantidad(id, valor) {
const cantidad = parseInt(valor || '0', 10);
if (!cantidad || cantidad <= 0) {
if (window.Notify) Notify.error('La cantidad debe ser mayor a cero');
this.cargarSolicitud();
return;
}
const it = (this.solicitudActual.tambos || []).find(function (t) { return t.id === id; });
if (!it) return;
it.cantidad = cantidad;
this.editarTamboRow(it);
},

async editarTamboRow(it) {
try {
const fd = new FormData();
fd.append('id', it.id);
fd.append('cantidad', it.cantidad);
fd.append('producto', it.producto || '');
const resp = await axios.post(this.BASE + '/editar-tambo', fd);
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Tambo actualizado correctamente');
} else {
if (window.Notify) Notify.error(json.message || 'Error al actualizar el tambo');
}
await this.cargarSolicitud();
} catch (e) {
console.error('Error editando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al actualizar el tambo');
}
},

async eliminarTambo(id) {
const result = await Swal.fire({
title: '¿Eliminar Registro?',
text: 'El tambo será eliminado de la solicitud',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, eliminar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#d33'
});
if (!result.isConfirmed) return;

try {
const resp = await axios.post(this.BASE + '/eliminar-tambo', { id: id });
const json = resp.data;
if (json.success) {
if (window.Notify) Notify.success(json.message || 'Tambo eliminado correctamente');
await this.cargarSolicitud();
} else {
if (window.Notify) Notify.error(json.message || 'Error al eliminar el tambo');
}
} catch (e) {
console.error('Error eliminando tambo:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al eliminar el tambo');
}
},

/* ================= FINALIZAR ================= */

async finalizarSolicitud() {
if (this.finalizando) return;

if (!this.solicitudActual.tambos || !this.solicitudActual.tambos.length) {
if (window.Notify) Notify.error('Agrega al menos un tambo a la solicitud');
return;
}

const confirm = await Swal.fire({
title: '¿Finalizar solicitud?',
text: 'La solicitud pasará a espera de la firma de Autorización',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar'
});
if (!confirm.isConfirmed) return;

this.finalizando = true;
try {
const resp = await axios.post(this.BASE + '/finalizar', { id: this.solicitudActual.id });
const json = resp.data;
if (json.success) {
Swal.fire({
icon: 'success',
title: 'Solicitud finalizada',
text: json.message || 'Solicitud finalizada correctamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
history.back();
} else {
window.location.href = this.BASE;
}
});
} else {
if (window.Notify) Notify.error(json.message || 'Error al finalizar la solicitud');
}
} catch (e) {
console.error('Error finalizando solicitud:', e);
if (window.Notify) Notify.error(e.response?.data?.message || 'Error al finalizar la solicitud');
} finally {
this.finalizando = false;
}
},

}));

});