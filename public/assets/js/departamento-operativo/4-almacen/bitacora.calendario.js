// ============================================================
// Bitácora de Maquinaria y Equipos — Almacén
// Calendario (FullCalendar) con el mismo diseño que
// /sasisopa/calendario, /sgm/calendario y
// mantenimiento-preventivo/calendario: calender-sidebar,
// totales (Pendientes / Finalizados / Total), dimensión de
// día y altura idénticas.
// Los eventos se piden por rango visible (start/end), no por
// mes: así se marcan también los meses vecinos de la rejilla.
// Emite 'mqb:dia' (fecha seleccionada → modal del día) y
// escucha 'mqb:refrescar' para repintar tras crear/eliminar.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('bitacoraCalendario', () => ({

        calendar: null,

        totales: {
            pendientes: 0,
            finalizados: 0,
            total: 0
        },

        colores: {
            todo: '#27ae60',      // todas finalizadas
            ninguno: '#f56954',   // todas pendientes
            mixto: '#f39c12'      // mixto
        },

        checkWindowWidth() {
            return window.innerWidth <= 1199;
        },

        initCalendario() {
            const el = document.getElementById('calendar');

            if (!el || typeof FullCalendar === 'undefined') {
                return;
            }

            const cont = document.getElementById('container');
            const idEquipo = parseInt(cont?.dataset.idEquipo || '0', 10);

            this.calendar = new FullCalendar.Calendar(el, {
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
                buttonText: { today: 'Hoy' },
                dayHeaderFormat: { weekday: 'long' },

                events: (info, successCallback, failureCallback) => {
                    axios.post(this.BASE + '/calendario', {
                        idEquipo: idEquipo,
                        start: info.startStr,
                        end: info.endStr
                    })
                    .then((response) => {
                        const r = response.data || {};
                        const data = r.data || {};
                        const dias = data.dias || [];

                        const eventos = dias.map((d) => ({
                            title: d.total + (d.total === 1 ? ' registro' : ' registros'),
                            start: d.fecha,
                            allDay: true,
                            backgroundColor: d.finalizadas === d.total
                                ? this.colores.todo
                                : (d.pendientes === d.total ? this.colores.ninguno : this.colores.mixto),
                            borderColor: d.finalizadas === d.total
                                ? this.colores.todo
                                : (d.pendientes === d.total ? this.colores.ninguno : this.colores.mixto),
                            extendedProps: {
                                total: d.total,
                                pendientes: d.pendientes,
                                finalizadas: d.finalizadas
                            }
                        }));

                        if (data.totales) {
                            this.totales = data.totales;
                        }

                        successCallback(eventos);
                    })
                    .catch(failureCallback);
                },

                dateClick: (info) => {
                    const fecha = info.dateStr.substring(0, 10);

                    document.dispatchEvent(new CustomEvent('mqb:dia', { detail: { fecha: fecha } }));
                },

                eventClick: (info) => {
                    const fecha = String(info.event.startStr || '').substring(0, 10);

                    document.dispatchEvent(new CustomEvent('mqb:dia', { detail: { fecha: fecha } }));
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
            });

            this.calendar.render();
            this.cambiarTamanoDias();

            // Refresco externo (crear/eliminar desde el modal del día).
            document.addEventListener('mqb:refrescar', () => {
                if (this.calendar) {
                    this.calendar.refetchEvents();
                    this.cambiarTamanoDias();
                }
            });
        },

        cambiarTamanoDias() {
            document.querySelectorAll('.fc-daygrid-day-number')
                .forEach((item) => {
                    item.style.fontSize = '1rem';
                    item.style.fontWeight = '700';
                });
        }

    }));

});
