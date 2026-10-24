document.addEventListener('alpine:init', () => {

Alpine.data('seguimientoForm', () => ({

loadingSeguimiento: false,

estacion: null,
reporte: null,
puesto: null,

errors: {
seguimiento: false
},

init() {
const container = document.getElementById('container');

if (container) {
this.estacion = container.dataset.estacion || null;
this.reporte = container.dataset.reporte || null;
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
const esFinalizacion = Number(noSeguimiento) === 2;
const result = await Swal.fire({
title: '¿Actualizar estatus?',
text: esFinalizacion
? `La solicitud #${this.reporte} cambiará de En proceso a Finalizada.`
: Number(noSeguimiento) === 1
? `La solicitud #${this.reporte} cambiará de Pendiente a En proceso.`
: `La solicitud #${this.reporte} cambiará de Finalizada a Entregada.`,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, actualizar estatus',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#198754',
...(esFinalizacion ? {
input: 'textarea',
inputPlaceholder: 'Comentario (opcional)',
inputAttributes: { maxlength: 2000, 'aria-label': 'Comentario de finalización' }
} : {})
});

if (!result.isConfirmed) return;

this.loadingSeguimiento = true;

const payload = {
idEstacion: this.estacion,
no_reporte: this.reporte,
idSeguimiento: noSeguimiento,
...(esFinalizacion ? { comentario: result.value || '' } : {})
};

try {

const res = await this.createAction({
url: '/solicitud-gafetes/seguimiento/update',
data: payload
});

if (res && res.success) {

if (typeof cargarTimeline === 'function') {
cargarTimeline(this.estacion, this.reporte, this.puesto);
}

this.reset();

} 

} catch (error) {
console.error(error);
this.notify('error', 'Error al guardar');
}

this.loadingSeguimiento = false;
},

}));

});
