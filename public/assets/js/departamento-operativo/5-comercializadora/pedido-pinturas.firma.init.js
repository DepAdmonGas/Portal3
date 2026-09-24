document.addEventListener('alpine:init', () => {

Alpine.data('pedidoPinturasFirmaComponent', () => ({

pedido: null,
token: '',
botonesDeshabilitados: false,
firmandoVobo: false,

BASE: '/departamento-operativo/comercializadora/pedido-pinturas',

init() {
const c = document.getElementById('container');
if (!c) return;

if (c.dataset.pedido) {
try {
this.pedido = JSON.parse(c.dataset.pedido);
} catch (e) {
this.pedido = null;
console.error('Error parseando data-pedido:', e, c.dataset.pedido);
}
}

window.pedidoPinturasFirmaComponentInstance = this;

const disableTime = localStorage.getItem('pedido_pinturas_token_disableTime');
if (disableTime) {
const elapsed = new Date().getTime() - parseInt(disableTime, 10);
if (elapsed < 30000) {
this.botonesDeshabilitados = true;
setTimeout(() => { this.botonesDeshabilitados = false; }, 30000 - elapsed);
} else {
localStorage.removeItem('pedido_pinturas_token_disableTime');
}
}
},

get id() {
if (this.pedido) {
const v = parseInt(this.pedido.id, 10);
if (v) return v;
}
const c = document.getElementById('container');
if (c && c.dataset.idPedido) {
return parseInt(c.dataset.idPedido, 10) || 0;
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
if (window.Notify) Notify.error('No se encontró el pedido');
return;
}

this.botonesDeshabilitados = true;
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
localStorage.setItem('pedido_pinturas_token_disableTime', String(new Date().getTime()));
setTimeout(() => { this.botonesDeshabilitados = false; }, 30000);
} else {
this.botonesDeshabilitados = false;
if (window.Notify) Notify.error(json.message || 'Error al crear el token');
}
} catch (e) {
this.botonesDeshabilitados = false;
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
localStorage.removeItem('pedido_pinturas_token_disableTime');
Swal.fire({
icon: 'success',
title: 'VoBo firmado',
text: json.message || 'El VoBo se ha firmado exitosamente',
timer: 2000,
showConfirmButton: false
}).then(() => {
if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
history.back();
} else {
window.location.href = this.BASE;
}
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