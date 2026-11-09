document.addEventListener('alpine:init', () => {
    Alpine.data('programaAuditoria', () => ({

         registros: [],
        years: [],

        loading: false,

        fechaInicio: '',
        fechaFin: '',
        periodoVisualizado: '',

        modalBuscar: null,

        init() {

            this.modalBuscar =
                new bootstrap.Modal(
                    document.getElementById('modalBuscar')
                );

            const year = new Date().getFullYear();

            this.fechaInicio = `${year}-01-01`;
            this.fechaFin = `${year + 1}-12-31`;
            
            this.buscar();
        },

        abrirModalBuscar() {
            this.modalBuscar.show();
        },

        async buscar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/auditorias/programa/formato',
                    {
                        params: {
                            inicio: this.fechaInicio,
                            fin: this.fechaFin
                        }
                    }
                );

                if (data.success) {

                    this.registros = data.data;
                    this.periodoVisualizado = this.formatearPeriodo(
                        this.fechaInicio,
                        this.fechaFin
                    );

                    this.years = [];

                    for (
                        let y = Number(data.inicio);
                        y <= Number(data.fin);
                        y++
                    ) {
                        this.years.push(y);
                    }
                }

            } finally {

                this.loading = false;
            }

            this.modalBuscar.hide();
        },

        mes(fecha) {

            const mes = new Date(fecha)
                .toLocaleDateString(
                    'es-MX',
                    {
                        month: 'long'
                    }
                );

            return mes.charAt(0).toUpperCase() + mes.slice(1);
        },

        formatearPeriodo(inicio, fin) {
            const formatearFecha = fecha => {
                const [year, month, day] = String(fecha || '').split('-').map(Number);
                if (!year || !month || !day) return '';

                return new Intl.DateTimeFormat('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(new Date(year, month - 1, day));
            };

            const desde = formatearFecha(inicio);
            const hasta = formatearFecha(fin);
            return desde && hasta ? `${desde} – ${hasta}` : '';
        },

        existeEnYear(registro, year) {

            return new Date(registro.fecha)
                .getFullYear() == year;
        },

        async descargarPdf() {

            const inicio = this.fechaInicio.split('-')[0];
            const fin = this.fechaFin.split('-')[0];
            const url = `/sasisopa/auditorias/programa/pdf/${inicio}/${fin}`;

            window.loader?.show();

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/pdf' }
                });

                if (!response.ok) {
                    throw new Error('No fue posible descargar el programa de auditorías.');
                }

                const archivo = await response.blob();
                if (!archivo.type.includes('pdf')) {
                    throw new Error('El servidor no devolvió un archivo PDF.');
                }

                const archivoUrl = URL.createObjectURL(archivo);
                const enlace = document.createElement('a');
                enlace.href = archivoUrl;
                enlace.download = 'Programa de auditorias.pdf';
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
                window.setTimeout(() => URL.revokeObjectURL(archivoUrl), 1000);
            } catch (error) {
                this.notify('error', error?.message || 'Error al descargar el archivo');
            } finally {
                window.loader?.hide();
            }
        }


    }));
});
