document.addEventListener('alpine:init', () => {
    Alpine.data('corteDiario', () => ({

        year: new Date().getFullYear(),
        meses: [],
        loading: false,
        url: '',

        init() {

            this.url = '/sasisopa/reporte-diario/facturas/' + this.year;

        this.year = Number(
            localStorage.getItem('reporteDiarioYear')
            ?? new Date().getFullYear()
        );

        this.$watch('year', value => {

            localStorage.setItem(
                'reporteDiarioYear',
                value
            );

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

        abrir(item){

            if(!item.habilitado){

                return;

            }

            location.href =
                `/sasisopa/reporte-diario/${item.mes}/${item.year}`;

        }

    }));
});