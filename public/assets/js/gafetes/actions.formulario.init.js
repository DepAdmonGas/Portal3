document.addEventListener('alpine:init', () => {
 
const container = document.getElementById('container');

if (container) {
const { estacion, reporte, puesto } = container.dataset;
cargarTimeline(estacion, reporte, Number(puesto));
}

function cargarTimeline(idEstacion, noReporte, idPuesto) {

fetch(`/solicitud-gafetes/seguimiento/timeline/${idEstacion}/${noReporte}`)
.then(r => r.json())
.then(({ data }) => {

const map = Object.fromEntries(
data.map(i => [i.seguimiento, i])
);

actualizarBotonSeguimiento(map, idPuesto);

})
.catch(error => {
console.error('Error timeline:', error);
});

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


/*
================================
FORMULARIO GAFETES
================================
*/

const containerModal = document.getElementById('nuevo');
const idEstacion = containerModal.dataset.estacion;
const noReporte = containerModal.dataset.reporte;

Alpine.data('gafetesForm', () => ({

clave: '',
nombre_g: '',
foto: null,
loading: false,

errors: {
clave: false,
nombre_g: false,
foto: false
},

handleFile(e) {
this.foto = e.target.files[0];
this.errors.foto = false;
},

validate() {

this.errors.clave = !this.clave;
this.errors.nombre_g = !this.nombre_g;
this.errors.foto = !this.foto;

if (
this.errors.clave ||
this.errors.nombre_g ||
this.errors.foto
) {
this.notify(
'error',
'Completa los campos obligatorios'
);
return false;
}

return true;

},

resetForm() {

this.clave = '';
this.nombre_g = '';
this.foto = null;

this.errors = {
clave: false,
nombre_g: false,
foto: false
};

if (this.$refs.foto) {
this.$refs.foto.value = null;
}

},

async submit() {

if (!this.validate()) return;

let formData = new FormData();

formData.append('no_reporte', noReporte);
formData.append('idEstacion', idEstacion);
formData.append('clave', this.clave);
formData.append('nombre_g', this.nombre_g);
formData.append('foto', this.foto);

try {

const res = await this.createAction({
url: '/solicitud-gafetes/create-reporte-formulario',
data: formData,
table: '#table-gafetes-formulario'
});

if (res && res.success) {

const modalEl =
document.getElementById('nuevo');

document.activeElement.blur();

modalEl.addEventListener(
'hidden.bs.modal',
() => {

this.resetForm();

},
{ once: true }
);

const modal =
bootstrap.Modal.getInstance(modalEl);

if (modal) {
modal.hide();
}

}

} catch (error) {

this.notify(
'error',
'Error al guardar'
);

}

}

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

async submitSeguimiento(noSeguimiento) {

if (this.loadingSeguimiento) return;
if (!this.validate(noSeguimiento)) return;

const result = await Swal.fire({
title: '¿Finalizar solicitud?',
text: `El No. de solicitud: ${this.reporte} será finalizado`,
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
no_reporte: this.reporte,
idSeguimiento: noSeguimiento
};

try {

const res = await this.createAction({
url: '/solicitud-gafetes/seguimiento/update',
data: payload
});

if (res && res.success) {

await Swal.fire({
icon: 'success',
title: 'Proceso completado',
text: 'La solicitud ha finalizado correctamente',
confirmButtonColor: '#198754'
});

window.location.href = '/solicitud-gafetes';

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