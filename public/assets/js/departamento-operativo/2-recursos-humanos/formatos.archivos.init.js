document.addEventListener('alpine:init', () => {
Alpine.data('formatosFirmaArchivosComponent', () => ({

verArchivosEmpleado(icon) {
try {
const archivos = JSON.parse(icon.dataset.archivos || '[]');
const nombre = icon.dataset.nombreEmpleado || 'Empleado';
const nombreEl = document.getElementById('archivosEmpleadoFirmaNombre');
const body = document.getElementById('archivosEmpleadoFirmaBody');

if (nombreEl) nombreEl.textContent = nombre;

if (archivos.length === 0) {
body.innerHTML = '<div class="text-center text-muted py-4"><i class="ti ti-files-off" style="font-size:50px;"></i><p class="mb-0 mt-2">El empleado no tiene archivos adjuntos</p></div>';
} else {
let html = '<div class="table-responsive"><table class="table table-striped table-bordered mb-0 align-middle text-nowrap">';
html += '<thead><tr><th class="text-start">Nombre del documento</th><th class="text-center">Archivo</th><th class="text-center">Acción</th></tr></thead><tbody>';
archivos.forEach(function(item) {
html += '<tr>';
html += '<td class="text-start">' + item.label + '</td>';
html += '<td class="text-center small text-muted">' + item.archivo + '</td>';
html += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/download?tipo=formatos-alta&file=' + encodeURIComponent(item.archivo) + '\',\'_blank\')"><i class="ti ti-download me-1"></i> Descargar</button></td>';
html += '</tr>';
});
html += '</tbody></table></div>';
body.innerHTML = html;
}

bootstrap.Modal.getOrCreateInstance(document.getElementById('modalArchivosEmpleadoFirma')).show();
} catch (e) {
console.error('Error abriendo archivos:', e);
}

}

}));

});

document.addEventListener('click', function(e) {
var icon = e.target.closest('[data-archivos]');
if (!icon) return;

try {
var archivos = JSON.parse(icon.dataset.archivos || '[]');
var nombre = icon.dataset.nombreEmpleado || 'Empleado';
var nombreEl = document.getElementById('archivosEmpleadoFirmaNombre');
var body = document.getElementById('archivosEmpleadoFirmaBody');

if (!nombreEl || !body) return;

nombreEl.textContent = nombre;

if (archivos.length === 0) {
body.innerHTML = '<div class="text-center text-muted py-4"><i class="ti ti-files-off" style="font-size:50px;"></i><p class="mb-0 mt-2">El empleado no tiene archivos adjuntos</p></div>';
} else {
var html = '<div class="table-responsive"><table class="table table-striped table-bordered mb-0 align-middle text-nowrap">';
html += '<thead><tr><th class="text-start">Nombre del documento</th><th class="text-center">Archivo</th><th class="text-center">Acción</th></tr></thead><tbody>';
archivos.forEach(function(item) {
html += '<tr>';
html += '<td class="text-start">' + item.label + '</td>';
html += '<td class="text-center small text-muted">' + item.archivo + '</td>';
html += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary" onclick="window.open(\'/download?tipo=formatos-alta&file=' + encodeURIComponent(item.archivo) + '\',\'_blank\')"><i class="ti ti-download me-1"></i> Descargar</button></td>';
html += '</tr>';
});
html += '</tbody></table></div>';
body.innerHTML = html;
}

bootstrap.Modal.getOrCreateInstance(document.getElementById('modalArchivosEmpleadoFirma')).show();
} catch (e) {
console.error('Error abriendo archivos:', e);
}
});
