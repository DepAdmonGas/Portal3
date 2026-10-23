// ============================================================
// Mantenimiento Preventivo — Almacén
// Calendario (FullCalendar) de sólo lectura.
// La estación se resuelve en el servidor (ModuleStationService),
// por lo que este componente no la envía: sólo pide start/end/fecha.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('mantenimientoPreventivoCalendario', () => ({

        BASE_URL: '/departamento-operativo/almacen/mantenimiento-preventivo',

        modalDetalle: null,
        modalDia: null,

        detalle: {},

        fechaSeleccionada: '',
        actividadesDia: [],

        calendar: null,

        calendarColors: {
            Danger: '#f56954',
            Warning: '#f39c12',
            Success: '#27ae60',
            Primary: '#0073b7'
        },

        totales: {
            pendientes: 0,
            finalizados: 0,
            total: 0
        },

        checkWindowWidth() {
            return window.innerWidth <= 1199;
        },

        init() {
            if (!document.getElementById('calendar')) {
                return;
            }

            this.modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalle'));
            this.modalDia = new bootstrap.Modal(document.getElementById('modalDia'));

            const container = document.getElementById('container');
            if (container && container.dataset.baseUrl) {
                this.BASE_URL = container.dataset.baseUrl;
            }

            this.calendar = new FullCalendar.Calendar(
                document.getElementById('calendar'),
                {
                    locale: 'es',
                    initialView: this.checkWindowWidth() ? 'listWeek' : 'dayGridMonth',
                    initialDate: new Date(),
                    firstDay: 0,
                    fixedWeekCount: false,
                    height: this.checkWindowWidth() ? 900 : 1052,
                    dayMaxEvents: true,
                    moreLinkText: (num) => `+${num} más`,
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
                    events: (info, successCallback, failureCallback) => {
                        axios.get(this.BASE_URL + '/calendario/eventos', {
                            params: {
                                start: info.startStr,
                                end: info.endStr
                            }
                        })
                        .then((response) => {
                            const r = response.data || {};
                            this.totales = r.totales || this.totales;
                            successCallback(r.eventos || []);
                        })
                        .catch(failureCallback);
                    },
                    dateClick: (info) => {
                        const [year, month, day] = info.dateStr.split('-');
                        const meses = [
                            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ];
                        this.fechaSeleccionada = `${parseInt(day, 10)} de ${meses[parseInt(month, 10) - 1]} del ${year}`;

                        axios.get(this.BASE_URL + '/calendario/dia', {
                            params: { fecha: info.dateStr }
                        })
                        .then((response) => {
                            this.actividadesDia = (response.data && response.data.data) || [];
                            this.modalDia.show();
                        });
                    },
                    eventClick: (info) => {
                        this.abrirDetalle(info.event.extendedProps);
                    },
                    eventDidMount: (info) => {
                        info.el.title = info.event.title;
                        info.el.style.cursor = 'pointer';

                        // Color explícito: el tema puede no inyectar las clases fc-bg-*.
                        const color = this.calendarColors[info.event.extendedProps.calendar];
                        if (color) {
                            info.el.style.backgroundColor = color;
                            info.el.style.borderColor = color;
                        }
                    },
                    windowResize: () => {
                        if (this.checkWindowWidth()) {
                            this.calendar.changeView('listWeek');
                            this.calendar.setOption('height', 900);
                        } else {
                            this.calendar.changeView('dayGridMonth');
                            this.calendar.setOption('height', 1052);
                        }
                    }
                }
            );

            this.calendar.render();

            document.querySelectorAll('.fc-daygrid-day-number')
                .forEach((item) => {
                    item.style.fontSize = '1rem';
                    item.style.fontWeight = '700';
                });
        },

        // Normaliza la información del evento o del renglón del día para el modal Detalle.
        abrirDetalle(item) {
            this.detalle = {
                folio_label: item.folio_label || ('00' + (item.folio ?? '')),
                tipo_titulo: item.tipo_titulo || item.nombre || 'Sin tipo',
                encargado: item.encargado || 'Sin asignar',
                status_label: item.status_label || 'Desconocido',
                status_badge: item.status_badge || 'bg-secondary',
                fecha: item.fecha || item.fecha_mantenimiento || 'Sin información',
                fecha2: item.fecha2 || item.proxima_fecha || 'Sin información',
                costo_label: item.costo_label || '$0.00',
                observaciones: item.observaciones || 'Sin observaciones'
            };

            this.modalDetalle.show();
        }

    }));
});