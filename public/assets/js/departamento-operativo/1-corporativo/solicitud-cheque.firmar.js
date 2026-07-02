document.addEventListener('alpine:init', () => {

Alpine.data('solicitudChequeFirmarComponent', () => ({
idSolicitud: 0,
token: '',
botonesDeshabilitados: false,
detalle: null,

init() {
  const c = this.$el;
  this.idSolicitud = parseInt(c.dataset.idSolicitud) || 0;
  this.idYear = parseInt(c.dataset.idYear) || 0;
  this.idMes = parseInt(c.dataset.idMes) || 0;
  if (c.dataset.detalle) {
    try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) {}
  }

  const disableTime = localStorage.getItem('sc_disableTime');
  if (disableTime) {
    const elapsed = new Date().getTime() - parseInt(disableTime);
    if (elapsed < 30000) {
      this.botonesDeshabilitados = true;
      setTimeout(() => { this.botonesDeshabilitados = false; }, 30000 - elapsed);
    } else {
      localStorage.removeItem('sc_disableTime');
    }
  }
  this.$nextTick(() => this._posicionarBadge());
},

_posicionarBadge() {
  var switcheo = document.querySelector('span.mb-1.badge.rounded-pill.text-bg-info');
  if (switcheo && switcheo.id !== 'contextBadge') switcheo.style.display = 'none';
},

crearTokenTelegram() {
  this.crearToken('telegram');
},

crearTokenEmail() {
  this.crearToken('email');
},

async crearToken(via) {
  this.botonesDeshabilitados = true;
  try {
    const fd = new FormData();
    fd.append('id_solicitud', this.idSolicitud);
    if (via === 'email') {
      fd.append('via', 'email');
    }

    const resp = await fetch('/departamento-operativo/solicitud-cheque/crear-token', {
      method: 'POST',
      body: fd
    });
    const json = await resp.json();

    if (json.success) {
      if (via === 'telegram') {
        if (window.Notify) Notify.success('El token fue enviado por Telegram');
      } else {
        if (window.Notify) Notify.success('El token fue enviado por correo electrónico');
      }
      if (window.Notify) Notify.warning('Deberá esperar 30 seg para volver a crear un nuevo token');

      const disableTime = new Date().getTime();
      localStorage.setItem('sc_disableTime', disableTime);
      setTimeout(() => { this.botonesDeshabilitados = false; }, 30000);
    } else {
      this.botonesDeshabilitados = false;
      if (window.Notify) Notify.error(json.message || 'Error al crear el token');
    }
  } catch (e) {
    this.botonesDeshabilitados = false;
    console.error('Error creating token:', e);
    if (window.Notify) Notify.error('Error al crear el token');
  }
},

async firmarSolicitud(tipoFirma) {
  if (!this.token.trim()) {
    if (window.Notify) Notify.error('Falta ingresar el token de seguridad');
    return;
  }

  try {
    const fd = new FormData();
    fd.append('id_solicitud', this.idSolicitud);
    fd.append('tipo_firma', tipoFirma);
    fd.append('token', this.token);

    const resp = await fetch('/departamento-operativo/solicitud-cheque/firmar', {
      method: 'POST',
      body: fd
    });
    const json = await resp.json();

    if (json.success) {
      localStorage.removeItem('sc_disableTime');
      Swal.fire({
          icon: 'success',
          title: 'Solicitud firmada',
          text: 'La solicitud de cheque se ha firmado exitosamente.',
          timer: 2000,
          showConfirmButton: false
      }).then(() => {
          window.location.href = '/departamento-operativo/solicitud-cheque/' + this.idYear + '/' + this.idMes;
      });
    } else {
      if (window.Notify) Notify.error(json.message || 'Error al firmar la solicitud');
    }
  } catch (e) {
    console.error('Error signing:', e);
    if (window.Notify) Notify.error('Error al firmar la solicitud');
  }
},

download(tipo, file) {
  if (!file) return;
  window.open('/download?tipo=' + encodeURIComponent(tipo) + '&file=' + encodeURIComponent(file), '_blank');
}
}));

});
