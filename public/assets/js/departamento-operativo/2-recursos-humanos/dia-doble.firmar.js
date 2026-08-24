document.addEventListener('alpine:init', () => {

Alpine.data('diaDobleFirmarComponent', () => ({
idReporte: 0,
token: '',
botonesDeshabilitados: false,
firmando: false,
detalle: null,

init() {
  const c = this.$el;
  this.idReporte = parseInt(c.dataset.idReporte) || 0;
  if (c.dataset.detalle) {
    try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) {}
  }

  const disableTime = localStorage.getItem('dd_disableTime');
  if (disableTime) {
    const elapsed = new Date().getTime() - parseInt(disableTime);
    if (elapsed < 30000) {
      this.botonesDeshabilitados = true;
      setTimeout(() => { this.botonesDeshabilitados = false; }, 30000 - elapsed);
    } else {
      localStorage.removeItem('dd_disableTime');
    }
  }
},

async crearToken(via) {
  this.botonesDeshabilitados = true;
  try {
    const resp = await axios.post('/departamento-operativo/recursos-humanos/dia-doble/crear-token', {
      id_reporte: this.idReporte,
      via: via
    });
    const json = resp.data;

    if (json.success) {
      if (via === 'telegram') {
        Notify.success('El token fue enviado por Telegram');
      } else {
        Notify.success('El token fue enviado por correo electrónico');
      }
      Notify.warning('Deberá esperar 30 seg para volver a crear un nuevo token');

      const disableTime = new Date().getTime();
      localStorage.setItem('dd_disableTime', disableTime);
      setTimeout(() => { this.botonesDeshabilitados = false; }, 30000);
    } else {
      this.botonesDeshabilitados = false;
      Notify.error(json.message || 'Error al crear el token');
    }
  } catch (e) {
    this.botonesDeshabilitados = false;
    console.error('Error creating token:', e);
    Notify.error('Error al crear el token');
  }
},

async firmarSolicitud(tipoFirma) {
  if (!this.token.trim()) {
    Notify.error('Falta ingresar el token de seguridad');
    return;
  }

  this.firmando = true;
  try {
    const resp = await axios.post('/departamento-operativo/recursos-humanos/dia-doble/firmar', {
      id_reporte: this.idReporte,
      tipo_firma: tipoFirma,
      token: parseInt(this.token) || 0
    });
    const json = resp.data;

    if (json.success) {
      localStorage.removeItem('dd_disableTime');
      Swal.fire({
          icon: 'success',
          title: 'Formato firmado',
          text: 'El formato de días dobles se ha firmado exitosamente.',
          timer: 2000,
          showConfirmButton: false
      }).then(() => {
          window.location.href = '/departamento-operativo/recursos-humanos/dia-doble/' + this.detalle.year;
      });
    } else {
      this.firmando = false;
      Notify.error(json.message || 'Error al firmar el formato');
    }
  } catch (e) {
    this.firmando = false;
    console.error('Error signing:', e);
    Notify.error('Error al firmar el formato');
  }
}

}));

});
