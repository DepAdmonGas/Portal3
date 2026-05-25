document.addEventListener('alpine:init', () => {

Alpine.data('yearMesComponent', () => ({
year: null,
mes: null,
init() {
const container = document.getElementById('container');
if (container) {
this.year = container.dataset.year || null;
this.mes = container.dataset.mes || null;
}
},
cambiarYearMes(year, mes) {
const url = '/departamento-operativo/corporativo/corte-diario/' + year + '/' + mes;
history.replaceState({ year, mes }, '', url);
location.reload();
}
}));

Alpine.data('editarCorteComponent', () => ({
idCorteDia: null,
fecha: '',
step: 'history',
historial: [],
motivo: '',
loading: false,
saving: false,

init() {
const modal = document.getElementById('modalEditarCorte');
if (modal) {
modal.addEventListener('hidden.bs.modal', () => this.resetForm());
}

document.addEventListener('click', (e) => {
const btn = e.target.closest('.btn-edit-corte');
if (btn) {
e.preventDefault();
const id = parseInt(btn.dataset.id);
const fecha = btn.dataset.fecha.split(' ')[0];
if (id && fecha) {
this.openEdit(id, fecha);
}
}
});
},

openEdit(idCorteDia, fecha) {
this.idCorteDia = idCorteDia;
this.fecha = fecha;
this.step = 'history';
this.motivo = '';
this.loading = true;

const modalEl = document.getElementById('modalEditarCorte');
if (!modalEl) return;
const modal = new bootstrap.Modal(modalEl);
modal.show();

axios.get('/departamento-operativo/corporativo/corte-diario/historial', {
params: { id: idCorteDia }
}).then(response => {
if (response.data.success) {
this.historial = response.data.data || [];
} else {
this.historial = [];
}
}).catch(() => {
this.historial = [];
}).finally(() => {
this.loading = false;
});
},

submitActivacion() {
if (!this.motivo.trim()) {
this.notify('error', 'El motivo es obligatorio');
return;
}

this.saving = true;

axios.post('/departamento-operativo/corporativo/corte-diario/activar', {
id: this.idCorteDia,
detalle: this.motivo
}, {
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
})
.then(response => {
if (response.data.success) {
this.notify('success', response.data.message);
this.step = 'history';
this.motivo = '';
this.reloadHistorial();
$('#table-corte-diario').DataTable().ajax.reload();
} else {
this.notify('error', response.data.message);
}
})
.catch(() => {
this.notify('error', 'Error al activar el corte');
})
.finally(() => this.saving = false);
},

reloadHistorial() {
axios.get('/departamento-operativo/corporativo/corte-diario/historial', {
params: { id: this.idCorteDia }
}).then(response => {
if (response.data.success) {
this.historial = response.data.data || [];
}
}).catch(() => {});
},

resetForm() {
this.idCorteDia = null;
this.fecha = '';
this.step = 'history';
this.historial = [];
this.motivo = '';
this.saving = false;
this.loading = false;
},

notify(type, message) {
if (typeof Swal !== 'undefined' && type === 'success') {
Swal.fire({ icon: 'success', title: 'Corte Diario', text: message, timer: 2000, showConfirmButton: false });
} else if (typeof Swal !== 'undefined' && type === 'error') {
Swal.fire({ icon: 'error', title: 'Error', text: message });
} else if (window.Notify && window.Notify[type]) {
window.Notify[type](message);
}
}
}));

});



