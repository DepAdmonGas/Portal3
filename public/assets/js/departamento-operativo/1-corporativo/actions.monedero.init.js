document.addEventListener('alpine:init', () => {

Alpine.data('monederoComponent', () => ({
idDia: null,
multiestacion: true,
loading: true,

d: {
bancarias: { bancomer: 0, amex: 0, inburgas: 0, inbursa: 0, total: 0 },
tarjetas_otros: { ticketcard: 0, efecticard: 0, sodexo: 0, total: 0 },
vales: { vale_accord: 0, vale_efectivale: 0, vale_sodexo: 0, si_vale: 0, total: 0 },
credito: { pago: 0, consumo: 0 },
debito: { pago: 0, consumo: 0 },
total_pago: 0,
total_consumo: 0,
},

init() {
const c = document.getElementById('container');
if (!c) return;
this.idDia = parseInt(c.dataset.idDia);
this.multiestacion = c.dataset.multiestacion === 'true';
this.cargarDatos();
},

async cargarDatos() {
this.loading = true;
try {
const resp = await fetch('/departamento-operativo/monedero/data/' + this.idDia);
const json = await resp.json();
if (json.success) {
this.d = json.data;
}
} catch (e) {
console.error('Error cargando monedero:', e);
} finally {
this.loading = false;
}
},

}));

});
