function reportesIndex() {

    return {

        cargando: false,
        buscando: false,
        cargandoAutolavado: false,

        estaciones: [],
        reportes: [],

        reporte: {
            nombre: '',
            tipo: 'anual',
            estacion: '',
        },

        filtros: {
            idEstacion: 0,
            year: new Date().getFullYear(),
            dia: '',
            mes: 0,
        },

        busqueda: {
            idEstacion: '',
            year: '',
            dia: '',
            mes: '0',
        },

        errors: {
            idEstacion: '',
            periodo: '',
        },

        detalleAutolavado: {
            tipo: '',
            year: null,
            dia: '',
            dia_formateado: '',
            meses: [],
            total: 0,
        },

        modalBuscar: null,
        modalAutolavado: null,

        async init() {

            const modalBuscar =
                document.getElementById(
                    'modalBuscarReporte'
                );

            const modalAutolavado =
                document.getElementById(
                    'modalAutolavado'
                );

            if (modalBuscar) {

                this.modalBuscar =
                    new bootstrap.Modal(
                        modalBuscar
                    );
            }

            if (modalAutolavado) {

                this.modalAutolavado =
                    new bootstrap.Modal(
                        modalAutolavado
                    );
            }

            await Promise.all([
                this.cargarEstaciones(),
                this.cargarReportes()
            ]);
        },

        get years() {

            const actual =
                new Date().getFullYear();

            const years = [];

            for (
                let year = actual;
                year >= 2021;
                year--
            ) {

                years.push(year);
            }

            return years;
        },


        get mostrarDia() {

            return Number(
                this.busqueda.idEstacion
            ) === 9;
        },


        get yearDeshabilitado() {

            return (
                this.mostrarDia
                && Boolean(
                    this.busqueda.dia
                )
            );
        },


        get diaDeshabilitado() {

            return (
                !this.mostrarDia
                || Boolean(
                    this.busqueda.year
                )
            );
        },


        get tituloReporte() {

            if (!this.reporte.nombre) {
                return '';
            }

            return (
                this.reporte.nombre
            );
        },


        get tituloAutolavado() {

            if (
                this.detalleAutolavado.tipo
                === 'anual'
            ) {

                return (
                    'Reporte anual '
                    + this.detalleAutolavado.year
                );
            }

            if (
                this.detalleAutolavado.tipo
                === 'diario'
            ) {

                return (
                    'Reporte diario '
                    + (
                        this.detalleAutolavado
                            .dia_formateado
                        ?? ''
                    )
                );
            }

            return '';
        },


        /*
        |--------------------------------------------------------------------------
        | Estaciones
        |--------------------------------------------------------------------------
        */

        async cargarEstaciones() {

            try {

                const response =
                    await axios.get(
                        '/reportes/estaciones'
                    );

                if (
                    response.data?.success
                ) {

                    this.estaciones =
                        response.data?.data
                        ?? [];

                    return;
                }

                this.estaciones = [];

            } catch (error) {

                console.error(
                    'Error al cargar estaciones:',
                    error
                );

                this.estaciones = [];
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Reportes
        |--------------------------------------------------------------------------
        */

        async cargarReportes() {

            try {

                this.cargando = true;

                const response =
                    await axios.get(
                        '/reportes/data',
                        {
                            params: {
                                idEstacion:
                                    this.filtros
                                        .idEstacion,

                                year:
                                    this.filtros
                                        .year,

                                dia:
                                    this.filtros
                                        .dia,

                                mes:
                                    this.filtros
                                        .mes,
                            }
                        }
                    );

                if (
                    response.data?.success
                ) {

                    const data =
                        response.data?.data
                        ?? {};

                    this.reportes =
                        data.reportes
                        ?? [];

                    this.reporte =
                        data.reporte
                        ?? {
                            nombre: '',
                            tipo: 'anual',
                            estacion: '',
                        };

                    return;
                }

                this.reportes = [];

                this.notificar(
                    'error',
                    response.data?.message
                    ?? 'No fue posible cargar los reportes.'
                );

            } catch (error) {

                console.error(
                    'Error al cargar reportes:',
                    error
                );

                this.reportes = [];

                this.notificar(
                    'error',
                    error.response?.data?.message
                    ?? 'No fue posible cargar los reportes.'
                );

            } finally {

                this.cargando = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Modal buscador
        |--------------------------------------------------------------------------
        */

        abrirBuscador() {

            this.limpiarBuscador();

            this.modalBuscar?.show();
        },


        limpiarBuscador() {

            this.busqueda = {
                idEstacion: '',
                year: '',
                dia: '',
                mes: '0',
            };

            this.errors = {
                idEstacion: '',
                periodo: '',
            };
        },


        cambioEstacion() {

            this.errors.idEstacion = '';
            this.errors.periodo = '';

            if (!this.mostrarDia) {

                this.busqueda.dia = '';
            }
        },


        cambioYear() {

            this.errors.periodo = '';

            if (
                this.busqueda.year
                && this.mostrarDia
            ) {

                this.busqueda.dia = '';
            }
        },


        cambioDia() {

            this.errors.periodo = '';

            if (this.busqueda.dia) {

                this.busqueda.year = '';
                this.busqueda.mes = '0';
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Buscar
        |--------------------------------------------------------------------------
        */

        async buscar() {

            if (this.buscando) {
                return;
            }

            this.errors.idEstacion = '';
            this.errors.periodo = '';

            let valido = true;


            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

            if (
                this.busqueda.idEstacion
                === ''
            ) {

                this.errors.idEstacion =
                    'Selecciona una estación.';

                valido = false;
            }


            /*
            |--------------------------------------------------------------------------
            | Periodo
            |--------------------------------------------------------------------------
            */

            if (
                !this.busqueda.year
                && !this.busqueda.dia
            ) {

                this.errors.periodo =
                    'Selecciona un año'
                    + (
                        this.mostrarDia
                            ? ' o un día.'
                            : '.'
                    );

                valido = false;
            }


            /*
            |--------------------------------------------------------------------------
            | Día solo estación 9
            |--------------------------------------------------------------------------
            */

            if (
                this.busqueda.dia
                && !this.mostrarDia
            ) {

                this.errors.periodo =
                    'La búsqueda diaria solo está disponible para esta estación específica.';

                valido = false;
            }


            if (!valido) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Buscar
            |--------------------------------------------------------------------------
            */

            try {

                this.buscando = true;

                this.filtros = {

                    idEstacion:
                        Number(
                            this.busqueda
                                .idEstacion
                        ),

                    year:
                        this.busqueda.year,

                    dia:
                        this.busqueda.dia,

                    mes:
                        Number(
                            this.busqueda.mes
                            || 0
                        ),
                };

                await this.cargarReportes();

                this.modalBuscar?.hide();

            } finally {

                this.buscando = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Acciones
        |--------------------------------------------------------------------------
        */

        ejecutarAccion(
            reporte,
            accion
        ) {

            if (
                accion.tipo
                === 'detalle'
                && reporte.id
                === 'autolavado'
            ) {

                this.verDetalleAutolavado();

                return;
            }

            if (accion.url) {

                window.location.href =
                    accion.url;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Autolavado
        |--------------------------------------------------------------------------
        */

        async verDetalleAutolavado() {

            this.detalleAutolavado = {
                tipo: '',
                year: null,
                dia: '',
                dia_formateado: '',
                meses: [],
                total: 0,
            };

            this.modalAutolavado?.show();

            try {

                this.cargandoAutolavado =
                    true;


                /*
                |--------------------------------------------------------------------------
                | Anual
                |--------------------------------------------------------------------------
                */

                if (
                    this.reporte.tipo
                    === 'anual'
                ) {

                    const response =
                        await axios.get(
                            '/reportes/autolavado/anual',
                            {
                                params: {
                                    idEstacion:
                                        this.filtros
                                            .idEstacion,

                                    year:
                                        this.filtros
                                            .year,
                                }
                            }
                        );

                    if (
                        !response.data?.success
                    ) {

                        throw new Error(
                            response.data?.message
                            ?? 'No fue posible cargar el detalle.'
                        );
                    }

                    const data =
                        response.data?.data
                        ?? {};

                    this.detalleAutolavado = {

                        tipo: 'anual',

                        year:
                            data.year
                            ?? null,

                        dia: '',

                        dia_formateado: '',

                        meses:
                            data.meses
                            ?? [],

                        total:
                            Number(
                                data.total
                                ?? 0
                            ),
                    };

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Diario
                |--------------------------------------------------------------------------
                */

                const response =
                    await axios.get(
                        '/reportes/autolavado/diario',
                        {
                            params: {
                                idEstacion:
                                    this.filtros
                                        .idEstacion,

                                dia:
                                    this.filtros
                                        .dia,
                            }
                        }
                    );

                if (
                    !response.data?.success
                ) {

                    throw new Error(
                        response.data?.message
                        ?? 'No fue posible cargar el detalle.'
                    );
                }

                const data =
                    response.data?.data
                    ?? {};

                this.detalleAutolavado = {

                    tipo: 'diario',

                    year: null,

                    dia:
                        data.dia
                        ?? '',

                    dia_formateado:
                        data.dia_formateado
                        ?? '',

                    meses: [],

                    total:
                        Number(
                            data.total
                            ?? 0
                        ),
                };

            } catch (error) {

                console.error(
                    'Error al cargar autolavado:',
                    error
                );

                this.modalAutolavado?.hide();

                this.notificar(
                    'error',
                    error.response?.data?.message
                    ?? error.message
                    ?? 'No fue posible cargar el reporte de autolavado.'
                );

            } finally {

                this.cargandoAutolavado =
                    false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Helpers
        |--------------------------------------------------------------------------
        */

        moneda(valor) {

            return new Intl.NumberFormat(
                'es-MX',
                {
                    style: 'currency',
                    currency: 'MXN',
                }
            ).format(
                Number(valor || 0)
            );
        },


        notificar(
            tipo,
            mensaje
        ) {

            if (
                typeof Swal
                !== 'undefined'
            ) {

                Swal.fire({
                    icon: tipo,
                    text: mensaje,
                    confirmButtonText: 'Aceptar',
                });

                return;
            }

            console.log(
                tipo,
                mensaje
            );
        },

    };
}