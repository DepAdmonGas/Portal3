document.addEventListener('alpine:init', () => {
    Alpine.data('reporteMes', (mes, year) => ({

        pdfUrl: '',
        mes,
        year,
        head:{
            productos:[],
            columnas:[]
        },
        rows:[],
        footer:[],
        loading:false,

        //Detalle

        modalDetalle: null,
        detalleFecha: '',
        detalleProductos: [],
        loadingDetalle: false,

        //Mensaje

        mensajes: [],
        nuevoMensaje: '',
        mensajeRow: null,
        loadingMensajes: false,

        init(){
            this.buscar();

              this.$nextTick(() => {
                this.modalDetalle = new bootstrap.Modal(
                    document.getElementById('modalDetalleReporte')
                );
            });

            this.pdfUrl = '/sasisopa/reporte-diario/pdf?idMes=' + this.mes + '&idYear=' + this.year;

        },

        async buscar() {

        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/reporte-diario/mes/datatable',
                {
                    params: {
                        mes: this.mes,
                        year: this.year
                    }
                }
            );

            this.head = data.head;

            // Preparar filas
            this.rows = data.data.map(row => ({

                ...row,

                celdas: row.productos.flatMap(p => ([
                    {
                        valor: p.vi,
                        clase: 'text-center'
                    },
                    {
                        valor: p.vv,
                        clase: 'text-center'
                    },
                    {
                        valor: p.vf,
                        clase: 'text-center'
                    },
                    {
                        valor: p.vc,
                        clase: 'text-center fw-bolder'
                    },
                    {
                        valor: p.merma,
                        clase: 'text-center text-danger fw-bolder'
                    }
                ]))

            }));

            // Preparar footer
            this.footer = data.footer.flatMap(p => ([
                {
                    valor: p.vi,
                    clase: 'text-center'
                },
                {
                    valor: p.vv,
                    clase: 'text-center'
                },
                {
                    valor: p.vf,
                    clase: 'text-center'
                },
                {
                    valor: p.vc,
                    clase: 'text-center'
                },
                {
                    valor: p.merma,
                    clase: 'text-center text-danger'
                }
            ]));

        } finally {

            this.loading = false;

        }

        },

        nuevo(){
            location.href =
                `/sasisopa/reporte-diario/nuevo/${this.mes}/${this.year}`;
        },

        editar(row){
            location.href =
                `/sasisopa/reporte-diario/editar/${row.id}/${row.id_fecha}`;
        },

        //Detalle

        async detalle(row) {

            this.loadingDetalle = true;

            try {

                const { data } = await axios.get(
                    `/sasisopa/reporte-diario/editar/base/${row.id}/${row.fecha}`
                );

                if (!data.success) {
                    this.notify('error', data.message);
                    return;
                }

                this.detalleFecha = data.fecha_larga;
                this.detalleProductos = data.data;

                const modal = new bootstrap.Modal(
                    document.getElementById('modalDetalleReporte')
                );

                modal.show();

            } catch (e) {

                this.notify(
                    'error',
                    'No fue posible obtener el detalle.'
                );

            } finally {

                this.loadingDetalle = false;

            }

        },

         //Mensaje

         async abrirMensajes(row){

            this.mensajeRow = row;
            this.loadingMensajes = true;

            try {

                await this.cargarMensajes(row);

                new bootstrap.Modal(
                    document.getElementById('modalMensajes')
                ).show();

            } finally {
                this.loadingMensajes = false;
            }
        },

        async cargarMensajes(row){

        const {data} = await axios.get(
            `/sasisopa/reporte-diario/mensajes/${row.id}/${row.id_fecha}`
        );

        if(data.success){
            this.mensajes = data.data;
        }
    },

        async enviarMensaje(){

    if(this.nuevoMensaje.trim()===''){
        return;
    }

    const {data} = await axios.post(
        '/sasisopa/reporte-diario/mensajes/create',
        {
            idReporte : this.mensajeRow.id,
            fecha     : this.mensajeRow.id_fecha,
            mensaje   : this.nuevoMensaje
        }
    );

    if(data.success){
        this.nuevoMensaje = '';
        await this.cargarMensajes(this.mensajeRow);
        this.mensajeRow.mensajes.total = data.total;
    }
}

    }));

});