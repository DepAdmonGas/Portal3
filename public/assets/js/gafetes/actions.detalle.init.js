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
const result = await Swal.fire({
title: '¿Actualizar seguimiento?',
text: `El No. de solicitud: ${this.reporte} sera actualizado`,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, actualizar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#198754'
});

if (!result.isConfirmed) return;

this.loadingSeguimiento = true;

const payload = {
idEstacion: this.estacion,
no_reporte: this.reporte,
idSeguimiento: noSeguimiento
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
}

}));

});