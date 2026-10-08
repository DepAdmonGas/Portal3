document.addEventListener('alpine:init', () => {
    Alpine.data('ordenCompraFirmarComponent', (idReporte) => ({
        BASE_URL: '/departamento-operativo/almacen/orden-compra',
        id: idReporte,
        token: '',
        botonesBloqueados: false,
        firmando: false,

        async generarToken(via) {
            this.botonesBloqueados = true;
            const fd = new FormData();
            fd.append('idReporte', this.id);
            fd.append('idVal', via);

            try {
                const resp = await axios.post(`${this.BASE_URL}/crear-token`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    setTimeout(() => { this.botonesBloqueados = false; }, 30000);
                } else {
                    this.notify('error', 'Error al generar el token.');
                    this.botonesBloqueados = false;
                }
            } catch (e) {
                this.notify('error', 'Error en el servidor al enviar el token.');
                this.botonesBloqueados = false;
            }
        },

        async firmarToken() {
            this.firmando = true;
            const fd = new FormData();
            fd.append('idReporte', this.id);
            fd.append('TokenValidacion', this.token);

            try {
                const resp = await axios.post(`${this.BASE_URL}/firmar-token`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    setTimeout(() => {
                        window.location.href = '/departamento-operativo/almacen';
                    }, 1500);
                } else {
                    this.notify('error', resp.data?.message || 'Token no válido.');
                    this.firmando = false;
                }
            } catch (e) {
                this.notify('error', 'Error al procesar la firma.');
                this.firmando = false;
            }
        }
    }));
});