document.addEventListener('DOMContentLoaded', () => {
    if (typeof ModuleStationSelector === 'undefined') return;

    const selector = document.querySelector('[id^="module-station-selector-"]');
    const moduleKey = selector ? selector.dataset.moduleKey : 'pivoteo';

    ModuleStationSelector.init(moduleKey, {
        customReload: function () {
            window.location.href = '/departamento-operativo/importacion/pivoteo';
        }
    });
});

document.addEventListener('alpine:init', () => {

    Alpine.data('pivoteoFirmarComponent', () => ({

        detalle: null,
        token: '',
        botonesDeshabilitados: false,
        guardando: false,

        init() {
            const c = this.$el;
            if (c.dataset.detalle) {
                try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) { this.detalle = null; }
            }

            const disableTime = localStorage.getItem('pivoteo_disableTime');
            if (disableTime) {
                const elapsed = new Date().getTime() - parseInt(disableTime);
                if (elapsed < 30000) {
                    this.botonesDeshabilitados = true;
                    setTimeout(() => { this.botonesDeshabilitados = false; }, 30000 - elapsed);
                } else {
                    localStorage.removeItem('pivoteo_disableTime');
                }
            }
        },

        get id() {
            return this.detalle ? (parseInt(this.detalle.id) || 0) : 0;
        },

        get estatus() {
            return this.detalle ? (parseInt(this.detalle.estatus) || 0) : 0;
        },

        get filas() {
            return (this.detalle && this.detalle.filas) || [];
        },

        get puedeFirmarAhora() {
            return !!(this.detalle && this.detalle.puede_firmar_ahora);
        },

        get firmaB() {
            const firmas = (this.detalle && this.detalle.firmas) || [];
            return firmas.find(f => f.tipo === 'B') || null;
        },

        formatoLitros(valor) {
            return Number(valor || 0).toFixed(2);
        },

        crearTokenTelegram() {
            this.crearToken('telegram');
        },

        crearTokenEmail() {
            this.crearToken('email');
        },

        async crearToken(via) {
            if (this.botonesDeshabilitados || this.guardando) return;
            this.botonesDeshabilitados = true;

            try {
                const resp = await axios.post('/departamento-operativo/importacion/pivoteo/token', {
                    id: this.id,
                    via: via
                });
                const json = resp.data;

                if (json.success) {
                    if (window.Notify) {
                        Notify.success(via === 'email'
                            ? 'El token fue enviado por correo electrónico'
                            : 'El token fue enviado por Telegram');
                        Notify.warning('Deberá esperar 30 seg para volver a crear un nuevo token');
                    }

                    localStorage.setItem('pivoteo_disableTime', String(new Date().getTime()));
                    setTimeout(() => { this.botonesDeshabilitados = false; }, 30000);
                } else {
                    this.botonesDeshabilitados = false;
                    if (window.Notify) Notify.error(json.message || 'Error al crear el token');
                }
            } catch (e) {
                this.botonesDeshabilitados = false;
                console.error('Error creating token:', e);
                if (window.Notify) Notify.error(e.response?.data?.message || 'Error al crear el token');
            }
        },

        async firmar() {
            if (this.guardando) return;

            if (!this.token.trim()) {
                if (window.Notify) Notify.error('Falta ingresar el token de seguridad');
                return;
            }

            this.guardando = true;

            try {
                const resp = await axios.post('/departamento-operativo/importacion/pivoteo/firmar', {
                    id: this.id,
                    tipo_firma: 'B',
                    token: this.token
                });
                const json = resp.data;

                if (json.success) {
                    localStorage.removeItem('pivoteo_disableTime');
                    Swal.fire({
                        icon: 'success',
                        title: 'Pivoteo firmado',
                        text: 'El pivoteo se ha firmado exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/departamento-operativo/importacion/pivoteo';
                    });
                } else {
                    this.guardando = false;
                    if (window.Notify) Notify.error(json.message || 'Error al firmar el pivoteo');
                }
            } catch (e) {
                this.guardando = false;
                console.error('Error signing:', e);
                if (window.Notify) Notify.error(e.response?.data?.message || 'Error al firmar el pivoteo');
            }
        }

    }));

});
