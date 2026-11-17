document.addEventListener('alpine:init', () => {

Alpine.data('pedidoAditivoFirmaComponent', () => ({

solicitud: null,
token: '',
botonesDeshabilitados: false,
tokenSegundos: 0,
timerToken: null,
firmandoVobo: false,
puedeFirmarVoBo: false,

BASE: '/departamento-operativo/comercializadora/pedido-aditivo',

displayQuantity(value) {
return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
},

init() {
const c = document.getElementById('container');
if (!c) return;

if (c.dataset.solicitud) {
try {
this.solicitud = JSON.parse(c.dataset.solicitud);
} catch (e) {
this.solicitud = null;
console.error('Error parseando data-solicitud:', e, c.dataset.solicitud);
}
}

this.puedeFirmarVoBo = c.dataset.puedeFirmar === 'true';

window.pedidoAditivoFirmaComponentInstance = this;

this.actualizarContadorToken();
this.timerToken = window.setInterval(() => this.actualizarContadorToken(), 1000);
},

actualizarContadorToken() {
const disableTime = parseInt(localStorage.getItem('pedido_aditivo_token_disableTime') || '0', 10);
const restante = disableTime ? Math.max(0, Math.ceil((30000 - (Date.now() - disableTime)) / 1000)) : 0;
this.tokenSegundos = restante;
this.botonesDeshabilitados = restante > 0;
if (!restante && disableTime) localStorage.removeItem('pedido_aditivo_token_disableTime');
},

destroy() { if (this.timerToken) window.clearInterval(this.timerToken); },

get id() {
if (this.solicitud) {
const v = parseInt(this.solicitud.id, 10);
if (v) return v;
}
const c = document.getElementById('container');
if (c && c.dataset.idSolicitud) {
return parseInt(c.dataset.idSolicitud, 10) || 0;
}
return 0;
},

crearTokenTelegram() {
this.crearToken('telegram');
},

crearTokenEmail() {
this.crearToken('email');
},

async crearToken(via) {
if (this.botonesDeshabilitados || this.firmandoVobo) return;
if (!this.id) {
if (window.Notify) Notify.error('No se encontró la solicitud');
return;
}

localStorage.setItem('pedido_aditivo_token_disableTime', String(Date.now()));
this.actualizarContadorToken();
try {
const fd = new FormData();
fd.append('id', this.id);
fd.append('via', via);

const resp = await fetch(this.BASE + '/crear-token', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
if (window.Notify) {
Notify.success(via === 'email'
? 'El token fue enviado por correo electrónico'
: 'El token fue enviado por Telegram');
Notify.warning('Deberá esperar 30 seg para volver a crear un nuevo token');
}
} else {
localStorage.removeItem('pedido_aditivo_token_disableTime');
this.actualizarContadorToken();
if (window.Notify) Notify.error(json.message || 'Error al crear el token');
}
} catch (e) {
localStorage.removeItem('pedido_aditivo_token_disableTime');
this.actualizarContadorToken();
console.error('Error creando token:', e);
if (window.Notify) Notify.error('Error al crear el token');
}
},

async firmarVobo() {
if (this.firmandoVobo) return;

if (!this.token.trim()) {
if (window.Notify) Notify.error('Ingresa el token de seguridad');
return;
}

this.firmandoVobo = true;
try {
const fd = new FormData();
fd.append('id', this.id);
fd.append('token', this.token.trim());

const resp = await fetch(this.BASE + '/firmar-vobo', { method: 'POST', body: fd });
const json = await resp.json();

if (json.success) {
localStorage.removeItem('pedido_aditivo_token_disableTime');
Swal.fire({
icon: 'success',
title: 'VoBo firmado',
text: json.message || 'El VoBo se ha firmado exitosamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
window.location.href = this.BASE;
});
} else {
this.firmandoVobo = false;
if (window.Notify) Notify.error(json.message || 'Error al firmar el VoBo');
}
} catch (e) {
this.firmandoVobo = false;
console.error('Error firmando VoBo:', e);
if (window.Notify) Notify.error('Error al firmar el VoBo');
}
},

}));

});
