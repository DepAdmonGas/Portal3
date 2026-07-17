document.addEventListener('DOMContentLoaded', () => {
ModuleStationSelector.init('ingresos-facturacion', {
customReload: function (ms) {
var v = ms.getValue();
if (v.id_estacion === null && v.id_depto === null) {
ms.hideBadge();
document.getElementById('ingresos-facturacion-content').style.display = 'none';
document.getElementById('ingresos-facturacion-empty-message').style.display = '';
return;
}
document.getElementById('ingresos-facturacion-empty-message').style.display = 'none';
document.getElementById('ingresos-facturacion-content').style.display = '';
document.dispatchEvent(new CustomEvent('if:station-change'));
}
});
});

document.addEventListener('alpine:init', () => {
Alpine.data('ingresosFacturacionComponent', () => ({
meses: ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
loading: false,
error: null,
posicion1: [],
posicion2: [],
totales1: {},
totales2: {},
diferencias: {},
idYear: 0,
idReporte: 0,
guardando: false,
archivos: [],

init() {
this.idYear = parseInt(this.$el.dataset.idYear) || 0;
const idEstacion = parseInt(this.$el.dataset.idEstacion) || 0;
document.addEventListener('if:station-change', () => this.loadData());
document.addEventListener('if:file-deleted', () => {
if (this.idReporte) this.cargarArchivos(this.idReporte);
});
if (idEstacion) this.loadData();
},

loadData() {
if (!this.idYear) return;
this.loading = true;
this.error = null;
axios.get('/departamento-operativo/ingresos-facturacion/data', {
params: { id_year: this.idYear }
}).then(res => {
if (res.data.success) {
this.idReporte = res.data.id_reporte;
this.posicion1 = res.data.posicion1.map(r => ({ ...r }));
this.posicion2 = res.data.posicion2.map(r => ({ ...r }));
this.calcularMontos();
} else {
this.error = res.data.message || 'Error al cargar datos';
}
}).catch(() => {
this.error = 'Error de conexión al cargar datos';
}).then(() => {
this.loading = false;
});
},

formatMoney(val) {
return '$ ' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},
formatInput(val) {
return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},

calcularMontos() {
const m = this.meses;
const fmt = v => this.formatMoney(v);
const t1 = {}, t2 = {}, d = {};
m.forEach(mes => {
const s1 = this.posicion1.reduce((a, r) => a + (parseFloat(r[mes]) || 0), 0);
const s2 = this.posicion2.reduce((a, r) => a + (parseFloat(r[mes]) || 0), 0);
t1[mes] = fmt(s1);
t2[mes] = fmt(s2);
d[mes] = fmt(s2 - s1);
});
t1.ejercicio = fmt(this.posicion1.reduce((a, r) => a + m.reduce((s, mes) => s + (parseFloat(r[mes]) || 0), 0), 0));
t2.ejercicio = fmt(this.posicion2.reduce((a, r) => a + m.reduce((s, mes) => s + (parseFloat(r[mes]) || 0), 0), 0));
d.ejercicio = fmt((parseFloat(t2.ejercicio.replace(/[$,]/g,'')) || 0) - (parseFloat(t1.ejercicio.replace(/[$,]/g,'')) || 0));
this.totales1 = t1;
this.totales2 = t2;
this.diferencias = d;
},

totalFila(row) {
return this.meses.reduce((s, mes) => s + (parseFloat(row[mes]) || 0), 0);
},

editIF(id, mes, posicion) {
const row = posicion === 1
? this.posicion1.find(r => r.id === id)
: this.posicion2.find(r => r.id === id);
if (!row) return;
const valor = parseFloat(row[mes]) || 0;
this.calcularMontos();
fetch('/departamento-operativo/ingresos-facturacion/update-cell', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ id, valor, mes: this.meses.indexOf(mes) + 1 })
}).then(r => r.json()).then(data => {
if (!data.success) console.error('Error al guardar');
});
},

abrirEntregables() {
if (!this.idReporte) return;
this.cargarArchivos(this.idReporte);
const modal = new bootstrap.Modal(document.getElementById('modalEntregables'));
modal.show();
},

cargarArchivos(idReporte) {
fetch('/departamento-operativo/ingresos-facturacion/list-files?id_reporte=' + idReporte)
.then(r => r.json())
.then(data => { this.archivos = data.archivos || []; })
.catch(() => {
if (window.Notify) Notify.error('Error al cargar archivos');
});
},

guardarArchivo() {
if (!this.idReporte) return;
const input = document.getElementById('archivoInput');
if (!input.files || !input.files[0]) {
if (window.Notify) Notify.error('Seleccione un archivo');
return;
}
this.guardando = true;
const formData = new FormData();
formData.append('id_reporte', this.idReporte);
formData.append('archivo', input.files[0]);
fetch('/departamento-operativo/ingresos-facturacion/upload-file', {
method: 'POST',
body: formData
}).then(r => r.json()).then(data => {
this.guardando = false;
if (data.success) {
input.value = '';
this.cargarArchivos(this.idReporte);
if (window.Notify) Notify.success(data.message || 'Archivo guardado correctamente');
} else {
if (window.Notify) Notify.error(data.message || 'Error al guardar el archivo');
}
}).catch(() => {
this.guardando = false;
if (window.Notify) Notify.error('Error de conexión al subir archivo');
});
},

download(tipo, archivo) {
if (!archivo) {
if (window.Notify) Notify.error('Archivo no disponible');
return;
}
window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(archivo), '_blank');
},

cambiarYear(year) {
window.location.href = '/departamento-operativo/corporativo/ingresos-facturacion/' + year;
}
}));
});
