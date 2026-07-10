document.addEventListener('alpine:init', () => {

    Alpine.data('calendario', () => ({

        modalDetalle: null,
        modalDia: null,

        detalle: {},

        fechaSeleccionada: '',
        actividadesDia: [],

        calendar: null,
        loading: false,

        calendarsEvents: {
            Danger: 'danger',
            Success: 'success',
            Primary: 'primary',
            Warning: 'warning'
        },

        totales: {
            pendientes:0,
            finalizados:0,
            reagendar:0,
            total:0
        },

        modalActividad: null,
        actividadesDisponibles: [],
        nuevaActividad: {
            actividad: '',
            fecha: ''
        },


        checkWindowWidth() {
            return window.innerWidth <= 1199;
        },


        /*
        |--------------------------------------------------------------------------
        | Guardar posición actual del calendario
        |--------------------------------------------------------------------------
        */

        guardarEstadoCalendario() {

            if (!this.calendar) {
                return;
            }

            localStorage.setItem(
                'calendario_estado',
                JSON.stringify({

                    date: this.calendar
                        .getDate()
                        .toISOString(),

                    view: this.calendar
                        .view
                        .type

                })
            );

        },


        /*
        |--------------------------------------------------------------------------
        | Restaurar posición del calendario
        |--------------------------------------------------------------------------
        */

        init() {


            this.modalDetalle =
                new bootstrap.Modal(
                    document.getElementById(
                        'modalDetalle'
                    )
                );


            this.modalDia =
                new bootstrap.Modal(
                    document.getElementById(
                        'modalDia'
                    )
                );

                this.modalActividad = new bootstrap.Modal(
                    document.getElementById('modalActividad')
                );


            const estado = this.obtenerEstadoCalendario();
            this.calendar =
                new FullCalendar.Calendar(

                    document.getElementById(
                        'calendar'
                    ),

                    {

                    locale:'es',


                    initialView: estado?.view
                    ?? (this.checkWindowWidth()
                        ? 'listWeek'
                        : 'dayGridMonth'),

                    initialDate: estado?.date ?? new Date(),


                    firstDay:0,

                    fixedWeekCount:false,


                    height:
                        this.checkWindowWidth()
                        ? 900
                        : 1052,


                    dayMaxEvents:true,

                     moreLinkText: function(num) {
                        return `+${num} más`;
                    },


                    displayEventTime:false,


                    headerToolbar:{

                        left:'prev,next today',

                        center:'title',

                        right:''

                    },


                    buttonText:{

                        today:'Hoy'

                    },


                    dayHeaderFormat:{

                        weekday:'long'

                    },



                    events:
                    (info,successCallback,failureCallback)=>{


                        axios.get(
                            '/sasisopa/calendario/eventos',
                            {

                            params:{

                                start:
                                    info.startStr,

                                end:
                                    info.endStr

                            }

                        })

                        .then(response=>{

                            this.totales = response.data.totales;
                            successCallback(
                                response.data.eventos
                            );

                        })

                        .catch(
                            failureCallback
                        );


                    },



                    eventClassNames:
                    ({event})=>{


                        const color =
                            this.calendarsEvents[
                                event.extendedProps.calendar
                            ];


                        return [

                            'event-fc-color',

                            'fc-bg-' + color

                        ];


                    },



                    dateClick:(info)=>{


                        const [
                            year,
                            month,
                            day
                        ] =
                        info.dateStr.split('-');



                        const meses=[

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
                        `${parseInt(day)} de ${meses[
                            parseInt(month)-1
                        ]} del ${year}`;



                        axios.get(
                            '/sasisopa/calendario/dia',
                            {

                            params:{

                                fecha:
                                info.dateStr

                            }

                        })

                        .then(response=>{


                            this.actividadesDia =
                                response.data;


                            this.modalDia.show();


                        });


                    },



                    eventClick:
                    async(info)=>{


                        const evento =
                            info.event.extendedProps;



                        /*
                        Cursos abren detalle
                        */

                        if(
                            evento.tipo === 'curso'
                        ){

                            this.abrirDetalle(
                                evento
                            );

                            return;

                        }



                        /*
                        Actividades normales
                        */

                        try {


                            const res =
                            await this.createAction({

                                url:
                                '/sasisopa/calendario/actividad/abrir',


                                data:{

                                    id:
                                    evento.id

                                }

                            });



                            if(res.success){


                                this.guardarEstadoCalendario();



                               const fecha = this.calendar.getDate().toISOString().substring(0, 10);
                                window.location.href = `${res.url}?returnDate=${fecha}`;


                            }



                        }
                        catch(error){

                            console.error(error);

                        }


                    },



                    eventDidMount:(info)=>{


                        info.el.title =
                            info.event.title;


                        info.el.style.cursor =
                            'pointer';


                    },



                    windowResize:()=>{


                        if(
                            this.checkWindowWidth()
                        ){

                            this.calendar.changeView(
                                'listWeek'
                            );


                            this.calendar.setOption(
                                'height',
                                900
                            );


                        }
                        else{


                            this.calendar.changeView(
                                'dayGridMonth'
                            );


                            this.calendar.setOption(
                                'height',
                                1052
                            );


                        }


                    }



                });


            this.calendar.render();
            this.cambiarTamanoDias();
            localStorage.removeItem('calendario_estado');


            /*
            Restaurar después de cargar
            */

      
        },

        cambiarTamanoDias() {

    document.querySelectorAll('.fc-daygrid-day-number')
        .forEach(item => {

            item.style.fontSize = '1rem';
            item.style.fontWeight = '700';

        });

},



        recargar(){


            this.calendar.refetchEvents();


        },



        abrirDetalle(item){


            axios.get(
                '/sasisopa/calendario/detalle',
                {

                params:{

                    id:item.id,

                    tipo:item.tipo

                }

            })


            .then(response=>{


                this.detalle =
                    response.data;


                this.modalDetalle.show();


            });


        },



        async reagendarCurso(){


            try{


                const res =
                await this.createAction({

                    url:
                    '/sasisopa/calendario/curso/reagendar',


                    data:{

                        id:
                        this.detalle.id

                    }

                });



                if(res.success){


                    this.modalDetalle.hide();


                    this.recargar();


                }



            }
            catch(error){


                this.notify(
                    'error',
                    'Error al guardar'
                );


            }


        },

        obtenerEstadoCalendario() {

    const estado = localStorage.getItem('calendario_estado');

    if (!estado) {
        return null;
    }

    try {

        return JSON.parse(estado);

    } catch (e) {

        return null;

    }

},

async abrirModalActividad() {

    try {

        const res = await axios.get(

            '/sasisopa/calendario/actividades'

        );

        this.actividadesDisponibles = res.data;

        this.nuevaActividad = {

            actividad: '',

            fecha: new Date()

                .toISOString()

                .substring(0,10)

        };

        this.modalActividad.show();

    } catch (error) {

        this.notify(

            'error',

            'No fue posible cargar las actividades.'

        );

    }

},

async guardarActividad() {

    if (!this.nuevaActividad.actividad) {
        this.notify(
            'error',
            'Seleccione una actividad.'
        );
        return;
    }

    if (!this.nuevaActividad.fecha) {
        this.notify(
            'warning',
            'Seleccione la fecha.'
        );
        return;
    }

    try {

        const res =
            await this.createAction({

                url:
                '/sasisopa/calendario/actividad/create',

                data: this.nuevaActividad

            });

        if (res.success) {

            this.modalActividad.hide();

            this.recargar();

            this.notify(

                'success',

                'Actividad agregada.'

            );

        }

    } catch (error) {

        this.notify(

            'error',

            'Ocurrió un error.'

        );

    }

},



    }));

});