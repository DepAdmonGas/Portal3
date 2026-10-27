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
        enviandoMensaje: false,
        offcanvasMensajes: null,

        init(){

            this.buscar();

            this.$nextTick(() => {
                const modalDetalle = document.getElementById('modalDetalleReporte');
                if (modalDetalle) this.modalDetalle = bootstrap.Modal.getOrCreateInstance(modalDetalle);
                const offcanvas = document.getElementById('modalMensajes');
                if (offcanvas) this.offcanvasMensajes = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);
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
            this.mensajes = [];
            this.nuevoMensaje = '';
            this.loadingMensajes = true;
            try {
                await this.cargarMensajes(row);
                this.$nextTick(() => {
                    this.offcanvasMensajes = bootstrap.Offcanvas.getOrCreateInstance(
                        document.getElementById('modalMensajes')
                    );
                    this.offcanvasMensajes.show();
                });
            } catch (error) {
                this.notify('error', error.response?.data?.message || error.message || 'No fue posible cargar los comentarios.');
            } finally {
                this.loadingMensajes = false;
            }
        },

        async cargarMensajes(row){
            const {data} = await axios.get(
                `/sasisopa/reporte-diario/mensajes/${row.id}/${row.id_fecha}`
            );
            if (!data.success) throw new Error(data.message || 'No fue posible cargar los comentarios.');
            this.mensajes = (data.data || []).map(item => ({
                ...item,
                fechaFormateada: [item.fecha, item.hora].filter(Boolean).join(' ')
            }));
            this.scrollChatToBottom();
        },

        scrollChatToBottom(){
            this.$nextTick(() => {
                const chat = this.$refs.chatContainer;
                if (chat) chat.scrollTop = chat.scrollHeight;
            });
        },

        async enviarMensaje(){
            const mensaje = this.nuevoMensaje.trim();
            if (this.enviandoMensaje || !mensaje || !this.mensajeRow) return;
            this.enviandoMensaje = true;
            try {
                const {data} = await axios.post(
                    '/sasisopa/reporte-diario/mensajes/create',
                    {
                        idReporte: this.mensajeRow.id,
                        fecha: this.mensajeRow.id_fecha,
                        mensaje
                    }
                );
                if (!data.success) throw new Error(data.message || 'No fue posible agregar el comentario.');
                this.nuevoMensaje = '';
                await this.cargarMensajes(this.mensajeRow);
                this.mensajeRow.mensajes.total = data.total;
            } catch (error) {
                this.notify('error', error.response?.data?.message || error.message || 'No fue posible agregar el comentario.');
            } finally {
                this.enviandoMensaje = false;
            }
        }

    }));

});
