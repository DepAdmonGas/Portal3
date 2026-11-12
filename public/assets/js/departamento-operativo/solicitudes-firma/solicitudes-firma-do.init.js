// ============================================================
// Solicitudes pendientes (Firma) - Departamento Operativo
// Offcanvas del sidebar que sustituye al botón flotante del sistema
// anterior. Sólo consulta y enlaza al flujo de firma existente.
// ============================================================
document.addEventListener('alpine:init', () => {
    Alpine.data('solicitudesFirmaDoComponent', () => ({
        cargando: false,
        total: 0,
        tipo: 'General',
        tipos: [],
        items: [],
        error: '',
        panel: null,
        _ultimaActualizacion: 0,

        init() {
            const panelEl = document.getElementById('offcanvasSolicitudesFirma');

            if (panelEl && window.bootstrap) {
                this.panel = window.bootstrap.Offcanvas.getOrCreateInstance(panelEl);
            }

            window.addEventListener('solicitudes-firma:abrir', () => this.abrir());
            window.addEventListener('pageshow', () => this.actualizarContador());
            window.addEventListener('focus', () => this.actualizarContador());
        },

        abrir() {
            if (!this.panel) return;

            this.tipo = 'General';
            this.error = '';
            this.panel.show();
            this.cargar();
        },

        async cargar() {
            this.cargando = true;
            this.error = '';

            try {
                const respuesta = await fetch(
                    '/departamento-operativo/solicitudes-firma/data?tipo=' + encodeURIComponent(this.tipo),
                    {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    }
                );

                const datos = await respuesta.json();

                if (!respuesta.ok || datos.success !== true) {
                    this.items = [];
                    this.error = datos.message || 'No fue posible consultar las solicitudes pendientes.';
                    return;
                }

                this.total = Number(datos.total || 0);
                this.tipos = Array.isArray(datos.tipos) ? datos.tipos : [];
                this.items = Array.isArray(datos.items) ? datos.items : [];
                this.pintarBadge(this.total);
            } catch (e) {
                this.items = [];
                this.error = 'No fue posible consultar las solicitudes pendientes.';
            } finally {
                this.cargando = false;
            }
        },

        async actualizarContador() {
            const ahora = Date.now();

            if (this.cargando || ahora - this._ultimaActualizacion < 1500) return;

            this._ultimaActualizacion = ahora;

            try {
                const respuesta = await fetch(
                    '/departamento-operativo/solicitudes-firma/data?tipo=General',
                    {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    }
                );

                if (!respuesta.ok) return;

                const datos = await respuesta.json();

                if (datos.success === true) {
                    this.total = Number(datos.total || 0);
                    this.pintarBadge(this.total);
                }
            } catch (e) {
                // Silencioso: el contador se reintenta en el próximo focus.
            }
        },

        pintarBadge(total) {
            const badge = document.getElementById('solicitudes-firma-do-badge');

            if (!badge) return;

            badge.textContent = String(total);
            badge.classList.toggle('d-none', total <= 0);
        },

        abrirSolicitud(item) {
            if (!item) return;

            if (!item.disponible || !item.url) {
                if (window.Notify) {
                    window.Notify.info(item.aviso || 'Esta solicitud no está disponible en Portal3.');
                }
                return;
            }

            window.location.href = item.url;
        }
    }));
});

document.addEventListener('click', (evento) => {
    const enlace = evento.target.closest('[data-solicitudes-firma]');

    if (!enlace) return;

    evento.preventDefault();

    enlace.classList.remove('active');
    const item = enlace.closest('li');
    if (item) item.classList.remove('active', 'selected');

    window.dispatchEvent(new CustomEvent('solicitudes-firma:abrir'));
});
