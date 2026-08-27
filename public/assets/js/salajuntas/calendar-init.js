document.addEventListener('alpine:init', () => {

    Alpine.data('calendario', (modulo = 'sala-juntas') => ({

        /*
        |--------------------------------------------------------------------------
        | ESTADO GENERAL
        |--------------------------------------------------------------------------
        */

        modulo,

        calendar: null,

        fechaSeleccionada: '',

        actividadesDia: [],

        juntaSeleccionada: null,

        /*
        |--------------------------------------------------------------------------
        | MODALES
        |--------------------------------------------------------------------------
        */

        modalDia: null,

        modalJunta: null,

        modalNuevaJunta: null,

        /*
        |--------------------------------------------------------------------------
        | NUEVA JUNTA
        |--------------------------------------------------------------------------
        */

        usuariosConvocantes: [],

        puestosDisponibles: [],

        personalDisponible: [],

        horasDisponibles: [],

        horasTermino: [],

        cargandoHoras: false,

        guardandoJunta: false,

        errorPersonal: false,

        errorNuevaJunta: '',

        nuevaJunta: {
            descripcion: '',
            idUsuario: '',
            fecha: '',
            hora_inicio: '',
            hora_termino: '',
            departamentos: [],
            personal: []
        },


        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        init() {

            /*
             * Modal día
             */
            const modalDiaElement =
                document.getElementById(
                    'modalDia'
                );

            if (modalDiaElement) {

                this.modalDia =
                    bootstrap.Modal.getOrCreateInstance(
                        modalDiaElement
                    );

            }


            /*
             * Modal detalle
             */
            const modalJuntaElement =
                document.getElementById(
                    'modalJunta'
                );

            if (modalJuntaElement) {

                this.modalJunta =
                    bootstrap.Modal.getOrCreateInstance(
                        modalJuntaElement
                    );

            }


            /*
             * Modal nueva junta
             */
            const modalNuevaJuntaElement =
                document.getElementById(
                    'modalNuevaJunta'
                );

            if (modalNuevaJuntaElement) {

                this.modalNuevaJunta =
                    bootstrap.Modal.getOrCreateInstance(
                        modalNuevaJuntaElement
                    );

            }


            /*
             * FullCalendar
             */
            const calendarElement =
                document.getElementById(
                    'calendar'
                );

            if (!calendarElement) {

                console.error(
                    'No se encontró el elemento #calendar'
                );

                return;
            }


            const estado =
                this.obtenerEstadoCalendario();


            this.calendar =
                new FullCalendar.Calendar(
                    calendarElement,
                    {

                        locale: 'es',

                        initialView:
                            estado?.view
                            ?? (
                                this.checkWindowWidth()
                                    ? 'listWeek'
                                    : 'dayGridMonth'
                            ),

                        initialDate:
                            estado?.date
                            ?? this.fechaLocal(),

                        firstDay: 0,

                        fixedWeekCount: false,

                        height:
                            this.checkWindowWidth()
                                ? 900
                                : 1052,

                        dayMaxEvents: true,

                        moreLinkText(num) {
                            return `+${num} más`;
                        },

                        displayEventTime: false,

                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: ''
                        },

                        buttonText: {
                            today: 'Hoy'
                        },

                        dayHeaderFormat: {
                            weekday: 'long'
                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CARGAR EVENTOS
                        |--------------------------------------------------------------------------
                        */

                        events: (
                            info,
                            successCallback,
                            failureCallback
                        ) => {

                            axios.get(
                                '/sala-juntas/calendario/data',
                                {
                                    params: {
                                        start:
                                            info.startStr,

                                        end:
                                            info.endStr
                                    }
                                }
                            )
                            .then(response => {

                                const eventos =
                                    response.data.eventos
                                    ?? [];

                                successCallback(
                                    eventos
                                );

                            })
                            .catch(error => {

                                console.error(
                                    'Error al cargar las juntas:',
                                    error.response?.data
                                    ?? error
                                );

                                failureCallback(
                                    error
                                );

                            });

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CLICK DÍA
                        |--------------------------------------------------------------------------
                        */

                        dateClick: (info) => {

                            this.abrirDia(
                                info.dateStr
                            );

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CLICK EVENTO
                        |--------------------------------------------------------------------------
                        */

                        eventClick: (info) => {

                            this.abrirJunta(
                                info.event.id
                            );

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | EVENTO RENDERIZADO
                        |--------------------------------------------------------------------------
                        */

                        eventDidMount: (info) => {

                            info.el.title =
                                info.event.title;

                            info.el.style.cursor =
                                'pointer';

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CAMBIO DE MES / SEMANA
                        |--------------------------------------------------------------------------
                        */

                        datesSet: () => {

                            this.cambiarTamanoDias();

                            this.guardarEstadoCalendario();

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | RESPONSIVE
                        |--------------------------------------------------------------------------
                        */

                        windowResize: () => {

                            const mobile =
                                this.checkWindowWidth();


                            if (mobile) {

                                if (
                                    this.calendar.view.type
                                    !== 'listWeek'
                                ) {

                                    this.calendar.changeView(
                                        'listWeek'
                                    );

                                }

                                this.calendar.setOption(
                                    'height',
                                    900
                                );

                                return;
                            }


                            if (
                                this.calendar.view.type
                                !== 'dayGridMonth'
                            ) {

                                this.calendar.changeView(
                                    'dayGridMonth'
                                );

                            }

                            this.calendar.setOption(
                                'height',
                                1052
                            );

                        }

                    }
                );


            this.calendar.render();

        },


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        checkWindowWidth() {

            return window.innerWidth <= 1199;

        },


        /*
        |--------------------------------------------------------------------------
        | ABRIR DÍA
        |--------------------------------------------------------------------------
        */

        async abrirDia(fecha) {

            const [
                year,
                month,
                day
            ] = fecha.split('-');


            const meses = [
                'Enero',
                'Febrero',
                'Marzo',
                'Abril',
                'Mayo',
                'Junio',
                'Julio',
                'Agosto',
                'Septiembre',
                'Octubre',
                'Noviembre',
                'Diciembre'
            ];


            this.fechaSeleccionada =
                `${parseInt(day)} de ${
                    meses[
                        parseInt(month) - 1
                    ]
                } del ${year}`;


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/dia',
                        {
                            params: {
                                fecha
                            }
                        }
                    );


                if (
                    response.data.success
                    === false
                ) {

                    this.actividadesDia = [];

                    return;
                }


                this.actividadesDia =
                    response.data.data
                    ?? [];


                if (this.modalDia) {

                    this.modalDia.show();

                }

            } catch (error) {

                console.error(
                    'Error al cargar las juntas del día:',
                    error.response?.data
                    ?? error
                );

                this.notify?.(
                    'error',
                    'No fue posible consultar las juntas del día.'
                );

            }

        },


        /*
        |--------------------------------------------------------------------------
        | ABRIR DETALLE
        |--------------------------------------------------------------------------
        */

        async abrirJunta(id) {

            if (!id) {
                return;
            }


            try {

                const response =
                    await axios.get(
                        `/sala-juntas/calendario/detalle/${id}`
                    );


                if (
                    !response.data.success
                ) {

                    this.notify?.(
                        'warning',
                        response.data.message
                        ?? 'No fue posible consultar la junta.'
                    );

                    return;
                }


                this.juntaSeleccionada =
                    response.data.data;


                /*
                 * Si modalDia está abierto,
                 * esperar a que termine de cerrar.
                 */
                const modalDiaElement =
                    document.getElementById(
                        'modalDia'
                    );


                if (
                    modalDiaElement
                    && modalDiaElement
                        .classList
                        .contains('show')
                ) {

                    modalDiaElement
                        .addEventListener(
                            'hidden.bs.modal',
                            () => {

                                if (
                                    this.modalJunta
                                ) {

                                    this.modalJunta.show();

                                }

                            },
                            {
                                once: true
                            }
                        );


                    this.modalDia.hide();

                    return;
                }


                /*
                 * Click directo desde FullCalendar
                 */
                if (this.modalJunta) {

                    this.modalJunta.show();

                }

            } catch (error) {

                console.error(
                    'Error al cargar el detalle de la junta:',
                    error.response?.data
                    ?? error
                );

                this.notify?.(
                    'error',
                    'No fue posible consultar el detalle de la junta.'
                );

            }

        },


        /*
        |--------------------------------------------------------------------------
        | NUEVA JUNTA
        |--------------------------------------------------------------------------
        */

        async abrirModalNuevaJunta(
            fecha = null
        ) {

            this.errorNuevaJunta = '';

            this.errorPersonal = false;

            this.horasDisponibles = [];

            this.horasTermino = [];


            this.nuevaJunta = {

                descripcion: '',

                idUsuario: '',

                fecha:
                    fecha
                    ?? this.fechaLocal(),

                hora_inicio: '',

                hora_termino: '',

                departamentos: [],

                personal: []

            };


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/nuevo'
                    );


                this.usuariosConvocantes =
                    response.data.convocantes
                    ?? [];


                this.puestosDisponibles =
                    response.data.puestos
                    ?? [];


                this.personalDisponible =
                    response.data.personal
                    ?? [];


                if (
                    response.data.usuario_actual
                ) {

                    this.nuevaJunta.idUsuario =
                        String(
                            response.data
                                .usuario_actual
                        );

                }


                await this
                    .cargarHorasDisponibles();


                if (
                    this.modalNuevaJunta
                ) {

                    this.modalNuevaJunta.show();

                }

            } catch (error) {

                console.error(
                    'Error al preparar nueva junta:',
                    error.response?.data
                    ?? error
                );


                this.errorNuevaJunta =
                    'No fue posible cargar la información de la reunión.';

            }

        },


        /*
        |--------------------------------------------------------------------------
        | HORARIOS DISPONIBLES
        |--------------------------------------------------------------------------
        */

        async cargarHorasDisponibles() {

            if (
                !this.nuevaJunta.fecha
            ) {

                return;

            }


            this.errorNuevaJunta = '';

            this.cargandoHoras = true;

            this.nuevaJunta.hora_inicio = '';

            this.nuevaJunta.hora_termino = '';

            this.horasDisponibles = [];

            this.horasTermino = [];


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/horas',
                        {
                            params: {
                                fecha:
                                    this.nuevaJunta.fecha
                            }
                        }
                    );


                this.horasDisponibles =
                    response.data.horas
                    ?? [];

            } catch (error) {

                console.error(
                    'Error al cargar horarios:',
                    error.response?.data
                    ?? error
                );


                this.errorNuevaJunta =
                    'No fue posible consultar los horarios disponibles.';

            } finally {

                this.cargandoHoras = false;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | CALCULAR HORAS DE TÉRMINO
        |--------------------------------------------------------------------------
        */

        actualizarHorasTermino() {

            const inicio =
                this.nuevaJunta
                    .hora_inicio;


            this.nuevaJunta
                .hora_termino = '';


            this.horasTermino = [];


            if (!inicio) {

                return;

            }


            const index =
                this.horasDisponibles
                    .indexOf(
                        inicio
                    );


            if (index === -1) {

                return;

            }


            const opciones = [];


            let horaActual =
                this.horaMasMinutos(
                    inicio,
                    30
                );


            /*
             * Agregamos finales consecutivos
             * mientras los bloques siguientes
             * continúen disponibles.
             */
            while (true) {

                opciones.push(
                    horaActual
                );


                if (
                    !this.horasDisponibles
                        .includes(
                            horaActual
                        )
                ) {

                    break;

                }


                horaActual =
                    this.horaMasMinutos(
                        horaActual,
                        30
                    );

            }


            this.horasTermino =
                [...new Set(
                    opciones
                )];

        },


        /*
        |--------------------------------------------------------------------------
        | SUMAR MINUTOS
        |--------------------------------------------------------------------------
        */

        horaMasMinutos(
            hora,
            minutos
        ) {

            if (!hora) {
                return '';
            }


            const [
                horas,
                mins
            ] = hora
                .split(':')
                .map(Number);


            const fecha =
                new Date(
                    2000,
                    0,
                    1,
                    horas,
                    mins
                );


            fecha.setMinutes(
                fecha.getMinutes()
                + minutos
            );


            return (
                String(
                    fecha.getHours()
                ).padStart(
                    2,
                    '0'
                )
                +
                ':'
                +
                String(
                    fecha.getMinutes()
                ).padStart(
                    2,
                    '0'
                )
            );

        },


        /*
        |--------------------------------------------------------------------------
        | GUARDAR JUNTA
        |--------------------------------------------------------------------------
        */

        async guardarJunta() {

            if (
                this.guardandoJunta
            ) {

                return;

            }


            this.errorNuevaJunta = '';

            this.errorPersonal = false;


            /*
             * Tema
             */
            if (
                !this.nuevaJunta
                    .descripcion
                    .trim()
            ) {

                this.errorNuevaJunta =
                    'Capture el tema de la reunión.';

                return;

            }


            /*
             * Convoca
             */
            if (
                !this.nuevaJunta
                    .idUsuario
            ) {

                this.errorNuevaJunta =
                    'Seleccione quién convoca.';

                return;

            }


            /*
             * Fecha
             */
            if (
                !this.nuevaJunta.fecha
            ) {

                this.errorNuevaJunta =
                    'Seleccione una fecha.';

                return;

            }


            /*
             * No permitir fecha anterior
             */
            if (
                this.nuevaJunta.fecha
                < this.fechaLocal()
            ) {

                this.errorNuevaJunta =
                    'La fecha seleccionada no es válida.';

                return;

            }


            /*
             * Horario
             */
            if (
                !this.nuevaJunta
                    .hora_inicio
                ||
                !this.nuevaJunta
                    .hora_termino
            ) {

                this.errorNuevaJunta =
                    'Seleccione el horario de la reunión.';

                return;

            }


            if (
                this.nuevaJunta
                    .hora_termino
                <=
                this.nuevaJunta
                    .hora_inicio
            ) {

                this.errorNuevaJunta =
                    'La hora de término debe ser posterior a la hora de inicio.';

                return;

            }


            /*
             * Departamento / personal
             */
            if (
                this.nuevaJunta
                    .departamentos
                    .length === 0
                &&
                this.nuevaJunta
                    .personal
                    .length === 0
            ) {

                this.errorPersonal = true;

                return;

            }


            this.guardandoJunta = true;


            try {

                const res =
                    await this.createAction({

                        url:
                            '/sala-juntas/calendario/create',

                        data:
                            this.nuevaJunta

                    });


                if (
                    res.success
                ) {

                    if (
                        this.modalNuevaJunta
                    ) {

                        this.modalNuevaJunta.hide();

                    }


                    this.resetNuevaJunta();


                    if (
                        this.calendar
                    ) {

                        this.calendar
                            .refetchEvents();

                    }


                    this.notify?.(
                        'success',
                        'Reunión registrada correctamente.'
                    );

                }

            } catch (error) {

                console.error(
                    'Error al guardar la junta:',
                    error.response?.data
                    ?? error
                );


                this.notify?.(
                    'error',
                    'No fue posible guardar la reunión.'
                );

            } finally {

                this.guardandoJunta = false;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | RESET FORMULARIO
        |--------------------------------------------------------------------------
        */

        resetNuevaJunta() {

            this.nuevaJunta = {

                descripcion: '',

                idUsuario: '',

                fecha: '',

                hora_inicio: '',

                hora_termino: '',

                departamentos: [],

                personal: []

            };


            this.horasDisponibles = [];

            this.horasTermino = [];

            this.errorPersonal = false;

            this.errorNuevaJunta = '';

        },


        /*
        |--------------------------------------------------------------------------
        | FECHA LOCAL YYYY-MM-DD
        |--------------------------------------------------------------------------
        */

        fechaLocal() {

            const fecha =
                new Date();


            const year =
                fecha.getFullYear();


            const month =
                String(
                    fecha.getMonth() + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    fecha.getDate()
                ).padStart(
                    2,
                    '0'
                );


            return `${year}-${month}-${day}`;

        },


        /*
        |--------------------------------------------------------------------------
        | CAMBIAR TAMAÑO DÍAS
        |--------------------------------------------------------------------------
        */

        cambiarTamanoDias() {

            this.$nextTick(() => {

                document
                    .querySelectorAll(
                        '.fc-daygrid-day-number'
                    )
                    .forEach(item => {

                        item.style.fontSize =
                            '1rem';

                        item.style.fontWeight =
                            '700';

                    });

            });

        },


        /*
        |--------------------------------------------------------------------------
        | RECARGAR CALENDARIO
        |--------------------------------------------------------------------------
        */

        recargar() {

            if (
                this.calendar
            ) {

                this.calendar
                    .refetchEvents();

            }

        },


        /*
        |--------------------------------------------------------------------------
        | GUARDAR ESTADO CALENDARIO
        |--------------------------------------------------------------------------
        */

        guardarEstadoCalendario() {

            if (
                !this.calendar
            ) {

                return;

            }


            const fecha =
                this.calendar
                    .getDate();


            const estado = {

                view:
                    this.calendar
                        .view
                        .type,

                date:
                    this.formatearFechaLocal(
                        fecha
                    )

            };


            localStorage.setItem(
                'calendario_estado',
                JSON.stringify(
                    estado
                )
            );

        },


        /*
        |--------------------------------------------------------------------------
        | RECUPERAR ESTADO
        |--------------------------------------------------------------------------
        */

        obtenerEstadoCalendario() {

            const estado =
                localStorage.getItem(
                    'calendario_estado'
                );


            if (!estado) {

                return null;

            }


            try {

                return JSON.parse(
                    estado
                );

            } catch (error) {

                localStorage.removeItem(
                    'calendario_estado'
                );

                return null;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | FORMATEAR DATE LOCAL
        |--------------------------------------------------------------------------
        */

        formatearFechaLocal(
            fecha
        ) {

            const year =
                fecha.getFullYear();


            const month =
                String(
                    fecha.getMonth()
                    + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    fecha.getDate()
                ).padStart(
                    2,
                    '0'
                );


            return `${year}-${month}-${day}`;

        }

    }));

});