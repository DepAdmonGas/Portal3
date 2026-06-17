document.addEventListener('alpine:init', () => {

Alpine.data('analisisCompra', () => ({
guardando: false,

guardarNotac(event, fecha, factura) {
const valor = event.target.value.trim();
if (!valor) return;

const formData = new FormData();
formData.append('fecha', fecha);
formData.append('factura', factura);
formData.append('valor', valor);

axios.post('/departamento-operativo/analisis-compra/notac', formData)
.then(response => {
if (response.data.success) {
event.target.classList.add('is-valid');
setTimeout(() => event.target.classList.remove('is-valid'), 1500);
}
})
.catch(() => {
event.target.classList.add('is-invalid');
setTimeout(() => event.target.classList.remove('is-invalid'), 1500);
});
},

guardarStatus(event, fecha, factura) {
const valor = event.target.value;

const formData = new FormData();
formData.append('fecha', fecha);
formData.append('factura', factura);
formData.append('valor', valor);

axios.post('/departamento-operativo/analisis-compra/status', formData)
.then(response => {
if (response.data.success) {
event.target.classList.add('is-valid');
setTimeout(() => event.target.classList.remove('is-valid'), 1500);
}
})
.catch(() => {
event.target.classList.add('is-invalid');
setTimeout(() => event.target.classList.remove('is-invalid'), 1500);
});
},
}));
});
