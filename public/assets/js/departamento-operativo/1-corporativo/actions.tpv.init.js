document.addEventListener('alpine:init', () => {

Alpine.data('tpvComponent', () => ({
idDia: null,
idYear: null,
idMes: null,
estado: 0,
multiestacion: false,
puedeEditar: false,
puedeCrear: false,
finalizado: false,
loading: true,
saving: false,

cierres: {},

get empresasList() {
return Object.keys(this.cierres);
},

init() {
const c = document.getElementById('container');
if (!c) return;
this.idDia = parseInt(c.dataset.idDia);
this.idYear = parseInt(c.dataset.idYear);
this.idMes = parseInt(c.dataset.idMes);
this.estado = parseInt(c.dataset.estado);
this.multiestacion = c.dataset.multiestacion === 'true';
this.puedeEditar = c.dataset.puedeEditar === 'true';
this.puedeCrear = c.dataset.puedeCrear === 'true';
this.finalizado = this.estado === 1;
this.cargarDatos();
},

async cargarDatos(showLoader = true) {
if (showLoader) this.loading = true;
try {
const resp = await fetch('/departamento-operativo/cierre-lote/data/' + this.idDia);
const json = await resp.json();
if (json.success) {
this.estado = json.estado;
this.finalizado = this.estado === 1;

const agrupados = json.cierres || {};
const order = this.getCompanyOrder();
this.cierres = {};
for (const emp of order) {
if (agrupados[emp]) {
this.cierres[emp] = agrupados[emp];
} else {
this.cierres[emp] = [];
}
}
}
} catch (e) {
console.error('Error cargando datos TPV:', e);
this.notify('Error al cargar datos', 'error');
} finally {
this.loading = false;
}
},

getCompanyOrder() {
const container = document.getElementById('container');
const estacion = container ? parseInt(container.dataset.idEstacion) || 1 : 1;
const base = ['TICKETCARD', 'AMERICAN EXPRESS', 'G500 FLETT', 'BBVA BANCOMER SA', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'INBURSA'];
const extra = [];
if (estacion === 2 || estacion === 14) extra.push('SHELL FLEET NAVIGATOR');
if (estacion === 3) { extra.push('ULTRAGAS'); extra.push('ENERGEX'); }
if (estacion === 14) extra.push('SANTANDER');
return base.concat(extra);
},

totalesEmpresa(empresa) {
const items = this.cierres[empresa] || [];
let ti = 0, tt = 0;
items.forEach(item => {
ti += parseFloat(item.importe) || 0;
tt += parseInt(item.ticktes) || 0;
});
return { total_importe: ti, total_ticket: tt };
},

formatNumber(v) {
return parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

async agregarCierre(empresa) {
if (this.saving) return;
this.saving = true;
try {
const resp = await fetch('/departamento-operativo/cierre-lote/crear', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: this.idDia, empresa: empresa })
});
const json = await resp.json();
if (json.success && json.data) {
const items = this.cierres[empresa] || [];
this.cierres[empresa] = [...items, json.data];
} else {
this.notify(json.message || 'Error al agregar', 'error');
}
} catch (e) {
this.notify('Error de conexión', 'error');
} finally {
this.saving = false;
}
},

async editarCierre(id, field, value) {
try {
const resp = await fetch('/departamento-operativo/cierre-lote/editar', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: id, field: field, value: value })
});
const json = await resp.json();
if (!json.success) {
this.notify(json.message || 'Error al guardar', 'error');
}
} catch (e) {
this.notify('Error de conexión', 'error');
}
},

async togglePendiente(id, empresa, estado) {
const label = estado === 1 ? 'Pendiente' : 'Activo';
if (typeof Swal !== 'undefined') {
const result = await Swal.fire({
title: 'Estado del lote',
text: '¿Desea cambiar el estado a ' + label + ' del cierre de lote seleccionado?',
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Aceptar',
cancelButtonText: 'Cancelar',
});
if (!result.isConfirmed) return;
} else {
if (!confirm('¿Desea cambiar el estado a ' + label + ' del cierre de lote seleccionado?')) return;
}

try {
const resp = await fetch('/departamento-operativo/cierre-lote/pendiente', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id: id, estado: estado })
});
const json = await resp.json();
if (json.success) {
const items = this.cierres[empresa] || [];
const idx = items.findIndex(i => i.id === id);
if (idx !== -1) {
items[idx].estado = estado;
}
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: 'success', title: label, text: 'Estado actualizado', timer: 1500, showConfirmButton: false });
} else {
this.notify('Estado actualizado a ' + label, 'success');
}
} else {
this.notify(json.message || 'Error al actualizar', 'error');
}
} catch (e) {
this.notify('Error de conexión', 'error');
}
},

notify(msg, type) {
if (typeof Swal !== 'undefined') {
Swal.fire({ icon: type === 'error' ? 'error' : 'success', title: msg, timer: 2000, showConfirmButton: false });
} else if (window.Notify) {
window.Notify(msg, type === 'error' ? 'error' : 'success');
}
},

}));
});