document.addEventListener('alpine:init', () => {
    Alpine.data('programaAuditoria', () => ({

         registros: [],
        years: [],

        loading: false,

        fechaInicio: '',
        fechaFin: '',

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

        existeEnYear(registro, year) {

            return new Date(registro.fecha)
                .getFullYear() == year;
        },

        descargarPdf() {

    const inicio =
        this.fechaInicio.split('-')[0];

    const fin =
        this.fechaFin.split('-')[0];

    window.open(
        `/sasisopa/auditorias/programa/pdf/${inicio}/${fin}`,
        '_blank'
    );
}


    }));
});