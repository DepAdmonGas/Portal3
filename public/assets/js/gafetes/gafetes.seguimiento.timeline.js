document.addEventListener('DOMContentLoaded', () => {
const { estacion, reporte, puesto } = document.getElementById('container').dataset;
cargarTimeline(estacion, reporte, Number(puesto));
});

const pasos = {
1: 'Creo la solicitud de gafetes',
2: 'Termino el proceso de elaboración los gafetes',
3: 'Los gafetes han sido entregados'
};

const pasosPendiente = {
1: 'La solicitud ha sido creada, pero no ha sido finalizada',
2: 'Los gafetes se encuentran en proceso de elaboración',
3: 'Los gafetes no han sido entregados'
};

function cargarTimeline(idEstacion, noReporte, idPuesto) {
fetch(`/solicitud-gafetes/seguimiento/timeline/${idEstacion}/${noReporte}`)
.then(r => r.json())
.then(({ data }) => {

const map = Object.fromEntries(data.map(i => [i.seguimiento, i]));

renderTimeline(map);
actualizarFooter(map, idPuesto);
});
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
const footer = document.querySelector('.card-footer');

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

