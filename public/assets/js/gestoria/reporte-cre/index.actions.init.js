document.addEventListener('alpine:init', () => {

    Alpine.data('reporteCre', (idEstacion) => ({

        idEstacion: Number(idEstacion),

        years: Array.from(
            {
                length:
                    new Date().getFullYear()
                    - 2019
                    + 1
            },
            (_, index) =>
                new Date().getFullYear()
                - index
        ),

        meses: [

            {
                id: 0,
                nombre: 'Todo el año'
            },

            {
                id: 1,
                nombre: 'Enero'
            },

            {
                id: 2,
                nombre: 'Febrero'
            },

            {
                id: 3,
                nombre: 'Marzo'
            },

            {
                id: 4,
                nombre: 'Abril'
            },

            {
                id: 5,
                nombre: 'Mayo'
            },

            {
                id: 6,
                nombre: 'Junio'
            },

            {
                id: 7,
                nombre: 'Julio'
            },

            {
                id: 8,
                nombre: 'Agosto'
            },

            {
                id: 9,
                nombre: 'Septiembre'
            },

            {
                id: 10,
                nombre: 'Octubre'
            },

            {
                id: 11,
                nombre: 'Noviembre'
            },

            {
                id: 12,
                nombre: 'Diciembre'
            }

        ],


        filtros: {

            year: null,

            mes: null

        },

        reporte: null,

        cargando: false,

        buscado: false,

        requestId: 0,

        chat: {

            dia: null,

            mensajes: [],

            mensaje: '',

            cargando: false,

            enviando: false,

            offcanvas: null

        },


        async init() {

            const fechaActual =
                new Date();


            this.filtros.year =
                fechaActual.getFullYear();


            this.filtros.mes =
                fechaActual.getMonth() + 1;


            await this.buscar();

        },

        async cambiarYear() {

            await this.buscar();

        },


        async cambiarMes() {

            await this.buscar();

        },


        async buscar() {

            if (!this.filtros.year) {

                return;

            }

            if (
                this.filtros.mes === null ||
                this.filtros.mes === undefined
            ) {

                return;

            }

            const currentRequest =
                ++this.requestId;


            this.cargando = true;

            this.buscado = false;


            try {

                const response =
                    await axios.get(
                        '/gestoria/reporte-cre/' +
                        this.idEstacion +
                        '/data',
                        {
                            params: {

                                year:
                                    Number(
                                        this.filtros.year
                                    ),

                                mes:
                                    Number(
                                        this.filtros.mes
                                    )

                            }
                        }
                    );

                if (
                    currentRequest !==
                    this.requestId
                ) {

                    return;

                }


                this.reporte =
                    response.data?.data ?? null;


            } catch (error) {

                if (
                    currentRequest !==
                    this.requestId
                ) {

                    return;

                }


                console.error(
                    'Error al cargar reporte CRE:',
                    error
                );


                this.reporte = null;


                this.notify(
                    'error',
                    'No fue posible cargar el reporte CRE.'
                );


            } finally {

                if (
                    currentRequest ===
                    this.requestId
                ) {

                    this.cargando = false;

                    this.buscado = true;

                }

            }

        },

        nombreMes(idMes) {

            const mes =
                this.meses.find(
                    item =>
                        Number(item.id) ===
                        Number(idMes)
                );


            return mes
                ? mes.nombre
                : '';

        },

        get periodoTexto() {

            if (!this.filtros.year) {

                return '';

            }

            if (
                Number(
                    this.filtros.mes
                ) === 0
            ) {

                return (
                    'Año ' +
                    this.filtros.year
                );

            }


            const mes =
                this.nombreMes(
                    this.filtros.mes
                );


            if (!mes) {

                return String(
                    this.filtros.year
                );

            }


            return (
                mes +
                ' del ' +
                this.filtros.year
            );

        },

        get tieneProductos() {

            return (
                this.reporte !== null &&
                Array.isArray(
                    this.reporte.productos
                ) &&
                this.reporte.productos.length > 0
            );

        },


        get tieneDias() {

            return (
                this.reporte !== null &&
                Array.isArray(
                    this.reporte.dias
                ) &&
                this.reporte.dias.length > 0
            );

        },

        formatearNumero(valor) {

            const numero =
                Number(
                    valor ?? 0
                );


            if (
                Number.isNaN(numero)
            ) {

                return '0.00';

            }


            return numero.toLocaleString(
                'es-MX',
                {
                    minimumFractionDigits: 2,

                    maximumFractionDigits: 2
                }
            );

        },

        formatearMoneda(valor) {

            const numero =
                Number(
                    valor ?? 0
                );


            if (
                Number.isNaN(numero)
            ) {

                return '$0.00';

            }


            return numero.toLocaleString(
                'es-MX',
                {
                    style:
                        'currency',

                    currency:
                        'MXN',

                    minimumFractionDigits:
                        2,

                    maximumFractionDigits:
                        2
                }
            );

        },

        formatearFecha(fecha) {

            if (!fecha) {

                return '';

            }

            const partes =
                String(fecha)
                    .split('-');


            if (
                partes.length !== 3
            ) {

                return fecha;

            }


            return (
                partes[2] +
                '/' +
                partes[1] +
                '/' +
                partes[0]
            );

        },

        //--------------

async abrirChat(dia) {

    this.chat.dia = dia;

    this.chat.mensajes = [];

    this.chat.mensaje = '';

    this.chat.cargando = true;


    const elemento =
        document.getElementById(
            'offcanvasReporteChat'
        );


    if (!elemento) {

        return;

    }


    this.chat.offcanvas =
        bootstrap.Offcanvas.getOrCreateInstance(
            elemento
        );

    this.chat.offcanvas.show();

    await this.cargarMensajes();

},

async cargarMensajes() {

    if (!this.chat.dia) {

        return;

    }


    this.chat.cargando = true;


    try {

        const response =
            await axios.get(
                '/gestoria/reporte-cre/' +
                this.idEstacion +
                '/mensajes',
                {
                    params: {

                        id_reporte:
                            this.reporte?.id,

                        id_fecha:
                            this.chat.dia.id_fecha

                    }
                }
            );


        this.chat.mensajes =
            response.data?.data ?? [];


        await this.$nextTick();


        this.scrollChat();

    } catch (error) {

        console.error(
            'Error al cargar mensajes:',
            error
        );


        this.chat.mensajes = [];


        this.notify(
            'error',
            'No fue posible cargar los mensajes.'
        );

    } finally {

        this.chat.cargando = false;

    }

},

async enviarMensaje() {

    const mensaje =
        this.chat.mensaje.trim();


    if (
        !mensaje ||
        !this.chat.dia ||
        this.chat.enviando
    ) {

        return;

    }


    this.chat.enviando = true;


    try {

        const response =
            await axios.post(
                '/gestoria/reporte-cre/' +
                this.idEstacion +
                '/mensajes/create',
                {

                    id_reporte:
                        this.reporte?.id,

                    id_fecha:
                        this.chat.dia.id_fecha,

                    mensaje:
                        mensaje

                }
            );


        const nuevoMensaje =
            response.data?.data ?? null;


        if (nuevoMensaje) {

            this.chat.mensajes.push(
                nuevoMensaje
            );

        } else {

            /*
             * Si el backend no devuelve el mensaje,
             * volvemos a consultar la conversación.
             */
            await this.cargarMensajes();

        }


        /*
         * Limpiar textarea.
         */
        this.chat.mensaje = '';


        /*
         * Actualizar contador del día.
         */
        this.chat.dia.total_mensajes =
            Number(
                this.chat.dia.total_mensajes ?? 0
            ) + 1;


        await this.$nextTick();


        this.scrollChat();

    } catch (error) {

        console.error(
            'Error al enviar mensaje:',
            error
        );


        this.notify(
            'error',
            'No fue posible enviar el mensaje.'
        );

    } finally {

        this.chat.enviando = false;

    }

},

scrollChat() {

    this.$nextTick(() => {

        const contenedor =
            this.$refs.chatMensajes;


        if (!contenedor) {

            return;

        }


        contenedor.scrollTop =
            contenedor.scrollHeight;

    });

},

descargarFacturasAnual() {

    /*
     * Solamente debe funcionar cuando:
     *
     * 0 = Todo el año.
     */
    if (
        Number(
            this.filtros.mes
        ) !== 0
    ) {

        return;

    }


    if (
        !this.filtros.year
    ) {

        return;

    }


    const year =
        Number(
            this.filtros.year
        );


    const url =
        '/gestoria/reporte-cre/' +
        this.idEstacion +
        '/facturas/anual' +
        '?year=' +
        encodeURIComponent(
            year
        );


    /*
     * La descarga la maneja directamente
     * el navegador.
     *
     * No usamos Axios porque no necesitamos
     * cargar el ZIP completo en memoria JS.
     */
    window.location.href =
        url;

},

    }));

});