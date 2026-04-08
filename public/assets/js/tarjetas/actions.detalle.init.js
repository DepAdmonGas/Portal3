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
const result = await Swal.fire({
title: '¿Actualizar seguimiento?',
text: `El No. de solicitud: ${this.solicitud} sera actualizado`,
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
no_reporte: this.solicitud,
idSeguimiento: noSeguimiento
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

//---------- FUNCIONALIDAD DE COMENTARIO ----------//
async submitComentario() {

if (this.loadingComentario) return;

const input = document.getElementById('comentarioInput');
const comentario = input ? input.value.trim() : '';

// 🔥 VALIDACIÓN
this.errors.comentario = !comentario;

if (this.errors.comentario) {
this.notify('error', 'El comentario es obligatorio');
return;
}

// 🔥 CONFIRMACIÓN
const result = await Swal.fire({
title: '¿Guardar comentario?',
text: `Se actualizará el comentario de la solicitud: ${this.solicitud}`,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, guardar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#198754'
});

if (!result.isConfirmed) return;

this.loadingComentario = true;

const payload = {
id_estacion: this.estacion,
no_solicitud: this.solicitud,
comentarios: comentario
};

try {

const res = await this.createAction({
url: '/solicitud-tarjetas/comentarios/update',
data: payload
});

if (res && res.success) {

}

} catch (error) {
this.notify('error', 'Error al guardar comentario');
}

this.loadingComentario = false;
}

}));

});


