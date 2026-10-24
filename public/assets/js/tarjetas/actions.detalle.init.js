document.addEventListener('alpine:init', () => {

Alpine.data('seguimientoForm', () => ({

loadingSeguimiento: false,

estacion: null,
solicitud: null,
puesto: null,

errors: {
seguimiento: false,
comentario: false
},

init() {
const container = document.getElementById('container');

if (container) {
this.estacion = container.dataset.estacion || null;
this.solicitud = container.dataset.solicitud || null;
this.puesto = container.dataset.puesto || null;
}
},

// VALIDACIÓN
validate(noSeguimiento) {

this.errors.seguimiento = !noSeguimiento;

if (this.errors.seguimiento) {
this.notify('error', 'Seguimiento inválido');
return false;
}

return true;
},

reset() {
this.errors.seguimiento = false;
},

// SUBMIT CON CONFIRMACIÓN
async submitSeguimiento(noSeguimiento) {

if (this.loadingSeguimiento) return;
if (!this.validate(noSeguimiento)) return;

// 🔥 CONFIRMACIÓN DIRECTA
const actual = ({1: 'Pendiente', 2: 'En proceso', 3: 'Finalizada'})[noSeguimiento];
const siguiente = ({1: 'En proceso', 2: 'Finalizada', 3: 'Entrega finalizada'})[noSeguimiento];
const config = {
title: 'Actualizar estatus',
text: `La solicitud cambiará de ${actual} a ${siguiente}.`,
icon: 'question',
showCancelButton: true,
confirmButtonText: 'Sí, actualizar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#198754'
};
if (noSeguimiento === 2) {
config.input = 'textarea';
config.inputPlaceholder = 'Comentario (opcional)';
config.inputAttributes = { maxlength: 2000, 'aria-label': 'Comentario (opcional)' };
}
const result = await Swal.fire(config);

if (!result.isConfirmed) return;

this.loadingSeguimiento = true;

const payload = {
idEstacion: this.estacion,
no_reporte: this.solicitud,
idSeguimiento: noSeguimiento,
comentarios: noSeguimiento === 2 ? String(result.value || '').trim() : ''
};

try {

const res = await this.createAction({
url: '/solicitud-tarjetas/seguimiento/update',
data: payload
});

if (res && res.success) {

if (typeof cargarTimeline === 'function') {
cargarTimeline(this.estacion, this.solicitud, this.puesto);
}

this.reset();

} 

} catch (error) {
this.notify('error', 'Error al guardar');
}

this.loadingSeguimiento = false;
},

}));

});


