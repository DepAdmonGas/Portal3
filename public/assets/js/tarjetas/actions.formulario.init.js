document.addEventListener('alpine:init', () => {

const container = document.getElementById('container');

if (container) {
const { estacion, seguimiento, puesto } = container.dataset;
cargarTimeline(estacion, seguimiento, Number(puesto));
}

function cargarTimeline(idEstacion, noSeguimiento, idPuesto) {
fetch(`/solicitud-tarjetas/seguimiento/timeline/${idEstacion}/${noSeguimiento}`)
.then(r => r.json())
.then(({ data }) => {

const map = Object.fromEntries(
data.map(i => [i.seguimiento, i])
);
 
actualizarBotonSeguimiento(map, idPuesto);
});

fetch(`/solicitud-tarjetas/archivo/${idEstacion}/${noSeguimiento}`)
.then(r => r.json())
.then(({ archivo }) => {
actualizarBotonArchivo(archivo);
})
.catch(err => console.error('Error archivo:', err));
}


function actualizarBotonSeguimiento(map, idPuesto) {
const pasosExistentes = Object.keys(map)
.map(Number)
.sort((a, b) => a - b);

const ultimoPaso = pasosExistentes.at(-1) || 0;
const siguiente = ultimoPaso + 1;
const contenedor = document.getElementById('botonSeguimiento');

if (!contenedor) return;

if (ultimoPaso >= 3) {
contenedor.innerHTML = `<li><a class="dropdown-item"> <i class="ti ti-circle-check"></i> Proceso completado</a></li>`;
return;
}

let ocultarBoton = "d-none";

if (siguiente === 1 && idPuesto === 6) {
ocultarBoton = "";
}

contenedor.innerHTML = `
<li class="${ocultarBoton}"
:disabled="loadingSeguimiento"
@click="submitSeguimiento(${siguiente})">
<a class="dropdown-item"> <i class="ti ti-check"></i> Finalizar</a>
</li>
`;
}

function actualizarBotonArchivo(archivo) {

const contenedor = document.getElementById('botonDescargaFile');
if (!contenedor) return;

contenedor.innerHTML = '';
const noDesc = !archivo;

contenedor.innerHTML = `
<li>
<a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-1 ${noDesc ? 'disabled' : ''}" ${noDesc ? '' : `@click="download('basico','${archivo}')"`}>
<i class="ti ti-file-download"></i> Descargar archivo
</a>
</li>`;
}

/*
================================
FORMULARIO TARJETAS
================================
*/

Alpine.data('tarjetasForm', () => ({

mode: 'create',
id: null,
razon_social: '',
nombre_usuario: '',
vehiculo: '',
placas: '',
no_unidad: '',
tarjeta: '',
tipo_tarjeta: '',
loading: false,

errors: {
razon_social: false,
nombre_usuario: false,
vehiculo: false,
placas: false,
no_unidad: false,
tarjeta: false,
tipo_tarjeta: false
},

init() {
const containerModal = document.getElementById('nuevo');

if (containerModal) {
this.idEstacionModal = containerModal.dataset.estacionModal || null;
this.noSolicitud = containerModal.dataset.solicitud || null;
}
},

//---------- MODAL PARA EDITAR EL CONTENIDO ---------//
openEdit(data) {

this.mode = 'edit';
this.id = data.id;
this.razon_social = data.razon_social ?? '';
this.nombre_usuario = data.no_flotilla ?? '';
this.vehiculo = data.vehiculo ?? '';
this.placas = data.placas ?? '';
this.no_unidad = data.no_unidad ?? '';
this.tarjeta = data.tarjeta ?? '';
this.tipo_tarjeta = data.tipo_tarjeta ?? '';

const modalEl = document.getElementById('nuevo');
if (modalEl) {
const modal = new bootstrap.Modal(modalEl);
modal.show();
}
},

// VALIDACIÓN
validate() {
    
this.errors.razon_social = !this.razon_social;
this.errors.nombre_usuario = !this.nombre_usuario;
this.errors.vehiculo = !this.vehiculo;
this.errors.placas = !this.placas;
this.errors.no_unidad = !this.no_unidad;
this.errors.tarjeta = !this.tarjeta;
this.errors.tipo_tarjeta = !this.tipo_tarjeta;

if (this.errors.razon_social || this.errors.nombre_usuario || this.errors.vehiculo || this.errors.placas || this.errors.no_unidad || this.errors.tarjeta || this.errors.tipo_tarjeta) {
this.notify('error', 'Completa los campos obligatorios');
return false;
}

return true;
},

// RESET
resetForm() {
this.mode = 'create';
this.razon_social = '';
this.nombre_usuario = '';
this.vehiculo = null;
this.placas = '';
this.no_unidad = '';
this.tarjeta = null;
this.tipo_tarjeta = '';

this.errors = {
razon_social: false,
nombre_usuario: false,
vehiculo: false,
placas: false,
no_unidad: false,
tarjeta: false,
tipo_tarjeta: false
};

},

resetModal(){

const modalEl = document.getElementById('nuevo');

// evento al cerrar completamente
modalEl.addEventListener('hidden.bs.modal', () => {
this.resetForm();
document.body.focus(); // opcional (mejora accesibilidad)
}, { once: true });

// quitar foco ANTES de cerrar
if (document.activeElement) {
document.activeElement.blur();
}

const modal = bootstrap.Modal.getInstance(modalEl);

if (modal) {
modal.hide();
}

},

// SUBMIT
async submit() {

if (!this.validate()) return;

let payload = {};
let url = '';

if (this.mode === 'create') {

url = '/solicitud-tarjetas/create-reporte-formulario';
payload = {
no_solicitud: this.noSolicitud,
idEstacion: this.idEstacionModal,
razon_social: this.razon_social,
nombre_usuario: this.nombre_usuario,
vehiculo: this.vehiculo,
placas: this.placas,
no_unidad: this.no_unidad,
tarjeta: this.tarjeta,
tipo_tarjeta: this.tipo_tarjeta
};

} else {

url = '/solicitud-tarjetas/update-reporte-formulario';
payload = {
id: this.id,
razon_social: this.razon_social,
nombre_usuario: this.nombre_usuario,
vehiculo: this.vehiculo,
placas: this.placas,
no_unidad: this.no_unidad,
tarjeta: this.tarjeta,
tipo_tarjeta: this.tipo_tarjeta
};

} 

try {

const res = await this.createAction({
url,
data: payload,
table: '#table-tarjetas-formulario'
});


if (res && res.success) {
this.resetModal();                
}

} catch (error) {
this.notify('error', 'Error al guardar');
}

},

}));

});


/*
================================
ACTUALIZAR SEGUIMIENTO
================================
*/

document.addEventListener('alpine:init', () => {

Alpine.data('seguimientoForm', () => ({

loadingSeguimiento: false,

estacion: null,
seguimiento: null,
puesto: null,

errors: {
seguimiento: false
},

init() {
const container = document.getElementById('container');

if (container) {
this.estacion = container.dataset.estacion || null;
this.seguimiento = container.dataset.seguimiento || null;
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

async submitSeguimiento(noSeguimiento) {

if (this.loadingSeguimiento) return;
if (!this.validate(noSeguimiento)) return;

const result = await Swal.fire({
title: '¿Finalizar solicitud?',
text: `El No. de solicitud: ${this.seguimiento} será finalizado`,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar',
confirmButtonColor: '#198754'
});

if (!result.isConfirmed) return;

this.loadingSeguimiento = true;

const payload = {
idEstacion: this.estacion,
no_reporte: this.seguimiento,
idSeguimiento: noSeguimiento
};

try {

const res = await this.createAction({
url: '/solicitud-tarjetas/seguimiento/update',
data: payload
});

if (res && res.success) {

await Swal.fire({
icon: 'success',
title: 'Proceso completado',
text: 'La solicitud ha finalizado correctamente',
confirmButtonColor: '#198754'
});

window.location.href = '/solicitud-tarjetas';

return;
}

} catch (error) {

console.error(error);
this.notify('error', 'Error al guardar');

}

this.loadingSeguimiento = false;

}

}));

});
