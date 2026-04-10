document.addEventListener('DOMContentLoaded', () => {
const { estacion, solicitud, puesto } = document.getElementById('container').dataset;
cargarTimeline(estacion, solicitud, Number(puesto));
});

const pasos = {
1: 'Creo la solicitud de tarjetas',
2: 'Termino el proceso de elaboración las tarjetas',
3: 'Las tarjetas han sido entregadas'
};

const pasosPendiente = {
1: 'La solicitud ha sido creada, pero no ha sido finalizada',
2: 'Las tarjetas se encuentran en proceso de elaboración',
3: 'Las tarjetas no han sido entregadas'
};

function cargarTimeline(idEstacion, noSolicitud, idPuesto) {
fetch(`/solicitud-tarjetas/seguimiento/timeline/${idEstacion}/${noSolicitud}`)
.then(r => r.json())
.then(({ data, comentarios }) => {
 
const map = Object.fromEntries(data.map(i => [i.seguimiento, i]));

renderTimeline(map);
actualizarFooter(map, idPuesto);
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

<a href="javascript:void(0)" 
class="hstack gap-3 file-chat-hover justify-content-between text-nowrap mb-2"
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

<span class="position-relative nav-icon-hover download-file">
<i class="ti ti-download text-dark fs-6"></i>
</span>

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

function actualizarFooter(map, idPuesto) {

const pasosExistentes = Object.keys(map).map(Number).sort((a,b) => a-b);
const ultimoPaso = pasosExistentes.at(-1) || 0;
const siguiente = ultimoPaso + 1;
const footer = document.querySelector('#seguimientoFooter');
if (!footer) return;

if (ultimoPaso >= 3) {
footer.innerHTML = `
<div class="text-success text-end fw-bold">
<i class="ti ti-circle-check"></i> Proceso completado
</div>
`;
return;
}

let ocultarBoton = "d-none";

if (siguiente === 1 && idPuesto === 6) {
ocultarBoton = "";
} 
else if (siguiente === 2 && idPuesto === 13) {
ocultarBoton = "";
} 
else if (siguiente === 3 && idPuesto === 6) {
ocultarBoton = "";
}

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

const btnComentario = (siguiente === 2 && idPuesto === 13 && ultimoPaso < 3);

if (btnComentario) {
body.innerHTML = `<textarea class="form-control" id="comentarioInput" rows="4">${texto}</textarea>`;
footer.innerHTML = `<button class="btn btn-success float-end" @click="submitComentario()"><i class="ti ti-check"></i> Guardar</button>`;
return;
}

body.innerHTML = `<p class="mb-0 ${ultimoPaso < 3 ? 'text-muted' : ''}">${texto}</p>`;
footer.classList.add('d-none');
}
