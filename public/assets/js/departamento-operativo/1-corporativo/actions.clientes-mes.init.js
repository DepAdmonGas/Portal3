document.addEventListener('alpine:init', () => {

Alpine.data('clientesMesComponent', () => ({
loading: false,
finalizando: false,
idYear: null,
idMes: null,
idEstacion: null,
idReporte: null,
finalizado: false,
multiestacion: false,

init() {
const c = document.getElementById('container');
if (!c) return;
this.idYear = parseInt(c.dataset.idYear);
this.idMes = parseInt(c.dataset.idMes);
this.idEstacion = parseInt(c.dataset.idEstacion);
this.idReporte = parseInt(c.dataset.idReporte);
this.finalizado = c.dataset.finalizado === 'true';
this.multiestacion = c.dataset.multiestacion === 'true';

},

async finalizarResumen() {
if (this.finalizado) return;
const confirm = await Swal.fire({
title: '¿Finalizar resumen?',
text: 'Una vez finalizado no podrá editar el saldo inicial de las cuentas del cliente.',
icon: 'warning',
showCancelButton: true,
confirmButtonColor: '#d33',
cancelButtonColor: '#6c757d',
confirmButtonText: 'Sí, finalizar',
cancelButtonText: 'Cancelar',
});
if (!confirm.isConfirmed) return;

this.finalizando = true;
try {
const resp = await fetch('/departamento-operativo/clientes-mes/finalizar', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ idReporte: this.idReporte }),
});
const json = await resp.json();
if (json.success) {
Swal.fire({ icon: 'success', title: 'Correcto', text: json.message, timer: 2000, showConfirmButton: false });
setTimeout(() => location.reload(), 1500);
} else {
Swal.fire({ icon: 'error', title: 'Error', text: json.message });
}
} catch (e) {
Swal.fire({ icon: 'error', title: 'Error', text: 'Error al finalizar' });
} finally {
this.finalizando = false;
}
},

descargar() {
window.location.href = '/departamento-operativo/clientes-mes/excel/' + this.idYear + '/' + this.idMes + '/' + this.idEstacion;
},

async listaClientes() {
    try {
        await fetch('/departamento-operativo/clientes-lista/guardar-contexto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'idYear=' + this.idYear + '&idMes=' + this.idMes + '&idDia=0'
        });
    } catch (e) { console.error(e); }
    window.location.href = '/departamento-operativo/clientes-lista';
},

formatNum(val) {
if (val === null || val === undefined || isNaN(val)) return '0.00';
return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
},
}));

});

