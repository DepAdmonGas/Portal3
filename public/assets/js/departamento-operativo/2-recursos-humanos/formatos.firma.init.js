document.addEventListener('alpine:init', () => {
    Alpine.data('formatosFirmarComponent', () => ({
        idFormato: 0,
        token: '',
        botonesDeshabilitados: false,
        firmandoVerificacion: false,
        signaturePad: null,
        status: 0,
        firmaB: 0,
        firmaC: 0,
        firmaD: 0,
        esFirmanteVOBO: false,
        esFirmanteAuth: false,
        esFirmanteVerificacion: false,

        init() {
            var c = this.$el;
            if (!c) return;
            this.idFormato = parseInt(c.dataset.idFormato) || 0;
            this.status = parseInt(c.dataset.status) || 0;
            this.firmaB = parseInt(c.dataset.firmaB) || 0;
            this.firmaC = parseInt(c.dataset.firmaC) || 0;
            this.firmaD = parseInt(c.dataset.firmaD) || 0;
            this.esFirmanteVOBO = c.dataset.esFirmanteVobo === 'true';
            this.esFirmanteAuth = c.dataset.esFirmanteAuth === 'true';
            this.esFirmanteVerificacion = c.dataset.esFirmanteVerificacion === 'true';

            var disableTime = localStorage.getItem('fmt_disableTime');
            if (disableTime) {
                var elapsed = new Date().getTime() - parseInt(disableTime);
                if (elapsed < 30000) {
                    this.botonesDeshabilitados = true;
                    var self = this;
                    setTimeout(function () { self.botonesDeshabilitados = false; }, 30000 - elapsed);
                } else {
                    localStorage.removeItem('fmt_disableTime');
                }
            }
            if (this.status === 3 && this.firmaD === 0 && this.esFirmanteVerificacion) {
                var self = this;
                setTimeout(function () { self.initSignaturePad(); }, 500);
            }
        },

        initSignaturePad() {
            var w = document.getElementById('signature-pad');
            var cv = w ? w.querySelector('canvas') : null;
            if (cv && typeof SignaturePad !== 'undefined') {
                this.signaturePad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
                this._resizeCanvas();
                var self = this;
                window.addEventListener('resize', function () { self._resizeCanvas(); });
            }
        },

        _resizeCanvas() {
            var w = document.getElementById('signature-pad');
            var cv = w ? w.querySelector('canvas') : null;
            if (!cv) return;
            var r = Math.max(window.devicePixelRatio || 1, 1);
            cv.width = cv.offsetWidth * r;
            cv.height = cv.offsetHeight * r;
            cv.getContext('2d').scale(r, r);
            if (this.signaturePad) this.signaturePad.clear();
        },

        limpiarFirma() {
            if (this.signaturePad) this.signaturePad.clear();
        },

        crearTokenTelegram() {
            this.crearToken('telegram');
        },

        crearTokenEmail() {
            this.crearToken('email');
        },

        crearToken(via) {
            var self = this;
            self.botonesDeshabilitados = true;
            if (window.loader) window.loader.show();

            var fd = new FormData();
            fd.append('id', self.idFormato);
            fd.append('via', via);

            fetch('/departamento-operativo/recursos-humanos/formatos/crear-token', {
                method: 'POST',
                body: fd
            })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    if (via === 'telegram') {
                        if (window.Notify) Notify.success('El token fue enviado por Telegram');
                    } else {
                        if (window.Notify) Notify.success('El token fue enviado por correo electr\u00f3nico');
                    }
                    if (window.Notify) Notify.warning('Deber\u00e1 esperar 30 seg para volver a crear un nuevo token');
                    var disableTime = new Date().getTime();
                    localStorage.setItem('fmt_disableTime', disableTime);
                    setTimeout(function () { self.botonesDeshabilitados = false; }, 30000);
                } else {
                    self.botonesDeshabilitados = false;
                    if (window.Notify) Notify.error(json.message || 'Error al crear el token');
                }
            })
            .catch(function (e) {
                self.botonesDeshabilitados = false;
                console.error('Error creating token:', e);
                if (window.Notify) Notify.error('Error al crear el token');
            })
            .finally(function () {
                if (window.loader) window.loader.hide();
            });
        },

        firmarFormato(tipoFirma) {
            if (!this.token.trim()) {
                if (window.Notify) Notify.error('Falta ingresar el token de seguridad');
                return;
            }

            if (window.loader) window.loader.show();
            var self = this;

            var fd = new FormData();
            fd.append('id', self.idFormato);
            fd.append('tipo_firma', tipoFirma);
            fd.append('token', self.token);

            fetch('/departamento-operativo/recursos-humanos/formatos/firmar', {
                method: 'POST',
                body: fd
            })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    localStorage.removeItem('fmt_disableTime');
                    Swal.fire({
                        icon: 'success',
                        title: 'Formato firmado',
                        text: 'El formato se ha firmado exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = '/departamento-operativo/recursos-humanos/formatos';
                    });
                } else {
                    if (window.Notify) Notify.error(json.message || 'Error al firmar el formato');
                }
            })
            .catch(function (e) {
                console.error('Error signing:', e);
                if (window.Notify) Notify.error('Error al firmar el formato');
            })
            .finally(function () {
                if (window.loader) window.loader.hide();
            });
        },

        firmarVerificacion() {
            if (!this.signaturePad || this.signaturePad.isEmpty()) {
                if (window.Notify) Notify.error('Dibuja la firma de verificaci\u00f3n en el canvas');
                return;
            }

            this.firmandoVerificacion = true;
            if (window.loader) window.loader.show();
            var self = this;

            var fd = new FormData();
            fd.append('id', self.idFormato);
            fd.append('firma', self.signaturePad.toDataURL());

            fetch('/departamento-operativo/recursos-humanos/formatos/firma-imagen', {
                method: 'POST',
                body: fd
            })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Formato finalizado',
                        text: 'La verificaci\u00f3n se firm\u00f3 y el formato qued\u00f3 finalizado.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = '/departamento-operativo/recursos-humanos/formatos';
                    });
                } else {
                    if (window.Notify) Notify.error(json.message || 'Error al firmar la verificaci\u00f3n');
                }
            })
            .catch(function (e) {
                console.error('Error signing verification:', e);
                if (window.Notify) Notify.error('Error al firmar la verificaci\u00f3n');
            })
            .finally(function () {
                if (window.loader) window.loader.hide();
                self.firmandoVerificacion = false;
            });
        }
    }));
});
