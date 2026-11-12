document.addEventListener('alpine:init', () => {
    Alpine.data('corteDiario', () => ({

        year: new Date().getFullYear(),
        meses: [],
        loading: false,
        url: '',

        init() {
            this.year = Number(
                localStorage.getItem('reporteDiarioYear')
                ?? this.$el.dataset.year
                ?? new Date().getFullYear()
            );
            this.year = Math.min(new Date().getFullYear(), Math.max(2019, this.year));
            this.url = '/sasisopa/reporte-diario/facturas/' + this.year;
            this.actualizarYearBreadcrumb();

            this.$watch('year', value => {
                localStorage.setItem('reporteDiarioYear', value);
            });

            this.buscar();

        },

        async buscar() {
           this.loading = true;

            try{

                const { data } = await axios.get(
                    '/sasisopa/reporte-diario/meses',
                    {
                        params:{
                            year:this.year
                        }
                    }
                );

                if(data.success){

                    this.meses = data.data;
                    this.url = '/sasisopa/reporte-diario/facturas/' + this.year;

                }

            }finally{

                this.loading = false;

            }

            
        },

        seleccionarYear(year) {
            const seleccionado = Number(year);
            if (!Number.isInteger(seleccionado) || seleccionado < 2019 || seleccionado > new Date().getFullYear()) return;

            this.year = seleccionado;
            this.url = '/sasisopa/reporte-diario/facturas/' + this.year;
            this.actualizarYearBreadcrumb();
            this.buscar();
        },

        actualizarYearBreadcrumb() {
            const etiqueta = document.getElementById('reporte-diario-year-label');
            if (etiqueta) etiqueta.textContent = this.year;
        },

        abrir(item){

            if(!item.habilitado){

                return;

            }

            location.href =
                `/sasisopa/reporte-diario/${item.mes}/${item.year}`;

        }

    }));

    document.addEventListener('click', event => {
        const opcion = event.target.closest('[data-reporte-diario-year]');
        if (!opcion) return;

        event.preventDefault();

        const contenedor = document.getElementById('container');
        if (!contenedor) return;

        const componente = Alpine.$data(contenedor);
        if (componente && typeof componente.seleccionarYear === 'function') {
            componente.seleccionarYear(opcion.dataset.reporteDiarioYear);
        }
    });
});
