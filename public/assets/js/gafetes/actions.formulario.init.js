document.addEventListener('alpine:init', () => {
 
const container = document.getElementById('container');

if (container) {
const { estacion, reporte, puesto } = container.dataset;
cargarTimeline(estacion, reporte, Number(puesto));
}

function cargarTimeline(idEstacion, noReporte, puesto) {

fetch(`/solicitud-gafetes/seguimiento/timeline/${idEstacion}/${noReporte}`)
.then(r => r.json())
.then(({ data }) => {

const map = Object.fromEntries(
data.map(i => [i.seguimiento, i])
);

actualizarBotonSeguimiento(map, puesto);

})
.catch(error => {
console.error('Error timeline:', error);
});

}

function actualizarBotonSeguimiento(map, puesto) {

const pasosExistentes = Object.keys(map)
.map(Number)
.sort((a, b) => a - b);

const ultimoPaso = pasosExistentes.at(-1) || 0;
const contenedor = document.getElementById('botonSeguimiento');

if (!contenedor) return;

if (ultimoPaso >= 3) {
contenedor.innerHTML = `<li><a class="dropdown-item pointer"> <i class="ti ti-circle-check"></i> Proceso completado</a></li>`;
return;
}

const siguiente = ultimoPaso + 1;
const puestoPermitido = siguiente === 2 ? [13, 1].includes(Number(puesto)) : [6, 7].includes(Number(puesto));
const ocultarBoton = puestoPermitido ? '' : 'd-none';
const texto = siguiente === 1 ? 'Iniciar proceso' : siguiente === 2 ? 'Finalizar proceso' : 'Finalizar entrega';

contenedor.innerHTML = `

<button class="btn bg-success text-white  ${ocultarBoton}"
:disabled="loadingSeguimiento"
@click="submitSeguimiento(${siguiente})">
 <i class="ti ti-check"></i> ${texto}
</button>
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
modo: 'nuevo',
idRegistro: null,
loading: false,

errors: {
clave: false,
nombre_g: false,
foto: false
},

openEdit(data) {
this.modo = 'editar';
this.idRegistro = Number(data.id);
this.clave = data.clave || '';
this.nombre_g = data.nombre_g || '';
this.foto = null;
this.errors = { clave: false, nombre_g: false, foto: false };
if (this.$refs.foto) this.$refs.foto.value = null;
bootstrap.Modal.getOrCreateInstance(document.getElementById('nuevo')).show();
},

handleFile(e) {
this.foto = e.target.files[0];
this.errors.foto = false;
},

validate() {

this.errors.clave = !this.clave;
this.errors.nombre_g = !this.nombre_g;
this.errors.foto = this.modo === 'nuevo' && !this.foto;

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
this.modo = 'nuevo';
this.idRegistro = null;

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
if (this.foto) formData.append('foto', this.foto);
const editar = this.modo === 'editar';
if (editar) formData.append('id', this.idRegistro);

try {

const res = await this.createAction({
url: editar ? '/solicitud-gafetes/update-reporte-formulario' : '/solicitud-gafetes/create-reporte-formulario',
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

this.notify('error', error?.message || 'Error al guardar');

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

await Swal.fire({
icon: 'success',
title: 'Estatus actualizado',
text: Number(noSeguimiento) === 1
? 'La solicitud quedó En proceso.'
: Number(noSeguimiento) === 2
? 'La solicitud quedó Finalizada.'
: 'La entrega quedó finalizada y el proceso está completo.',
confirmButtonColor: '#198754'
});

window.location.reload();

return;
}

} catch (error) {

console.error(error);
this.notify('error', 'Error al guardar');

}

this.loadingSeguimiento = false;

},

}));

});
