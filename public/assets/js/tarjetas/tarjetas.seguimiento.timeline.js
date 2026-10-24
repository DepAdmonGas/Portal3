document.addEventListener('DOMContentLoaded', () => {
const { estacion, solicitud, puesto } = document.getElementById('container').dataset;
cargarTimeline(estacion, solicitud, Number(puesto));
});

const pasos = {
1: 'La solicitud pasó a En proceso',
2: 'La solicitud quedó Finalizada',
3: 'La entrega quedó finalizada'
};

const pasosPendiente = {
1: 'Pendiente de iniciar el proceso',
2: 'Pendiente de finalizar el proceso',
3: 'Pendiente de finalizar la entrega'
};

function cargarTimeline(idEstacion, noSolicitud, idPuesto) {
fetch(`/solicitud-tarjetas/seguimiento/timeline/${idEstacion}/${noSolicitud}`)
.then(r => r.json())
.then(({ data, comentarios, siguientePaso, puedeActualizar, estatus }) => {
 
const map = Object.fromEntries(data.map(i => [i.seguimiento, i]));

renderTimeline(map);
actualizarFooter(map, idPuesto, siguientePaso, puedeActualizar, estatus);
actualizarComentarios(map, idPuesto, comentarios);
});

fetch(`/solicitud-tarjetas/archivo/${idEstacion}/${noSolicitud}`)
.then(r => r.json())
.then(({ archivo }) => {
actualizarBotonArchivo(archivo);
})
.catch(err => console.error('Error archivo:', err));
}

function actualizarBotonArchivo(archivo) {

const contenedor = document.getElementById('botonDescargaFile');
if (!contenedor) return;

contenedor.innerHTML = '';
if (!archivo) return;

const nombre = archivo.split('/').pop();

contenedor.innerHTML = `
<div class="files-chat mb-4">
<h6 class="fw-semibold mb-3 text-nowrap">
Descargar archivo:
</h6>

<a 
class="hstack gap-3 file-chat-hover justify-content-between text-nowrap mb-2 pointer"
@click="download('solicitud-tarjetas','${archivo}')">

<div class="d-flex align-items-center gap-3">
<div class="rounded-1 text-bg-light p-2">
<i class="ti ti-file fs-6"></i>
</div>

<div>
<h6 class="fw-semibold mb-0">
${nombre}
</h6>
</div>
</div>

<butt class="position-relative nav-icon-hover download-file">
<i class="ti ti-download text-dark fs-6"></i>
</butt>

</a>
</div>
`;

// 🔥 IMPORTANTE para Alpine
Alpine.initTree(contenedor);
}

function renderTimeline(map) {

const html = Array.from({ length: 3 }, (_, i) => i + 1).map(i => {

const data = map[i];
const usuario = data?.usuario || 'Sin informacion';

let fecha = '--';

if (data) {
const f = new Date(data.fecha_hora);

const fechaFormateada = f.toLocaleDateString('es-MX', {
day: 'numeric',
month: 'long',
year: 'numeric'
});

const horaFormateada = f.toLocaleTimeString('es-MX', {
hour: '2-digit',
minute: '2-digit'
});

fecha = `${fechaFormateada}, ${horaFormateada}`;
}

const color = !data ? 'bg-danger' : 'bg-success';
const texto = data ? pasos[i] : pasosPendiente[i];

// 🔥 detectar último paso
const esUltimo = i === 3;

const linea = esUltimo
? '' : `<span class="timeline-badge-border d-block flex-shrink-0"></span>`;

return `
<li class="timeline-item d-flex position-relative overflow-hidden">

<div class="timeline-time text-muted flex-shrink-0 text-end">
${usuario}
</div>

<div class="timeline-badge-wrap d-flex flex-column align-items-center">
<span class="timeline-badge ${color} flex-shrink-0"></span>
${linea}
</div>

<div class="timeline-desc fs-3 text-dark">
<strong>${texto}</strong>
${data ? `<br><small class="text-muted">${fecha}</small>` : ''}
</div>

</li>`;
}).join('');

document.getElementById('timeline-seguimiento').innerHTML = html;
}

function actualizarFooter(map, idPuesto, siguienteAutorizado, puedeActualizar, estatus) {

const pasosExistentes = Object.keys(map).map(Number).sort((a,b) => a-b);
const ultimoPaso = pasosExistentes.at(-1) || 0;
const siguiente = siguienteAutorizado;
const footer = document.querySelector('#seguimientoFooter');
if (!footer) return;

if (!puedeActualizar || !siguiente) {
footer.innerHTML = `
<div class="text-success text-end fw-bold">
<i class="ti ti-circle-check"></i> ${Number(estatus) === 4 ? 'Entrega finalizada' : 'No hay acciones disponibles para este estatus'}
</div>
`;
return;
}

let ocultarBoton = "d-none";

ocultarBoton = "";

footer.innerHTML = `
<button class="btn btn-success float-end ${ocultarBoton}"
:disabled="loadingSeguimiento"
@click="submitSeguimiento(${siguiente})">
<i class="ti ti-refresh"></i> Seguimiento
</button>
`;
}

function actualizarComentarios(map, idPuesto, comentarios) {

const pasosExistentes = Object.keys(map).map(Number).sort((a,b) => a-b);
const ultimoPaso = pasosExistentes.at(-1) || 0;
const siguiente = ultimoPaso + 1;

const body = document.getElementById('DivComentariosBody');
const footer = document.getElementById('DivComentariosFooter');

if (!body || !footer) return;

const texto = comentarios || 'Sin comentarios';
body.innerHTML = '';
footer.innerHTML = '';
footer.classList.remove('d-none');

const comentarioTexto = document.createElement('p');
comentarioTexto.className = `mb-0 ${ultimoPaso < 3 ? 'text-muted' : ''}`;
comentarioTexto.textContent = texto;
body.replaceChildren(comentarioTexto);
footer.classList.add('d-none');
}
